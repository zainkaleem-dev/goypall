<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();

global $db;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->query("UPDATE " . DB_PREFIX . "contact_messages SET status = 'read' WHERE id = ?", [$id]);
        }
        redirect('/admin/messages.php');
    }
    
    if ($action === 'mark_replied') {
        $id = (int)($_POST['id'] ?? 0);
        $notes = trim($_POST['admin_notes'] ?? '');
        if ($id > 0) {
            $db->query("UPDATE " . DB_PREFIX . "contact_messages SET status = 'replied', admin_notes = ? WHERE id = ?", [$notes, $id]);
        }
        flash('success', 'Message marked as replied.');
        redirect('/admin/messages.php');
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->query("DELETE FROM " . DB_PREFIX . "contact_messages WHERE id = ?", [$id]);
            admin_log('message_delete', "ID $id");
            flash('success', 'Message deleted.');
        }
        redirect('/admin/messages.php');
    }
    
    if ($action === 'delete_all_read') {
        $db->query("DELETE FROM " . DB_PREFIX . "contact_messages WHERE status IN ('read','replied')");
        admin_log('messages_bulk_delete', 'Deleted all read/replied messages');
        flash('success', 'All read/replied messages deleted.');
        redirect('/admin/messages.php');
    }
}

// Filter
$filter = $_GET['status'] ?? 'all';
$where = '';
$params = [];
if ($filter === 'unread') { $where = "WHERE status = 'unread'"; }
elseif ($filter === 'read') { $where = "WHERE status = 'read'"; }
elseif ($filter === 'replied') { $where = "WHERE status = 'replied'"; }

$messages = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "contact_messages $where ORDER BY created_at DESC", $params);
$unreadCount = $db->fetch("SELECT COUNT(*) c FROM " . DB_PREFIX . "contact_messages WHERE status = 'unread'")['c'] ?? 0;
$totalCount = $db->fetch("SELECT COUNT(*) c FROM " . DB_PREFIX . "contact_messages")['c'] ?? 0;

// View single message
$viewId = (int)($_GET['view'] ?? 0);
$viewing = null;
if ($viewId > 0) {
    $viewing = $db->fetch("SELECT * FROM " . DB_PREFIX . "contact_messages WHERE id = ?", [$viewId]);
    // Auto mark as read
    if ($viewing && $viewing['status'] === 'unread') {
        $db->query("UPDATE " . DB_PREFIX . "contact_messages SET status = 'read' WHERE id = ?", [$viewId]);
        $viewing['status'] = 'read';
    }
}

include __DIR__ . '/_header.php';
?>

<div class="page-head">
    <div>
        <h1>📬 Contact Messages</h1>
        <div class="subtitle"><?php echo $totalCount; ?> total · <?php echo $unreadCount; ?> unread</div>
    </div>
    <?php if ($viewing): ?>
    <a href="/admin/messages.php" class="btn btn-secondary">← Back to all messages</a>
    <?php endif; ?>
</div>

<?php if ($viewing): ?>
<!-- ========== VIEW SINGLE MESSAGE ========== -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin-bottom:4px;border:none;padding:0;">
                <?php echo escape($viewing['subject'] ?: '(No subject)'); ?>
            </h2>
            <div class="muted" style="font-size:13px;">
                From <strong><?php echo escape($viewing['name']); ?></strong> 
                &lt;<?php echo escape($viewing['email']); ?>&gt;
                · <?php echo date('M j, Y g:i A', strtotime($viewing['created_at'])); ?>
            </div>
        </div>
        <span class="badge badge-<?php echo $viewing['status']==='unread'?'danger':($viewing['status']==='replied'?'success':'info'); ?>">
            <?php echo $viewing['status']; ?>
        </span>
    </div>
    
    <div style="background:var(--c-bg);border:1px solid var(--c-border);border-radius:8px;padding:20px;margin:16px 0;font-size:15px;line-height:1.7;white-space:pre-wrap;word-break:break-word;">
<?php echo escape($viewing['message']); ?>
    </div>
    
    <div style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--c-text-muted);margin-bottom:16px;">
        <span>IP: <?php echo escape($viewing['ip_address']); ?></span>
    </div>
    
    <?php if ($viewing['admin_notes']): ?>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:16px;">
        <strong style="color:#15803d;">Admin Notes:</strong>
        <p style="margin:4px 0 0;color:#166534;"><?php echo escape($viewing['admin_notes']); ?></p>
    </div>
    <?php endif; ?>
    
    <div style="display:flex;gap:8px;flex-wrap:wrap;padding-top:16px;border-top:1px solid var(--c-border);">
        <a href="mailto:<?php echo escape($viewing['email']); ?>?subject=Re: <?php echo escape($viewing['subject'] ?: 'Your inquiry'); ?>" class="btn btn-primary">
            ✉ Reply via Email
        </a>
        
        <form method="POST" style="display:inline;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="mark_replied">
            <input type="hidden" name="id" value="<?php echo (int)$viewing['id']; ?>">
            <input type="text" name="admin_notes" placeholder="Add a note (optional)" 
                   style="padding:8px 12px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;width:200px;">
            <button type="submit" class="btn btn-success">✓ Mark Replied</button>
        </form>
        
        <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this message?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo (int)$viewing['id']; ?>">
            <button type="submit" class="btn btn-danger">🗑 Delete</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ========== MESSAGE LIST ========== -->

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <a href="/admin/messages.php" class="btn btn-sm <?php echo $filter==='all'?'btn-primary':'btn-secondary'; ?>">All (<?php echo $totalCount; ?>)</a>
    <a href="/admin/messages.php?status=unread" class="btn btn-sm <?php echo $filter==='unread'?'btn-primary':'btn-secondary'; ?>">Unread (<?php echo $unreadCount; ?>)</a>
    <a href="/admin/messages.php?status=read" class="btn btn-sm <?php echo $filter==='read'?'btn-primary':'btn-secondary'; ?>">Read</a>
    <a href="/admin/messages.php?status=replied" class="btn btn-sm <?php echo $filter==='replied'?'btn-primary':'btn-secondary'; ?>">Replied</a>
    
    <?php if ($totalCount > 0): ?>
    <form method="POST" style="margin-left:auto;" onsubmit="return confirmDelete('Delete all read/replied messages? This cannot be undone.');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="delete_all_read">
        <button type="submit" class="btn btn-sm btn-danger">🗑 Delete Read/Replied</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (empty($messages)): ?>
        <p class="muted text-center" style="padding:30px;">No messages <?php echo $filter !== 'all' ? "with status \"$filter\"" : 'yet'; ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>From</th>
                        <th>Subject / Preview</th>
                        <th>Date</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                    <tr style="<?php echo $msg['status']==='unread'?'background:#eff6ff;font-weight:600;':''; ?>">
                        <td>
                            <span class="badge badge-<?php echo $msg['status']==='unread'?'danger':($msg['status']==='replied'?'success':'neutral'); ?>">
                                <?php echo $msg['status']; ?>
                            </span>
                        </td>
                        <td>
                            <div><?php echo escape($msg['name']); ?></div>
                            <div style="font-size:11px;color:var(--c-text-muted);font-weight:400;"><?php echo escape($msg['email']); ?></div>
                        </td>
                        <td style="max-width:300px;">
                            <div style="font-weight:600;"><?php echo escape($msg['subject'] ?: '(No subject)'); ?></div>
                            <div style="font-size:12px;color:var(--c-text-muted);font-weight:400;margin-top:2px;">
                                <?php echo escape(substr($msg['message'], 0, 80)) . (strlen($msg['message']) > 80 ? '…' : ''); ?>
                            </div>
                        </td>
                        <td style="white-space:nowrap;font-size:12px;font-weight:400;">
                            <?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?>
                        </td>
                        <td class="actions">
                            <a href="/admin/messages.php?view=<?php echo (int)$msg['id']; ?>" class="btn btn-sm btn-primary">👁 View</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$msg['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                            </form>
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
