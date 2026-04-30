<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();

global $db;
$user = current_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Valid email required.');
        } else {
            $existing = $db->fetch("SELECT id FROM " . DB_PREFIX . "users WHERE email = ? AND id != ?", [$email, $user['id']]);
            if ($existing) {
                flash('error', 'That email is already used by another admin.');
            } else {
                $db->query("UPDATE " . DB_PREFIX . "users SET name = ?, email = ? WHERE id = ?", [$name, $email, $user['id']]);
                admin_log('profile_update');
                flash('success', 'Profile updated.');
            }
        }
        redirect('/admin/profile.php');
    }
    
    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        
        if (!password_verify($current, $user['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->query("UPDATE " . DB_PREFIX . "users SET password_hash = ? WHERE id = ?", [$hash, $user['id']]);
            admin_log('password_change');
            flash('success', 'Password changed successfully.');
        }
        redirect('/admin/profile.php');
    }
}

include __DIR__ . '/_header.php';
?>

<div class="page-head">
    <div>
        <h1>👤 My Profile</h1>
        <div class="subtitle">Update your account details</div>
    </div>
</div>

<div class="card">
    <h2>Profile Information</h2>
    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_profile">
        
        <div class="form-row">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?php echo escape($user['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?php echo escape($user['email']); ?>">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Save Profile</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>🔒 Change Password</h2>
    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="change_password">
        
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required minlength="8">
                <small>At least 8 characters.</small>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">🔒 Change Password</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>📊 Account Info</h2>
    <table class="data-table">
        <tr><td><strong>Account Created</strong></td><td><?php echo escape($user['created_at']); ?></td></tr>
        <tr><td><strong>Last Login</strong></td><td><?php echo escape($user['last_login'] ?? 'Never'); ?></td></tr>
        <tr><td><strong>Role</strong></td><td><span class="badge badge-info"><?php echo escape($user['role']); ?></span></td></tr>
    </table>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
