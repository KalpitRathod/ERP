<?php
// Support Tickets – modules/crm/tickets.php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','accounts','travel_coordinator']);

$page_title    = 'Support Tickets';
$active_module = 'crm';
$active_sub    = 'tickets';

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>
<div class="card">
    <div class="card-header"><h2>Support Tickets</h2></div>
    <div class="card-body">
        <div class="alert alert-info">
            This module is scaffolded and ready for implementation.
            Add your queries, forms, and tables here.
        </div>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
