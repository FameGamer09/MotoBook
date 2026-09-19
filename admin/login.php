<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } elseif (loginAdmin($email, $password)) {
        redirect('/dashboard.php');
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-icon">M</div>
            <h1>Motobook Super Admin</h1>
            <p>Sign in to access the platform control center</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?= e($_POST['email'] ?? 'admin@motobook.com') ?>" placeholder="admin@motobook.com">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required
                       placeholder="Enter password" value="password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">Sign In</button>
        </form>

        <p class="text-muted text-center mt-1" style="font-size:0.8rem;margin-top:1rem;">
            Default login: admin@motobook.com / password
        </p>
    </div>
</div>
</body>
</html>
