<?php
require_once dirname(__DIR__) . '/init.php';

if (is_admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    
    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        global $db;
        $user = $db->fetch("SELECT * FROM " . DB_PREFIX . "users WHERE email = ? LIMIT 1", [$email]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Success
            session_regenerate_id(true);
            $_SESSION['admin_user_id'] = $user['id'];
            $db->query("UPDATE " . DB_PREFIX . "users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            redirect('/admin/dashboard.php');
        } else {
            $error = 'Invalid email or password.';
            // Brief delay to slow brute force
            usleep(500000);
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — <?php echo escape(settings('site_name', 'Pitch')); ?></title>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css?v=<?php echo APP_VERSION; ?>">
</head>
<body>

<div class="login-page">
    <div class="login-card">
        <h1>🔐 Admin Login</h1>
        <p class="subtitle"><?php echo escape(settings('site_name', 'Pitch Page')); ?> Admin Panel</p>
        
        <?php if ($error): ?>
        <div class="alert alert-error">⚠ <?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <?php echo csrf_field(); ?>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus 
                       value="<?php echo escape($_POST['email'] ?? ''); ?>"
                       placeholder="admin@yourdomain.com">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Your password">
            </div>
            
            <button type="submit" class="btn-login">Sign In →</button>
        </form>
        
        <div style="text-align:center;margin-top:24px;color:#9ca3af;font-size:12px;">
            <a href="<?php echo SITE_URL; ?>/" style="color:#6b7280;">← Back to site</a>
        </div>
    </div>
</div>

</body>
</html>
