<?php
// /erp/auth/logout.php
require_once dirname(__DIR__) . '/config/config.php';
require_once ERP_ROOT . '/includes/functions.php';
audit('auth', 'logout');
$_SESSION = [];
session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
