<?php
/**
 * Pitch Page System — Bootstrap
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Redirect to installer if not configured
if (!file_exists(__DIR__ . '/config/config.php') && is_dir(__DIR__ . '/install')) {
    header('Location: /install/');
    exit;
}

require_once __DIR__ . '/config/config.php';

// Sessions
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_name(SESSION_NAME);
session_start();

// Autoload
spl_autoload_register(function ($class) {
    $file = INCLUDES_PATH . '/' . $class . '.php';
    if (file_exists($file)) require_once $file;
});

// Globals
$db = Database::getInstance();

// === Helpers ===

function escape($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid CSRF token. Please refresh and try again.');
    }
}

function is_admin_logged_in() {
    return !empty($_SESSION['admin_user_id']);
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function current_admin() {
    global $db;
    if (!is_admin_logged_in()) return null;
    return $db->fetch("SELECT * FROM " . DB_PREFIX . "users WHERE id = ?", [$_SESSION['admin_user_id']]);
}

function settings($key = null, $default = '') {
    global $db;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $rows = $db->fetchAll("SELECT setting_key, setting_value FROM " . DB_PREFIX . "settings");
        foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
    }
    if ($key === null) return $cache;
    return $cache[$key] ?? $default;
}

function set_setting($key, $value) {
    global $db;
    $existing = $db->fetch("SELECT id FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
    if ($existing) {
        $db->query("UPDATE " . DB_PREFIX . "settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
    } else {
        $db->query("INSERT INTO " . DB_PREFIX . "settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
    }
}

function flash($key, $value = null) {
    if ($value === null) {
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    $_SESSION['flash'][$key] = $value;
}

function admin_log($action, $details = '') {
    global $db;
    if (!is_admin_logged_in()) return;
    $db->query("INSERT INTO " . DB_PREFIX . "admin_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)",
        [$_SESSION['admin_user_id'], $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function redirect($url) {
    if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
        $url = SITE_URL . $url;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Send email — tries SMTP first, then PHP mail()
 * Returns true on success, error string on failure
 */
function send_smtp_email($to, $subject, $htmlBody) {
    $host = settings('smtp_host');
    $port = (int)(settings('smtp_port') ?: 587);
    $user = settings('smtp_user');
    $pass = settings('smtp_pass');
    $encryption = settings('smtp_encryption') ?: 'tls';
    $fromEmail = settings('smtp_from_email') ?: settings('contact_email') ?: 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $fromName = settings('smtp_from_name') ?: settings('site_name') ?: 'CryptoExchange';
    
    $errors = [];
    
    // METHOD 1: SMTP if configured
    if ($host && $user && $pass) {
        $smtpResult = _send_via_smtp($host, $port, $user, $pass, $encryption, $fromEmail, $fromName, $to, $subject, $htmlBody);
        if ($smtpResult === true) return true;
        $errors[] = 'SMTP: ' . $smtpResult;
    }
    
    // METHOD 2: PHP mail() with proper headers
    $mailResult = _send_via_mail($fromEmail, $fromName, $to, $subject, $htmlBody);
    if ($mailResult === true) return true;
    $errors[] = 'mail(): ' . $mailResult;
    
    return implode(' | ', $errors);
}

function _send_via_mail($fromEmail, $fromName, $to, $subject, $htmlBody) {
    $boundary = md5(time());
    $headers = '';
    $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    $sent = @mail($to, $subject, $htmlBody, $headers, "-f" . $fromEmail);
    return $sent ? true : 'mail() returned false';
}

function _send_via_smtp($host, $port, $user, $pass, $encryption, $fromEmail, $fromName, $to, $subject, $htmlBody) {
    $timeout = 15;
    $smtp = false;
    $lastResp = '';
    
    try {
        // Connect
        if ($encryption === 'ssl') {
            $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
            $smtp = @stream_socket_client('ssl://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        } else {
            $smtp = @stream_socket_client($host . ':' . $port, $errno, $errstr, $timeout);
        }
        
        if (!$smtp) return "Connect failed: {$errstr} ({$errno})";
        stream_set_timeout($smtp, $timeout);
        
        $lastResp = _smtp_read($smtp);
        if (substr($lastResp, 0, 3) !== '220') return "Banner: {$lastResp}";
        
        // EHLO
        _smtp_cmd($smtp, "EHLO " . gethostname(), '250', $lastResp);
        
        // STARTTLS
        if ($encryption === 'tls') {
            _smtp_cmd($smtp, "STARTTLS", '220', $lastResp);
            $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
            if (!@stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                @fclose($smtp);
                return "TLS handshake failed";
            }
            _smtp_cmd($smtp, "EHLO " . gethostname(), '250', $lastResp);
        }
        
        // AUTH
        _smtp_cmd($smtp, "AUTH LOGIN", '334', $lastResp);
        _smtp_cmd($smtp, base64_encode($user), '334', $lastResp);
        _smtp_cmd($smtp, base64_encode($pass), '235', $lastResp);
        
        // MAIL FROM
        _smtp_cmd($smtp, "MAIL FROM:<{$fromEmail}>", '250', $lastResp);
        
        // RCPT TO
        _smtp_cmd($smtp, "RCPT TO:<{$to}>", '250', $lastResp);
        
        // DATA
        _smtp_cmd($smtp, "DATA", '354', $lastResp);
        
        // Message
        $msg = "From: {$fromName} <{$fromEmail}>\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: {$subject}\r\n";
        $msg .= "Date: " . date('r') . "\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "\r\n";
        $msg .= str_replace("\n.", "\n..", $htmlBody) . "\r\n";
        $msg .= ".\r\n";
        
        fwrite($smtp, $msg);
        $lastResp = _smtp_read($smtp);
        if (substr($lastResp, 0, 3) !== '250') {
            @fclose($smtp);
            return "Send rejected: {$lastResp}";
        }
        
        fwrite($smtp, "QUIT\r\n");
        @fclose($smtp);
        return true;
        
    } catch (Exception $e) {
        if ($smtp) @fclose($smtp);
        return $e->getMessage();
    }
}

function _smtp_cmd($smtp, $cmd, $expect, &$lastResp) {
    fwrite($smtp, $cmd . "\r\n");
    $lastResp = _smtp_read($smtp);
    if (substr($lastResp, 0, 3) !== $expect) {
        @fclose($smtp);
        throw new Exception("Expected {$expect}, got: {$lastResp}");
    }
}

function _smtp_read($smtp) {
    $data = '';
    while ($line = @fgets($smtp, 515)) {
        $data .= $line;
        if (substr($line, 3, 1) === ' ' || strlen($line) < 4) break;
    }
    return trim($data);
}
