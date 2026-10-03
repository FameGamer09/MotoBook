<?php
declare(strict_types=1);
$root = __DIR__;
require_once $root . '/login.php';

$ok = 0; $fail = 0;
function println(string $m): void { echo $m . PHP_EOL; }

println("=== TEST 1: SSO Auth (juan.rider@motobook.com / Motobook200409) ===");
$result = sso_authenticate('juan.rider@motobook.com', 'Motobook200409');
if (($result['ok'] ?? false) === true && ($result['panel'] ?? '') === 'rider') {
    println("  PASS - panel=rider, role={$result['role']}, name=".($result['data']['name'] ?? '?'));
    println("       rider_code=".($result['data']['rider_code'] ?? '?'));
    $ok++;
    $riderId = (int)($result['data']['id'] ?? 0);
} else {
    println("  FAIL - ".json_encode($result, JSON_UNESCAPED_UNICODE));
    $fail++;
    $riderId = 0;
}

if ($riderId > 0) {
    println("\n=== TEST 2: Set SSO session and resolve Rider cookie ===");
    $ssoToken = bin2hex(random_bytes(16));
    $_SESSION['sso_identity'] = [
        'authenticated_at' => date('c'),
        'panel' => 'rider',
        'role'  => 'rider',
        'data'  => $result['data'],
        'rider_id' => $riderId,
    ];
    $_SESSION['rider_id'] = $riderId;
    println("  PASS - rider_id=$riderId set in SSO session (cookie: ".session_name()."=".session_id().")");
    $ok++;

    println("\n=== TEST 3: Rider shell PHP (A-rider/index.php bootstrap) ===");
    $ch = curl_init();
    $cookieFile = sys_get_temp_dir().'/rider_test_cookie_'.md5(uniqid('',true)).'.txt';
    $sessName = session_name();
    $sessId   = session_id();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://127.0.0.1:8080/IM-101/motobook/A-rider/index.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Cookie: '.$sessName.'='.$sessId],
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    @unlink($cookieFile);

    if ($code === 200 && is_string($resp) && strlen($resp) > 1000) {
        $hasBoot = (bool)preg_match('#__RIDER_BOOT__#', $resp);
        $hasRoot = (bool)preg_match('#rider-root#', $resp);
        $hasBuildJs = (bool)preg_match('#/A-rider/public/build/assets/main-DCWptHFj\.js#', $resp);
        $hasRid = (bool)preg_match('#riderId:\s*'.$riderId.'\b#', $resp);
        $devFallback = (bool)preg_match('#localhost:5174#', $resp);
        println("  HTTP=$code bytes=".strlen($resp));
        println("  __RIDER_BOOT__: ".($hasBoot?'YES':'NO'));
        println("  rider-root div: ".($hasRoot?'YES':'NO'));
        println("  PROD build JS loaded: ".($hasBuildJs?'YES (Vite manifest correct!)':'NO'));
        println("  riderId matches session ($riderId): ".($hasRid?'YES':'NO'));
        println("  DEV fallback (5174): ".($devFallback?'YES (BAD - should be PROD)':'NO (GOOD - using build)'));
        if ($hasBoot && $hasBuildJs && $hasRid && !$devFallback) { println("  PASS - Rider shell uses production Vite build correctly"); $ok++; }
        else { println("  FAIL - build wiring issue"); $fail++; }
    } elseif ($code === 302) {
        println("  REDIRECT (no session / not authenticated) - code=$code");
        $fail++;
    } else {
        println("  FAIL - HTTP=$code err=$err bytes=".strlen((string)$resp));
        $fail++;
    }

    println("\n=== TEST 4: Rider API bootstrap (GET /A-rider/api/orders) ===");
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => 'http://127.0.0.1:8080/IM-101/motobook/A-rider/api/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Cookie: '.$sessName.'='.$sessId,
        ],
    ]);
    $resp2 = curl_exec($ch2);
    $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $ct2   = curl_getinfo($ch2, CURLINFO_CONTENT_TYPE);
    curl_close($ch2);
    println("  HTTP=$code2 ContentType=".($ct2 ?? '?')." bytes=".strlen((string)$resp2));
    $data2 = json_decode((string)$resp2, true);
    if (is_array($data2)) {
        if (isset($data2['error'])) {
            println("  API returned error: ".json_encode($data2, JSON_UNESCAPED_UNICODE));
        } else {
            $count = is_array($data2['orders'] ?? null) ? count($data2['orders']) : (is_array($data2) ? count($data2) : 0);
            println("  PASS - Got JSON, items~=$count");
            println("  Keys: ".implode(', ', array_slice(array_keys(is_array($data2) ? $data2 : []), 0, 8)));
            $ok++;
        }
    } else {
        println("  NOT JSON - preview: ".substr((string)$resp2, 0, 200));
    }
}

println("\n==============================================");
println("TEST SUMMARY: PASS=$ok FAIL=$fail");
println("==============================================");
exit($fail > 0 ? 1 : 0);
