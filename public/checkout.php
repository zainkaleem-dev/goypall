<?php
require_once __DIR__ . '/../init.php';

$stripeSecret = settings('stripe_secret_key');
$stripeEnabled = settings('stripe_enabled') === '1';
$payramEnabled = settings('payram_enabled') === '1';
$payramUrl = settings('payram_api_url');
$payramKey = settings('payram_api_key');
$npApiKey = settings('nowpayments_api_key');
$npEnabled = settings('nowpayments_enabled') === '1';
$gateway = settings('payment_gateway') ?: 'stripe';
$productName = settings('product_name') ?: 'CryptoExchange Script';
$price = (float)(settings('product_price') ?: 299);
$siteName = settings('site_name') ?: 'CryptoExchange';
$error = '';

$hasStripe = $stripeEnabled && !empty($stripeSecret);
$hasPayram = $payramEnabled && !empty($payramUrl) && !empty($payramKey);
$hasNP = $npEnabled && !empty($npApiKey);

if (!$hasStripe && !$hasPayram && !$hasNP) {
    die('Payment is not configured. Please contact the site owner.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $telegram = trim($_POST['telegram'] ?? '');
    $selectedGateway = $_POST['gateway'] ?? 'stripe';
    
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $orderNumber = 'ORD-' . strtoupper(substr(md5(time() . $email), 0, 8));
        $downloadToken = bin2hex(random_bytes(32));
        
        global $db;
        try {
            $db->insert('orders', [
                'order_number' => $orderNumber,
                'email' => $email,
                'whatsapp' => $whatsapp ?: null,
                'telegram' => $telegram ?: null,
                'product_name' => $productName,
                'amount' => $price,
                'currency' => 'USD',
                'status' => 'pending',
                'download_token' => $downloadToken,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (Exception $e) {
            $error = 'Error creating order. Please try again.';
        }
        
        if (!$error) {
            $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            
            // Route to selected payment gateway
            if ($selectedGateway === 'payram' && $hasPayram) {
                header('Location: /payram-checkout?order=' . urlencode($orderNumber));
                exit;
            } elseif ($selectedGateway === 'nowpayments' && $hasNP) {
                header('Location: /nowpayments-checkout?order=' . urlencode($orderNumber));
                exit;
            } else {
                // Stripe checkout
                $sessionData = [
                    'payment_method_types' => ['card'],
                    'customer_email' => $email,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => $productName,
                                'description' => 'One-time purchase — Full source code, installation support, and 6 months email support',
                            ],
                            'unit_amount' => (int)($price * 100),
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => $siteUrl . '/thank-you?order=' . urlencode($orderNumber),
                    'cancel_url' => $siteUrl . '/checkout',
                    'metadata' => ['order_number' => $orderNumber, 'email' => $email],
                ];
                
                $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_USERPWD => $stripeSecret . ':',
                    CURLOPT_POSTFIELDS => http_build_query_nested($sessionData),
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
                $session = json_decode($response, true);
                
                if (!empty($session['url'])) {
                    $db->query("UPDATE " . DB_PREFIX . "orders SET stripe_session_id = ? WHERE order_number = ?", [$session['id'], $orderNumber]);
                    header('Location: ' . $session['url']);
                    exit;
                } else {
                    $error = 'Payment error: ' . ($session['error']['message'] ?? 'Could not create checkout session.');
                }
            }
        }
    }
}

function http_build_query_nested($data, $prefix = '') {
    $result = [];
    foreach ($data as $key => $value) {
        $fullKey = $prefix ? $prefix . '[' . $key . ']' : $key;
        if (is_array($value)) $result = array_merge($result, http_build_query_nested($value, $fullKey));
        else $result[$fullKey] = $value;
    }
    return $prefix ? $result : http_build_query($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Checkout — <?php echo escape($siteName); ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#f5f6fa;color:#1a1a2e;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;min-height:100vh;}
        .nav{background:#fff;border-bottom:1px solid #e8e8e8;padding:14px 20px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
        .nav a{color:#1a1a2e;text-decoration:none;font-size:18px;font-weight:800;}.nav a span{color:#f0b90b;}
        .wrap{max-width:520px;margin:40px auto;padding:0 16px 60px;}
        .card{background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:32px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.04);}
        h1{font-size:22px;color:#1a1a2e;margin-bottom:4px;}
        .sub{color:#6b7280;font-size:13px;margin-bottom:24px;}
        .product-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:18px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;}
        .product-box .name{font-weight:700;font-size:15px;color:#1a1a2e;}
        .product-box .desc{font-size:11px;color:#6b7280;margin-top:2px;}
        .product-box .price{font-size:24px;font-weight:800;color:#16a34a;}
        .fg{margin-bottom:16px;}
        .fg label{display:block;font-size:12px;color:#374151;margin-bottom:4px;font-weight:600;}
        .fg label .req{color:#ef4444;}.fg label .opt{display:inline-block;background:#f3f4f6;color:#6b7280;font-size:9px;padding:1px 6px;border-radius:3px;margin-left:4px;font-weight:600;}
        .fg input{width:100%;background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:11px 14px;color:#1a1a2e;font-size:14px;outline:none;transition:border 0.15s;}
        .fg input:focus{border-color:#f0b90b;box-shadow:0 0 0 3px rgba(240,185,11,0.1);}
        .fg .hint{font-size:10px;color:#9ca3af;margin-top:3px;}
        .err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px;border-radius:8px;margin-bottom:16px;font-size:13px;}
        /* Gateway selector */
        .gw-options{display:flex;gap:10px;margin-bottom:20px;}
        .gw-opt{flex:1;border:2px solid #e5e7eb;border-radius:10px;padding:14px;cursor:pointer;text-align:center;transition:all 0.15s;position:relative;}
        .gw-opt:hover{border-color:#d1d5db;}
        .gw-opt.selected{border-color:#f0b90b;background:#fffbeb;}
        .gw-opt input[type=radio]{position:absolute;opacity:0;}
        .gw-opt .gw-icon{font-size:28px;margin-bottom:6px;}
        .gw-opt .gw-name{font-size:13px;font-weight:700;color:#1a1a2e;}
        .gw-opt .gw-desc{font-size:10px;color:#6b7280;margin-top:2px;}
        .gw-opt .gw-badge{display:inline-block;background:#d1fae5;color:#065f46;font-size:8px;padding:2px 6px;border-radius:10px;font-weight:700;margin-top:4px;}
        .btn{display:block;width:100%;background:#16a34a;color:#fff;border:none;padding:14px;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;transition:background 0.15s;}
        .btn:hover{background:#15803d;}
        .secure{text-align:center;margin-top:14px;font-size:11px;color:#9ca3af;}
        .secure span{color:#16a34a;font-weight:600;}
        .guarantee{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;text-align:center;font-size:12px;color:#92400e;margin-top:16px;}
        .guarantee strong{color:#78350f;}
        .back{text-align:center;margin-top:20px;}.back a{color:#6b7280;text-decoration:none;font-size:13px;}.back a:hover{color:#f0b90b;}
        .features{font-size:12px;color:#4b5563;line-height:2;}.features div{padding-left:24px;position:relative;}.features div:before{content:"✓";position:absolute;left:0;color:#16a34a;font-weight:700;font-size:14px;}
    </style>
</head>
<body>

<div class="nav"><a href="/"><span>⚡</span> <?php echo escape($siteName); ?></a></div>

<div class="wrap">
    <div class="card">
        <h1>Complete Your Purchase</h1>
        <p class="sub">Enter your details below to proceed to secure payment.</p>
        
        <div class="product-box">
            <div>
                <div class="name"><?php echo escape($productName); ?></div>
                <div class="desc">One-time payment • Lifetime license</div>
            </div>
            <div class="price">$<?php echo number_format($price, 2); ?></div>
        </div>
        
        <?php if ($error): ?><div class="err"><?php echo escape($error); ?></div><?php endif; ?>
        
        <form method="POST">
            <div class="fg">
                <label>Email Address <span class="req">*</span></label>
                <input type="email" name="email" value="<?php echo escape($_POST['email'] ?? ''); ?>" required placeholder="your@email.com">
                <div class="hint">📧 Download link will be sent to this email after payment.</div>
            </div>
            
            <div class="fg">
                <label>WhatsApp Number <span class="opt">Optional</span></label>
                <input type="text" name="whatsapp" value="<?php echo escape($_POST['whatsapp'] ?? ''); ?>" placeholder="+1 555 123 4567">
            </div>
            
            <div class="fg">
                <label>Telegram ID <span class="opt">Optional</span></label>
                <input type="text" name="telegram" value="<?php echo escape($_POST['telegram'] ?? ''); ?>" placeholder="@yourusername">
            </div>
            
            <!-- Payment Gateway Selection -->
            <?php
            $gateways = [];
            if ($hasStripe) $gateways[] = 'stripe';
            if ($hasNP) $gateways[] = 'nowpayments';
            if ($hasPayram) $gateways[] = 'payram';
            $defaultGW = $gateways[0] ?? 'stripe';
            ?>
            <?php if (count($gateways) > 1): ?>
            <div class="fg">
                <label>Payment Method</label>
                <div class="gw-options">
                    <?php if ($hasStripe): ?>
                    <label class="gw-opt<?php echo $defaultGW === 'stripe' ? ' selected' : ''; ?>" onclick="selGW(this,'stripe')">
                        <input type="radio" name="gateway" value="stripe"<?php echo $defaultGW === 'stripe' ? ' checked' : ''; ?>>
                        <div class="gw-icon">💳</div>
                        <div class="gw-name">Card Payment</div>
                        <div class="gw-desc">Visa, Mastercard, Apple Pay</div>
                        <div class="gw-badge">Powered by Stripe</div>
                        <div style="font-size:9px;color:#dc2626;font-weight:700;margin-top:4px;">🔒 KYC Required</div>
                    </label>
                    <?php endif; ?>
                    <?php if ($hasNP): ?>
                    <label class="gw-opt<?php echo $defaultGW === 'nowpayments' ? ' selected' : ''; ?>" onclick="selGW(this,'nowpayments')">
                        <input type="radio" name="gateway" value="nowpayments"<?php echo $defaultGW === 'nowpayments' ? ' checked' : ''; ?>>
                        <div class="gw-icon">₮</div>
                        <div class="gw-name">Pay with USDT</div>
                        <div class="gw-desc">Tron, Polygon, ERC-20, BSC</div>
                        <div class="gw-badge">via NOWPayments</div>
                        <div style="font-size:9px;color:#16a34a;font-weight:700;margin-top:4px;">✅ No KYC</div>
                    </label>
                    <?php endif; ?>
                    <?php if ($hasPayram): ?>
                    <label class="gw-opt<?php echo $defaultGW === 'payram' ? ' selected' : ''; ?>" onclick="selGW(this,'payram')">
                        <input type="radio" name="gateway" value="payram"<?php echo $defaultGW === 'payram' ? ' checked' : ''; ?>>
                        <div class="gw-icon">💳</div>
                        <div class="gw-name">Credit / Debit Card</div>
                        <div class="gw-desc">Secure Global Payments</div>
                        <div class="gw-badge">Fast & Secure</div>
                    </label>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <input type="hidden" name="gateway" value="<?php echo $defaultGW; ?>">
            <?php endif; ?>
            
            <button type="submit" class="btn">🔒 Proceed to Secure Payment — $<?php echo number_format($price, 2); ?></button>
        </form>
        
        <div class="secure">🔒 <span>Secure checkout</span> — your payment details are never stored on our servers.</div>
        <div class="guarantee">💯 <strong>60-Day Money-Back Guarantee.</strong></div>
    </div>
    
    <div class="card">
        <div style="font-size:13px;font-weight:700;color:#1a1a2e;margin-bottom:8px;">What you'll get:</div>
        <div class="features">
            <div>Complete PHP source code (no encryption)</div>
            <div>Stock, ETF, Forex & Crypto trading</div>
            <div>4-step web installation wizard</div>
            <div>6 months free email support</div>
            <div>Free installation service</div>
            <div>Lifetime license for 1 domain</div>
        </div>
    </div>
    
    <div class="back"><a href="/">← Back to product page</a></div>
</div>

<script>
function selGW(el,gw){
    document.querySelectorAll('.gw-opt').forEach(function(o){o.classList.remove('selected');});
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked=true;
}
</script>

</body>
</html>
