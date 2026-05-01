<?php
require_once __DIR__ . '/../init.php';
global $db;

$orderNumber = trim($_GET['order'] ?? '');
if (!$orderNumber) {
    header('Location: /checkout');
    exit;
}

$order = $db->fetch("SELECT * FROM " . DB_PREFIX . "orders WHERE order_number = ? AND status = 'pending'", [$orderNumber]);
if (!$order) {
    header('Location: /checkout');
    exit;
}

$payramApiUrl = rtrim(settings('payram_api_url'), '/');
$payramApiKey = settings('payram_api_key');

if (!$payramApiUrl || !$payramApiKey || settings('payram_enabled') !== '1') {
    die('PayRam is not configured or disabled.');
}

$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$productName = settings('product_name') ?: 'CryptoExchange Script';

$invoiceData = [
    'amount' => (float)$order['amount'],
    'currency' => 'USD',
    'settleCurrency' => 'USDC',
    'settleNetwork' => 'ETH',
    'orderId' => $order['order_number'],
    'description' => $productName . ' - Order #' . $order['order_number'],
    'customerEmail' => $order['email'],
    'webhookUrl' => $siteUrl . '/payram-webhook',
    'returnUrl' => $siteUrl . '/thank-you?order=' . urlencode($order['order_number']),
    'cancelUrl' => $siteUrl . '/checkout',
];

$ch = curl_init($payramApiUrl . '/api/v1/payment');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $payramApiKey,
        'API-Key: ' . $payramApiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($invoiceData),
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$result = json_decode($response, true);

$logDir = ROOT_PATH . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
@file_put_contents($logDir . '/payram_log.txt', date('Y-m-d H:i:s') . ' | Order: ' . $orderNumber . ' | HTTP: ' . $httpCode . ' | Response: ' . $response . "\n", FILE_APPEND);

// PayRam might return the URL in different fields depending on version, checking common ones
$checkoutUrl = $result['paymentUrl'] ?? $result['checkoutUrl'] ?? $result['url'] ?? '';
$paymentId = $result['paymentId'] ?? $result['id'] ?? '';

if ($httpCode >= 200 && $httpCode < 300 && $checkoutUrl) {
    $db->query("UPDATE " . DB_PREFIX . "orders SET stripe_session_id = ? WHERE order_number = ?", ['pr_' . $paymentId, $orderNumber]);
    header('Location: ' . $checkoutUrl);
    exit;
} else {
    $errorMsg = $result['message'] ?? $result['error'] ?? $curlError ?: 'Failed to create PayRam session';
    die('Payment error: ' . htmlspecialchars($errorMsg) . '<br><a href="/checkout">Go back</a>');
}
