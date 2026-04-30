<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/init.php';
require_admin_login();

echo "A. Before _header.php<br>";

global $db;

// Test what _header.php does
$adminUser = current_admin();
echo "B. Admin user: " . ($adminUser['email'] ?? 'NULL') . "<br>";

$currentScript = basename($_SERVER['SCRIPT_NAME']);
echo "C. Current script: $currentScript<br>";

// Test the unread count query that _header uses
try {
    $count = $db->fetch("SELECT COUNT(*) c FROM " . DB_PREFIX . "contact_messages WHERE status = 'unread'");
    echo "D. Unread messages query works: " . ($count['c'] ?? '0') . "<br>";
} catch (Exception $e) {
    echo "D. ERROR: " . $e->getMessage() . "<br>";
}

echo "E. Now trying to include _header.php...<br>";
include __DIR__ . '/_header.php';
echo "<h1>IT WORKS!</h1>";
include __DIR__ . '/_footer.php';
