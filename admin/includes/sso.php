<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

function ssoColumns(PDO $pdo, string $table): array
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $columns = $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll(PDO::FETCH_COLUMN, 1);

        return array_fill_keys(array_map('strtolower', $columns), true);
    }

    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    return array_fill_keys(array_map('strtolower', $statement->fetchAll(PDO::FETCH_COLUMN)), true);
}

function ssoPasswordMatches(string $password, string $storedPassword, bool $allowLegacy = false): bool
{
    if ($storedPassword === '') {
        return false;
    }

    if (password_verify($password, $storedPassword)) {
        return true;
    }

    if (! $allowLegacy) {
        return false;
    }

    if (strlen($storedPassword) === 32 && hash_equals(strtolower($storedPassword), md5($password))) {
        return true;
    }

    if (strlen($storedPassword) === 40 && hash_equals(strtolower($storedPassword), sha1($password))) {
        return true;
    }

    return hash_equals($storedPassword, $password);
}

function ssoNormalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function ssoPhoneVariants(string $phone): array
{
    $phone = ssoNormalizePhone($phone);
    $variants = [$phone];

    if (strlen($phone) === 12 && str_starts_with($phone, '63')) {
        $variants[] = '0'.substr($phone, 2);
    } elseif (strlen($phone) === 11 && str_starts_with($phone, '0')) {
        $variants[] = '63'.substr($phone, 1);
    } elseif (strlen($phone) === 10 && str_starts_with($phone, '9')) {
        $variants[] = '0'.$phone;
        $variants[] = '63'.$phone;
    }

    return array_values(array_unique($variants));
}

function ssoPhoneExpression(string $column): string
{
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";
}

function ssoPhoneLookup(string $column, array $phoneVariants): string
{
    return ssoPhoneExpression($column).' IN ('.implode(', ', array_fill(0, count($phoneVariants), '?')).')';
}

function ssoFindPasswordResetAccount(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare('SELECT id, email FROM super_admins WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);
    if ($account !== false) {
        return ['type' => 'super_admin', 'id' => (int) $account['id'], 'email' => (string) $account['email']];
    }

    $statement = $pdo->prepare('SELECT id, email FROM staff WHERE email = ? AND is_active = 1 LIMIT 1');
    $statement->execute([$email]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);
    if ($account !== false) {
        return ['type' => 'staff', 'id' => (int) $account['id'], 'email' => (string) $account['email']];
    }

    $statement = $pdo->prepare(
        'SELECT id, owner_email AS email FROM partnership_stores
         WHERE owner_email = ? AND owner_password IS NOT NULL LIMIT 1'
    );
    $statement->execute([$email]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);
    if ($account !== false) {
        return ['type' => 'store_owner', 'id' => (int) $account['id'], 'email' => (string) $account['email']];
    }

    $statement = $pdo->prepare(
        "SELECT id, email FROM riders WHERE email = ? AND LOWER(status) NOT IN ('inactive', 'suspended') LIMIT 1"
    );
    $statement->execute([$email]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);

    return $account === false
        ? null
        : ['type' => 'rider', 'id' => (int) $account['id'], 'email' => (string) $account['email']];
}

function ssoEnsurePasswordResetTable(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sso_password_reset_tokens (
                token_hash TEXT PRIMARY KEY,
                email TEXT NOT NULL,
                account_type TEXT NOT NULL,
                account_id INTEGER NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );

        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sso_password_reset_tokens (
            token_hash CHAR(64) NOT NULL PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            account_type VARCHAR(32) NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX sso_password_reset_email_account_index (email, account_type, account_id),
            INDEX sso_password_reset_expires_index (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ssoCreatePasswordResetToken(PDO $pdo, array $account): string
{
    ssoEnsurePasswordResetTable($pdo);
    $token = bin2hex(random_bytes(32));
    $statement = $pdo->prepare(
        'DELETE FROM sso_password_reset_tokens WHERE email = ? AND account_type = ? AND account_id = ?'
    );
    $statement->execute([$account['email'], $account['type'], $account['id']]);

    $statement = $pdo->prepare(
        'INSERT INTO sso_password_reset_tokens (token_hash, email, account_type, account_id, expires_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $now = time();
    $statement->execute([
        hash('sha256', $token),
        $account['email'],
        $account['type'],
        $account['id'],
        date('Y-m-d H:i:s', $now + 1800),
        date('Y-m-d H:i:s', $now),
    ]);

    return $token;
}

function ssoResetPassword(PDO $pdo, string $token, string $password): bool
{
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1 || strlen($password) < 12) {
        return false;
    }

    ssoEnsurePasswordResetTable($pdo);
    $pdo->beginTransaction();
    try {
        $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $pdo->prepare(
            'SELECT token_hash, account_type, account_id FROM sso_password_reset_tokens
             WHERE token_hash = ? AND expires_at > ? LIMIT 1'.$lockClause
        );
        $statement->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
        $reset = $statement->fetch(PDO::FETCH_ASSOC);
        if ($reset === false) {
            $pdo->rollBack();

            return false;
        }

        $targets = [
            'super_admin' => ['table' => 'super_admins', 'column' => 'password'],
            'staff' => ['table' => 'staff', 'column' => 'password'],
            'store_owner' => ['table' => 'partnership_stores', 'column' => 'owner_password'],
        ];
        if ($reset['account_type'] === 'rider') {
            $riderColumns = ssoColumns($pdo, 'riders');
            $passwordColumn = isset($riderColumns['password_hash']) ? 'password_hash' : 'password';
            if (! isset($riderColumns[$passwordColumn])) {
                $pdo->rollBack();

                return false;
            }
            $target = ['table' => 'riders', 'column' => $passwordColumn];
        } elseif (isset($targets[$reset['account_type']])) {
            $target = $targets[$reset['account_type']];
        } else {
            $pdo->rollBack();

            return false;
        }

        $statement = $pdo->prepare("UPDATE `{$target['table']}` SET `{$target['column']}` = ? WHERE id = ?");
        $statement->execute([password_hash($password, PASSWORD_DEFAULT), $reset['account_id']]);
        if ($statement->rowCount() !== 1) {
            $pdo->rollBack();

            return false;
        }

        $statement = $pdo->prepare('DELETE FROM sso_password_reset_tokens WHERE token_hash = ?');
        $statement->execute([$reset['token_hash']]);
        $pdo->commit();

        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function ssoSendPasswordResetEmail(string $email, string $resetUrl): void
{
    $projectRoot = dirname(__DIR__, 2);
    require_once $projectRoot.'/vendor/autoload.php';
    $application = require $projectRoot.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    Mail::raw(
        "We received a request to reset your MotoBook password.\n\n"
            ."Use this link within 30 minutes to set a new password:\n{$resetUrl}\n\n"
            .'If you did not request a reset, you can ignore this email.',
        static function (Message $message) use ($email): void {
            $message->to($email)->subject('Reset your MotoBook password');
        }
    );
}

function ssoAbsoluteUrl(string $path): string
{
    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ? 'https'
        : 'http';
    $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $port = (int) ($_SERVER['SERVER_PORT'] ?? ($scheme === 'https' ? 443 : 80));
    if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) {
        $host .= ':'.$port;
    }

    return $scheme.'://'.$host.$path;
}

function ssoStartAuthSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('motobook_sso_session');
        session_set_cookie_params(ssoSessionCookieParameters());
        session_start();
    }

    if (empty($_SESSION['sso_csrf_token'])) {
        $_SESSION['sso_csrf_token'] = bin2hex(random_bytes(32));
    }
}

function ssoValidCsrfToken(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['sso_csrf_token'])
        && hash_equals((string) $_SESSION['sso_csrf_token'], $token);
}

function ssoH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ssoBaseUrl(string $scriptName): string
{
    $scriptName = str_replace('\\', '/', $scriptName);
    $adminPosition = strrpos($scriptName, '/admin/');

    return $adminPosition === false ? '' : rtrim(substr($scriptName, 0, $adminPosition), '/');
}

function ssoRegisterRider(PDO $pdo, string $name, string $email, string $phone, string $password): bool
{
    if (
        trim($name) === ''
        || mb_strlen($name) > 150
        || ! filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150
        || preg_match('/^\+?[0-9()\s.-]{7,30}$/', $phone) !== 1
        || strlen($password) < 12
    ) {
        return false;
    }

    $emailChecks = [
        'super_admins' => 'SELECT 1 FROM super_admins WHERE email = ? LIMIT 1',
        'staff' => 'SELECT 1 FROM staff WHERE email = ? LIMIT 1',
        'partnership_stores' => 'SELECT 1 FROM partnership_stores WHERE owner_email = ? LIMIT 1',
        'riders' => 'SELECT 1 FROM riders WHERE email = ? LIMIT 1',
    ];
    foreach ($emailChecks as $query) {
        $statement = $pdo->prepare($query);
        $statement->execute([$email]);
        if ($statement->fetchColumn() !== false) {
            return false;
        }
    }

    $statement = $pdo->prepare('SELECT 1 FROM staff WHERE phone = ? LIMIT 1');
    $statement->execute([$phone]);
    if ($statement->fetchColumn() !== false) {
        return false;
    }

    $statement = $pdo->prepare('SELECT 1 FROM riders WHERE phone = ? LIMIT 1');
    $statement->execute([$phone]);
    if ($statement->fetchColumn() !== false) {
        return false;
    }

    $riderCode = 'RDR-APP-'.strtoupper(bin2hex(random_bytes(4)));
    $statement = $pdo->prepare(
        "INSERT INTO riders (rider_code, full_name, email, phone, password, status, duty_status)
         VALUES (?, ?, ?, ?, ?, 'inactive', 'offline')"
    );
    $statement->execute([
        $riderCode,
        trim($name),
        strtolower($email),
        trim($phone),
        password_hash($password, PASSWORD_DEFAULT),
    ]);

    return true;
}

/**
 * @return array{ok: bool, message?: string, panel?: string, role?: string, url?: string, data?: array<string, mixed>}
 */
function ssoAuthenticate(PDO $pdo, string $email, string $password, string $baseUrl): array
{
    $identifier = trim($email);
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
    $phone = ssoNormalizePhone($identifier);
    if ((! $isEmail && (strlen($phone) < 7 || strlen($phone) > 15)) || $password === '') {
        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }
    $phoneVariants = ssoPhoneVariants($identifier);

    $admin = false;
    if ($isEmail) {
        $statement = $pdo->prepare('SELECT id, name, email, password FROM super_admins WHERE email = ? LIMIT 1');
        $statement->execute([$identifier]);
        $admin = $statement->fetch(PDO::FETCH_ASSOC);
    }

    if ($admin !== false) {
        if (! ssoPasswordMatches($password, (string) $admin['password'])) {
            return ['ok' => false, 'message' => 'Invalid email or password.'];
        }

        return [
            'ok' => true,
            'panel' => 'admin',
            'role' => 'super_admin',
            'url' => $baseUrl.'/admin/dashboard.php',
            'data' => [
                'id' => (int) $admin['id'],
                'name' => (string) $admin['name'],
                'email' => (string) $admin['email'],
            ],
        ];
    }

    $staffColumns = ssoColumns($pdo, 'staff');
    $staffLookup = $isEmail
        ? 's.email = ?'
        : (isset($staffColumns['phone']) ? ssoPhoneLookup('s.phone', $phoneVariants) : '1 = 0');
    $lookupValues = $isEmail ? [$identifier] : $phoneVariants;
    $statement = $pdo->prepare("SELECT id FROM staff s WHERE {$staffLookup} AND s.is_active = 0 LIMIT 1");
    $statement->execute($lookupValues);
    if ($statement->fetchColumn() !== false) {
        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }

    $staffTypeExpression = isset($staffColumns['staff_type']) ? 's.staff_type' : "'platform'";
    $staffLookup = $isEmail
        ? 's.email = ?'
        : (isset($staffColumns['phone']) ? ssoPhoneLookup('s.phone', $phoneVariants) : '1 = 0');
    $statement = $pdo->prepare(
        "SELECT s.id, s.full_name, s.email, s.password, s.role, s.store_id, {$staffTypeExpression} AS staff_type,
                ps.store_name
         FROM staff s
         LEFT JOIN partnership_stores ps ON ps.id = s.store_id
         WHERE {$staffLookup} AND s.is_active = 1
         LIMIT 1"
    );
    $statement->execute($lookupValues);
    $staff = $statement->fetch(PDO::FETCH_ASSOC);

    if ($staff !== false) {
        if (! ssoPasswordMatches($password, (string) $staff['password'])) {
            return ['ok' => false, 'message' => 'Invalid email or password.'];
        }

        $staffType = ($staff['staff_type'] ?? 'platform') === 'store' ? 'store' : 'platform';
        $storeId = $staff['store_id'] === null ? null : (int) $staff['store_id'];
        if ($staffType === 'store' && $storeId === null) {
            return ['ok' => false, 'message' => 'This account is not assigned to a store. Contact an administrator.'];
        }

        return [
            'ok' => true,
            'panel' => 'management',
            'role' => $staffType === 'store' ? 'store_staff' : 'platform_staff',
            'url' => $baseUrl.($staffType === 'store' ? '/A-management/menu.php' : '/A-management/orders.php?tab=kanban'),
            'data' => [
                'id' => (int) $staff['id'],
                'name' => (string) $staff['full_name'],
                'email' => (string) $staff['email'],
                'staff_type' => $staffType,
                'store_id' => $storeId,
                'store_name' => $staff['store_name'] === null ? null : (string) $staff['store_name'],
                'role' => (string) ($staff['role'] ?? 'store_operator'),
            ],
        ];
    }

    $store = false;
    if ($isEmail) {
        $statement = $pdo->prepare(
            'SELECT id, store_name, owner_name, owner_email, owner_password
             FROM partnership_stores WHERE owner_email = ? LIMIT 1'
        );
        $statement->execute([$identifier]);
        $store = $statement->fetch(PDO::FETCH_ASSOC);
    }

    if ($store !== false) {
        if (! ssoPasswordMatches($password, (string) ($store['owner_password'] ?? ''))) {
            return ['ok' => false, 'message' => 'Invalid email or password.'];
        }

        return [
            'ok' => true,
            'panel' => 'management',
            'role' => 'store_owner',
            'url' => $baseUrl.'/A-management/menu.php',
            'data' => [
                'id' => (int) $store['id'],
                'name' => (string) ($store['owner_name'] ?: $store['store_name'].' Owner'),
                'email' => (string) $store['owner_email'],
                'store_id' => (int) $store['id'],
                'store_name' => (string) $store['store_name'],
            ],
        ];
    }

    $riderColumns = ssoColumns($pdo, 'riders');
    $nameColumns = array_values(array_filter(
        ['name', 'full_name'],
        static fn (string $column): bool => isset($riderColumns[$column])
    ));
    $passwordColumns = array_values(array_filter(
        ['password_hash', 'password'],
        static fn (string $column): bool => isset($riderColumns[$column])
    ));
    $nameExpression = $nameColumns === []
        ? "''"
        : 'COALESCE('.implode(', ', array_map(
            static fn (string $column): string => "NULLIF(`{$column}`, '')",
            $nameColumns
        )).", '')";
    $passwordExpression = $passwordColumns === []
        ? "''"
        : 'COALESCE('.implode(', ', array_map(
            static fn (string $column): string => "NULLIF(`{$column}`, '')",
            $passwordColumns
        )).", '')";
    $riderLookup = $isEmail
        ? 'email = ?'
        : (isset($riderColumns['phone']) ? ssoPhoneLookup('phone', $phoneVariants) : '1 = 0');
    $statement = $pdo->prepare(
        "SELECT id, {$nameExpression} AS name, email, rider_code, {$passwordExpression} AS stored_password, status
         FROM riders WHERE {$riderLookup} LIMIT 1"
    );
    $statement->execute($lookupValues);
    $rider = $statement->fetch(PDO::FETCH_ASSOC);

    if ($rider !== false) {
        if (! ssoPasswordMatches($password, (string) $rider['stored_password'], true)
            || in_array(strtoupper((string) $rider['status']), ['INACTIVE', 'SUSPENDED'], true)) {
            return ['ok' => false, 'message' => 'Invalid email or password.'];
        }

        $status = strtoupper((string) $rider['status']);

        return [
            'ok' => true,
            'panel' => 'rider',
            'role' => 'rider',
            'url' => $baseUrl.'/A-rider/',
            'data' => [
                'id' => (int) $rider['id'],
                'rider_code' => (string) $rider['rider_code'],
                'name' => (string) $rider['name'],
                'email' => (string) $rider['email'],
                'status' => in_array($status, ['ACTIVE', 'ON_DUTY', 'ON_SHIFT'], true) ? $status : 'OFFLINE',
            ],
        ];
    }

    return ['ok' => false, 'message' => 'Invalid email or password.'];
}

function ssoSafeRedirect(?string $redirect, string $panel, string $baseUrl): ?string
{
    if ($redirect === null || $redirect === '' || preg_match('/[\r\n]/', $redirect) === 1) {
        return null;
    }

    $parts = parse_url($redirect);
    if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }

    if (isset($parts['host'])) {
        $currentScheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            ? 'https'
            : 'http';
        $currentOrigin = parse_url($currentScheme.'://'.($_SERVER['HTTP_HOST'] ?? ''));
        if ($currentOrigin === false) {
            return null;
        }
        $redirectScheme = strtolower((string) ($parts['scheme'] ?? ''));
        $currentPort = $currentOrigin['port'] ?? ($currentScheme === 'https' ? 443 : 80);
        $redirectPort = $parts['port'] ?? ($redirectScheme === 'https' ? 443 : 80);
        if ($redirectScheme !== $currentScheme
            || strtolower((string) $parts['host']) !== strtolower((string) ($currentOrigin['host'] ?? ''))
            || $redirectPort !== $currentPort) {
            return null;
        }
    } elseif (isset($parts['scheme']) || ! str_starts_with((string) ($parts['path'] ?? ''), '/')
        || str_starts_with((string) ($parts['path'] ?? ''), '//')) {
        return null;
    }

    $path = (string) ($parts['path'] ?? '');
    $panelPath = match ($panel) {
        'admin' => $baseUrl.'/admin',
        'management' => $baseUrl.'/A-management',
        'rider' => $baseUrl.'/A-rider',
        default => '',
    };

    if ($panelPath === '' || ($path !== $panelPath && ! str_starts_with($path, $panelPath.'/'))) {
        return null;
    }

    return $path.(isset($parts['query']) ? '?'.$parts['query'] : '')
        .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
}

function ssoSessionCookieParameters(): array
{
    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function ssoSeedPanelSession(array $auth): void
{
    $sessionNames = [
        'admin' => 'motobook_admin_session',
        'management' => 'motobook_ops_session',
        'rider' => 'motobook_rider_session',
    ];
    $sessionName = $sessionNames[$auth['panel']];
    $data = $auth['data'];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    session_id('');
    session_name($sessionName);
    session_set_cookie_params(ssoSessionCookieParameters());
    session_start();
    session_regenerate_id(true);
    $_SESSION = [];

    if ($auth['panel'] === 'admin') {
        $_SESSION['admin_id'] = (int) $data['id'];
        $_SESSION['admin_name'] = (string) $data['name'];
        $_SESSION['admin_email'] = (string) $data['email'];
    } elseif ($auth['panel'] === 'management') {
        $isOwner = $auth['role'] === 'store_owner';
        $_SESSION['ops_user_id'] = (int) ($isOwner ? $data['store_id'] : $data['id']);
        $_SESSION['ops_user'] = [
            'id' => (int) ($isOwner ? $data['store_id'] : $data['id']),
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'type' => (string) $auth['role'],
            'role' => (string) ($data['role'] ?? ($isOwner ? 'store_manager' : 'platform_operator')),
            'store_id' => isset($data['store_id']) ? (int) $data['store_id'] : null,
            'store_name' => $data['store_name'] ?? null,
            'staff_type' => (string) ($data['staff_type'] ?? 'store_owner'),
        ];
    } else {
        $_SESSION['rider_id'] = (int) $data['id'];
        $_SESSION['rider'] = [
            'id' => (int) $data['id'],
            'rider_code' => (string) $data['rider_code'],
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'status' => (string) $data['status'],
        ];
    }

    session_write_close();

    setcookie('motobook_sso_session', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => ssoSessionCookieParameters()['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
