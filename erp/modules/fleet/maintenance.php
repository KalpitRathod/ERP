<?php
// Fleet – Maintenance – modules/fleet/maintenance.php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','logistics_manager']);

$page_title    = 'Fleet – Maintenance';
$active_module = 'fleet';
$active_sub    = 'maintenance';

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>
<div class="card">
    <div class="card-header"><h2>Fleet – Maintenance</h2></div>
    <div class="card-body">
        <div class="alert alert-info">
            This module is scaffolded and ready for implementation.
            Add your queries, forms, and tables here.
        </div>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
