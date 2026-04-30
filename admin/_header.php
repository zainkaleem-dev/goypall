<?php
/**
 * Admin Layout — sidebar + topbar
 * Include at the top of every admin page (after init.php and require_admin_login())
 */

if (!is_admin_logged_in()) {
    redirect('/admin/login.php');
}

$adminUser = current_admin();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$siteName = settings('site_name', 'Pitch Admin');

function admin_nav_link($script, $label, $icon = '') {
    global $currentScript;
    $active = ($currentScript === $script) ? 'active' : '';
    return '<a href="' . SITE_URL . '/admin/' . $script . '" class="admin-nav-link ' . $active . '">' 
         . ($icon ? '<span class="ico">' . $icon . '</span>' : '') 
         . '<span>' . htmlspecialchars($label) . '</span></a>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — <?php echo escape($siteName); ?></title>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css?v=<?php echo APP_VERSION; ?>">
</head>
<body>

<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <span class="brand-icon">⚡</span>
            <span class="brand-text"><?php echo escape($siteName); ?></span>
        </div>
        
        <nav class="admin-nav">
            <div class="nav-section">Dashboard</div>
            <?php echo admin_nav_link('dashboard.php', 'Overview', '📊'); ?>
            <?php echo admin_nav_link('orders.php', 'Orders', '📦'); ?>
            <?php echo admin_nav_link('tickets.php', 'Tickets', '🎫'); ?>
            
            <div class="nav-section">Content</div>
            <?php echo admin_nav_link('settings.php', 'Site Settings', '⚙️'); ?>
            <?php echo admin_nav_link('screenshots.php', 'Screenshots', '🖼️'); ?>
            <?php echo admin_nav_link('faqs.php', 'FAQ', '❓'); ?>
            <?php echo admin_nav_link('pages.php', 'Pages', '📄'); ?>
            <?php
            // Unread message count badge
            $unreadMsgCount = $db->fetch("SELECT COUNT(*) c FROM " . DB_PREFIX . "contact_messages WHERE status = 'unread'")['c'] ?? 0;
            $msgBadge = $unreadMsgCount > 0 ? ' <span style="background:#dc2626;color:#fff;padding:1px 7px;border-radius:99px;font-size:10px;font-weight:700;margin-left:4px;">' . $unreadMsgCount . '</span>' : '';
            ?>
            <a href="<?php echo SITE_URL; ?>/admin/messages.php" class="admin-nav-link <?php echo $currentScript === 'messages.php' ? 'active' : ''; ?>">
                <span class="ico">📬</span><span>Messages<?php echo $msgBadge; ?></span>
            </a>
            
            <div class="nav-section">Account</div>
            <?php echo admin_nav_link('profile.php', 'My Profile', '👤'); ?>
            <a href="<?php echo SITE_URL; ?>/admin/logout.php" class="admin-nav-link"><span class="ico">🚪</span><span>Logout</span></a>
        </nav>
    </aside>
    
    <!-- Main content area -->
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')" aria-label="Menu">☰</button>
            </div>
            <div class="topbar-right">
                <a href="<?php echo SITE_URL; ?>/" target="_blank" class="topbar-link">View Site ↗</a>
                <span class="topbar-user">👤 <?php echo escape($adminUser['name'] ?? $adminUser['email']); ?></span>
            </div>
        </header>
        
        <div class="admin-content">
            <?php
            // Flash messages
            $success = flash('success');
            $error = flash('error');
            if ($success): ?>
                <div class="alert alert-success">✓ <?php echo escape($success); ?></div>
            <?php endif;
            if ($error): ?>
                <div class="alert alert-error">⚠ <?php echo escape($error); ?></div>
            <?php endif; ?>
