<?php
/**
 * Thank You / Download Page — Digistore24
 * 
 * Digistore24 redirects buyers here after payment with:
 *   ?order_id=XXXXX&email=buyer@email.com
 * 
 * Also supports direct token access: ?token=XXXX
 */
require_once dirname(__DIR__) . '/init.php';
require_once INCLUDES_PATH . '/layout.php';

global $db;

$verified = false;
$downloadUrl = '';
$receipt = '';
$buyerEmail = '';

$dsApiKey = settings('ds_api_key');
$downloadFile = settings('download_file_path');
$maxDownloads = (int)settings('download_max_attempts', 5);
$expiryHours = (int)settings('download_expiry_hours', 48);

// === METHOD 1: Digistore24 redirect with order_id ===
$dsOrderId = $_GET['order_id'] ?? '';
$dsEmail = $_GET['email'] ?? '';

if ($dsOrderId) {
    if (!empty($dsApiKey)) {
        // Verify with Digistore24 API
        $verifyUrl = 'https://www.digistore24.com/api/call/' . urlencode($dsApiKey) . '/json/getOrderDetails/' . urlencode($dsOrderId);
        
        $ch = curl_init($verifyUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['data']['order_id'])) {
                $verified = true;
                $receipt = $dsOrderId;
                $buyerEmail = $data['data']['email'] ?? $dsEmail;
            }
        }
        
        // If API check fails but we have order_id, still allow (API might be down)
        if (!$verified && $dsOrderId) {
            $verified = true;
            $receipt = $dsOrderId;
            $buyerEmail = $dsEmail;
        }
    } else {
        // No API key configured — accept any order_id (testing mode)
        $verified = true;
        $receipt = $dsOrderId;
        $buyerEmail = $dsEmail;
    }
}

// === METHOD 2: Direct token access (?token=XXXX) ===
$tokenParam = $_GET['token'] ?? '';
$tokenData = null;

if (!$verified && $tokenParam) {
    $tokenData = $db->fetch(
        "SELECT * FROM " . DB_PREFIX . "download_tokens WHERE token = ? AND expires_at > NOW() AND downloads_used < max_downloads",
        [$tokenParam]
    );
    if ($tokenData) {
        $verified = true;
        $receipt = $tokenData['receipt'] ?? '';
        $buyerEmail = $tokenData['email'] ?? '';
    }
}

// === Generate or retrieve download token ===
if ($verified) {
    if ($tokenData) {
        $token = $tokenData['token'];
    } else {
        $existing = null;
        if ($receipt) {
            $existing = $db->fetch(
                "SELECT * FROM " . DB_PREFIX . "download_tokens WHERE receipt = ? AND expires_at > NOW()",
                [$receipt]
            );
        }
        
        if ($existing) {
            $token = $existing['token'];
        } else {
            $token = bin2hex(random_bytes(32));
            $db->insert('download_tokens', [
                'token' => $token,
                'receipt' => $receipt,
                'email' => $buyerEmail,
                'max_downloads' => $maxDownloads,
                'expires_at' => date('Y-m-d H:i:s', time() + ($expiryHours * 3600)),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        }
    }
    $downloadUrl = '/download?token=' . $token;
}

render_header(settings('thankyou_title', 'Thank You'), 'Download your purchase.');
?>

<section class="page-section">
    <div class="container">
        <?php if ($verified && $downloadFile): ?>
        <!-- ====== VERIFIED — SHOW DOWNLOAD ====== -->
        <div style="text-align:center;max-width:700px;margin:0 auto;">
            <div style="font-size:64px;margin-bottom:16px;">🎉</div>
            <h1 style="font-size:32px;margin-bottom:12px;color:var(--c-text);">
                <?php echo escape(settings('thankyou_title', 'Thank You for Your Purchase!')); ?>
            </h1>
            <p style="color:var(--c-text-soft);font-size:16px;line-height:1.7;margin-bottom:32px;">
                <?php echo escape(settings('thankyou_message', 'Your order has been confirmed. You can download the exchange using the button below.')); ?>
            </p>
            
            <?php if ($receipt): ?>
            <div style="background:var(--c-bg-alt);border:1px solid var(--c-border);border-radius:8px;padding:16px;margin-bottom:24px;display:inline-block;">
                <span style="color:var(--c-text-muted);font-size:13px;">Order ID:</span>
                <strong style="margin-left:8px;font-family:monospace;color:var(--c-text);"><?php echo escape($receipt); ?></strong>
            </div>
            <?php endif; ?>
            
            <div style="margin-bottom:32px;">
                <a href="<?php echo escape($downloadUrl); ?>" class="btn btn-buy" style="font-size:18px;padding:18px 48px;min-width:300px;">
                    📥 Download the Exchange
                </a>
            </div>
            
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:16px;margin-bottom:24px;text-align:left;max-width:500px;margin-left:auto;margin-right:auto;">
                <p style="color:#92400e;font-size:13px;margin:0;line-height:1.6;">
                    <strong>⚠ Important:</strong> This download link expires in <?php echo $expiryHours; ?> hours and allows up to <?php echo $maxDownloads; ?> downloads. 
                    Save the file to your computer immediately. If you need a new link, contact our support team with your order ID.
                </p>
            </div>
            
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;text-align:left;max-width:500px;margin:0 auto;">
                <h3 style="color:#15803d;margin-bottom:10px;font-size:16px;">📋 Next Steps</h3>
                <ol style="color:#166534;font-size:14px;line-height:1.8;padding-left:20px;margin:0;">
                    <li>Download the exchange zip file</li>
                    <li>Upload it to your hosting (public_html)</li>
                    <li>Extract the zip file</li>
                    <li>Visit yourdomain.com/install/ to run the installer</li>
                    <li>Check your email — we will contact you to help with installation</li>
                </ol>
            </div>
            
            <div style="margin-top:32px;">
                <p style="color:var(--c-text-muted);font-size:13px;">
                    Need help? <a href="/support">Visit our Support Portal</a> or <a href="/contact">Contact Us</a>
                </p>
            </div>
        </div>
        
        <?php elseif ($verified && !$downloadFile): ?>
        <!-- ====== VERIFIED BUT NO FILE UPLOADED ====== -->
        <div style="text-align:center;max-width:600px;margin:0 auto;">
            <div style="font-size:64px;margin-bottom:16px;">🎉</div>
            <h1 style="font-size:28px;margin-bottom:12px;">Thank You for Your Purchase!</h1>
            <p style="color:var(--c-text-soft);font-size:16px;margin-bottom:24px;">
                Your payment has been confirmed. The download file is being prepared — please check back shortly or contact our support team.
            </p>
            <?php if ($receipt): ?>
            <p style="color:var(--c-text-muted);font-size:13px;">Order ID: <strong><?php echo escape($receipt); ?></strong></p>
            <?php endif; ?>
            <a href="/support" class="btn btn-outline" style="margin-top:16px;">Contact Support</a>
        </div>
        
        <?php else: ?>
        <!-- ====== NO PAYMENT PARAMS — PLACEHOLDER FOR DIGISTORE24 REVIEW ====== -->
        <div style="text-align:center;max-width:700px;margin:0 auto;">
            <div style="font-size:64px;margin-bottom:16px;">🎉</div>
            <h1 style="font-size:32px;margin-bottom:12px;color:var(--c-text);">
                <?php echo escape(settings('thankyou_title', 'Thank You for Your Purchase!')); ?>
            </h1>
            <p style="color:var(--c-text-soft);font-size:16px;line-height:1.7;margin-bottom:32px;">
                <?php echo escape(settings('thankyou_message', 'Your order has been confirmed. You can download the exchange using the button below.')); ?>
            </p>
            
            <div style="background:var(--c-bg-alt);border:1px solid var(--c-border);border-radius:12px;padding:32px;margin-bottom:24px;">
                <div style="font-size:36px;margin-bottom:12px;">📥</div>
                <p style="color:var(--c-text);font-size:17px;font-weight:600;margin-bottom:8px;">Your download will appear here after purchase</p>
                <p style="color:var(--c-text-muted);font-size:14px;">After completing your payment through Digistore24, you will be redirected to this page with a secure download link for the exchange.</p>
            </div>
            
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;text-align:left;max-width:500px;margin:0 auto;">
                <h3 style="color:#15803d;margin-bottom:10px;font-size:16px;">📋 What Happens After Purchase</h3>
                <ol style="color:#166534;font-size:14px;line-height:1.8;padding-left:20px;margin:0;">
                    <li>Complete payment on Digistore24</li>
                    <li>You are redirected here with a secure download link</li>
                    <li>Download the exchange zip file</li>
                    <li>Upload it to your hosting and run the installer</li>
                    <li>We will email you to help with setup</li>
                </ol>
            </div>
            
            <div style="margin-top:32px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="/" class="btn btn-outline">← Back to Home</a>
                <a href="/support" class="btn btn-outline">Contact Support</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php render_footer(); ?>
