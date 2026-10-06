<?php

require_once __DIR__.'/../../admin/includes/sso.php';

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
