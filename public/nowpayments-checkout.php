<?php
require_once __DIR__ . '/../init.php';
global $db;
$orderNumber = trim($_GET['order'] ?? '');
if (!$orderNumber) { header('Location: /checkout'); exit; }
$order = $db->fetch("SELECT * FROM " . DB_PREFIX . "orders WHERE order_number = ? AND status = 'pending'", [$orderNumber]);
if (!$order) { header('Location: /checkout'); exit; }
$npApiKey = settings('nowpayments_api_key');
if (!$npApiKey) { die('NOWPayments is not configured.'); }
$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$productName = settings('product_name') ?: 'CryptoExchange Script';
$invoiceData = [
    'price_amount' => (float)$order['amount'],
    'price_currency' => 'usd',
    'order_id' => $order['order_number'],
    'order_description' => $productName . ' - Order #' . $order['order_number'],
    'ipn_callback_url' => $siteUrl . '/nowpayments-webhook',
    'success_url' => $siteUrl . '/thank-you?order=' . urlencode($order['order_number']),
    'cancel_url' => $siteUrl . '/checkout',
    'is_fee_paid_by_user' => true,
];
$ch = curl_init('https://api.nowpayments.io/v1/invoice');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $npApiKey],
    CURLOPT_POSTFIELDS => json_encode($invoiceData), CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
$result = json_decode($response, true);
$logDir = ROOT_PATH . '/logs'; if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
@file_put_contents($logDir . '/nowpayments_log.txt', date('Y-m-d H:i:s') . ' | Order: ' . $orderNumber . ' | HTTP: ' . $httpCode . ' | Response: ' . $response . "\n", FILE_APPEND);
if ($result && !empty($result['invoice_url'])) {
    $db->query("UPDATE " . DB_PREFIX . "orders SET stripe_session_id = ? WHERE order_number = ?", ['np_' . ($result['id'] ?? ''), $orderNumber]);
    header('Location: ' . $result['invoice_url']); exit;
} else {
    die('Payment error: ' . htmlspecialchars($result['message'] ?? $curlError ?: 'Failed') . '<br><a href="/checkout">Go back</a>');
}
