<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();

global $db;

// Stats
$stats = [
    'screenshots' => $db->fetch("SELECT COUNT(*) c FROM " . DB_PREFIX . "screenshots WHERE status='active'")['c'] ?? 0,
    'faqs'        => $db->fetch("SELECT COUNT(*) c FROM " . DB_PREFIX . "faqs WHERE status='active'")['c'] ?? 0,
    'pages'       => $db->fetch("SELECT COUNT(*) c FROM " . DB_PREFIX . "pages WHERE status='published'")['c'] ?? 0,
    'admins'      => $db->fetch("SELECT COUNT(*) c FROM " . DB_PREFIX . "users")['c'] ?? 0,
];

include __DIR__ . '/_header.php';
?>

<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="subtitle">Welcome back, <?php echo escape($adminUser['name'] ?? 'Admin'); ?> 👋</div>
    </div>
    <div>
        <a href="/" target="_blank" class="btn btn-secondary">View Pitch Page ↗</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">🖼️</div>
        <div class="stat-label">Screenshots</div>
        <div class="stat-value"><?php echo $stats['screenshots']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">❓</div>
        <div class="stat-label">FAQ Items</div>
        <div class="stat-value"><?php echo $stats['faqs']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📄</div>
        <div class="stat-label">Pages</div>
        <div class="stat-value"><?php echo $stats['pages']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👤</div>
        <div class="stat-label">Admin Users</div>
        <div class="stat-value"><?php echo $stats['admins']; ?></div>
    </div>
</div>

<div class="card">
    <h2>🚀 Quick Actions</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
        <a href="/admin/settings.php" class="btn btn-primary">⚙️ Edit Site Settings</a>
        <a href="/admin/screenshots.php" class="btn btn-success">🖼️ Manage Screenshots</a>
        <a href="/admin/faqs.php" class="btn btn-secondary">❓ Manage FAQ</a>
        <a href="/admin/pages.php" class="btn btn-secondary">📄 Edit Pages</a>
    </div>
</div>

<div class="card">
    <h2>📋 Setup Checklist</h2>
    <p class="muted" style="margin-bottom:16px;">Complete these to make your pitch page conversion-ready:</p>
    
    <?php
    $checks = [
        ['label' => 'Upload your logo', 'done' => !empty(settings('logo_url')), 'link' => '/admin/settings.php#branding'],
        ['label' => 'Set Demo button URL', 'done' => settings('demo_button_url') !== '#' && settings('demo_button_url') !== '', 'link' => '/admin/settings.php#cta'],
        ['label' => 'Set Buy Now button URL', 'done' => settings('buy_button_url') !== '#' && settings('buy_button_url') !== '', 'link' => '/admin/settings.php#cta'],
        ['label' => 'Set Support URL', 'done' => settings('support_url') !== 'https://your-support-site.com' && settings('support_url') !== '', 'link' => '/admin/settings.php#cta'],
        ['label' => 'Add at least 3 screenshots', 'done' => $stats['screenshots'] >= 3, 'link' => '/admin/screenshots.php'],
        ['label' => 'Add at least 5 FAQs', 'done' => $stats['faqs'] >= 5, 'link' => '/admin/faqs.php'],
        ['label' => 'Edit Refund Policy content', 'done' => true, 'link' => '/admin/pages.php'],
        ['label' => 'Review Privacy Policy', 'done' => true, 'link' => '/admin/pages.php'],
        ['label' => 'Review Terms of Use', 'done' => true, 'link' => '/admin/pages.php'],
    ];
    ?>
    <ul style="list-style:none;padding:0;">
        <?php foreach ($checks as $c): ?>
        <li style="padding:10px 12px;border-radius:6px;display:flex;align-items:center;gap:12px;<?php echo $c['done'] ? 'background:#f0fdf4;' : 'background:#fffbeb;'; ?>">
            <span style="font-size:18px;"><?php echo $c['done'] ? '✅' : '⏳'; ?></span>
            <span style="flex:1;"><?php echo escape($c['label']); ?></span>
            <a href="<?php echo escape($c['link']); ?>" class="btn btn-sm btn-secondary">Go →</a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
