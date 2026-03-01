<?php
// Expense Approvals – modules/expense/approvals.php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','accounts','hr_manager']);

$page_title    = 'Expense Approvals';
$active_module = 'expenses';
$active_sub    = 'approvals';

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>
<div class="card">
    <div class="card-header"><h2>Expense Approvals</h2></div>
    <div class="card-body">
        <div class="alert alert-info">
            This module is scaffolded and ready for implementation.
            Add your queries, forms, and tables here.
        </div>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
