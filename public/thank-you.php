<?php
require_once __DIR__ . '/../init.php';

global $db;
$stripeSecret = settings('stripe_secret_key');
$orderNumber = trim($_GET['order'] ?? '');
$siteName = settings('site_name') ?: 'CryptoExchange';
$contactEmail = settings('contact_email') ?: 'support@goypall.net';
$productName = settings('product_name') ?: 'CryptoExchange Script';

$verified = false;
$order = null;
$emailSent = false;
$emailError = '';

// Handle manual resend
if ($_GET['resend'] ?? '' === '1' && $orderNumber) {
    $order = $db->fetch("SELECT * FROM " . DB_PREFIX . "orders WHERE order_number = ?", [$orderNumber]);
    if ($order && $order['status'] === 'paid') {
        $result = sendDownloadEmail($order);
        $emailSent = ($result === true);
        $emailError = $emailSent ? '' : (string)$result;
        $verified = true;
        
        $logDir = ROOT_PATH . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        @file_put_contents($logDir . '/email_log.txt', date('Y-m-d H:i:s') . ' | RESEND | To: ' . $order['email'] . ' | Result: ' . ($emailSent ? 'SUCCESS' : 'FAILED: ' . $emailError) . "\n", FILE_APPEND);
    }
} elseif ($orderNumber) {
    $order = $db->fetch("SELECT * FROM " . DB_PREFIX . "orders WHERE order_number = ?", [$orderNumber]);
    
    if ($order && $order['status'] === 'pending' && $order['stripe_session_id']) {
        // Verify with Stripe
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($order['stripe_session_id']));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $stripeSecret . ':',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $session = json_decode($response, true);
        
        if (!empty($session['payment_status']) && $session['payment_status'] === 'paid') {
            $db->query("UPDATE " . DB_PREFIX . "orders SET status = 'paid', updated_at = NOW() WHERE id = ?", [$order['id']]);
            $order['status'] = 'paid';
            $verified = true;
            
            // Send download email
            $result = sendDownloadEmail($order);
            $emailSent = ($result === true);
            $emailError = $emailSent ? '' : (string)$result;
            
            $logDir = ROOT_PATH . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            @file_put_contents($logDir . '/email_log.txt', date('Y-m-d H:i:s') . ' | To: ' . $order['email'] . ' | Result: ' . ($emailSent ? 'SUCCESS' : 'FAILED: ' . $emailError) . "\n", FILE_APPEND);
            @file_put_contents($logDir . '/purchases.log', date('Y-m-d H:i:s') . ' | ' . $order['email'] . ' | $' . $order['amount'] . ' | ' . $orderNumber . "\n", FILE_APPEND);
        } else {
            $emailError = 'Stripe: ' . ($session['payment_status'] ?? 'unknown') . ' | curl: ' . $curlError;
        }
    } elseif ($order && $order['status'] === 'paid') {
        $verified = true;
    }
}

function sendDownloadEmail($order) {
    $siteName = settings('site_name') ?: 'CryptoExchange';
    $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $productName = settings('product_name') ?: 'CryptoExchange Script';
    $downloadLink = $siteUrl . '/download?token=' . urlencode($order['download_token']);
    
    $subject = "Your Download - " . $productName . " [Order #" . $order['order_number'] . "]";
    
    $body = '<html><body style="background:#f5f6fa;padding:30px;font-family:Arial,sans-serif;">';
    $body .= '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #e8e8e8;overflow:hidden;">';
    $body .= '<div style="background:#f0b90b;padding:20px;text-align:center;"><h1 style="margin:0;color:#1a1a2e;font-size:22px;">' . htmlspecialchars($siteName) . '</h1></div>';
    $body .= '<div style="padding:30px;">';
    $body .= '<h2 style="color:#16a34a;margin:0 0 8px;">Thank You for Your Purchase!</h2>';
    $body .= '<p style="color:#6b7280;font-size:14px;">Your payment has been confirmed. Click below to download.</p>';
    $body .= '<div style="background:#f9fafb;border-radius:8px;padding:16px;margin:20px 0;">';
    $body .= '<p style="margin:4px 0;font-size:13px;"><strong>Order:</strong> #' . htmlspecialchars($order['order_number']) . '</p>';
    $body .= '<p style="margin:4px 0;font-size:13px;"><strong>Product:</strong> ' . htmlspecialchars($productName) . '</p>';
    $body .= '<p style="margin:4px 0;font-size:13px;"><strong>Amount:</strong> $' . number_format($order['amount'], 2) . ' USD</p>';
    $body .= '</div>';
    $body .= '<div style="text-align:center;margin:24px 0;">';
    $body .= '<a href="' . htmlspecialchars($downloadLink) . '" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:14px 40px;border-radius:8px;font-size:16px;font-weight:bold;">Download Now</a>';
    $body .= '</div>';
    $body .= '<p style="text-align:center;color:#ef4444;font-size:12px;font-weight:bold;">This link can only be used once. Save the file after downloading.</p>';
    $body .= '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;margin-top:20px;text-align:center;">';
    $body .= '<p style="color:#92400e;font-size:13px;margin:0;">Need help? Contact <a href="mailto:support@goypall.net" style="color:#f0b90b;">support@goypall.net</a></p>';
    $body .= '</div>';
    $body .= '</div></div></body></html>';
    
    return send_smtp_email($order['email'], $subject, $body);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Thank You — <?php echo escape($siteName); ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#f5f6fa;color:#1a1a2e;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;min-height:100vh;}
        .nav{background:#fff;border-bottom:1px solid #e8e8e8;padding:14px 20px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
        .nav a{color:#1a1a2e;text-decoration:none;font-size:18px;font-weight:800;}.nav a span{color:#f0b90b;}
        .wrap{max-width:620px;margin:40px auto;padding:0 16px 60px;}
        .card{background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:32px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,0.03);}
        .icon-big{font-size:64px;text-align:center;margin-bottom:12px;}
        h1{text-align:center;font-size:26px;color:#16a34a;margin-bottom:6px;}
        .sub{text-align:center;color:#6b7280;font-size:14px;margin-bottom:24px;}
        .detail-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:16px;}
        .dr{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f3f4f620;font-size:13px;}
        .dr:last-child{border-bottom:none;}
        .dr .l{color:#6b7280;}.dr .v{color:#1a1a2e;font-weight:600;}
        .email-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:20px;text-align:center;margin-bottom:16px;}
        .email-box .em-icon{font-size:36px;margin-bottom:8px;}
        .email-box h3{color:#16a34a;font-size:15px;margin-bottom:4px;}
        .email-box p{color:#6b7280;font-size:12px;margin:0;}
        .email-box .addr{color:#1a1a2e;font-weight:700;}
        .email-fail{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:20px;text-align:center;margin-bottom:16px;}
        .email-fail h3{color:#dc2626;font-size:15px;margin-bottom:4px;}
        .email-fail p{color:#6b7280;font-size:12px;margin:4px 0;}
        .email-fail a{color:#f0b90b;font-weight:600;text-decoration:none;}
        .resend-btn{display:inline-block;background:#f0b90b;color:#1a1a2e;text-decoration:none;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:700;margin-top:8px;}
        .support-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px;text-align:center;margin-bottom:16px;}
        .support-box h3{color:#92400e;font-size:14px;margin-bottom:6px;}
        .support-box p{color:#6b7280;font-size:12px;margin:0;}
        .support-box a{color:#f0b90b;text-decoration:underline;font-weight:600;}
        .steps{font-size:12px;color:#4b5563;line-height:2;}
        .steps div{padding:4px 0;}
        .fail{text-align:center;}
        .fail h1{color:#dc2626;}
        .fail p{color:#6b7280;font-size:13px;margin:8px 0;}
        .fail a{color:#f0b90b;text-decoration:none;font-weight:600;}
        .debug{background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:10px;margin-top:8px;font-size:10px;color:#9ca3af;word-break:break-all;}
        .footer{text-align:center;padding:30px;color:#9ca3af;font-size:11px;}
    </style>
</head>
<body>

<div class="nav"><a href="/"><span>⚡</span> <?php echo escape($siteName); ?></a></div>

<div class="wrap">
<?php if ($verified && $order): ?>

    <div class="card">
        <div class="icon-big">🎉</div>
        <h1>Payment Successful!</h1>
        <p class="sub">Thank you for purchasing <?php echo escape($productName); ?></p>
        
        <div class="detail-box">
            <div class="dr"><span class="l">Order Number</span><span class="v">#<?php echo escape($order['order_number']); ?></span></div>
            <div class="dr"><span class="l">Product</span><span class="v"><?php echo escape($productName); ?></span></div>
            <div class="dr"><span class="l">Amount Paid</span><span class="v" style="color:#16a34a;">$<?php echo number_format($order['amount'], 2); ?> USD</span></div>
            <div class="dr"><span class="l">Email</span><span class="v"><?php echo escape($order['email']); ?></span></div>
        </div>
    </div>
    
    <?php if ($emailSent): ?>
    <div class="email-box">
        <div class="em-icon">📧</div>
        <h3>Download Link Sent!</h3>
        <p>We've sent the download link to<br><span class="addr"><?php echo escape($order['email']); ?></span></p>
        <p style="margin-top:8px;">Check your inbox (and spam folder). The link can only be used <strong style="color:#dc2626;">once</strong>.</p>
    </div>
    <?php else: ?>
    <div class="email-fail">
        <div style="font-size:36px;margin-bottom:8px;">⚠️</div>
        <h3>Email Delivery Issue</h3>
        <p>We couldn't send the download link to your email automatically.</p>
        <p><a href="/thank-you?order=<?php echo urlencode($order['order_number']); ?>&resend=1">🔄 Click here to retry sending</a></p>
        <p style="margin-top:8px;">Or contact <a href="mailto:support@goypall.net">support@goypall.net</a> with order <strong>#<?php echo escape($order['order_number']); ?></strong></p>
        <?php if ($emailError): ?>
        <div class="debug">Error: <?php echo escape($emailError); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="support-box">
        <h3>🛠️ Need Help with Installation, Setup & Launch?</h3>
        <p>Our team is ready to help you get started.<br>
        <a href="https://product.goypall.net/support">Contact Support →</a> or email <a href="mailto:support@goypall.net">support@goypall.net</a></p>
    </div>
    
    <div class="card" style="padding:20px;">
        <h3 style="color:#1a1a2e;margin-bottom:10px;font-size:14px;">🚀 What's Next?</h3>
        <div class="steps">
            <div>1️⃣ Check your email for the download link</div>
            <div>2️⃣ Upload the zip to your hosting and extract it</div>
            <div>3️⃣ Visit <strong>yourdomain.com/install/</strong> to run the setup wizard</div>
            <div>4️⃣ Configure your API keys in the admin panel</div>
            <div>5️⃣ Your exchange is live! 🎉</div>
        </div>
    </div>

<?php else: ?>

    <div class="card fail">
        <div class="icon-big">⚠️</div>
        <h1>Payment Not Verified</h1>
        <p>We couldn't verify your payment. This could mean the payment is still processing.</p>
        <p>If you completed payment, wait a moment and refresh this page.</p>
        <?php if ($emailError): ?><div class="debug"><?php echo escape($emailError); ?></div><?php endif; ?>
        <p style="margin-top:16px;">
            <a href="/">← Return to Homepage</a>
            &nbsp;|&nbsp; <a href="mailto:support@goypall.net">Contact Support</a>
        </p>
    </div>

<?php endif; ?>
</div>

<div class="footer"><?php echo escape(settings('footer_text') ?: '© ' . date('Y') . ' ' . $siteName); ?></div>

</body>
</html>
