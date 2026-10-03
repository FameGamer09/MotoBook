<?php
declare(strict_types=1);
$root = __DIR__;
require_once $root . '/login.php';

function println(string $m): void { echo $m . PHP_EOL; }

println("=== URL PROBE: Which paths work on PHP built-in server? ===");

$urls = [
    '/login.php'                              => 'SSO login (no IM prefix)',
    '/A-rider/index.php'                      => 'Rider shell (no IM prefix)',
    '/A-rider/public/build/.vite/manifest.json' => 'Vite manifest (no IM prefix)',
    '/A-rider/public/build/assets/main-DCWptHFj.js' => 'Vite main.js build',
    '/IM-101/motobook/login.php'              => 'SSO login (WITH IM prefix)',
];

foreach ($urls as $u => $desc) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://127.0.0.1:8080' . $u,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    println(sprintf("  HTTP %3d  %-55s  %s", $code, $u, $desc));
}

println("\n=== NOW: Test Rider shell AT CORRECT URL (/A-rider/index.php) with SESSION ===");

// Set up session first
$result = sso_authenticate('juan.rider@motobook.com', 'Motobook200409');
$riderId = 0;
if (($result['ok'] ?? false) === true) {
    $riderId = (int)($result['data']['id'] ?? 0);
    $_SESSION['sso_identity'] = [
        'authenticated_at' => date('c'),
        'panel' => 'rider',
        'role'  => 'rider',
        'data'  => $result['data'],
        'rider_id' => $riderId,
    ];
    $_SESSION['rider_id'] = $riderId;
    println("  Authenticated RDR-0001 rid=$riderId, session set");
}
$sessName = session_name();
$sessId   = session_id();

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://127.0.0.1:8080/A-rider/index.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Cookie: '.$sessName.'='.$sessId],
    CURLOPT_FOLLOWLOCATION => false,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

println("  HTTP=$code bytes=".strlen((string)$resp));
if ($code === 302) {
    println("  GOT 302 REDIRECT (session not propagating via separate PHP process — expected). Body first 300 chars:");
    println("  ".substr((string)$resp, 0, 300));
} elseif ($code === 200 && strlen((string)$resp) > 1000) {
    $hasBoot = (bool)preg_match('#__RIDER_BOOT__#', $resp);
    $hasRoot = (bool)preg_match('#rider-root#', $resp);
    $devFallback = (bool)preg_match('#localhost:5174#', $resp);
    preg_match('#<script[^>]+src="([^"]+)"#', $resp, $m);
    $firstScript = $m[1] ?? '(none)';
    preg_match('#<link[^>]+stylesheet[^>]+href="([^"]+)"#', $resp, $cm);
    $firstCss = $cm[1] ?? '(none)';
    println("  __RIDER_BOOT__: ".($hasBoot?'YES':'NO'));
    println("  rider-root: ".($hasRoot?'YES':'NO'));
    println("  DEV fallback (5174): ".($devFallback?'BAD - will blank screen':'GOOD - using build'));
    println("  FIRST <script src>: $firstScript");
    println("  FIRST  <css href>:  $firstCss");
}
