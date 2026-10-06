<?php

declare(strict_types=1);

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/sso.php';

$baseUrl = ssoBaseUrl($_SERVER['SCRIPT_NAME'] ?? '/admin/register.php');
$pageUrl = $baseUrl.'/admin/register.php';
ssoStartAuthSession();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$error = '';
$name = '';
$email = '';
$phone = '';
$submitted = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (! ssoValidCsrfToken(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $submitted = ssoRegisterRider(getDBConnection(), $name, $email, $phone, $password);
            if (! $submitted) {
                $error = 'Please check your details. The email or phone may already be registered, or the password may not meet the requirements.';
            }
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $error = 'That email or phone number is already registered.';
            } else {
                error_log('MotoBook rider registration failed: '.$exception->getMessage());
                $error = 'We could not submit your application right now. Please try again later.';
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Rider Sign Up | Motobook</title>
    <link rel="stylesheet" href="<?= ssoH($baseUrl) ?>/admin/assets/css/auth.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">
        <div class="brand-mark"><img src="<?= ssoH($baseUrl) ?>/public/images/login-scooter.svg" alt="Motobook scooter"></div>
        <span>Motobook</span>
    </div>
    <h1>Rider Sign Up</h1>
    <p class="intro">Apply for a rider account.<br>Admin approval is required before sign-in.</p>
    <?php if ($submitted) { ?>
        <p class="notice notice-success" role="status">Your rider application was submitted. An administrator must activate the account before you can sign in.</p>
        <div class="form-actions">
            <a class="primary-button button-link" href="<?= ssoH($baseUrl) ?>/admin/login.php">Return to Log In</a>
        </div>
    <?php } else { ?>
        <?php if ($error !== '') { ?>
            <p class="notice notice-error" role="alert"><?= ssoH($error) ?></p>
        <?php } ?>
        <form method="post" action="<?= ssoH($pageUrl) ?>" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= ssoH($_SESSION['sso_csrf_token']) ?>">
            <div class="field">
                <label for="name">Full Name</label>
                <div class="input-wrap"><input id="name" name="name" type="text" autocomplete="name" maxlength="150" required value="<?= ssoH($name) ?>" placeholder="Enter your full name"></div>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <div class="input-wrap"><input id="email" name="email" type="email" autocomplete="email" maxlength="150" required value="<?= ssoH($email) ?>" placeholder="Enter your email"></div>
            </div>
            <div class="field">
                <label for="phone">Phone Number</label>
                <div class="input-wrap"><input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" required value="<?= ssoH($phone) ?>" placeholder="Enter your phone number"></div>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap"><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required placeholder="At least 12 characters"></div>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm Password</label>
                <div class="input-wrap"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required placeholder="Enter your password again"></div>
            </div>
            <div class="form-actions">
                <button class="primary-button" type="submit">Submit Rider Application</button>
                <a class="secondary-link" href="<?= ssoH($baseUrl) ?>/admin/login.php">Already have an account? Log In</a>
            </div>
        </form>
    <?php } ?>
</main>
</body>
</html>
