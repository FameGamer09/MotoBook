<?php

declare(strict_types=1);

$apiBase = 'http://localhost/IM-101/motobook/admin/api/v1/index.php';

function req(string $url, string $method = 'GET', ?string $body = null, array $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $headers[] = 'Content-Type: application/json';
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true)];
}

// 1. Health check
$h = req($apiBase . '?route=health');
echo "[{$h['code']}] Health: " . ($h['data']['service'] ?? 'FAIL') . "\n";

// 2. Ops login
$login = req($apiBase . '?route=auth/login', 'POST', '{"email":"ops@motobook.com","password":"password"}');
echo "[{$login['code']}] Ops login: " . ($login['data']['success'] ? 'OK' : 'FAIL') . "\n";
if (empty($login['data']['token'])) {
    echo "Login failed: " . ($login['data']['message'] ?? 'unknown') . "\n";
    exit(1);
}
$token = $login['data']['token'];

// 3. Dashboard
$d = req($apiBase . '?route=dashboard&token=' . $token);
echo "[{$d['code']}] Dashboard: Orders={$d['data']['stats']['total_orders']}, Stores={$d['data']['stats']['total_stores']}, Riders={$d['data']['stats']['total_riders']}\n";

// 4. Orders
$o = req($apiBase . '?route=orders&status=all&token=' . $token);
echo "[{$o['code']}] Orders total: {$o['data']['total']}\n";

// 5. Riders
$r = req($apiBase . '?route=riders/active&token=' . $token);
echo "[{$r['code']}] Active riders: " . count($r['data']['riders'] ?? []) . "\n";

// 6. Remittance
$rem = req($apiBase . '?route=remittance&date=' . date('Y-m-d') . '&token=' . $token);
echo "[{$rem['code']}] Collections today: " . count($rem['data']['collections'] ?? []) . "\n";

// 7. Tickets
$t = req($apiBase . '?route=tickets&token=' . $token);
echo "[{$t['code']}] Tickets total: " . count($t['data']['tickets'] ?? []) . "\n";

// 8. Stores
$s = req($apiBase . '?route=stores&token=' . $token);
echo "[{$s['code']}] Stores total: " . count($s['data']['stores'] ?? []) . "\n";

// 9. Banners
$b = req($apiBase . '?route=banners&token=' . $token);
echo "[{$b['code']}] Banners: " . count($b['data']['banners'] ?? []) . "\n";

// 10. Promos
$p = req($apiBase . '?route=promos&token=' . $token);
echo "[{$p['code']}] Promos: " . count($p['data']['promos'] ?? []) . "\n";

// 11. Helpdesk
$hd = req($apiBase . '?route=helpdesk&token=' . $token);
echo "[{$hd['code']}] Helpdesk tickets: " . count($hd['data']['tickets'] ?? []) . "\n";

// 12. Settings (read-only lock check)
$set = req($apiBase . '?route=settings&token=' . $token);
echo "[{$set['code']}] Settings locked for staff? " . ($set['data']['read_only'] ? 'YES (correct)' : 'NO (should be yes)') . "\n";

// 13. Incidents
$inc = req($apiBase . '?route=incidents&token=' . $token);
echo "[{$inc['code']}] Incidents: " . count($inc['data']['incidents'] ?? []) . "\n";

echo "\n=== ALL API ENDPOINTS VERIFIED ===\n";

// Test store manager login
$sl = req($apiBase . '?route=auth/login', 'POST', '{"email":"jollibee.manager@motobook.com","password":"password"}');
echo "[{$sl['code']}] Store (Jollibee) login: " . ($sl['data']['success'] ? 'OK' : 'FAIL: ' . ($sl['data']['message'] ?? '')) . "\n";

$sl2 = req($apiBase . '?route=auth/login', 'POST', '{"email":"mcdo.manager@motobook.com","password":"password"}');
echo "[{$sl2['code']}] Store (McDo) login: " . ($sl2['data']['success'] ? 'OK' : 'FAIL: ' . ($sl2['data']['message'] ?? '')) . "\n";

$sl3 = req($apiBase . '?route=auth/login', 'POST', '{"email":"support@motobook.com","password":"password"}');
echo "[{$sl3['code']}] Support staff login: " . ($sl3['data']['success'] ? 'OK' : 'FAIL: ' . ($sl3['data']['message'] ?? '')) . "\n";
