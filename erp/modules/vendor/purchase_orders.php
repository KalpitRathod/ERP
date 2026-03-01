<?php
// Purchase Orders – modules/vendor/purchase_orders.php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','logistics_manager','accounts']);

$page_title    = 'Purchase Orders';
$active_module = 'vendor';
$active_sub    = 'purchase_orders';

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>
<div class="card">
    <div class="card-header"><h2>Purchase Orders</h2></div>
    <div class="card-body">
        <div class="alert alert-info">
            This module is scaffolded and ready for implementation.
            Add your queries, forms, and tables here.
        </div>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
