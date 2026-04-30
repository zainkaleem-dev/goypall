<?php
require_once __DIR__ . '/../init.php';

global $db;
$siteName = settings('site_name') ?: 'CryptoExchange';
$contactEmail = settings('contact_email') ?: '';
$whatsappUrl = settings('contact_whatsapp_url') ?: '';
$telegramUrl = settings('contact_telegram_url') ?: '';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $orderNum = trim($_POST['order_number'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (!$name || !$email || !$message) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db->insert('contact_messages', [
                'name' => $name,
                'email' => $email,
                'subject' => ($orderNum ? '[Order #' . $orderNum . '] ' : '') . $subject,
                'message' => $message,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            $success = 'Your message has been sent! We\'ll get back to you within 24 hours.';
        } catch (Exception $e) {
            $error = 'Failed to send message. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Support — <?php echo escape($siteName); ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0b0e11;color:#eaecef;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;min-height:100vh;}
        .nav{background:#181a20;border-bottom:1px solid #2b3139;padding:14px 20px;text-align:center;}
        .nav a{color:#f0b90b;text-decoration:none;font-size:18px;font-weight:800;}
        .wrap{max-width:800px;margin:40px auto;padding:0 16px;}
        h1{font-size:24px;color:#eaecef;margin-bottom:4px;}
        .sub{color:#848e9c;font-size:14px;margin-bottom:28px;}
        .grid{display:grid;grid-template-columns:1fr 300px;gap:20px;}
        .card{background:#181a20;border:1px solid #2b3139;border-radius:12px;padding:28px;margin-bottom:16px;}
        .fg{margin-bottom:16px;}
        .fg label{display:block;font-size:12px;color:#848e9c;margin-bottom:4px;font-weight:600;}
        .fg label .req{color:#f6465d;}
        .fg input,.fg textarea,.fg select{width:100%;background:#0b0e11;border:1px solid #363c45;border-radius:6px;padding:10px 12px;color:#eaecef;font-size:14px;outline:none;font-family:inherit;}
        .fg input:focus,.fg textarea:focus{border-color:#f0b90b;}
        .fg textarea{resize:vertical;min-height:120px;}
        .btn{display:block;width:100%;background:#f0b90b;color:#181a20;border:none;padding:12px;border-radius:6px;font-size:15px;font-weight:700;cursor:pointer;}
        .btn:hover{background:#d4a30a;}
        .err{background:#f6465d15;border:1px solid #f6465d30;color:#f6465d;padding:12px;border-radius:6px;margin-bottom:16px;font-size:13px;}
        .ok{background:#0ecb8115;border:1px solid #0ecb8130;color:#0ecb81;padding:12px;border-radius:6px;margin-bottom:16px;font-size:13px;}
        .info-card{background:#0b0e11;border-radius:8px;padding:16px;margin-bottom:12px;}
        .info-card h3{font-size:13px;color:#eaecef;margin-bottom:6px;}
        .info-card p{font-size:12px;color:#848e9c;line-height:1.6;margin:0;}
        .info-card a{color:#f0b90b;text-decoration:none;font-weight:600;}
        .chat-btns{display:flex;flex-direction:column;gap:8px;margin-top:12px;}
        .chat-btn{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;}
        .chat-btn.wa{background:#25d36620;color:#25d366;border:1px solid #25d36640;}
        .chat-btn.tg{background:#0088cc20;color:#0088cc;border:1px solid #0088cc40;}
        .back{text-align:center;margin-top:20px;}.back a{color:#848e9c;text-decoration:none;font-size:13px;}.back a:hover{color:#f0b90b;}
        .footer{text-align:center;padding:30px;color:#5e6673;font-size:11px;}
        @media(max-width:700px){.grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>

<div class="nav"><a href="/">⚡ <?php echo escape($siteName); ?></a></div>

<div class="wrap">
    <h1>🎫 Support</h1>
    <p class="sub">Need help? Send us a message and we'll respond within 24 hours.</p>
    
    <div class="grid">
        <div>
            <div class="card">
                <?php if ($success): ?><div class="ok"><?php echo escape($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="err"><?php echo escape($error); ?></div><?php endif; ?>
                
                <form method="POST">
                    <div class="fg">
                        <label>Your Name <span class="req">*</span></label>
                        <input type="text" name="name" value="<?php echo escape($_POST['name'] ?? ''); ?>" required placeholder="John Doe">
                    </div>
                    <div class="fg">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="email" value="<?php echo escape($_POST['email'] ?? ''); ?>" required placeholder="your@email.com">
                    </div>
                    <div class="fg">
                        <label>Order Number (if applicable)</label>
                        <input type="text" name="order_number" value="<?php echo escape($_POST['order_number'] ?? ''); ?>" placeholder="ORD-XXXXXXXX">
                    </div>
                    <div class="fg">
                        <label>Subject</label>
                        <select name="subject">
                            <option value="General Question">General Question</option>
                            <option value="Installation Help">Installation Help</option>
                            <option value="Setup & Configuration">Setup & Configuration</option>
                            <option value="Bug Report">Bug Report</option>
                            <option value="Customization Request">Customization Request</option>
                            <option value="Refund Request">Refund Request</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Message <span class="req">*</span></label>
                        <textarea name="message" required placeholder="Describe your issue or question..."><?php echo escape($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn">📨 Send Message</button>
                </form>
            </div>
        </div>
        
        <div>
            <div class="info-card">
                <h3>📧 Email Support</h3>
                <?php if ($contactEmail): ?>
                <p>Email us directly at<br><a href="mailto:<?php echo escape($contactEmail); ?>"><?php echo escape($contactEmail); ?></a></p>
                <?php else: ?>
                <p>Use the form to send us a message.</p>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <h3>⏰ Response Time</h3>
                <p>We typically respond within 24 hours on business days. Include your order number for faster service.</p>
            </div>
            
            <div class="info-card">
                <h3>📋 Support Includes</h3>
                <p>✓ Installation assistance<br>✓ Configuration help<br>✓ Bug fixes<br>✓ General usage guidance<br>✓ 6 months free support</p>
            </div>
            
            <?php if ($whatsappUrl || $telegramUrl): ?>
            <div class="info-card">
                <h3>💬 Live Chat</h3>
                <p>For faster response:</p>
                <div class="chat-btns">
                    <?php if ($whatsappUrl): ?>
                    <a href="<?php echo escape($whatsappUrl); ?>" target="_blank" class="chat-btn wa">💬 WhatsApp</a>
                    <?php endif; ?>
                    <?php if ($telegramUrl): ?>
                    <a href="<?php echo escape($telegramUrl); ?>" target="_blank" class="chat-btn tg">✈️ Telegram</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="back"><a href="/">← Back to product page</a></div>
</div>

<div class="footer"><?php echo escape(settings('footer_text') ?: '© ' . date('Y') . ' ' . $siteName); ?></div>

</body>
</html>
