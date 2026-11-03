<?php

require_once __DIR__.'/../../admin/includes/sso.php';
require_once __DIR__.'/../../A-management/config/session.php';

beforeEach(function () {
    $this->pdo = new PDO('sqlite::memory:');
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->pdo->exec('CREATE TABLE super_admins (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT)');
    $this->pdo->exec('CREATE TABLE partnership_stores (id INTEGER PRIMARY KEY, store_name TEXT, owner_name TEXT, owner_email TEXT, owner_password TEXT)');
    $this->pdo->exec('CREATE TABLE staff (id INTEGER PRIMARY KEY, full_name TEXT, email TEXT, phone TEXT, password TEXT, role TEXT, store_id INTEGER, staff_type TEXT, is_active INTEGER)');
    $this->pdo->exec('CREATE TABLE riders (id INTEGER PRIMARY KEY, full_name TEXT, email TEXT, phone TEXT, rider_code TEXT, password TEXT, status TEXT, duty_status TEXT)');
});

test('shared sign in authenticates an existing admin account', function () {
    $statement = $this->pdo->prepare('INSERT INTO super_admins (name, email, password) VALUES (?, ?, ?)');
    $statement->execute(['Admin One', 'admin@example.test', password_hash('secret', PASSWORD_DEFAULT)]);

    $auth = ssoAuthenticate($this->pdo, 'admin@example.test', 'secret', '/motobook');

    expect($auth['ok'])->toBeTrue()
        ->and($auth['panel'])->toBe('admin')
        ->and($auth['url'])->toBe('/motobook/admin/dashboard.php');
});

test('admin panel sessions use writable project storage', function () {
    $sessionPath = motobookAdminSessionSavePath();
    $previousPath = session_save_path();
    $previousName = session_name();
    $sessionId = null;

    try {
        ssoSeedPanelSession([
            'panel' => 'admin',
            'role' => 'admin',
            'data' => ['id' => 42, 'name' => 'Test Admin', 'email' => 'admin@example.test'],
        ]);
        $sessionId = session_id();

        expect(is_dir($sessionPath))->toBeTrue()
            ->and(is_writable($sessionPath))->toBeTrue()
            ->and(session_save_path())->toBe($sessionPath)
            ->and($_SESSION['admin_id'])->toBe(42)
            ->and(session_status())->toBe(PHP_SESSION_NONE);
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($sessionId !== null) {
            @unlink($sessionPath.DIRECTORY_SEPARATOR.'sess_'.$sessionId);
        }

        session_id('');
        session_name($previousName);
        session_save_path($previousPath);
    }
});

test('management panel reads session files from the shared login session directory', function () {
    $sharedLoginSessionPath = motobookAdminSessionSavePath();
    $managementSessionPath = motobookManagementSessionSavePath();

    expect($managementSessionPath)->toBe($sharedLoginSessionPath)
        ->and(is_writable($managementSessionPath))->toBeTrue();
});

test('rider panel reads the session created by shared sign in', function () {
    $sessionPath = motobookAdminSessionSavePath();
    $previousPath = session_save_path();
    $previousName = session_name();
    $previousScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
    $sessionId = null;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    session_id('');
    $_SERVER['SCRIPT_NAME'] = '/A-rider/index.php';

    try {
        ssoSeedPanelSession([
            'panel' => 'rider',
            'role' => 'rider',
            'data' => [
                'id' => 42,
                'rider_code' => 'RDR-042',
                'name' => 'Test Rider',
                'email' => 'rider@example.test',
                'status' => 'ACTIVE',
            ],
        ]);
        $sessionId = session_id();

        require_once __DIR__.'/../../A-rider/config/app.php';

        expect(session_name())->toBe('motobook_rider_session')
            ->and(session_save_path())->toBe($sessionPath)
            ->and(is_writable(session_save_path()))->toBeTrue()
            ->and($_SESSION['rider_id'])->toBe(42);
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($sessionId !== null && $sessionId !== '') {
            @unlink($sessionPath.DIRECTORY_SEPARATOR.'sess_'.$sessionId);
        }

        session_id('');
        session_name($previousName);
        session_save_path($previousPath);

        if ($previousScriptName === null) {
            unset($_SERVER['SCRIPT_NAME']);
        } else {
            $_SERVER['SCRIPT_NAME'] = $previousScriptName;
        }
    }
});

test('shared sign in authenticates management staff and sends them to management', function () {
    $statement = $this->pdo->prepare(
        'INSERT INTO staff (full_name, email, password, role, staff_type, is_active) VALUES (?, ?, ?, ?, ?, 1)'
    );
    $statement->execute(['Dispatcher', 'staff@example.test', password_hash('secret', PASSWORD_DEFAULT), 'order_approver', 'platform']);

    $auth = ssoAuthenticate($this->pdo, 'staff@example.test', 'secret', '/motobook');

    expect($auth['ok'])->toBeTrue()
        ->and($auth['panel'])->toBe('management')
        ->and($auth['url'])->toBe('/motobook/A-management/orders.php?tab=kanban');
});

test('shared sign in authenticates an existing rider account', function () {
    $statement = $this->pdo->prepare(
        'INSERT INTO riders (full_name, email, phone, rider_code, password, status) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->execute(['Rider One', 'rider@example.test', '09171234567', 'RDR-001', password_hash('secret', PASSWORD_DEFAULT), 'active']);

    $auth = ssoAuthenticate($this->pdo, 'rider@example.test', 'secret', '/motobook');

    expect($auth['ok'])->toBeTrue()
        ->and($auth['panel'])->toBe('rider')
        ->and($auth['url'])->toBe('/motobook/A-rider/');
});

test('shared sign in rejects invalid passwords and inactive accounts', function () {
    $statement = $this->pdo->prepare(
        'INSERT INTO riders (full_name, email, phone, rider_code, password, status) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->execute(['Rider One', 'rider@example.test', '09171234567', 'RDR-001', password_hash('secret', PASSWORD_DEFAULT), 'suspended']);

    expect(ssoAuthenticate($this->pdo, 'rider@example.test', 'wrong', '/motobook')['ok'])->toBeFalse()
        ->and(ssoAuthenticate($this->pdo, 'rider@example.test', 'secret', '/motobook')['ok'])->toBeFalse();
});

test('shared sign in accepts a rider phone number', function () {
    $statement = $this->pdo->prepare(
        'INSERT INTO riders (full_name, email, phone, rider_code, password, status) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->execute(['Rider One', 'rider@example.test', '+63 917-123-4567', 'RDR-001', password_hash('secret', PASSWORD_DEFAULT), 'active']);

    $auth = ssoAuthenticate($this->pdo, '09171234567', 'secret', '/motobook');

    expect($auth['ok'])->toBeTrue()
        ->and($auth['panel'])->toBe('rider');
});

test('public sign up creates an inactive rider account', function () {
    expect(ssoRegisterRider($this->pdo, 'New Rider', 'new@example.test', '09170001111', 'very-secure-password'))
        ->toBeTrue();

    $rider = $this->pdo->query('SELECT email, password, status FROM riders WHERE email = "new@example.test"')->fetch(PDO::FETCH_ASSOC);

    expect($rider['email'])->toBe('new@example.test')
        ->and($rider['status'])->toBe('inactive')
        ->and(password_verify('very-secure-password', $rider['password']))->toBeTrue()
        ->and(ssoAuthenticate($this->pdo, 'new@example.test', 'very-secure-password', '/motobook')['ok'])->toBeFalse();
});

test('password reset tokens update one account and cannot be reused', function () {
    $statement = $this->pdo->prepare(
        'INSERT INTO riders (full_name, email, phone, rider_code, password, status) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->execute(['Rider One', 'rider@example.test', '09171234567', 'RDR-001', password_hash('old-password', PASSWORD_DEFAULT), 'active']);
    $account = ssoFindPasswordResetAccount($this->pdo, 'rider@example.test');
    $token = ssoCreatePasswordResetToken($this->pdo, $account);

    expect(ssoResetPassword($this->pdo, $token, 'new-secure-password'))
        ->toBeTrue()
        ->and(ssoResetPassword($this->pdo, $token, 'another-secure-password'))
        ->toBeFalse();

    $storedPassword = $this->pdo->query('SELECT password FROM riders WHERE email = "rider@example.test"')->fetchColumn();
    expect(password_verify('new-secure-password', $storedPassword))->toBeTrue();
});

test('password reset works for management staff and store owner accounts', function () {
    $staffStatement = $this->pdo->prepare(
        'INSERT INTO staff (full_name, email, password, role, staff_type, is_active) VALUES (?, ?, ?, ?, ?, 1)'
    );
    $staffStatement->execute(['Dispatcher', 'staff@example.test', password_hash('old-password', PASSWORD_DEFAULT), 'order_approver', 'platform']);
    $ownerStatement = $this->pdo->prepare(
        'INSERT INTO partnership_stores (store_name, owner_name, owner_email, owner_password) VALUES (?, ?, ?, ?)'
    );
    $ownerStatement->execute(['Store', 'Owner', 'owner@example.test', password_hash('old-password', PASSWORD_DEFAULT)]);

    foreach (['staff@example.test', 'owner@example.test'] as $email) {
        $account = ssoFindPasswordResetAccount($this->pdo, $email);
        $token = ssoCreatePasswordResetToken($this->pdo, $account);

        expect(ssoResetPassword($this->pdo, $token, 'new-secure-password'))->toBeTrue();
    }

    $staffPassword = $this->pdo->query('SELECT password FROM staff WHERE email = "staff@example.test"')->fetchColumn();
    $ownerPassword = $this->pdo->query('SELECT owner_password FROM partnership_stores WHERE owner_email = "owner@example.test"')->fetchColumn();

    expect(password_verify('new-secure-password', $staffPassword))->toBeTrue()
        ->and(password_verify('new-secure-password', $ownerPassword))->toBeTrue();
});

test('shared sign in only accepts redirects within the authenticated panel', function () {
    $originalHost = $_SERVER['HTTP_HOST'] ?? null;
    $originalHttps = $_SERVER['HTTPS'] ?? null;
    $_SERVER['HTTP_HOST'] = 'motobook.test';
    $_SERVER['HTTPS'] = 'on';

    try {
        expect(ssoSafeRedirect('https://motobook.test/motobook/A-rider/orders', 'rider', '/motobook'))
            ->toBe('/motobook/A-rider/orders')
            ->and(ssoSafeRedirect('https://evil.test/motobook/A-rider/orders', 'rider', '/motobook'))
            ->toBeNull()
            ->and(ssoSafeRedirect('/admin/dashboard.php', 'rider', '/motobook'))
            ->toBeNull();
    } finally {
        if ($originalHost === null) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $originalHost;
        }

        if ($originalHttps === null) {
            unset($_SERVER['HTTPS']);
        } else {
            $_SERVER['HTTPS'] = $originalHttps;
        }
    }
});
