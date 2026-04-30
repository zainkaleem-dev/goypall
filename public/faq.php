<?php
/**
 * Public FAQ page
 */
require_once dirname(__DIR__) . '/init.php';
require_once INCLUDES_PATH . '/layout.php';

global $db;

$faqs = $db->fetchAll(
    "SELECT * FROM " . DB_PREFIX . "faqs WHERE status = 'active' ORDER BY category ASC, sort_order ASC, id ASC"
);

// Group by category
$grouped = [];
foreach ($faqs as $faq) {
    $cat = $faq['category'] ?: 'General';
    if (!isset($grouped[$cat])) $grouped[$cat] = [];
    $grouped[$cat][] = $faq;
}

render_header('Frequently Asked Questions', 'Answers to common questions about ' . settings('site_name'));
?>

<section class="page-section">
    <div class="container">
        <div class="page-header">
            <h1>Frequently Asked Questions</h1>
            <p class="meta">Everything you need to know about our product</p>
        </div>

        <?php if (empty($faqs)): ?>
            <div class="page-content" style="text-align:center;color:var(--c-text-muted);">
                <p>No FAQs published yet. Please check back soon.</p>
            </div>
        <?php else: ?>
            <div class="faq-list">
                <?php foreach ($grouped as $category => $items): ?>
                    <h2 class="faq-category"><?php echo escape($category); ?></h2>
                    <?php foreach ($items as $faq): ?>
                        <div class="faq-item">
                            <button class="faq-question" type="button">
                                <?php echo escape($faq['question']); ?>
                            </button>
                            <div class="faq-answer">
                                <?php echo $faq['answer']; // Allow HTML from admin ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <div style="text-align:center;margin-top:50px;padding:30px;background:var(--c-bg-alt);border-radius:var(--radius-lg);max-width:600px;margin-left:auto;margin-right:auto;">
                <p style="color:var(--c-text-soft);margin-bottom:16px;">Still have questions?</p>
                <a href="<?php echo escape(settings('support_url', '#')); ?>" target="_blank" rel="noopener" class="btn btn-outline">Contact Support ↗</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php render_footer(); ?>
