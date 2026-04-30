<?php
/**
 * Pitch Page — Main Homepage
 */

// Redirect to installer if not configured
if (!file_exists(__DIR__ . '/config/config.php') && is_dir(__DIR__ . '/install')) {
    header('Location: install/');
    exit;
}

require_once __DIR__ . '/init.php';

// Handle pretty URL routing (page=xxx from .htaccess rewrite)
$page = $_GET['page'] ?? '';

// Routes
$routes = [
    'faq'                   => __DIR__ . '/public/faq.php',
    'page'                  => __DIR__ . '/public/page.php',
    'contact'               => __DIR__ . '/public/contact.php',
    'checkout'              => __DIR__ . '/public/checkout.php',
    'thank-you'             => __DIR__ . '/public/thank-you.php',
    'download'              => __DIR__ . '/public/download.php',
    'support'               => __DIR__ . '/public/support.php',
    'payram-checkout'       => __DIR__ . '/public/payram-checkout.php',
    'payram-webhook'        => __DIR__ . '/public/payram-webhook.php',
    'nowpayments-checkout'  => __DIR__ . '/public/nowpayments-checkout.php',
    'nowpayments-webhook'   => __DIR__ . '/public/nowpayments-webhook.php',
];

if ($page && isset($routes[$page])) {
    require $routes[$page];
    exit;
}

// Otherwise render the pitch homepage
require_once INCLUDES_PATH . '/layout.php';

global $db;

$screenshots = $db->fetchAll(
    "SELECT * FROM " . DB_PREFIX . "screenshots WHERE status = 'active' ORDER BY sort_order ASC, id ASC"
);

render_header();
?>

<!-- ====== HERO ====== -->
<section class="hero">
    <div class="container">
        <h1 class="hero-title"><?php echo escape(settings('hero_title')); ?></h1>
        <p class="hero-subtitle"><?php echo escape(settings('hero_subtitle')); ?></p>
    </div>
</section>

<!-- ====== SCREENSHOTS CAROUSEL ====== -->
<?php if (!empty($screenshots)): ?>
<section class="screenshots-section">
    <div class="container">
        <div class="carousel-wrap">
            <button class="carousel-btn carousel-prev" onclick="carouselPrev()" aria-label="Previous">‹</button>
            
            <div class="carousel-viewport">
                <div class="carousel-track" id="carouselTrack">
                    <?php foreach ($screenshots as $i => $s): ?>
                    <div class="carousel-slide<?php echo $i === 0 ? ' active' : ''; ?>" data-index="<?php echo $i; ?>">
                        <img src="<?php echo escape($s['image_url']); ?>" 
                             alt="<?php echo escape($s['title'] ?: 'Screenshot ' . ($i+1)); ?>" 
                             onclick="openLightbox(<?php echo $i; ?>)" 
                             loading="lazy">
                        <?php if ($s['caption']): ?>
                        <div class="carousel-caption"><?php echo escape($s['caption']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button class="carousel-btn carousel-next" onclick="carouselNext()" aria-label="Next">›</button>
        </div>
        
        <div class="carousel-dots">
            <?php foreach ($screenshots as $i => $s): ?>
            <button class="carousel-dot<?php echo $i === 0 ? ' active' : ''; ?>" 
                    onclick="goToSlide(<?php echo $i; ?>)" 
                    aria-label="Go to slide <?php echo $i+1; ?>"></button>
            <?php endforeach; ?>
        </div>
        
        <p class="carousel-hint">📷 Click any screenshot to view full size · Use arrow keys to navigate</p>
    </div>
</section>

<!-- ====== LIGHTBOX ====== -->
<div class="lightbox" id="lightbox" onclick="if(event.target===this)closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()" aria-label="Close">×</button>
    <button class="lightbox-prev" onclick="lightboxPrev(event)" aria-label="Previous">‹</button>
    <button class="lightbox-next" onclick="lightboxNext(event)" aria-label="Next">›</button>
    <div class="lightbox-content">
        <img src="" id="lightboxImage" alt="">
        <div class="lightbox-caption" id="lightboxCaption"></div>
        <div class="lightbox-counter"><span id="lightboxCurrent">1</span> / <span id="lightboxTotal"><?php echo count($screenshots); ?></span></div>
    </div>
</div>

<script>
window.PITCH_SCREENSHOTS = <?php echo json_encode(array_map(function($s){ 
    return ['url' => $s['image_url'], 'caption' => $s['caption'] ?? '', 'title' => $s['title'] ?? '']; 
}, $screenshots)); ?>;
</script>
<?php endif; ?>

<!-- ====== CTA SECTION (Demo + Buy Now) ====== -->
<section class="cta-section">
    <div class="container">
        <div class="cta-grid">
            <div class="cta-block">
                <p class="cta-text"><?php echo escape(settings('cta_left_text')); ?></p>
                <a href="<?php echo escape(settings('demo_button_url')); ?>" 
                   target="_blank" 
                   rel="noopener" 
                   class="btn btn-demo">
                    🚀 <?php echo escape(settings('demo_button_text')); ?>
                </a>
            </div>
            
            <div class="cta-divider"></div>
            
            <div class="cta-block">
                <p class="cta-text"><?php echo escape(settings('cta_right_text')); ?></p>
                <a href="<?php echo escape(settings('buy_button_url')); ?>" 
                   class="btn btn-buy">
                    💰 <?php echo escape(settings('buy_button_text')); ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ====== POST-PURCHASE INFO ====== -->
<section class="info-section">
    <div class="container">
        <div class="info-card">
            <div class="info-icon">📦</div>
            <p class="info-text"><?php echo escape(settings('post_purchase_text')); ?></p>
        </div>
        
        <div class="info-card info-card-promise">
            <div class="info-icon">🎯</div>
            <p class="info-text info-promise"><?php echo escape(settings('support_promise')); ?></p>
        </div>
        
        <div class="info-card info-card-promise">
            <div class="info-icon">🛠</div>
            <p class="info-text info-promise">
                <strong>We provide paid customization.</strong> 
                Need custom features or modifications? <a href="/contact" style="color:#f0b90b;text-decoration:underline;">Contact us</a> for a quote.
            </p>
        </div>
        
        <div class="info-card info-card-promise">
            <div class="info-icon">💯</div>
            <p class="info-text info-promise">
                <strong>60-day money-back guarantee.</strong> 
                <a href="/page/refund">Read our refund policy</a>.
            </p>
        </div>
    </div>
</section>

<!-- ====== SHORT DESCRIPTION ====== -->
<section class="description-section">
    <div class="container">
        <h2 class="section-title">About the Product</h2>
        <div class="description-content">
            <?php echo settings('short_description'); ?>
        </div>
        <div class="description-actions">
            <a href="/page/full-description" class="btn btn-outline">
                📖 Read Full Detailed Description
            </a>
        </div>
    </div>
</section>

<?php render_footer(); ?>
