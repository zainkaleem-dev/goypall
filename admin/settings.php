<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();
global $db;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_all') {
        $fields = [
            'site_name','page_title','hero_title','hero_subtitle',
            'demo_button_text','demo_button_url','buy_button_text','buy_button_url',
            'support_url','support_button_text',
            'cta_left_text','cta_right_text',
            'post_purchase_text','support_promise',
            'short_description',
            'footer_text','contact_email',
            'seo_description','seo_keywords',
            'contact_phone','contact_whatsapp_url','contact_telegram_url',
            'contact_page_title','contact_page_subtitle',
            'stripe_public_key','stripe_secret_key','stripe_enabled','product_price','product_name',
            'download_file_url','download_enabled',
            'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption','smtp_from_email','smtp_from_name',
            'payram_api_url','payram_api_key','payram_enabled','payment_gateway',
            'nowpayments_api_key','nowpayments_ipn_secret','nowpayments_enabled',
        ];
        foreach ($fields as $key) { if (isset($_POST[$key])) set_setting($key, trim($_POST[$key])); }
        admin_log('settings_save', 'Saved settings');
        flash('success', 'Settings saved successfully.');
        redirect('/admin/settings.php' . (isset($_POST['_tab']) ? '#' . $_POST['_tab'] : ''));
    }
    
    if ($action === 'test_smtp') {
        $testEmail = trim($_POST['test_email'] ?? '');
        if ($testEmail && filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $result = send_smtp_email($testEmail, 'SMTP Test', '<div style="font-family:sans-serif;padding:20px;"><h2 style="color:#f0b90b;">SMTP is working!</h2><p>Sent at ' . date('Y-m-d H:i:s') . '</p></div>');
            flash($result === true ? 'success' : 'error', $result === true ? 'Test email sent to ' . $testEmail : 'SMTP Error: ' . $result);
        } else flash('error', 'Enter a valid email.');
        redirect('/admin/settings.php#tab-smtp');
    }
    
    if ($action === 'upload_logo') {
        if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','gif','svg','webp'])) {
                $logoDir = UPLOADS_PATH . '/logo'; if (!is_dir($logoDir)) @mkdir($logoDir, 0755, true);
                $newName = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoDir . '/' . $newName)) {
                    $old = settings('logo_url'); if ($old && strpos($old, '/uploads/logo/') !== false) @unlink(UPLOADS_PATH . '/logo/' . basename($old));
                    set_setting('logo_url', '/uploads/logo/' . $newName);
                    flash('success', 'Logo uploaded.');
                } else flash('error', 'Upload failed.');
            } else flash('error', 'Invalid image type.');
        } else flash('error', 'No file selected.');
        redirect('/admin/settings.php#branding');
    }
    if ($action === 'remove_logo') {
        $old = settings('logo_url'); if ($old && strpos($old, '/uploads/logo/') !== false) @unlink(UPLOADS_PATH . '/logo/' . basename($old));
        set_setting('logo_url', ''); flash('success', 'Logo removed.'); redirect('/admin/settings.php#branding');
    }
    if ($action === 'upload_product') {
        if (!empty($_FILES['product_file']['tmp_name']) && $_FILES['product_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['product_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['zip','rar','7z','tar','gz']) && $_FILES['product_file']['size'] <= 200*1024*1024) {
                $dlDir = UPLOADS_PATH . '/downloads'; if (!is_dir($dlDir)) @mkdir($dlDir, 0755, true);
                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($_FILES['product_file']['name'], PATHINFO_FILENAME));
                $newName = $safeName . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['product_file']['tmp_name'], $dlDir . '/' . $newName)) {
                    $old = settings('product_file_path'); if ($old && file_exists(UPLOADS_PATH . '/downloads/' . $old)) @unlink(UPLOADS_PATH . '/downloads/' . $old);
                    set_setting('product_file_path', $newName); set_setting('product_file_size', $_FILES['product_file']['size']);
                    set_setting('product_file_original', $_FILES['product_file']['name']); set_setting('download_file_url', '/download');
                    flash('success', 'Product file uploaded! (' . round($_FILES['product_file']['size']/1024/1024, 2) . ' MB)');
                } else flash('error', 'Failed to save file.');
            } else flash('error', 'Invalid file type or too large (max 200MB).');
        } else { flash('error', 'No file selected.'); }
        redirect('/admin/settings.php#tab-payment');
    }
    if ($action === 'remove_product') {
        $old = settings('product_file_path'); if ($old && file_exists(UPLOADS_PATH . '/downloads/' . $old)) @unlink(UPLOADS_PATH . '/downloads/' . $old);
        set_setting('product_file_path', ''); set_setting('product_file_size', ''); set_setting('product_file_original', ''); set_setting('download_file_url', '');
        flash('success', 'Product file removed.'); redirect('/admin/settings.php#tab-payment');
    }
}

$s = function($key) { return settings($key); };
include __DIR__ . '/_header.php';
?>

<div class="page-head"><div><h1>⚙️ Site Settings</h1><div class="subtitle">Configure your pitch page</div></div></div>

<div class="tab-bar">
    <button class="tab-btn active" onclick="showTab('tab-general', this)">📝 General</button>
    <button class="tab-btn" onclick="showTab('tab-hero', this)">🏠 Hero & CTA</button>
    <button class="tab-btn" onclick="showTab('tab-seo', this)">🔍 SEO</button>
    <button class="tab-btn" onclick="showTab('tab-contact', this)">📞 Contact</button>
    <button class="tab-btn" onclick="showTab('tab-payment', this)">💳 Payment</button>
    <button class="tab-btn" onclick="showTab('tab-smtp', this)">📧 SMTP</button>
</div>

<!-- GENERAL -->
<div class="tab-pane active" id="tab-general">
    <div class="card" id="branding"><h2>🎨 Branding</h2>
        <?php $logoUrl = $s('logo_url'); if ($logoUrl): ?>
        <div style="margin-bottom:16px;display:flex;align-items:center;gap:16px;"><img src="<?php echo escape($logoUrl); ?>" alt="Logo" style="max-height:60px;">
        <form method="POST" onsubmit="return confirm('Remove?');"><input type="hidden" name="action" value="remove_logo"><?php echo csrf_field(); ?><button type="submit" class="btn btn-sm btn-danger">Remove</button></form></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="upload_logo">
        <div class="form-group"><label>Upload Logo</label><input type="file" name="logo" accept="image/*" required></div>
        <button type="submit" class="btn btn-primary">Upload Logo</button></form>
    </div>
    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_all"><input type="hidden" name="_tab" value="tab-general">
    <div class="card"><h2>📝 General</h2>
        <div class="form-group"><label>Site Name</label><input type="text" name="site_name" value="<?php echo escape($s('site_name')); ?>"></div>
        <div class="form-group"><label>Page Title</label><input type="text" name="page_title" value="<?php echo escape($s('page_title')); ?>"></div>
        <div class="form-group"><label>Footer Text</label><input type="text" name="footer_text" value="<?php echo escape($s('footer_text')); ?>"></div>
        <div class="form-group"><label>Short Description (HTML)</label><textarea name="short_description" rows="6"><?php echo escape($s('short_description')); ?></textarea></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">💾 Save</button></div>
    </div></form>
</div>

<!-- HERO & CTA -->
<div class="tab-pane" id="tab-hero">
    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_all"><input type="hidden" name="_tab" value="tab-hero">
    <div class="card"><h2>🏠 Hero</h2>
        <div class="form-group"><label>Hero Title</label><input type="text" name="hero_title" value="<?php echo escape($s('hero_title')); ?>"></div>
        <div class="form-group"><label>Hero Subtitle</label><textarea name="hero_subtitle" rows="2"><?php echo escape($s('hero_subtitle')); ?></textarea></div>
    </div>
    <div class="card"><h2>💵 Buttons</h2>
        <div class="form-row"><div class="form-group"><label>Demo Text</label><input type="text" name="demo_button_text" value="<?php echo escape($s('demo_button_text')); ?>"></div>
        <div class="form-group"><label>Demo URL</label><input type="text" name="demo_button_url" value="<?php echo escape($s('demo_button_url')); ?>"></div></div>
        <div class="form-row"><div class="form-group"><label>Buy Text</label><input type="text" name="buy_button_text" value="<?php echo escape($s('buy_button_text')); ?>"></div>
        <div class="form-group"><label>Buy URL</label><input type="text" name="buy_button_url" value="<?php echo escape($s('buy_button_url')); ?>"></div></div>
    </div>
    <div class="card"><h2>📢 CTA & Info</h2>
        <div class="form-row"><div class="form-group"><label>CTA Left</label><input type="text" name="cta_left_text" value="<?php echo escape($s('cta_left_text')); ?>"></div>
        <div class="form-group"><label>CTA Right</label><input type="text" name="cta_right_text" value="<?php echo escape($s('cta_right_text')); ?>"></div></div>
        <div class="form-group"><label>Post-Purchase Text</label><input type="text" name="post_purchase_text" value="<?php echo escape($s('post_purchase_text')); ?>"></div>
        <div class="form-group"><label>Support Promise</label><input type="text" name="support_promise" value="<?php echo escape($s('support_promise')); ?>"></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">💾 Save</button></div>
    </div></form>
</div>

<!-- SEO -->
<div class="tab-pane" id="tab-seo">
    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_all"><input type="hidden" name="_tab" value="tab-seo">
    <div class="card"><h2>🔍 SEO</h2>
        <div class="form-group"><label>Meta Description</label><textarea name="seo_description" rows="3"><?php echo escape($s('seo_description')); ?></textarea></div>
        <div class="form-group"><label>Meta Keywords</label><input type="text" name="seo_keywords" value="<?php echo escape($s('seo_keywords')); ?>"></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">💾 Save</button></div>
    </div></form>
</div>

<!-- CONTACT -->
<div class="tab-pane" id="tab-contact">
    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_all"><input type="hidden" name="_tab" value="tab-contact">
    <div class="card"><h2>📞 Contact</h2>
        <div class="form-group"><label>Email</label><input type="email" name="contact_email" value="<?php echo escape($s('contact_email')); ?>"></div>
        <div class="form-group"><label>Phone</label><input type="text" name="contact_phone" value="<?php echo escape($s('contact_phone')); ?>"></div>
        <div class="form-group"><label>WhatsApp URL</label><input type="text" name="contact_whatsapp_url" value="<?php echo escape($s('contact_whatsapp_url')); ?>"></div>
        <div class="form-group"><label>Telegram URL</label><input type="text" name="contact_telegram_url" value="<?php echo escape($s('contact_telegram_url')); ?>"></div>
        <div class="form-group"><label>Contact Page Title</label><input type="text" name="contact_page_title" value="<?php echo escape($s('contact_page_title')); ?>"></div>
        <div class="form-group"><label>Contact Page Subtitle</label><input type="text" name="contact_page_subtitle" value="<?php echo escape($s('contact_page_subtitle')); ?>"></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">💾 Save</button></div>
    </div></form>
</div>

<!-- PAYMENT -->
<div class="tab-pane" id="tab-payment">
    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_all"><input type="hidden" name="_tab" value="tab-payment">
    
    <div class="card"><h2>🔀 Payment Gateway</h2>
        <div class="form-group"><label>Active Gateway</label>
        <select name="payment_gateway">
            <option value="stripe" <?php echo ($s('payment_gateway') ?: 'stripe') === 'stripe' ? 'selected' : ''; ?>>💳 Stripe Only</option>
            <option value="nowpayments" <?php echo $s('payment_gateway') === 'nowpayments' ? 'selected' : ''; ?>>₿ NOWPayments Only (Crypto)</option>
            <option value="payram" <?php echo $s('payment_gateway') === 'payram' ? 'selected' : ''; ?>>🪙 PayRam Only (USDC)</option>
            <option value="both" <?php echo $s('payment_gateway') === 'both' ? 'selected' : ''; ?>>💳₿🪙 All — Let buyer choose</option>
        </select></div>
    </div>

    <div class="card"><h2>💳 Stripe</h2>
        <div class="form-group"><label>Enable Stripe</label>
        <select name="stripe_enabled">
            <option value="0" <?php echo ($s('stripe_enabled') ?: '0') === '0' ? 'selected' : ''; ?>>❌ Disabled</option>
            <option value="1" <?php echo $s('stripe_enabled') === '1' ? 'selected' : ''; ?>>✅ Enabled</option>
        </select></div>
        <div class="form-group"><label>Publishable Key</label><input type="text" name="stripe_public_key" value="<?php echo escape($s('stripe_public_key')); ?>" placeholder="pk_live_..."></div>
        <div class="form-group"><label>Secret Key</label><input type="password" name="stripe_secret_key" value="<?php echo escape($s('stripe_secret_key')); ?>" placeholder="sk_live_..."></div>
    </div>

    <div class="card"><h2>₿ NOWPayments (Crypto)</h2>
        <p class="muted mb-2">Accept 150+ cryptocurrencies. Non-custodial — funds go to your wallet. <a href="https://nowpayments.io" target="_blank">nowpayments.io</a></p>
        <div class="form-group"><label>Enable NOWPayments</label>
        <select name="nowpayments_enabled">
            <option value="0" <?php echo ($s('nowpayments_enabled') ?: '0') === '0' ? 'selected' : ''; ?>>❌ Disabled</option>
            <option value="1" <?php echo $s('nowpayments_enabled') === '1' ? 'selected' : ''; ?>>✅ Enabled</option>
        </select></div>
        <div class="form-group"><label>API Key</label><input type="password" name="nowpayments_api_key" value="<?php echo escape($s('nowpayments_api_key')); ?>" placeholder="your-api-key"><small>From NOWPayments → Store Settings → API Key</small></div>
        <div class="form-group"><label>IPN Secret (optional)</label><input type="password" name="nowpayments_ipn_secret" value="<?php echo escape($s('nowpayments_ipn_secret')); ?>" placeholder="your-ipn-secret"><small>For webhook verification</small></div>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;margin-top:8px;">
            <p style="font-size:11px;color:#92400e;margin:0;"><strong>Setup:</strong> 1) Sign up at nowpayments.io 2) Add wallet 3) Copy API Key 4) Set IPN URL to: <strong><?php echo escape((defined('SITE_URL') ? rtrim(SITE_URL,'/') : '') . '/nowpayments-webhook'); ?></strong></p>
        </div>
    </div>

    <div class="card"><h2>🪙 PayRam (Card-to-USDC)</h2>
        <p class="muted mb-2">Customers pay with cards → you receive USDC. Self-hosted.</p>
        <div class="form-group"><label>Enable PayRam</label>
        <select name="payram_enabled">
            <option value="0" <?php echo ($s('payram_enabled') ?: '0') === '0' ? 'selected' : ''; ?>>❌ Disabled</option>
            <option value="1" <?php echo $s('payram_enabled') === '1' ? 'selected' : ''; ?>>✅ Enabled</option>
        </select></div>
        <div class="form-group"><label>PayRam API URL</label><input type="text" name="payram_api_url" value="<?php echo escape($s('payram_api_url')); ?>" placeholder="https://payram.yourdomain.com"></div>
        <div class="form-group"><label>PayRam API Key</label><input type="password" name="payram_api_key" value="<?php echo escape($s('payram_api_key')); ?>"></div>
    </div>

    <div class="card"><h2>📦 Product</h2>
        <div class="form-group"><label>Product Name</label><input type="text" name="product_name" value="<?php echo escape($s('product_name') ?: 'CryptoExchange Script'); ?>"></div>
        <div class="form-group"><label>Price (USD)</label><input type="number" name="product_price" value="<?php echo escape($s('product_price') ?: '299'); ?>" min="1" step="0.01"></div>
    </div>

    <div class="card"><h2>⚙️ Download</h2>
        <div class="form-group"><label>Enable Downloads</label>
        <select name="download_enabled">
            <option value="1" <?php echo ($s('download_enabled') ?: '1') === '1' ? 'selected' : ''; ?>>✅ Enabled</option>
            <option value="0" <?php echo $s('download_enabled') === '0' ? 'selected' : ''; ?>>❌ Disabled</option>
        </select></div>
        <div class="form-group"><label>External URL (optional)</label><input type="text" name="download_file_url" value="<?php echo escape($s('download_file_url')); ?>" placeholder="Leave blank for uploaded file"></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">💾 Save Payment Settings</button></div>
    </div>
    </form>

    <div class="card"><h2>📥 Product File</h2>
        <?php $pf = $s('product_file_path'); $po = $s('product_file_original'); $ps = (int)$s('product_file_size'); ?>
        <?php if ($pf && file_exists(UPLOADS_PATH . '/downloads/' . $pf)): ?>
        <div style="padding:14px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;margin-bottom:16px;">
            <strong style="color:#065f46;">📦 <?php echo escape($po ?: $pf); ?></strong> — <?php echo round($ps/1024/1024,2); ?> MB
            <form method="POST" style="display:inline;margin-left:12px;" onsubmit="return confirm('Remove?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="remove_product"><button type="submit" class="btn btn-sm btn-danger">🗑 Remove</button></form>
        </div>
        <?php else: ?><p style="color:#dc2626;font-size:13px;margin-bottom:12px;">⚠️ No product file uploaded.</p><?php endif; ?>
        <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="upload_product">
        <div class="form-group"><label>Upload File</label><input type="file" name="product_file" accept=".zip,.rar,.7z,.tar,.gz" required><small>Max 200MB</small></div>
        <button type="submit" class="btn btn-primary">📤 Upload</button></form>
    </div>
</div>

<!-- SMTP -->
<div class="tab-pane" id="tab-smtp">
    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_all"><input type="hidden" name="_tab" value="tab-smtp">
    <div class="card"><h2>📧 SMTP</h2>
        <div class="form-row"><div class="form-group"><label>Host</label><input type="text" name="smtp_host" value="<?php echo escape($s('smtp_host')); ?>" placeholder="smtp.gmail.com"></div>
        <div class="form-group"><label>Port</label><input type="number" name="smtp_port" value="<?php echo escape($s('smtp_port') ?: '587'); ?>"></div></div>
        <div class="form-row"><div class="form-group"><label>Username</label><input type="text" name="smtp_user" value="<?php echo escape($s('smtp_user')); ?>"></div>
        <div class="form-group"><label>Password</label><input type="password" name="smtp_pass" value="<?php echo escape($s('smtp_pass')); ?>"></div></div>
        <div class="form-group"><label>Encryption</label><select name="smtp_encryption">
            <option value="tls" <?php echo ($s('smtp_encryption') ?: 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (587)</option>
            <option value="ssl" <?php echo $s('smtp_encryption') === 'ssl' ? 'selected' : ''; ?>>SSL (465)</option>
            <option value="none" <?php echo $s('smtp_encryption') === 'none' ? 'selected' : ''; ?>>None</option>
        </select></div>
        <div class="form-row"><div class="form-group"><label>From Email</label><input type="email" name="smtp_from_email" value="<?php echo escape($s('smtp_from_email')); ?>"></div>
        <div class="form-group"><label>From Name</label><input type="text" name="smtp_from_name" value="<?php echo escape($s('smtp_from_name') ?: $s('site_name')); ?>"></div></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">💾 Save SMTP</button></div>
    </div></form>
    <div class="card"><h2>🧪 Test</h2>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="test_smtp">
        <div class="form-row"><div class="form-group"><label>Send To</label><input type="email" name="test_email" value="<?php echo escape($s('contact_email')); ?>" required></div>
        <div class="form-group" style="display:flex;align-items:flex-end;"><button type="submit" class="btn btn-secondary">📨 Send Test</button></div></div>
        </form>
    </div>
</div>

<script>
function showTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(function(p){ p.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
    window.location.hash = id;
}
(function(){
    var hash = window.location.hash.replace('#','');
    if (!hash) return;
    var btn = document.querySelector('[onclick*="' + hash + '"]');
    var pane = document.getElementById(hash);
    if (btn && pane) {
        document.querySelectorAll('.tab-btn').forEach(function(t){ t.classList.remove('active'); });
        document.querySelectorAll('.tab-pane').forEach(function(p){ p.classList.remove('active'); });
        btn.classList.add('active'); pane.classList.add('active');
    }
})();
</script>

<?php include __DIR__ . '/_footer.php'; ?>
