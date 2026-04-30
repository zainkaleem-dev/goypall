<?php
/**
 * Support Portal — My Tickets (client-side)
 */
require_once dirname(__DIR__) . '/init.php';
require_once INCLUDES_PATH . '/layout.php';

global $db;

// Must be logged in as client
if (empty($_SESSION['client_id'])) {
    header('Location: /support');
    exit;
}

$clientId = (int)$_SESSION['client_id'];
$clientName = $_SESSION['client_name'] ?? '';

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    // Create new ticket
    if ($action === 'create') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['low','normal','high']) ? $_POST['priority'] : 'normal';
        
        if (!$subject || !$message) {
            $error = 'Subject and message are required.';
        } else {
            $ticketId = $db->insert('tickets', [
                'client_id' => $clientId,
                'subject' => $subject,
                'priority' => $priority,
            ]);
            $db->insert('ticket_messages', [
                'ticket_id' => $ticketId,
                'sender_type' => 'client',
                'sender_id' => $clientId,
                'message' => $message,
            ]);
            $success = 'Ticket #' . $ticketId . ' created successfully.';
        }
    }
    
    // Reply to ticket
    if ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        // Verify ticket belongs to this client
        $ticket = $db->fetch("SELECT * FROM " . DB_PREFIX . "tickets WHERE id = ? AND client_id = ?", [$ticketId, $clientId]);
        if (!$ticket) {
            $error = 'Ticket not found.';
        } elseif (!$message) {
            $error = 'Please enter a message.';
        } elseif ($ticket['status'] === 'closed') {
            $error = 'This ticket is closed. Please create a new ticket.';
        } else {
            $db->insert('ticket_messages', [
                'ticket_id' => $ticketId,
                'sender_type' => 'client',
                'sender_id' => $clientId,
                'message' => $message,
            ]);
            $db->query("UPDATE " . DB_PREFIX . "tickets SET status = 'open', updated_at = NOW() WHERE id = ?", [$ticketId]);
            $success = 'Reply sent.';
        }
    }
    
    if (!$error && !$success) {
        header('Location: /support/tickets');
        exit;
    }
}

// View single ticket
$viewId = (int)($_GET['view'] ?? 0);
$viewing = null;
$messages = [];
if ($viewId > 0) {
    $viewing = $db->fetch("SELECT * FROM " . DB_PREFIX . "tickets WHERE id = ? AND client_id = ?", [$viewId, $clientId]);
    if ($viewing) {
        $messages = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC", [$viewId]);
    }
}

// My tickets list
$tickets = $db->fetchAll(
    "SELECT t.*, (SELECT COUNT(*) FROM " . DB_PREFIX . "ticket_messages WHERE ticket_id = t.id) as msg_count 
     FROM " . DB_PREFIX . "tickets t WHERE t.client_id = ? ORDER BY t.updated_at DESC",
    [$clientId]
);

render_header('My Support Tickets', 'View and manage your support tickets.');
?>

<section class="page-section">
    <div class="container">
        <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h1>My Support Tickets</h1>
                <p class="meta">Welcome, <?php echo escape($clientName); ?> · <a href="/support/logout" style="color:var(--c-danger);">Sign out</a></p>
            </div>
            <div>
                <?php if (!$viewing): ?>
                <button class="btn btn-contact-submit" style="width:auto;padding:12px 24px;" onclick="document.getElementById('newTicketModal').style.display='flex'">
                    ➕ New Ticket
                </button>
                <?php else: ?>
                <a href="/support/tickets" class="btn btn-outline" style="min-width:auto;padding:10px 20px;">← Back to Tickets</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($success): ?>
        <div class="contact-alert contact-alert-success"><span class="contact-alert-icon">✓</span><div><?php echo escape($success); ?></div></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="contact-alert contact-alert-error"><span class="contact-alert-icon">⚠</span><div><?php echo escape($error); ?></div></div>
        <?php endif; ?>
        
        <?php if ($viewing): ?>
        <!-- ========== VIEW TICKET ========== -->
        <div class="ticket-view-header">
            <div>
                <h2 style="margin:0;font-size:20px;">Ticket #<?php echo (int)$viewing['id']; ?> — <?php echo escape($viewing['subject']); ?></h2>
                <div style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <span class="ticket-badge ticket-badge-<?php echo $viewing['status']; ?>"><?php echo ucfirst($viewing['status']); ?></span>
                    <span class="ticket-badge ticket-badge-<?php echo $viewing['priority']; ?>"><?php echo ucfirst($viewing['priority']); ?> priority</span>
                    <span style="color:var(--c-text-muted);font-size:13px;">Created <?php echo date('M j, Y g:i A', strtotime($viewing['created_at'])); ?></span>
                </div>
            </div>
        </div>
        
        <div class="ticket-thread">
            <?php foreach ($messages as $msg): ?>
            <div class="ticket-msg <?php echo $msg['sender_type'] === 'admin' ? 'ticket-msg-admin' : 'ticket-msg-client'; ?>">
                <div class="ticket-msg-header">
                    <strong><?php echo $msg['sender_type'] === 'admin' ? '🛟 Support Team' : '👤 You'; ?></strong>
                    <span><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></span>
                </div>
                <div class="ticket-msg-body"><?php echo nl2br(escape($msg['message'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($viewing['status'] !== 'closed'): ?>
        <div class="ticket-reply-form">
            <h3 style="margin-bottom:12px;">Reply</h3>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="ticket_id" value="<?php echo (int)$viewing['id']; ?>">
                <div class="contact-field">
                    <textarea name="message" rows="4" required placeholder="Type your reply..."></textarea>
                </div>
                <button type="submit" class="btn-contact-submit" style="width:auto;padding:10px 24px;">✉ Send Reply</button>
            </form>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:20px;background:var(--c-bg-alt);border-radius:8px;margin-top:20px;color:var(--c-text-muted);">
            This ticket is closed. <button class="btn btn-outline" style="min-width:auto;padding:8px 16px;margin-left:8px;" onclick="document.getElementById('newTicketModal').style.display='flex'">Open New Ticket</button>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- ========== TICKETS LIST ========== -->
        <?php if (empty($tickets)): ?>
        <div style="text-align:center;padding:60px 20px;background:#fff;border:1px solid var(--c-border);border-radius:12px;">
            <div style="font-size:48px;margin-bottom:16px;">🎫</div>
            <h3 style="margin-bottom:8px;">No tickets yet</h3>
            <p style="color:var(--c-text-muted);margin-bottom:20px;">Create a ticket to get help from our support team.</p>
            <button class="btn-contact-submit" style="width:auto;padding:12px 28px;" onclick="document.getElementById('newTicketModal').style.display='flex'">➕ Create First Ticket</button>
        </div>
        <?php else: ?>
        <div class="tickets-list">
            <?php foreach ($tickets as $t): ?>
            <a href="/support/tickets?view=<?php echo (int)$t['id']; ?>" class="ticket-row">
                <div class="ticket-row-left">
                    <span class="ticket-badge ticket-badge-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span>
                    <div>
                        <div class="ticket-row-subject">#<?php echo (int)$t['id']; ?> — <?php echo escape($t['subject']); ?></div>
                        <div class="ticket-row-meta">
                            <?php echo ucfirst($t['priority']); ?> priority · <?php echo (int)$t['msg_count']; ?> messages · Updated <?php echo date('M j, g:i A', strtotime($t['updated_at'])); ?>
                        </div>
                    </div>
                </div>
                <span style="color:var(--c-primary);font-size:14px;">View →</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- New Ticket Modal -->
<div class="support-modal" id="newTicketModal" onclick="if(event.target===this)this.style.display='none'">
    <div class="support-modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="margin:0;">➕ New Support Ticket</h3>
            <button onclick="document.getElementById('newTicketModal').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--c-text-muted);">×</button>
        </div>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="contact-field">
                <label>Subject *</label>
                <input type="text" name="subject" required placeholder="Brief description of your issue">
            </div>
            <div class="contact-field">
                <label>Priority</label>
                <select name="priority" style="width:100%;padding:11px 14px;border:1px solid var(--c-border);border-radius:8px;font-size:14px;font-family:inherit;">
                    <option value="low">Low</option>
                    <option value="normal" selected>Normal</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div class="contact-field">
                <label>Message *</label>
                <textarea name="message" rows="5" required placeholder="Describe your issue in detail..."></textarea>
            </div>
            <button type="submit" class="btn-contact-submit">🎫 Submit Ticket</button>
        </form>
    </div>
</div>

<?php render_footer(); ?>
