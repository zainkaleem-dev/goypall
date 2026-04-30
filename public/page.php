<?php
/**
 * Generic page viewer for refund / privacy / terms / full-description / etc.
 * URL: /page/{slug}
 */
require_once dirname(__DIR__) . '/init.php';
require_once INCLUDES_PATH . '/layout.php';

global $db;

// Slug from URL: index.php redirects /page/{slug} → ?page=page&slug={slug}
$slug = trim($_GET['slug'] ?? '');

if (!$slug) {
    http_response_code(404);
    render_header('Page Not Found');
    echo '<section class="page-section"><div class="container"><div class="page-header"><h1>Page Not Found</h1></div><p style="text-align:center;color:var(--c-text-muted);">The page you requested does not exist.</p></div></section>';
    render_footer();
    exit;
}

$page = $db->fetch(
    "SELECT * FROM " . DB_PREFIX . "pages WHERE slug = ? AND status = 'published' LIMIT 1",
    [$slug]
);

if (!$page) {
    http_response_code(404);
    render_header('Page Not Found');
    echo '<section class="page-section"><div class="container"><div class="page-header"><h1>Page Not Found</h1></div><p style="text-align:center;color:var(--c-text-muted);">The page <code>' . escape($slug) . '</code> was not found.</p><div style="text-align:center;margin-top:30px;"><a href="/" class="btn btn-outline">Back to Home</a></div></div></section>';
    render_footer();
    exit;
}

render_header($page['title'], $page['meta_description'] ?? '');
?>

<section class="page-section">
    <div class="container">
        <div class="page-header">
            <h1><?php echo escape($page['title']); ?></h1>
            <p class="meta">Last updated: <?php echo date('F j, Y', strtotime($page['updated_at'])); ?></p>
        </div>

        <div class="page-content">
            <?php echo $page['content']; // HTML content from admin editor ?>
        </div>

        <?php if ($slug === 'full-description'): ?>
        <div style="text-align:center;margin-top:50px;padding:30px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:var(--radius-lg);max-width:700px;margin-left:auto;margin-right:auto;">
            <h3 style="color:var(--c-text);margin-bottom:12px;">Ready to launch?</h3>
            <p style="color:var(--c-text-soft);margin-bottom:20px;"><?php echo escape(settings('cta_right_text')); ?></p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="<?php echo escape(settings('demo_button_url')); ?>" target="_blank" rel="noopener" class="btn btn-demo">🚀 <?php echo escape(settings('demo_button_text')); ?></a>
                <a href="<?php echo escape(settings('buy_button_url')); ?>" class="btn btn-buy">💰 <?php echo escape(settings('buy_button_text')); ?></a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php render_footer(); ?>
