<?php
require_once dirname(__DIR__) . '/init.php';
unset($_SESSION['client_id'], $_SESSION['client_name'], $_SESSION['client_email']);
header('Location: /support');
exit;
