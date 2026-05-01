<?php
require_once __DIR__ . '/../init.php';
global $db;

$logDir = ROOT_PATH . '/logs'; 
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

@file_put_contents($logDir . '/payram_webhook.txt', date('Y-m-d H:i:s') . ' | Data: ' . $rawInput . "\n", FILE_APPEND);

if (!$data) { 
    http_response_code(400); 
    echo 'Invalid payload'; 
    exit; 
}

// Optional API Key/Signature Verification
// $headers = getallheaders(); // could verify if needed

$status = strtolower($data['status'] ?? $data['payment_status'] ?? ''); 
$orderId = $data['orderId'] ?? $data['order_id'] ?? '';
$paymentId = $data['paymentId'] ?? $data['id'] ?? '';

// Typical success statuses
if ($orderId && in_array($status, ['completed', 'paid', 'success', 'finished'])) {
    
    // Allow lookup by order_number or the prefixed session ID
    $order = $db->fetch("SELECT * FROM " . DB_PREFIX . "orders WHERE order_number = ? AND status = 'pending'", [$orderId]);
    
    if (!$order && $paymentId) {
        $order = $db->fetch("SELECT * FROM " . DB_PREFIX . "orders WHERE stripe_session_id = ? AND status = 'pending'", ['pr_' . $paymentId]);
    }
    
    if ($order) {
        $db->query("UPDATE " . DB_PREFIX . "orders SET status = 'paid', updated_at = NOW() WHERE id = ?", [$order['id']]);
        
        @file_put_contents($logDir . '/purchases.log', date('Y-m-d H:i:s') . ' | PAYRAM | ' . $order['email'] . ' | $' . $order['amount'] . ' | ' . $order['order_number'] . "\n", FILE_APPEND);
        
        $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $siteName = settings('site_name') ?: 'CryptoExchange'; 
        $productName = settings('product_name') ?: 'CryptoExchange Script';
        $downloadLink = $siteUrl . '/download?token=' . urlencode($order['download_token']);
        
        $subject = "Your Download - " . $productName . " [Order #" . $order['order_number'] . "]";
        
        $body = '<html><body style="background:#f5f6fa;padding:30px;font-family:Arial,sans-serif;"><div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #e8e8e8;overflow:hidden;"><div style="background:#f0b90b;padding:20px;text-align:center;"><h1 style="margin:0;color:#1a1a2e;font-size:22px;">'.htmlspecialchars($siteName).'</h1></div><div style="padding:30px;"><h2 style="color:#16a34a;">Thank You!</h2><p>Your payment has been successfully processed.</p><div style="background:#f9fafb;border-radius:8px;padding:16px;margin:20px 0;"><p style="margin:4px 0;font-size:13px;"><strong>Order:</strong> #'.htmlspecialchars($order['order_number']).'</p><p style="margin:4px 0;font-size:13px;"><strong>Amount:</strong> $'.number_format($order['amount'], 2).' USD</p></div><div style="text-align:center;margin:24px 0;"><a href="'.htmlspecialchars($downloadLink).'" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:14px 40px;border-radius:8px;font-size:16px;font-weight:bold;">Download Now</a></div><p style="text-align:center;color:#ef4444;font-size:12px;font-weight:bold;">This link can only be used once. Save the file after downloading.</p></div></div></body></html>';
        
        $emailResult = send_smtp_email($order['email'], $subject, $body);
        @file_put_contents($logDir . '/email_log.txt', date('Y-m-d H:i:s') . ' | PAYRAM | To: ' . $order['email'] . ' | ' . ($emailResult === true ? 'OK' : $emailResult) . "\n", FILE_APPEND);
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
