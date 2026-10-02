<?php

declare(strict_types=1);

function riderAdminDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = getenv('MOTOBOOK_ADMIN_DB_HOST') ?: '127.0.0.1';
    $user = getenv('MOTOBOOK_ADMIN_DB_USER') ?: 'root';
    $pass = getenv('MOTOBOOK_ADMIN_DB_PASS') ?: '';
    $name = getenv('MOTOBOOK_ADMIN_DB_NAME') ?: 'motobook_admin';
    $port = (int) (getenv('MOTOBOOK_ADMIN_DB_PORT') ?: 3306);
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function riderPasswordColumn(PDO $pdo): ?string
{
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'riders'");
    $stmt->execute();
    $columns = array_fill_keys(array_map('strtolower', array_column($stmt->fetchAll(), 'COLUMN_NAME')), true);

    if (isset($columns['password_hash'])) {
        return 'password_hash';
    }

    return isset($columns['password']) ? 'password' : null;
}

function riderPasswordMatches(PDO $pdo, int $riderId, string $password): bool
{
    $column = riderPasswordColumn($pdo);
    if ($column === null) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT `{$column}` FROM riders WHERE id = ? LIMIT 1");
    $stmt->execute([$riderId]);
    $stored = (string) ($stmt->fetchColumn() ?: '');
    if ($password === '' || $stored === '') {
        return false;
    }

    return password_verify($password, $stored)
        || (strlen($stored) === 32 && hash_equals(strtolower($stored), md5($password)))
        || (strlen($stored) === 40 && hash_equals(strtolower($stored), sha1($password)))
        || hash_equals($stored, $password);
}

function riderSavePassword(PDO $pdo, int $riderId, string $password): bool
{
    $column = riderPasswordColumn($pdo);
    if ($column === null) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE riders SET `{$column}` = ? WHERE id = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $riderId]);

    return $stmt->rowCount() === 1;
}

function riderRequireAuth(): array
{
    if (empty($_SESSION['rider_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'UNAUTHENTICATED', 'redirect' => UNIFIED_LOGIN_URL], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $pdo = riderAdminDB();
        // Use INFORMATION_SCHEMA guard to safely detect which new cols exist on legacy riders table.
        static $colCache = null;
        if ($colCache === null) {
            $colCache = [];
            $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'riders'");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $r) {
                $colCache[strtolower($r['COLUMN_NAME'])] = true;
            }
        }
        $col = static fn (string $name): bool => isset($colCache[strtolower($name)]);
        $safeCol = static function (string $name, string $defaultExpr = "''") use ($col): string {
            return $col($name) ? "r.`$name`" : $defaultExpr;
        };
        $nameParts = [];
        if ($col('name')) {
            $nameParts[] = "NULLIF(r.`name`, '')";
        }
        if ($col('full_name')) {
            $nameParts[] = "NULLIF(r.`full_name`, '')";
        }
        $nameExpression = $nameParts ? 'COALESCE('.implode(', ', $nameParts).", '')" : "''";
        $sql = "SELECT r.id,
            {$nameExpression} AS name,
            r.email,
            CASE UPPER(r.status)
                WHEN 'ACTIVE'    THEN 'ACTIVE'
                WHEN 'ON_DUTY'   THEN 'ON_SHIFT'
                WHEN 'ON_SHIFT'  THEN 'ON_SHIFT'
                WHEN 'SUSPENDED' THEN 'SUSPENDED'
                WHEN 'INACTIVE'  THEN 'INACTIVE'
                ELSE 'OFFLINE'
            END AS status,
            {$safeCol('vehicle_plate', "''")} AS vehicle_plate,
            {$safeCol('phone', "''")} AS phone,
            COALESCE({$safeCol('city', "''")}, '') AS city,
            r.rider_code, r.created_at,
            {$safeCol('updated_at', 'r.created_at')} AS updated_at,
            {$safeCol('gcash_mobile_number')} AS gcash_mobile_number,
            {$safeCol('gcash_account_name')} AS gcash_account_name,
            {$safeCol('gcash_qr_data_uri')} AS gcash_qr_data_uri,
            {$safeCol('fcm_push_token')} AS fcm_push_token,
            {$safeCol('fcm_push_sub_json', "'null'")} AS fcm_push_sub_json,
            COALESCE({$safeCol('duty_today_payout', '0.00')}, 0.00) AS duty_today_payout,
            COALESCE({$safeCol('completed_today', '0')}, 0) AS completed_today,
            COALESCE({$safeCol('acceptance_rate', '0.00')}, 0.00) AS acceptance_rate,
            COALESCE({$safeCol('active_hours_today', '0.00')}, 0.00) AS active_hours_today,
            {$safeCol('current_shift_started_at', 'NULL')} AS current_shift_started_at
            FROM riders r WHERE r.id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int) $_SESSION['rider_id']]);
        $row = $stmt->fetch();
        if (! $row) {
            throw new RuntimeException('RIDER_NOT_FOUND');
        }
        if (! in_array($row['status'], ['ACTIVE', 'ON_SHIFT', 'OFFLINE'], true)) {
            throw new RuntimeException('RIDER_STATUS_BLOCKED');
        }

        return $row;
    } catch (Throwable $e) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'SESSION_INVALID', 'detail' => $e->getMessage(), 'redirect' => UNIFIED_LOGIN_URL], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function riderJsonOut(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function riderInputJson(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function riderHaversineMeters(float $latA, float $lngA, float $latB, float $lngB): float
{
    $r = 6371000.0;
    $phi1 = deg2rad($latA);
    $phi2 = deg2rad($latB);
    $dPhi = deg2rad($latB - $latA);
    $dLam = deg2rad($lngB - $lngA);
    $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLam / 2) ** 2;

    return 2.0 * $r * asin(sqrt($a));
}
