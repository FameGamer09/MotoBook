<?php

declare(strict_types=1);

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/sso.php';

$baseUrl = ssoBaseUrl($_SERVER['SCRIPT_NAME'] ?? '/admin/reset-password.php');
$pageUrl = $baseUrl.'/admin/reset-password.php';
ssoStartAuthSession();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$error = '';
$success = false;
$validToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;

if ($validToken) {
    try {
        ssoEnsurePasswordResetTable(getDBConnection());
        $statement = getDBConnection()->prepare(
            'SELECT 1 FROM sso_password_reset_tokens WHERE token_hash = ? AND expires_at > ? LIMIT 1'
        );
        $statement->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
        $validToken = $statement->fetchColumn() !== false;
    } catch (Throwable $exception) {
        error_log('MotoBook password reset token lookup failed: '.$exception->getMessage());
        $validToken = false;
        $error = 'We could not verify this reset link. Please request a new one.';
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $validToken) {
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (! ssoValidCsrfToken(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (strlen($password) < 12) {
        $error = 'Your password must be at least 12 characters.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $success = ssoResetPassword(getDBConnection(), $token, $password);
            if (! $success) {
                $validToken = false;
                $error = 'This reset link is invalid or has expired. Request a new one.';
            }
        } catch (Throwable $exception) {
            error_log('MotoBook password reset failed: '.$exception->getMessage());
            $error = 'We could not reset your password right now. Please try again later.';
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Choose a New Password | Motobook</title>
    <link rel="stylesheet" href="<?= ssoH($baseUrl) ?>/admin/assets/css/auth.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">
        <div class="brand-mark"><img src="<?= ssoH($baseUrl) ?>/public/images/login-scooter.svg" alt="Motobook scooter"></div>
        <span>Motobook</span>
    </div>
    <h1>Choose a New Password</h1>
    <p class="intro">Use at least 12 characters for your new password.</p>
    <?php if ($success) { ?>
        <p class="notice notice-success" role="status">Your password has been changed. You can now sign in with your new password.</p>
        <div class="form-actions"><a class="primary-button button-link" href="<?= ssoH($baseUrl) ?>/admin/login.php">Return to Log In</a></div>
    <?php } else { ?>
        <?php if ($error !== '') { ?>
            <p class="notice notice-error" role="alert"><?= ssoH($error) ?></p>
        <?php } ?>
        <?php if ($validToken) { ?>
            <form method="post" action="<?= ssoH($pageUrl) ?>" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= ssoH($_SESSION['sso_csrf_token']) ?>">
                <input type="hidden" name="token" value="<?= ssoH($token) ?>">
                <div class="field">
                    <label for="password">New Password</label>
                    <div class="input-wrap"><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required></div>
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm New Password</label>
                    <div class="input-wrap"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></div>
                </div>
                <div class="form-actions"><button class="primary-button" type="submit">Reset Password</button></div>
            </form>
        <?php } else { ?>
            <div class="form-actions"><a class="primary-button button-link" href="<?= ssoH($baseUrl) ?>/admin/forgot-password.php">Request a New Reset Link</a></div>
        <?php } ?>
    <?php } ?>
</main>
</body>
</html>
