<?php

declare(strict_types=1);

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/sso.php';

$baseUrl = ssoBaseUrl($_SERVER['SCRIPT_NAME'] ?? '/admin/login.php');
$loginUrl = $baseUrl.'/admin/login.php';
$formUrl = $loginUrl;
if (isset($_GET['redirect']) && is_string($_GET['redirect'])) {
    $formUrl .= '?redirect='.rawurlencode($_GET['redirect']);
}

ssoStartAuthSession();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['sso_csrf_token'])) {
    $_SESSION['sso_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$identifier = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    $now = time();
    $attempts = array_filter(
        $_SESSION['sso_login_attempts'] ?? [],
        static fn (int $attempt): bool => $attempt > $now - 900
    );
    $_SESSION['sso_login_attempts'] = array_values($attempts);

    if (! ssoValidCsrfToken($csrfToken)) {
        $error = 'Your sign-in session expired. Refresh the page and try again.';
    } elseif (count($attempts) >= 5) {
        $error = 'Too many sign-in attempts. Wait 15 minutes and try again.';
    } else {
        try {
            $auth = ssoAuthenticate(getDBConnection(), $identifier, $password, $baseUrl);
            if ($auth['ok']) {
                $destination = ssoSafeRedirect(
                    isset($_GET['redirect']) && is_string($_GET['redirect']) ? $_GET['redirect'] : null,
                    $auth['panel'],
                    $baseUrl
                ) ?? $auth['url'];
                unset($_SESSION['sso_login_attempts']);
                ssoSeedPanelSession($auth);
                header('Location: '.$destination, true, 303);
                exit;
            }
            $_SESSION['sso_login_attempts'][] = $now;
            $error = $auth['message'];
        } catch (Throwable $exception) {
            error_log('Motobook sign-in unavailable: '.$exception->getMessage());
            $error = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Log In | Motobook</title>
    <link rel="stylesheet" href="<?= ssoH($baseUrl) ?>/admin/assets/css/auth.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">
        <div class="brand-mark">
            <img src="<?= ssoH($baseUrl) ?>/public/images/825310998_1610647237377200_4391978218746131377_n.png" alt="Motobook scooter">
        </div>
        <span>Motobook</span>
    </div>
    <h1>Log In</h1>
    <p class="intro">Welcome back! Please login<br class="desktop-break"> to your account.</p>
    <?php if ($error !== '') { ?>
        <p class="notice notice-error" role="alert"><?= ssoH($error) ?></p>
    <?php } ?>
    <p class="notice notice-info" id="provider-notice" role="status" aria-live="polite" hidden></p>
    <form method="post" action="<?= ssoH($formUrl) ?>" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= ssoH($_SESSION['sso_csrf_token']) ?>">
        <div class="field">
            <label for="identifier">Email or Phone Number</label>
            <div class="input-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M5 21v-2a7 7 0 0 1 14 0v2"></path></svg>
                <input id="identifier" name="identifier" type="text" autocomplete="username" maxlength="150"
                       placeholder="Enter your Email or Phone Number" required
                       value="<?= ssoH($identifier) ?>">
            </div>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 1 1 8 0v3"></path></svg>
                <input id="password" name="password" type="password" autocomplete="current-password"
                       placeholder="Enter your password" required>
                <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="eye-off-icon" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3 3.9M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.6 10.6 0 0 0 3.3-.5"></path></svg>
                </button>
            </div>
        </div>
        <div class="forgot-row"><a href="<?= ssoH($baseUrl) ?>/admin/forgot-password.php">Forget Password?</a></div>
        <button class="primary-button" type="submit">Log In</button>
    </form>
    <div class="divider"><span>or continue with</span></div>
    <div class="social-buttons" aria-label="Other sign-in options">
        <button class="social-button" type="button" data-provider="Google" aria-label="Continue with Google">
            <svg class="google-icon" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11a9.4 9.4 0 0 1-4.1 6.2v5.1h6.6c3.9-3.6 6.1-8.8 6.1-15Z"></path><path fill="#FF3D00" d="M24 44c5.6 0 10.3-1.9 13.7-5.1l-6.6-5.1c-1.8 1.2-4.1 2-7.1 2-5.4 0-10-3.7-11.6-8.7H5.6v5.3A20 20 0 0 0 24 44Z"></path><path fill="#4CAF50" d="M12.4 27.1a12 12 0 0 1 0-6.2v-5.3H5.6a20 20 0 0 0 0 16.8l6.8-5.3Z"></path><path fill="#1976D2" d="M24 12.2c3.1 0 5.9 1.1 8.1 3.2l6-6C34.5 5.9 29.7 4 24 4A20 20 0 0 0 5.6 15.6l6.8 5.3c1.6-5 6.2-8.7 11.6-8.7Z"></path></svg>
        </button>
        <button class="social-button" type="button" data-provider="Facebook" aria-label="Continue with Facebook">
            <svg class="facebook-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13.6 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5h1.7V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.4V13h2.8v8h3.4Z"></path></svg>
        </button>
        <button class="social-button" type="button" data-provider="Apple" aria-label="Continue with Apple">
            <svg class="apple-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16.7 12.8c0-2.3 1.9-3.4 2-3.5a4.3 4.3 0 0 0-3.4-1.8c-1.4-.2-2.8.8-3.5.8s-1.8-.8-3-.8a4.5 4.5 0 0 0-3.8 2.3c-1.6 2.8-.4 6.9 1.2 9.1.8 1.1 1.7 2.3 2.8 2.2 1.1 0 1.5-.7 2.9-.7s1.8.7 3 .7 2-1.1 2.8-2.2a9.8 9.8 0 0 0 1.3-2.6 4 4 0 0 1-2.3-3.5ZM14.5 6a4.1 4.1 0 0 0 1-3 4.2 4.2 0 0 0-2.7 1.4 3.9 3.9 0 0 0-1 2.9 3.5 3.5 0 0 0 2.7-1.3Z"></path></svg>
        </button>
    </div>
    <p class="signup-copy">Don't have an account? <a href="<?= ssoH($baseUrl) ?>/admin/register.php">Sign Up</a></p>
    <p class="footer-copy">One unified sign-in for every Motobook panel. If you get stuck, contact the Super Admin or return to <a href="<?= ssoH($loginUrl) ?>">this login page</a>.</p>
</main>
<script>
    const passwordField = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');
    passwordToggle.addEventListener('click', () => {
        const visible = passwordField.type === 'password';
        passwordField.type = visible ? 'text' : 'password';
        passwordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('aria-pressed', String(visible));
        passwordToggle.querySelector('.eye-icon').hidden = visible;
        passwordToggle.querySelector('.eye-off-icon').hidden = !visible;
    });

    document.querySelectorAll('[data-provider]').forEach((button) => {
        button.addEventListener('click', () => {
            const provider = button.dataset.provider;
            const notice = document.getElementById('provider-notice');
            notice.textContent = `${provider} sign-in is not configured yet. Contact the administrator to enable it.`;
            notice.hidden = false;
        });
    });
</script>
</body>
</html>
