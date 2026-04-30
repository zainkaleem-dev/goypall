<?php
require_once dirname(__DIR__) . '/init.php';

global $db;
$siteName = settings('site_name') ?: 'CryptoExchange';
$contactEmail = settings('contact_email') ?: 'support@goypall.net';
$whatsappUrl = settings('contact_whatsapp_url') ?: '';
$telegramUrl = settings('contact_telegram_url') ?: '';

function support_user() { return $_SESSION['support_user'] ?? null; }
function support_logged_in() { return !empty($_SESSION['support_user']); }

$page = $_GET['p'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';
        $orderNum = trim($_POST['order_number'] ?? '');
        
        if (!$name || !$email || !$pass) { $error = 'All fields are required.'; $page = 'register'; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Invalid email address.'; $page = 'register'; }
        elseif (strlen($pass) < 6) { $error = 'Password must be at least 6 characters.'; $page = 'register'; }
        elseif ($pass !== $pass2) { $error = 'Passwords do not match.'; $page = 'register'; }
        else {
            try {
                $exists = $db->fetch("SELECT id FROM " . DB_PREFIX . "customers WHERE email = ?", [$email]);
                if ($exists) { $error = 'Email already registered. Please sign in.'; $page = 'register'; }
                else {
                    $db->insert('customers', [
                        'name' => $name, 'email' => $email,
                        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                        'order_number' => $orderNum ?: null,
                    ]);
                    $cust = $db->fetch("SELECT * FROM " . DB_PREFIX . "customers WHERE email = ?", [$email]);
                    $_SESSION['support_user'] = $cust;
                    redirect('/support');
                }
            } catch (Exception $e) { $error = 'Registration error. Please try again.'; $page = 'register'; }
        }
    }
    
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (!$email || !$pass) { $error = 'Email and password are required.'; $page = 'login'; }
        else {
            try {
                $cust = $db->fetch("SELECT * FROM " . DB_PREFIX . "customers WHERE email = ?", [$email]);
                if ($cust && password_verify($pass, $cust['password_hash'])) {
                    if ($cust['status'] === 'banned') { $error = 'Your account has been suspended.'; $page = 'login'; }
                    else {
                        $db->query("UPDATE " . DB_PREFIX . "customers SET last_login = NOW() WHERE id = ?", [$cust['id']]);
                        $_SESSION['support_user'] = $cust;
                        redirect('/support');
                    }
                } else { $error = 'Invalid email or password.'; $page = 'login'; }
            } catch (Exception $e) { $error = 'Login error. Please try again.'; $page = 'login'; }
        }
    }
    
    if ($action === 'create_ticket' && support_logged_in()) {
        $subject = trim($_POST['subject'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $message = trim($_POST['message'] ?? '');
        if (!$subject || !$message) { $error = 'Subject and message are required.'; $page = 'new'; }
        else {
            try {
                $ticketNum = 'TK-' . strtoupper(substr(md5(time() . support_user()['id']), 0, 8));
                $ticketId = $db->insert('tickets', [
                    'ticket_number' => $ticketNum,
                    'customer_id' => support_user()['id'],
                    'subject' => $subject,
                    'priority' => $priority,
                    'status' => 'open',
                ]);
                $db->insert('ticket_replies', [
                    'ticket_id' => $ticketId,
                    'sender' => 'customer',
                    'message' => $message,
                ]);
                $success = 'Ticket #' . $ticketNum . ' created successfully!';
                $page = '';
            } catch (Exception $e) { $error = 'Error creating ticket: ' . $e->getMessage(); $page = 'new'; }
        }
    }
    
    if ($action === 'reply_ticket' && support_logged_in()) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        try {
            $ticket = $db->fetch("SELECT * FROM " . DB_PREFIX . "tickets WHERE id = ? AND customer_id = ?", [$ticketId, support_user()['id']]);
            if ($ticket && $message) {
                $db->insert('ticket_replies', ['ticket_id' => $ticketId, 'sender' => 'customer', 'message' => $message]);
                $db->query("UPDATE " . DB_PREFIX . "tickets SET status = 'open', updated_at = NOW() WHERE id = ?", [$ticketId]);
                redirect('/support?p=ticket&id=' . $ticket['ticket_number']);
            }
        } catch (Exception $e) { $error = 'Error sending reply.'; }
    }
    
    if ($action === 'logout') { unset($_SESSION['support_user']); redirect('/support'); }
}

if ($page === 'logout') { unset($_SESSION['support_user']); redirect('/support'); }

$view = 'login';
if (support_logged_in()) {
    $view = 'dashboard';
    if ($page === 'new') $view = 'new_ticket';
    if ($page === 'ticket') $view = 'view_ticket';
} else {
    $view = ($page === 'register') ? 'register' : 'login';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Support — <?php echo escape($siteName); ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#f5f6fa;color:#1a1a2e;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;min-height:100vh;}
        .nav{background:#fff;border-bottom:1px solid #e8e8e8;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
        .nav .logo{color:#1a1a2e;text-decoration:none;font-size:18px;font-weight:800;}
        .nav .logo span{color:#f0b90b;}
        .nav .right{display:flex;align-items:center;gap:14px;font-size:13px;}
        .nav .right a{color:#6b7280;text-decoration:none;}.nav .right a:hover{color:#f0b90b;}
        .nav .user{color:#1a1a2e;font-weight:600;font-size:12px;background:#f3f4f6;padding:4px 10px;border-radius:20px;}
        .wrap{max-width:880px;margin:30px auto;padding:0 16px 60px;}
        .card{background:#fff;border:1px solid #e8e8e8;border-radius:10px;padding:28px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,0.03);}
        h1{font-size:22px;margin-bottom:4px;color:#1a1a2e;}
        .sub{color:#6b7280;font-size:13px;margin-bottom:20px;}
        .fg{margin-bottom:14px;}
        .fg label{display:block;font-size:11px;color:#374151;margin-bottom:3px;font-weight:600;}
        .fg label .req{color:#ef4444;}
        .fg input,.fg textarea,.fg select{width:100%;background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:10px 12px;color:#1a1a2e;font-size:14px;outline:none;font-family:inherit;transition:border 0.15s;}
        .fg input:focus,.fg textarea:focus,.fg select:focus{border-color:#f0b90b;box-shadow:0 0 0 3px rgba(240,185,11,0.1);}
        .fg textarea{resize:vertical;min-height:100px;}
        .fg .hint{font-size:10px;color:#9ca3af;margin-top:2px;}
        .btn{display:inline-block;background:#f0b90b;color:#1a1a2e;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;text-align:center;transition:background 0.15s;}
        .btn:hover{background:#d4a30a;}
        .btn-green{background:#16a34a;color:#fff;}.btn-green:hover{background:#15803d;}
        .btn-full{display:block;width:100%;}
        .btn-sm{padding:6px 14px;font-size:12px;}
        .err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;}
        .ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;}
        .link{color:#f0b90b;text-decoration:none;font-weight:600;}.link:hover{text-decoration:underline;}
        /* Tickets */
        .tk-list{border-top:1px solid #e8e8e8;}
        .tk-row{display:grid;grid-template-columns:90px 1fr 80px 80px 100px;gap:8px;padding:12px 0;border-bottom:1px solid #f3f4f6;align-items:center;font-size:13px;}
        .tk-row.hd{color:#6b7280;font-size:10px;text-transform:uppercase;font-weight:700;border-bottom:1px solid #e8e8e8;}
        .tk-num{font-family:monospace;font-weight:700;font-size:12px;color:#374151;}
        .tk-subj a{color:#1a1a2e;text-decoration:none;font-weight:600;}.tk-subj a:hover{color:#f0b90b;}
        .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;}
        .badge-open{background:#fef3c7;color:#92400e;}
        .badge-replied{background:#d1fae5;color:#065f46;}
        .badge-closed{background:#f3f4f6;color:#6b7280;}
        .badge-low{background:#f3f4f6;color:#6b7280;}
        .badge-medium{background:#fef3c7;color:#92400e;}
        .badge-high{background:#fee2e2;color:#991b1b;}
        .badge-urgent{background:#dc2626;color:#fff;}
        /* Messages */
        .msg{padding:14px 16px;border-radius:10px;margin-bottom:10px;}
        .msg-cust{background:#f9fafb;border-left:3px solid #f0b90b;}
        .msg-admin{background:#f0fdf4;border-left:3px solid #16a34a;}
        .msg-head{display:flex;justify-content:space-between;margin-bottom:6px;font-size:11px;}
        .msg-head .who{font-weight:700;}.msg-head .when{color:#9ca3af;}
        .msg-body{font-size:13px;color:#374151;line-height:1.6;white-space:pre-wrap;}
        /* Sidebar */
        .sidebar{display:grid;grid-template-columns:1fr 260px;gap:16px;}
        .cc{background:#fff;border:1px solid #e8e8e8;border-radius:8px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,0.03);}
        .cc h4{font-size:12px;color:#1a1a2e;margin-bottom:4px;}
        .cc p{font-size:11px;color:#6b7280;line-height:1.5;margin:0;}
        .cc a{color:#f0b90b;text-decoration:none;font-weight:600;}
        .chat-btn{display:block;padding:8px 12px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;margin-top:6px;text-align:center;}
        .chat-btn.wa{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;}
        .chat-btn.tg{background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;}
        .empty{text-align:center;padding:30px;color:#9ca3af;font-size:13px;}
        .footer{text-align:center;padding:24px;color:#9ca3af;font-size:11px;}
        @media(max-width:700px){.sidebar{grid-template-columns:1fr;}.tk-row{grid-template-columns:1fr;gap:4px;}.tk-row.hd{display:none;}}
    </style>
</head>
<body>

<div class="nav">
    <a href="/" class="logo"><span>⚡</span> <?php echo escape($siteName); ?></a>
    <div class="right">
        <a href="/">Home</a>
        <a href="/faq">FAQ</a>
        <a href="/contact">Contact</a>
        <?php if (support_logged_in()): ?>
        <span class="user">👤 <?php echo escape(support_user()['name']); ?></span>
        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="logout"><button type="submit" style="background:none;border:none;color:#6b7280;cursor:pointer;font-size:13px;">Logout</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="wrap">

<?php if ($error): ?><div class="err"><?php echo escape($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="ok"><?php echo escape($success); ?></div><?php endif; ?>

<?php if ($view === 'login'): ?>
<div style="max-width:420px;margin:40px auto;">
    <div class="card">
        <h1>🎫 Support Portal</h1>
        <p class="sub">Sign in to view and manage your support tickets.</p>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="fg"><label>Email Address <span class="req">*</span></label><input type="email" name="email" value="<?php echo escape($_POST['email'] ?? ''); ?>" required placeholder="your@email.com"></div>
            <div class="fg"><label>Password <span class="req">*</span></label><input type="password" name="password" required></div>
            <button type="submit" class="btn btn-full">Sign In</button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:13px;color:#6b7280;">Don't have an account? <a href="/support?p=register" class="link">Create one</a></p>
    </div>
    <div class="cc" style="text-align:center;">
        <h4>📧 Email Support</h4>
        <p><a href="mailto:support@goypall.net">support@goypall.net</a></p>
    </div>
</div>

<?php elseif ($view === 'register'): ?>
<div style="max-width:420px;margin:40px auto;">
    <div class="card">
        <h1>📝 Create Account</h1>
        <p class="sub">Register to submit and track support tickets.</p>
        <form method="POST">
            <input type="hidden" name="action" value="register">
            <div class="fg"><label>Full Name <span class="req">*</span></label><input type="text" name="name" value="<?php echo escape($_POST['name'] ?? ''); ?>" required placeholder="John Doe"></div>
            <div class="fg"><label>Email Address <span class="req">*</span></label><input type="email" name="email" value="<?php echo escape($_POST['email'] ?? ''); ?>" required placeholder="your@email.com"></div>
            <div class="fg"><label>Order Number <span style="color:#9ca3af;font-size:9px;">(optional)</span></label><input type="text" name="order_number" value="<?php echo escape($_POST['order_number'] ?? ''); ?>" placeholder="ORD-XXXXXXXX"><div class="hint">If you've purchased, enter your order number for priority support.</div></div>
            <div class="fg"><label>Password <span class="req">*</span></label><input type="password" name="password" required minlength="6"></div>
            <div class="fg"><label>Confirm Password <span class="req">*</span></label><input type="password" name="password2" required minlength="6"></div>
            <button type="submit" class="btn btn-full">Create Account</button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:13px;color:#6b7280;">Already have an account? <a href="/support" class="link">Sign in</a></p>
    </div>
</div>

<?php elseif ($view === 'dashboard'): ?>
<div class="sidebar">
    <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1>🎫 My Tickets</h1>
            <a href="/support?p=new" class="btn btn-sm btn-green">+ New Ticket</a>
        </div>
        <div class="card" style="padding:16px;">
            <?php
            try { $tickets = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "tickets WHERE customer_id = ? ORDER BY updated_at DESC", [support_user()['id']]); } catch (Exception $e) { $tickets = []; }
            ?>
            <?php if (empty($tickets)): ?>
            <div class="empty">No tickets yet. <a href="/support?p=new" class="link">Create your first ticket</a></div>
            <?php else: ?>
            <div class="tk-list">
                <div class="tk-row hd"><div>Ticket</div><div>Subject</div><div>Priority</div><div>Status</div><div>Updated</div></div>
                <?php foreach ($tickets as $t): ?>
                <div class="tk-row">
                    <div class="tk-num">#<?php echo escape($t['ticket_number']); ?></div>
                    <div class="tk-subj"><a href="/support?p=ticket&id=<?php echo escape($t['ticket_number']); ?>"><?php echo escape($t['subject']); ?></a></div>
                    <div><span class="badge badge-<?php echo $t['priority']; ?>"><?php echo strtoupper($t['priority']); ?></span></div>
                    <div><span class="badge badge-<?php echo $t['status']; ?>"><?php echo strtoupper($t['status']); ?></span></div>
                    <div style="font-size:11px;color:#9ca3af;"><?php echo date('M j, g:ia', strtotime($t['updated_at'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div>
        <div class="cc"><h4>📧 Email Support</h4><p><a href="mailto:support@goypall.net">support@goypall.net</a></p></div>
        <div class="cc"><h4>⏰ Response Time</h4><p>We respond within 24 hours on business days.</p></div>
        <?php if ($whatsappUrl): ?><a href="<?php echo escape($whatsappUrl); ?>" target="_blank" class="chat-btn wa">💬 WhatsApp</a><?php endif; ?>
        <?php if ($telegramUrl): ?><a href="<?php echo escape($telegramUrl); ?>" target="_blank" class="chat-btn tg">✈️ Telegram</a><?php endif; ?>
    </div>
</div>

<?php elseif ($view === 'new_ticket'): ?>
<div style="max-width:600px;margin:0 auto;">
    <div style="margin-bottom:12px;"><a href="/support" class="link" style="font-size:13px;">← Back to tickets</a></div>
    <div class="card">
        <h1>📝 New Support Ticket</h1>
        <p class="sub">Describe your issue and we'll get back to you as soon as possible.</p>
        <form method="POST">
            <input type="hidden" name="action" value="create_ticket">
            <div class="fg"><label>Subject <span class="req">*</span></label><input type="text" name="subject" value="<?php echo escape($_POST['subject'] ?? ''); ?>" required placeholder="Brief description of your issue"></div>
            <div class="fg"><label>Priority</label><select name="priority"><option value="low">Low — General question</option><option value="medium" selected>Medium — Need help</option><option value="high">High — Something is broken</option><option value="urgent">Urgent — Site is down</option></select></div>
            <div class="fg"><label>Message <span class="req">*</span></label><textarea name="message" required placeholder="Describe your issue in detail..."><?php echo escape($_POST['message'] ?? ''); ?></textarea></div>
            <button type="submit" class="btn btn-green btn-full">Submit Ticket</button>
        </form>
    </div>
    <div class="cc" style="text-align:center;"><h4>📧 Or email us directly</h4><p><a href="mailto:support@goypall.net">support@goypall.net</a></p></div>
</div>

<?php elseif ($view === 'view_ticket'): ?>
<?php
$ticketNum = trim($_GET['id'] ?? '');
try { $ticket = $db->fetch("SELECT * FROM " . DB_PREFIX . "tickets WHERE ticket_number = ? AND customer_id = ?", [$ticketNum, support_user()['id']]); } catch (Exception $e) { $ticket = null; }
if (!$ticket): ?>
<div class="card"><div class="empty">Ticket not found.</div></div>
<?php else:
try { $replies = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC", [$ticket['id']]); } catch (Exception $e) { $replies = []; }
?>
<div style="margin-bottom:12px;"><a href="/support" class="link" style="font-size:13px;">← Back to tickets</a></div>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
        <div>
            <h1 style="font-size:18px;">#<?php echo escape($ticket['ticket_number']); ?> — <?php echo escape($ticket['subject']); ?></h1>
            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Created <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></p>
        </div>
        <div style="display:flex;gap:6px;">
            <span class="badge badge-<?php echo $ticket['priority']; ?>"><?php echo strtoupper($ticket['priority']); ?></span>
            <span class="badge badge-<?php echo $ticket['status']; ?>"><?php echo strtoupper($ticket['status']); ?></span>
        </div>
    </div>
</div>
<?php foreach ($replies as $r): ?>
<div class="msg <?php echo $r['sender'] === 'admin' ? 'msg-admin' : 'msg-cust'; ?>">
    <div class="msg-head">
        <span class="who" style="color:<?php echo $r['sender'] === 'admin' ? '#16a34a' : '#92400e'; ?>;"><?php echo $r['sender'] === 'admin' ? '🛡️ Support Team' : '👤 You'; ?></span>
        <span class="when"><?php echo date('M j, g:i A', strtotime($r['created_at'])); ?></span>
    </div>
    <div class="msg-body"><?php echo nl2br(escape($r['message'])); ?></div>
</div>
<?php endforeach; ?>
<?php if ($ticket['status'] !== 'closed'): ?>
<div class="card" style="margin-top:8px;">
    <form method="POST">
        <input type="hidden" name="action" value="reply_ticket">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
        <div class="fg"><label>Reply</label><textarea name="message" required placeholder="Type your reply..."></textarea></div>
        <button type="submit" class="btn btn-green">Send Reply</button>
    </form>
</div>
<?php else: ?>
<div class="card" style="text-align:center;color:#9ca3af;font-size:13px;">This ticket is closed. <a href="/support?p=new" class="link">Open a new ticket</a> if you need further help.</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

</div>
<div class="footer"><?php echo escape(settings('footer_text') ?: '© ' . date('Y') . ' ' . $siteName); ?></div>
</body>
</html>
