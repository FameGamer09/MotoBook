<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/migrate_ops.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function apiJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function settingsMap(PDO $pdo): array
{
    $rows = $pdo->query('SELECT setting_key, setting_value, setting_label, is_locked_for_staff FROM platform_settings')->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[$row['setting_key']] = $row;
    }

    return $map;
}

function settingValue(PDO $pdo, string $key, string $default = '0'): string
{
    $map = settingsMap($pdo);

    return (string) ($map[$key]['setting_value'] ?? $default);
}

function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
        return $matches[1];
    }

    return $_GET['token'] ?? null;
}

function requireApiUser(PDO $pdo): array
{
    $token = bearerToken();
    if (!$token) {
        apiJson(['success' => false, 'message' => 'Missing API token.'], 401);
    }

    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT * FROM api_tokens WHERE token_hash = ? AND expires_at > NOW()');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row) {
        apiJson(['success' => false, 'message' => 'Invalid or expired API token.'], 401);
    }

    $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')->execute([$row['id']]);

    return $row;
}

function forbidStaffAccounts(array $user): void
{
    if (in_array($user['actor_type'], ['platform_staff', 'store_staff', 'store_owner'], true)) {
        apiJson(['success' => false, 'message' => 'Management staff cannot create or remove staff accounts.'], 403);
    }
}

function storeScopeId(array $user): ?int
{
    if (in_array($user['actor_type'], ['store_staff', 'store_owner'], true)) {
        $pdo = getDBConnection();
        if ($user['actor_type'] === 'store_owner') {
            return (int) $user['actor_id'];
        }
        $stmt = $pdo->prepare('SELECT store_id FROM staff WHERE id = ?');
        $stmt->execute([$user['actor_id']]);

        return (int) ($stmt->fetchColumn() ?: 0) ?: null;
    }

    return null;
}

function delayThreshold(PDO $pdo): int
{
    return max(5, (int) settingValue($pdo, 'delay_threshold_minutes', '35'));
}

function decorateOrder(array $order, int $threshold): array
{
    $terminal = in_array($order['order_status'], ['delivered', 'cancelled'], true);
    $age = (int) ((time() - strtotime((string) $order['created_at'])) / 60);
    $order['age_minutes'] = $age;
    $order['is_delayed'] = !$terminal && ($order['order_status'] === 'delayed' || $age > $threshold);
    $order['display_status'] = $order['is_delayed'] && $order['order_status'] !== 'delayed'
        ? 'delayed'
        : $order['order_status'];

    return $order;
}

function issueToken(PDO $pdo, string $actorType, int $actorId, string $email): string
{
    $plain = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO api_tokens (token_hash, actor_type, actor_id, actor_email, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 12 HOUR))');
    $stmt->execute([hash('sha256', $plain), $actorType, $actorId, $email]);

    return $plain;
}

function logOrderEvent(PDO $pdo, int $orderId, string $type, string $notes, string $actorEmail): void
{
    $pdo->prepare('INSERT INTO order_events (order_id, event_type, notes, actor_email) VALUES (?, ?, ?, ?)')
        ->execute([$orderId, $type, $notes, $actorEmail]);
}
