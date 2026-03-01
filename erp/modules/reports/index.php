<?php
// ================================================================
//  Reports – Index
//  /erp/modules/reports/index.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','accounts','hr_manager','logistics_manager']);

$page_title    = 'Reports & Analytics';
$active_module = 'reports';
$pdo           = db();

// Date range filter
$from  = sanitize_string($_GET['from'] ?? date('Y-m-01'));
$to    = sanitize_string($_GET['to']   ?? date('Y-m-t'));

// ---- Report Data -----------------------------------------------
$revenue       = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE created_at BETWEEN '$from' AND '$to 23:59:59' AND status='paid'")->fetchColumn();
$invoice_count = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE created_at BETWEEN '$from' AND '$to 23:59:59'")->fetchColumn();
$expense_total = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE exp_date BETWEEN '$from' AND '$to' AND status IN ('approved','reimbursed')")->fetchColumn();
$trip_count    = (int)$pdo->query("SELECT COUNT(*) FROM travel_bookings WHERE pickup_date BETWEEN '$from' AND '$to'")->fetchColumn();
$hr_headcount  = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
$po_total      = (float)$pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM purchase_orders WHERE po_date BETWEEN '$from' AND '$to' AND status='received'")->fetchColumn();

// Monthly invoice trend (last 6 months)
$trend = $pdo->query(
    "SELECT DATE_FORMAT(created_at,'%b %Y') AS mon, COALESCE(SUM(total_amount),0) AS amt
     FROM invoices WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND status='paid'
     GROUP BY YEAR(created_at),MONTH(created_at) ORDER BY created_at"
)->fetchAll();

// Top companies by invoice value
$top_companies = $pdo->query(
    "SELECT c.name, COALESCE(SUM(i.total_amount),0) AS total
     FROM invoices i JOIN companies c ON c.id=i.company_id
     WHERE i.status='paid' GROUP BY c.id ORDER BY total DESC LIMIT 5"
)->fetchAll();

// Expense breakdown
$exp_by_cat = $pdo->query(
    "SELECT category, COUNT(*) as cnt, COALESCE(SUM(amount),0) AS total
     FROM expenses WHERE exp_date BETWEEN '$from' AND '$to' AND status NOT IN ('draft','rejected')
     GROUP BY category ORDER BY total DESC"
)->fetchAll();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<div class="filter-bar">
    <form method="get" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
        <div class="col"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
        <div class="col"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
        <div class="col auto"><input type="submit" class="btn btn-primary" value="Run Reports"></div>
    </form>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><span class="kv"><?= fmt_money($revenue) ?></span><span class="kl">Revenue Collected</span></div>
    <div class="kpi-card"><span class="kv"><?= $invoice_count ?></span><span class="kl">Invoices Raised</span></div>
    <div class="kpi-card"><span class="kv"><?= fmt_money($expense_total) ?></span><span class="kl">Expenses Approved</span></div>
    <div class="kpi-card"><span class="kv"><?= $trip_count ?></span><span class="kl">Trips in Period</span></div>
    <div class="kpi-card"><span class="kv"><?= $hr_headcount ?></span><span class="kl">Active Employees</span></div>
    <div class="kpi-card"><span class="kv"><?= fmt_money($po_total) ?></span><span class="kl">PO Value Received</span></div>
</div>

<div class="row" style="gap:16px;align-items:flex-start;">
    <!-- Invoice Trend -->
    <div class="card" style="flex:2;">
        <div class="card-header"><h2>Revenue Trend (Last 6 Months)</h2></div>
        <div class="card-body">
            <?php if (!$trend): ?>
                <p class="text-muted">No paid invoices found.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Month</th><th>Revenue (Rs.)</th><th>Bar</th></tr></thead>
                <tbody>
                <?php $max = max(array_column($trend,'amt') ?: [1]); ?>
                <?php foreach ($trend as $r): ?>
                <tr>
                    <td><?= h($r['mon']) ?></td>
                    <td class="fw-bold"><?= number_format((float)$r['amt'],2) ?></td>
                    <td>
                        <div style="background:#4477aa;height:14px;width:<?= round(($r['amt']/$max)*200) ?>px;"></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Clients -->
    <div class="card" style="flex:1;">
        <div class="card-header"><h2>Top 5 Clients</h2></div>
        <div class="card-body">
            <?php if (!$top_companies): ?>
                <p class="text-muted">No data.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Company</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($top_companies as $i => $co): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= h($co['name']) ?></td>
                    <td class="fw-bold"><?= number_format((float)$co['total'],2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Expense Breakdown -->
<div class="card">
    <div class="card-header"><h2>Expense Breakdown by Category</h2></div>
    <div class="card-body">
        <?php if (!$exp_by_cat): ?>
            <p class="text-muted">No expenses in this period.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Category</th><th>Count</th><th>Total (Rs.)</th></tr></thead>
            <tbody>
            <?php foreach ($exp_by_cat as $e): ?>
            <tr>
                <td><?= h($e['category']) ?></td>
                <td><?= (int)$e['cnt'] ?></td>
                <td class="fw-bold"><?= number_format((float)$e['total'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr>
                <td>TOTAL</td>
                <td><?= array_sum(array_column($exp_by_cat,'cnt')) ?></td>
                <td><?= number_format(array_sum(array_column($exp_by_cat,'total')),2) ?></td>
            </tr></tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
