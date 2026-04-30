<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();

global $db;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reset_download') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $db->query("UPDATE " . DB_PREFIX . "orders SET download_used = 0, download_at = NULL WHERE id = ?", [$orderId]);
        flash('success', 'Download link reset. Customer can download again.');
        redirect('/admin/orders.php');
    }
    
    if ($action === 'refund') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $db->query("UPDATE " . DB_PREFIX . "orders SET status = 'refunded' WHERE id = ?", [$orderId]);
        flash('success', 'Order marked as refunded.');
        redirect('/admin/orders.php');
    }
}

// Stats
$totalOrders = $db->fetch("SELECT COUNT(*) as c FROM " . DB_PREFIX . "orders")['c'] ?? 0;
$paidOrders = $db->fetch("SELECT COUNT(*) as c FROM " . DB_PREFIX . "orders WHERE status = 'paid'")['c'] ?? 0;
$totalRevenue = $db->fetch("SELECT COALESCE(SUM(amount), 0) as s FROM " . DB_PREFIX . "orders WHERE status = 'paid'")['s'] ?? 0;
$todayOrders = $db->fetch("SELECT COUNT(*) as c FROM " . DB_PREFIX . "orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")['c'] ?? 0;

// Get orders with search
$search = trim($_GET['q'] ?? '');
if ($search) {
    $s = '%' . $search . '%';
    $orders = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "orders WHERE order_number LIKE ? OR email LIKE ? OR whatsapp LIKE ? OR telegram LIKE ? OR stripe_session_id LIKE ? OR CAST(id AS CHAR) = ? OR product_name LIKE ? ORDER BY created_at DESC LIMIT 100", [$s, $s, $s, $s, $s, $search, $s]);
} else {
    $orders = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "orders ORDER BY created_at DESC LIMIT 100");
}

include __DIR__ . '/_header.php';
?>

<div class="page-head">
    <div>
        <h1>📦 Orders</h1>
        <div class="subtitle">View purchases and manage downloads</div>
    </div>
</div>

<!-- Search -->
<div class="card" style="padding:16px;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <input type="text" name="q" value="<?php echo escape($search); ?>" placeholder="Search by order number or email..." style="flex:1;padding:10px 14px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;outline:none;">
        <button type="submit" class="btn btn-primary btn-sm" style="padding:10px 20px;">🔍 Search</button>
        <?php if ($search): ?><a href="/admin/orders.php" class="btn btn-sm btn-secondary" style="padding:10px 16px;">Clear</a><?php endif; ?>
    </form>
    <?php if ($search): ?><p style="margin-top:8px;font-size:12px;color:#888;">Showing results for: <strong>"<?php echo escape($search); ?>"</strong> (<?php echo count($orders); ?> found)</p><?php endif; ?>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px;">
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:28px;font-weight:800;color:var(--c-primary);"><?php echo $totalOrders; ?></div>
        <div style="font-size:12px;color:#888;">Total Orders</div>
    </div>
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:28px;font-weight:800;color:#22c55e;"><?php echo $paidOrders; ?></div>
        <div style="font-size:12px;color:#888;">Paid Orders</div>
    </div>
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:28px;font-weight:800;color:#22c55e;">$<?php echo number_format($totalRevenue, 2); ?></div>
        <div style="font-size:12px;color:#888;">Total Revenue</div>
    </div>
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:28px;font-weight:800;color:var(--c-primary);"><?php echo $todayOrders; ?></div>
        <div style="font-size:12px;color:#888;">Today</div>
    </div>
</div>

<!-- Orders Table -->
<div class="card" style="padding:0;overflow:hidden;">
    <?php if (empty($orders)): ?>
    <div style="text-align:center;padding:40px;color:#888;">No orders yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Email</th>
                <th>WhatsApp</th>
                <th>Telegram</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Download</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong style="font-family:monospace;font-size:12px;">#<?php echo escape($o['order_number']); ?></strong></td>
                <td>
                    <a href="mailto:<?php echo escape($o['email']); ?>" style="color:var(--c-primary);text-decoration:none;">
                        <?php echo escape($o['email']); ?>
                    </a>
                </td>
                <td>
                    <?php if ($o['whatsapp']): ?>
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $o['whatsapp']); ?>" target="_blank" style="color:#25d366;text-decoration:none;font-size:12px;">
                        <?php echo escape($o['whatsapp']); ?>
                    </a>
                    <?php else: ?>
                    <span style="color:#ccc;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($o['telegram']): ?>
                    <a href="https://t.me/<?php echo ltrim($o['telegram'], '@'); ?>" target="_blank" style="color:#0088cc;text-decoration:none;font-size:12px;">
                        <?php echo escape($o['telegram']); ?>
                    </a>
                    <?php else: ?>
                    <span style="color:#ccc;">—</span>
                    <?php endif; ?>
                </td>
                <td><strong style="color:#22c55e;">$<?php echo number_format($o['amount'], 2); ?></strong></td>
                <td>
                    <?php
                    $statusColors = ['pending'=>'#f59e0b','paid'=>'#22c55e','refunded'=>'#ef4444','expired'=>'#6b7280'];
                    $sc = $statusColors[$o['status']] ?? '#6b7280';
                    ?>
                    <span style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">
                        <?php echo strtoupper($o['status']); ?>
                    </span>
                </td>
                <td>
                    <?php if ($o['download_used']): ?>
                    <span style="color:#22c55e;font-size:11px;font-weight:600;">✓ Downloaded</span>
                    <div style="font-size:10px;color:#888;"><?php echo $o['download_at'] ? date('M j, g:ia', strtotime($o['download_at'])) : ''; ?></div>
                    <?php else: ?>
                    <span style="color:#f59e0b;font-size:11px;">Not yet</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:11px;color:#888;">
                    <?php echo date('M j, Y', strtotime($o['created_at'])); ?><br>
                    <?php echo date('g:i A', strtotime($o['created_at'])); ?>
                </td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <?php if ($o['download_used'] && $o['status'] === 'paid'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Reset download? Customer will be able to download again.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="reset_download">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-secondary" style="font-size:10px;padding:3px 8px;">🔄 Reset</button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($o['status'] === 'paid'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark as refunded?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="refund">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="font-size:10px;padding:3px 8px;">💸 Refund</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
