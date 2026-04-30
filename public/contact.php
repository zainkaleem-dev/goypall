<?php
/**
 * Public Contact Us page
 */
require_once dirname(__DIR__) . '/init.php';
require_once INCLUDES_PATH . '/layout.php';

global $db;

$success = '';
$error = '';
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    $old = compact('name', 'email', 'subject', 'message');
    
    if (!$name || strlen($name) < 2) {
        $error = 'Please enter your name.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!$message || strlen($message) < 10) {
        $error = 'Please enter a message (at least 10 characters).';
    } else {
        // Rate limit: max 3 messages per hour from same IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $recent = $db->fetch(
            "SELECT COUNT(*) c FROM " . DB_PREFIX . "contact_messages WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        );
        
        if ($recent && $recent['c'] >= 3) {
            $error = 'Too many messages. Please try again later.';
        } else {
            $db->insert('contact_messages', [
                'name'       => $name,
                'email'      => $email,
                'subject'    => $subject,
                'message'    => $message,
                'ip_address' => $ip,
            ]);
            $success = 'Your message has been sent successfully! We will get back to you as soon as possible.';
            $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
        }
    }
}

$phone     = settings('contact_phone');
$email_addr = settings('contact_email');
$whatsapp  = settings('contact_whatsapp_url');
$telegram  = settings('contact_telegram_url');
$pageTitle = settings('contact_page_title', 'Contact Us');
$pageSub   = settings('contact_page_subtitle', 'Have a question? We would love to hear from you.');

render_header('Contact Us', 'Get in touch with us for any questions or support inquiries.');
?>

<section class="page-section">
    <div class="container">
        <div class="page-header">
            <h1><?php echo escape($pageTitle); ?></h1>
            <p class="meta"><?php echo escape($pageSub); ?></p>
        </div>
        
        <div class="contact-layout">
            <!-- Contact Form -->
            <div class="contact-form-wrap">
                <?php if ($success): ?>
                <div class="contact-alert contact-alert-success">
                    <span class="contact-alert-icon">✓</span>
                    <div>
                        <strong>Message Sent!</strong>
                        <p><?php echo escape($success); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="contact-alert contact-alert-error">
                    <span class="contact-alert-icon">⚠</span>
                    <div><?php echo escape($error); ?></div>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="contact-form">
                    <?php echo csrf_field(); ?>
                    
                    <div class="contact-form-row">
                        <div class="contact-field">
                            <label for="c_name">Your Name *</label>
                            <input type="text" id="c_name" name="name" required 
                                   value="<?php echo escape($old['name']); ?>" 
                                   placeholder="John Doe">
                        </div>
                        <div class="contact-field">
                            <label for="c_email">Your Email *</label>
                            <input type="email" id="c_email" name="email" required 
                                   value="<?php echo escape($old['email']); ?>" 
                                   placeholder="john@example.com">
                        </div>
                    </div>
                    
                    <div class="contact-field">
                        <label for="c_subject">Subject</label>
                        <input type="text" id="c_subject" name="subject" 
                               value="<?php echo escape($old['subject']); ?>" 
                               placeholder="How can we help?">
                    </div>
                    
                    <div class="contact-field">
                        <label for="c_message">Message *</label>
                        <textarea id="c_message" name="message" rows="6" required 
                                  placeholder="Tell us more about your inquiry..."><?php echo escape($old['message']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-contact-submit">
                        ✉ Send Message
                    </button>
                </form>
            </div>
            
            <!-- Contact Info Sidebar -->
            <div class="contact-sidebar">
                <?php if ($email_addr): ?>
                <div class="contact-info-card">
                    <div class="contact-info-icon">📧</div>
                    <div>
                        <h4>Email</h4>
                        <a href="mailto:<?php echo escape($email_addr); ?>"><?php echo escape($email_addr); ?></a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($phone): ?>
                <div class="contact-info-card">
                    <div class="contact-info-icon">📞</div>
                    <div>
                        <h4>Phone</h4>
                        <a href="tel:<?php echo escape(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo escape($phone); ?></a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($whatsapp || $telegram): ?>
                <div class="contact-info-card">
                    <div class="contact-info-icon">💬</div>
                    <div>
                        <h4>Chat With Us</h4>
                        <div class="contact-chat-buttons">
                            <?php if ($whatsapp): ?>
                            <a href="<?php echo escape($whatsapp); ?>" target="_blank" rel="noopener" class="btn-whatsapp">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                WhatsApp
                            </a>
                            <?php endif; ?>
                            <?php if ($telegram): ?>
                            <a href="<?php echo escape($telegram); ?>" target="_blank" rel="noopener" class="btn-telegram">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0 12 12 0 0011.944 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                                Telegram
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="contact-info-card contact-info-hours">
                    <div class="contact-info-icon">🕐</div>
                    <div>
                        <h4>Response Time</h4>
                        <p>We typically respond within 24 hours on business days.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php render_footer(); ?>
