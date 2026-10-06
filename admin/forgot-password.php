<?php

declare(strict_types=1);

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/sso.php';

$baseUrl = ssoBaseUrl($_SERVER['SCRIPT_NAME'] ?? '/admin/forgot-password.php');
$pageUrl = $baseUrl.'/admin/forgot-password.php';
ssoStartAuthSession();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$email = '';
$error = '';
$success = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $now = time();

    if (! ssoValidCsrfToken(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (isset($_SESSION['sso_recovery_last_requested_at'])
        && $_SESSION['sso_recovery_last_requested_at'] > $now - 60) {
        $error = 'Please wait a minute before requesting another reset link.';
    } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Enter a valid email address.';
    } else {
        $_SESSION['sso_recovery_last_requested_at'] = $now;
        try {
            $pdo = getDBConnection();
            $account = ssoFindPasswordResetAccount($pdo, $email);
            if ($account !== null) {
                $token = ssoCreatePasswordResetToken($pdo, $account);
                $resetUrl = ssoAbsoluteUrl($baseUrl.'/admin/reset-password.php?token='.rawurlencode($token));
                ssoSendPasswordResetEmail($account['email'], $resetUrl);
            }
            $success = true;
        } catch (Throwable $exception) {
            error_log('MotoBook password reset email could not be sent: '.$exception->getMessage());
            $error = 'We could not send a reset email right now. Please try again later or contact an administrator.';
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Reset Password | Motobook</title>
    <link rel="stylesheet" href="<?= ssoH($baseUrl) ?>/admin/assets/css/auth.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">
        <div class="brand-mark"><img src="<?= ssoH($baseUrl) ?>/public/images/login-scooter.svg" alt="Motobook scooter"></div>
        <span>Motobook</span>
    </div>
    <h1>Forgot Password?</h1>
    <p class="intro">Enter the email on your Motobook account and we’ll send a secure reset link.</p>
    <?php if ($error !== '') { ?>
        <p class="notice notice-error" role="alert"><?= ssoH($error) ?></p>
    <?php } ?>
    <?php if ($success) { ?>
        <p class="notice notice-success" role="status">If an active account uses that email, a password reset link has been sent. The link expires in 30 minutes.</p>
    <?php } else { ?>
        <form method="post" action="<?= ssoH($pageUrl) ?>" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= ssoH($_SESSION['sso_csrf_token']) ?>">
            <div class="field">
                <label for="email">Email</label>
                <div class="input-wrap"><input id="email" name="email" type="email" autocomplete="email" maxlength="150" required value="<?= ssoH($email) ?>" placeholder="Enter your account email"></div>
            </div>
            <div class="form-actions">
                <button class="primary-button" type="submit">Send Reset Link</button>
                <a class="secondary-link" href="<?= ssoH($baseUrl) ?>/admin/login.php">Back to Log In</a>
            </div>
        </form>
    <?php } ?>
</main>
</body>
</html>
