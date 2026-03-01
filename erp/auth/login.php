<?php
// ================================================================
//  ERP – Login Page
//  /erp/auth/login.php
// ================================================================
require_once dirname(__DIR__) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';

// Already logged in → straight to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize_string($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        $stmt = db()->prepare(
            'SELECT u.id, u.name, u.password_hash, u.status, r.name AS role
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is inactive. Please contact the administrator.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            // Store session
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role'];

            // Update last login
            db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
               ->execute([$user['id']]);

            audit('auth', 'login');
            header('Location: ' . BASE_URL . '/dashboard/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo">
            <div class="logo-lg">ERP</div>
            <h2><?= APP_NAME ?></h2>
            <p><?= h(ORG_NAME) ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <div style="margin-top:20px;">
                <input type="submit" class="btn btn-primary btn-lg" style="width:100%;" value="Sign In">
            </div>
        </form>

        <div style="text-align:center; margin-top:20px; font-size:11px; color:#556677;">
            Default: admin@erp.local / Admin@1234
        </div>
    </div>
</div>
</body>
</html>
