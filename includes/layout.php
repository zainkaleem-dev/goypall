<?php
/**
 * Public-facing layout helpers
 * Include site header/footer with proper styling
 */

function render_header($pageTitle = '', $metaDesc = '') {
    $siteName = settings('site_name', 'CryptoExchange Pro');
    $title = $pageTitle ? $pageTitle . ' — ' . $siteName : settings('page_title', $siteName);
    $desc = $metaDesc ?: settings('seo_description', '');
    $keywords = settings('seo_keywords', '');
    $logo = settings('logo_url');
    $favicon = settings('favicon_url');
    $supportUrl = settings('support_url', '#');
    $supportText = settings('support_button_text', 'Support');
    
    // Get pages for footer/nav
    global $db;
    $navPages = $db->fetchAll("SELECT slug, title FROM " . DB_PREFIX . "pages WHERE status = 'published' ORDER BY sort_order ASC, id ASC");
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo escape($title); ?></title>
<meta name="description" content="<?php echo escape($desc); ?>">
<?php if ($keywords): ?><meta name="keywords" content="<?php echo escape($keywords); ?>"><?php endif; ?>
<?php if ($favicon): ?><link rel="icon" href="<?php echo escape($favicon); ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/public.css?v=<?php echo APP_VERSION; ?>">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a href="/" class="brand">
            <?php if ($logo): ?>
                <img src="<?php echo escape($logo); ?>" alt="<?php echo escape($siteName); ?>" class="brand-logo">
            <?php else: ?>
                <span class="brand-icon">⚡</span>
            <?php endif; ?>
            <span class="brand-name"><?php echo escape($siteName); ?></span>
        </a>
        
        <button class="nav-toggle" onclick="document.querySelector('.site-nav').classList.toggle('open')" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        
        <nav class="site-nav">
            <a href="/" class="nav-link">Home</a>
            <a href="/faq" class="nav-link">FAQ</a>
            <a href="/contact" class="nav-link">Contact</a>
            <a href="/page/refund" class="nav-link">Refund</a>
            <a href="/page/privacy" class="nav-link">Privacy</a>
            <a href="/page/terms" class="nav-link">Terms</a>
            <a href="/support" class="nav-link nav-support">Support Portal</a>
        </nav>
    </div>
</header>

<main class="site-main">
<?php
}

function render_footer() {
    $siteName = settings('site_name', 'CryptoExchange Pro');
    $footerText = settings('footer_text', '© ' . date('Y') . ' ' . $siteName . '. All rights reserved.');
    $contactEmail = settings('contact_email', '');
    $supportUrl = settings('support_url', '#');
    ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h4><?php echo escape($siteName); ?></h4>
                <p class="footer-tagline"><?php echo escape(settings('hero_subtitle', '')); ?></p>
                <?php if ($contactEmail): ?>
                <p class="footer-contact">📧 <a href="mailto:<?php echo escape($contactEmail); ?>"><?php echo escape($contactEmail); ?></a></p>
                <?php endif; ?>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/faq">FAQ</a></li>
                    <li><a href="/contact">Contact Us</a></li>
                    <li><a href="/page/full-description">Full Description</a></li>
                    <li><a href="<?php echo escape($supportUrl); ?>" target="_blank" rel="noopener">Support ↗</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <ul>
                    <li><a href="/page/terms">Terms of Use</a></li>
                    <li><a href="/page/privacy">Privacy Policy</a></li>
                    <li><a href="/page/refund">Refund Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <?php echo escape($footerText); ?>
        </div>
    </div>
</footer>

<script src="/assets/js/public.js?v=<?php echo APP_VERSION; ?>"></script>
</body>
</html>
<?php
}
