<?php
require_once __DIR__ . '/../init.php';

global $db;
$token = trim($_GET['token'] ?? '');
$productFile = settings('product_file_path');

if (!$token) {
    die('Invalid download link.');
}

// Find order by token
$order = $db->fetch("SELECT * FROM " . DB_PREFIX . "orders WHERE download_token = ? AND status = 'paid'", [$token]);

if (!$order) {
    die('Invalid or expired download link. If you believe this is an error, please contact support.');
}

// Check if already used
if ($order['download_used']) {
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Download Expired</title>
    <style>body{background:#0b0e11;color:#eaecef;font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px;}
    .card{background:#181a20;border:1px solid #2b3139;border-radius:12px;padding:36px;max-width:480px;text-align:center;}
    h1{color:#f6465d;font-size:22px;margin-bottom:8px;}p{color:#848e9c;font-size:13px;line-height:1.6;}
    a{color:#f0b90b;text-decoration:none;font-weight:600;}</style></head>
    <body><div class="card">
        <div style="font-size:48px;margin-bottom:12px;">⚠️</div>
        <h1>Download Link Already Used</h1>
        <p>This download link has already been used. Each link can only be used once for security reasons.</p>
        <p style="margin-top:16px;">Need to download again? <a href="https://product.goypall.net/support">Contact Support</a> with your order number <strong style="color:#eaecef;">#<?php echo escape($order['order_number']); ?></strong>.</p>
    </div></body></html>
    <?php
    exit;
}

// Check product file
if (!$productFile || !file_exists(UPLOADS_PATH . '/downloads/' . $productFile)) {
    die('Product file not found. Please contact support.');
}

// Mark as used
$db->query("UPDATE " . DB_PREFIX . "orders SET download_used = 1, download_at = NOW() WHERE id = ?", [$order['id']]);

// Log download
$logDir = ROOT_PATH . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
@file_put_contents($logDir . '/downloads.log', date('Y-m-d H:i:s') . ' | ' . $order['email'] . ' | ' . $order['order_number'] . ' | ' . $_SERVER['REMOTE_ADDR'] . "\n", FILE_APPEND);

// Serve file
$filePath = UPLOADS_PATH . '/downloads/' . $productFile;
$originalName = settings('product_file_original') ?: $productFile;

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($originalName) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($filePath);
exit;
