<?php
// HR – Payroll – modules/hr/payroll.php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','hr_manager']);

$page_title    = 'HR – Payroll';
$active_module = 'hr';
$active_sub    = 'payroll';

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>
<div class="card">
    <div class="card-header"><h2>HR – Payroll</h2></div>
    <div class="card-body">
        <div class="alert alert-info">
            This module is scaffolded and ready for implementation.
            Add your queries, forms, and tables here.
        </div>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
