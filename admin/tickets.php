<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();

global $db;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if ($ticketId && $message) {
            try {
                $db->insert('ticket_replies', ['ticket_id' => $ticketId, 'sender' => 'admin', 'message' => $message]);
                $db->query("UPDATE " . DB_PREFIX . "tickets SET status = 'replied', updated_at = NOW() WHERE id = ?", [$ticketId]);
                flash('success', 'Reply sent successfully!');
            } catch (Exception $e) { flash('error', 'Error: ' . $e->getMessage()); }
        }
        redirect('/admin/tickets.php?view=' . $ticketId);
    }
    
    if ($action === 'close') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $db->query("UPDATE " . DB_PREFIX . "tickets SET status = 'closed', updated_at = NOW() WHERE id = ?", [$ticketId]);
        flash('success', 'Ticket closed.');
        redirect('/admin/tickets.php');
    }
    
    if ($action === 'reopen') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $db->query("UPDATE " . DB_PREFIX . "tickets SET status = 'open', updated_at = NOW() WHERE id = ?", [$ticketId]);
        flash('success', 'Ticket reopened.');
        redirect('/admin/tickets.php?view=' . $ticketId);
    }
}

$viewTicketId = (int)($_GET['view'] ?? 0);

$totalTickets = $db->fetch("SELECT COUNT(*) as c FROM " . DB_PREFIX . "tickets")['c'] ?? 0;
$openTickets = $db->fetch("SELECT COUNT(*) as c FROM " . DB_PREFIX . "tickets WHERE status = 'open'")['c'] ?? 0;
$repliedTickets = $db->fetch("SELECT COUNT(*) as c FROM " . DB_PREFIX . "tickets WHERE status = 'replied'")['c'] ?? 0;
$totalCustomers = $db->fetch("SELECT COUNT(*) as c FROM " . DB_PREFIX . "customers")['c'] ?? 0;

$pColors = ['low'=>'#6b7280','medium'=>'#f59e0b','high'=>'#ef4444','urgent'=>'#ef4444'];
$sColors = ['open'=>'#ef4444','replied'=>'#22c55e','closed'=>'#6b7280'];

include __DIR__ . '/_header.php';
?>

<div class="page-head">
    <div>
        <h1>🎫 Support Tickets</h1>
        <div class="subtitle">Manage customer support tickets and replies</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
    <div class="card" style="text-align:center;padding:16px;"><div style="font-size:24px;font-weight:800;color:#ef4444;"><?php echo $openTickets; ?></div><div style="font-size:11px;color:#888;">Open</div></div>
    <div class="card" style="text-align:center;padding:16px;"><div style="font-size:24px;font-weight:800;color:#22c55e;"><?php echo $repliedTickets; ?></div><div style="font-size:11px;color:#888;">Replied</div></div>
    <div class="card" style="text-align:center;padding:16px;"><div style="font-size:24px;font-weight:800;color:var(--c-primary);"><?php echo $totalTickets; ?></div><div style="font-size:11px;color:#888;">Total</div></div>
    <div class="card" style="text-align:center;padding:16px;"><div style="font-size:24px;font-weight:800;color:var(--c-primary);"><?php echo $totalCustomers; ?></div><div style="font-size:11px;color:#888;">Customers</div></div>
</div>

<?php if ($viewTicketId): ?>
<?php
$ticket = $db->fetch("SELECT t.*, COALESCE(c.name, 'Unknown') as cust_name, COALESCE(c.email, 'N/A') as cust_email, c.order_number as cust_order FROM " . DB_PREFIX . "tickets t LEFT JOIN " . DB_PREFIX . "customers c ON t.customer_id = c.id WHERE t.id = ?", [$viewTicketId]);
if ($ticket):
$replies = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC", [$viewTicketId]);
?>

<div style="margin-bottom:12px;">
    <a href="/admin/tickets.php" style="color:var(--c-primary);text-decoration:none;font-size:13px;">← All Tickets</a>
</div>

<!-- Ticket Header -->
<div class="card">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:center;">
        <div>
            <h2 style="margin:0;font-size:18px;">#<?php echo escape($ticket['ticket_number']); ?> — <?php echo escape($ticket['subject']); ?></h2>
            <p style="font-size:12px;color:#888;margin-top:4px;">
                👤 <strong><?php echo escape($ticket['cust_name']); ?></strong> 
                (<a href="mailto:<?php echo escape($ticket['cust_email']); ?>" style="color:var(--c-primary);"><?php echo escape($ticket['cust_email']); ?></a>)
                <?php if ($ticket['cust_order']): ?> • 📦 Order: <strong><?php echo escape($ticket['cust_order']); ?></strong><?php endif; ?>
                • <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;align-items:center;">
            <span style="background:<?php echo $pColors[$ticket['priority']] ?? '#6b7280'; ?>20;color:<?php echo $pColors[$ticket['priority']] ?? '#6b7280'; ?>;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;"><?php echo strtoupper($ticket['priority']); ?></span>
            <span style="background:<?php echo $sColors[$ticket['status']] ?? '#6b7280'; ?>20;color:<?php echo $sColors[$ticket['status']] ?? '#6b7280'; ?>;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;"><?php echo strtoupper($ticket['status']); ?></span>
            <?php if ($ticket['status'] !== 'closed'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Close this ticket?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                <button type="submit" class="btn btn-sm btn-secondary">🔒 Close</button>
            </form>
            <?php else: ?>
            <form method="POST" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reopen">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                <button type="submit" class="btn btn-sm btn-primary">🔓 Reopen</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Messages -->
<?php foreach ($replies as $r): ?>
<div style="padding:16px;border-radius:8px;margin-bottom:8px;<?php echo $r['sender'] === 'admin' ? 'background:#f0fdf4;border-left:4px solid #22c55e;' : 'background:#fff;border:1px solid #e5e7eb;border-left:4px solid #f59e0b;'; ?>">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:11px;">
        <span style="font-weight:700;color:<?php echo $r['sender'] === 'admin' ? '#16a34a' : '#92400e'; ?>;">
            <?php echo $r['sender'] === 'admin' ? '🛡️ Admin (You)' : '👤 ' . escape($ticket['cust_name']); ?>
        </span>
        <span style="color:#9ca3af;"><?php echo date('M j, Y — g:i A', strtotime($r['created_at'])); ?></span>
    </div>
    <div style="font-size:13px;color:#374151;line-height:1.7;white-space:pre-wrap;"><?php echo nl2br(escape($r['message'])); ?></div>
</div>
<?php endforeach; ?>

<!-- ADMIN REPLY FORM -->
<div class="card" style="margin-top:12px;border:2px solid var(--c-primary);">
    <h3 style="margin:0 0 12px;font-size:15px;color:var(--c-primary);">✏️ Reply to Customer</h3>
    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
        <div class="form-group">
            <textarea name="message" rows="6" required placeholder="Type your reply to the customer here..." style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;"></textarea>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary" style="font-size:15px;padding:12px 30px;">📨 Send Reply</button>
            <span style="font-size:11px;color:#9ca3af;padding-top:14px;">This will be visible to the customer and ticket status will change to "Replied".</span>
        </div>
    </form>
</div>

<?php else: ?>
<div class="card"><p style="text-align:center;color:#888;">Ticket not found.</p></div>
<?php endif; ?>

<?php else: ?>
<!-- ALL TICKETS LIST -->
<div class="card" style="padding:0;overflow:hidden;">
    <?php
    try {
        $tickets = $db->fetchAll("SELECT t.*, COALESCE(c.name, 'Unknown') as cust_name, COALESCE(c.email, 'N/A') as cust_email, c.order_number as cust_order FROM " . DB_PREFIX . "tickets t LEFT JOIN " . DB_PREFIX . "customers c ON t.customer_id = c.id ORDER BY FIELD(t.status,'open','replied','closed'), t.updated_at DESC LIMIT 100");
    } catch (Exception $e) { $tickets = []; }
    ?>
    <?php if (empty($tickets)): ?>
    <div style="text-align:center;padding:40px;color:#888;">No support tickets yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Ticket</th><th>Customer</th><th>Subject</th><th>Priority</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr style="<?php echo $t['status'] === 'open' ? 'background:#fef2f210;' : ''; ?>">
            <td><strong style="font-family:monospace;font-size:11px;">#<?php echo escape($t['ticket_number']); ?></strong></td>
            <td>
                <div style="font-weight:600;font-size:12px;"><?php echo escape($t['cust_name']); ?></div>
                <div style="font-size:10px;color:#888;"><?php echo escape($t['cust_email']); ?></div>
                <?php if ($t['cust_order']): ?><div style="font-size:9px;color:var(--c-primary);">📦 <?php echo escape($t['cust_order']); ?></div><?php endif; ?>
            </td>
            <td style="max-width:250px;">
                <a href="/admin/tickets.php?view=<?php echo $t['id']; ?>" style="color:var(--c-text);text-decoration:none;font-weight:600;font-size:13px;">
                    <?php echo escape($t['subject']); ?>
                </a>
            </td>
            <td><span style="background:<?php echo $pColors[$t['priority']] ?? '#6b7280'; ?>20;color:<?php echo $pColors[$t['priority']] ?? '#6b7280'; ?>;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;"><?php echo strtoupper($t['priority']); ?></span></td>
            <td><span style="background:<?php echo $sColors[$t['status']] ?? '#6b7280'; ?>20;color:<?php echo $sColors[$t['status']] ?? '#6b7280'; ?>;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;"><?php echo strtoupper($t['status']); ?></span></td>
            <td style="font-size:11px;color:#888;"><?php echo date('M j, g:ia', strtotime($t['updated_at'])); ?></td>
            <td>
                <a href="/admin/tickets.php?view=<?php echo $t['id']; ?>" class="btn btn-sm btn-primary">📩 View & Reply</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
