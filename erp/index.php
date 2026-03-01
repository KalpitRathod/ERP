<?php
// /erp/index.php – Entry point
require_once __DIR__ . '/config/config.php';
require_once ERP_ROOT . '/includes/functions.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
