<?php
// ================================================================
//  ERP – Unified Dashboard
//  /erp/dashboard/index.php
// ================================================================
require_once dirname(__DIR__) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_login();

$page_title    = 'Dashboard';
$active_module = 'dashboard';
$role          = current_role();
$pdo           = db();

// ---- KPIs (role-aware) ----------------------------------------

// Always visible
$total_employees = $pdo->query('SELECT COUNT(*) FROM employees WHERE status="active"')->fetchColumn();
$total_vehicles  = $pdo->query('SELECT COUNT(*) FROM vehicles WHERE status="active"')->fetchColumn();
$open_tickets    = $pdo->query('SELECT COUNT(*) FROM support_tickets WHERE status IN ("open","in_progress")')->fetchColumn();
$pending_pos     = $pdo->query('SELECT COUNT(*) FROM purchase_orders WHERE status IN ("draft","sent")')->fetchColumn();

// Travel KPIs
$today_bookings  = $pdo->query('SELECT COUNT(*) FROM travel_bookings WHERE pickup_date = CURDATE()')->fetchColumn();
$pending_invoices = $pdo->query('SELECT COUNT(*) FROM invoices WHERE status IN ("draft","sent")')->fetchColumn();

// Inventory
$low_stock_count = $pdo->query(
    'SELECT COUNT(*) FROM stock s JOIN items i ON i.id = s.item_id WHERE s.qty <= i.reorder_level'
)->fetchColumn();

// Expenses
$pending_expense_approvals = $pdo->query(
    'SELECT COUNT(*) FROM expenses WHERE status = "submitted"'
)->fetchColumn();

// Expiry warnings (vehicles documents)
$expiring_vehicles = $pdo->query(
    'SELECT reg_no, insurance_exp, puc_exp FROM vehicles
     WHERE status = "active" AND (
       insurance_exp BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
       puc_exp       BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ) ORDER BY insurance_exp LIMIT 5'
)->fetchAll();

// Recent bookings
$recent_bookings = $pdo->query(
    'SELECT b.booking_ref, b.passenger_name, b.pickup_date, b.pickup_time,
            b.booking_type, b.status, v.reg_no
     FROM travel_bookings b
     LEFT JOIN vehicles v ON v.id = b.vehicle_id
     ORDER BY b.created_at DESC LIMIT 8'
)->fetchAll();

// Module quick links (role filtered in nav, visible on dashboard always)
include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<!-- KPI GRID -->
<div class="kpi-grid">
    <div class="kpi-card">
        <span class="kv"><?= (int)$total_employees ?></span>
        <span class="kl">Active Employees</span>
    </div>
    <div class="kpi-card">
        <span class="kv"><?= (int)$total_vehicles ?></span>
        <span class="kl">Active Vehicles</span>
    </div>
    <div class="kpi-card">
        <span class="kv"><?= (int)$today_bookings ?></span>
        <span class="kl">Today's Bookings</span>
    </div>
    <div class="kpi-card <?= $low_stock_count > 0 ? 'red' : 'green' ?>">
        <span class="kv"><?= (int)$low_stock_count ?></span>
        <span class="kl">Low Stock Items</span>
    </div>
    <div class="kpi-card <?= $pending_invoices > 0 ? 'yellow' : '' ?>">
        <span class="kv"><?= (int)$pending_invoices ?></span>
        <span class="kl">Pending Invoices</span>
    </div>
    <div class="kpi-card <?= $pending_expense_approvals > 0 ? 'yellow' : '' ?>">
        <span class="kv"><?= (int)$pending_expense_approvals ?></span>
        <span class="kl">Expense Approvals</span>
    </div>
    <div class="kpi-card <?= $open_tickets > 0 ? 'yellow' : '' ?>">
        <span class="kv"><?= (int)$open_tickets ?></span>
        <span class="kl">Open Tickets</span>
    </div>
    <div class="kpi-card <?= $pending_pos > 0 ? 'yellow' : '' ?>">
        <span class="kv"><?= (int)$pending_pos ?></span>
        <span class="kl">Open Purchase Orders</span>
    </div>
</div>

<!-- ALERTS ROW -->
<?php if ($expiring_vehicles): ?>
<div class="notice-row">
    <div class="notice-card warn">
        <div class="nc-title">Vehicle Document Expiry – Action Required</div>
        <div class="nc-msg">
            <?php foreach ($expiring_vehicles as $v): ?>
                <strong><?= h($v['reg_no']) ?></strong> –
                Insurance: <span class="exp-warn"><?= fmt_date($v['insurance_exp']) ?></span>,
                PUC: <span class="exp-warn"><?= fmt_date($v['puc_exp']) ?></span><br>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODULE QUICK LINKS -->
<div class="card">
    <div class="card-header"><h2>Module Quick Access</h2></div>
    <div class="card-body">
        <div class="module-grid">
            <?php if (in_roles(['super_admin','hr_manager'])): ?>
            <a href="<?= BASE_URL ?>/modules/hr/employees.php" class="module-tile">
                <span class="mt-label">HR & Payroll</span>
                <span class="mt-sub">Employees | Attendance | Payroll</span>
            </a>
            <?php endif; ?>
            <?php if (in_roles(['super_admin','logistics_manager'])): ?>
            <a href="<?= BASE_URL ?>/modules/inventory/stock.php" class="module-tile">
                <span class="mt-label">Inventory</span>
                <span class="mt-sub">Stock | Procurement | Warehouses</span>
            </a>
            <a href="<?= BASE_URL ?>/modules/fleet/vehicles.php" class="module-tile green">
                <span class="mt-label">Fleet Management</span>
                <span class="mt-sub">Vehicles | Drivers | Maintenance</span>
            </a>
            <?php endif; ?>
            <?php if (in_roles(['super_admin','travel_coordinator','employee'])): ?>
            <a href="<?= BASE_URL ?>/modules/travel/bookings.php" class="module-tile">
                <span class="mt-label">Travel</span>
                <span class="mt-sub">Bookings | Duty Slips | Invoices</span>
            </a>
            <?php endif; ?>
            <?php if (in_roles(['super_admin','accounts','employee'])): ?>
            <a href="<?= BASE_URL ?>/modules/expense/expenses.php" class="module-tile yellow">
                <span class="mt-label">Expenses</span>
                <span class="mt-sub">My Expenses | Approvals | Reports</span>
            </a>
            <?php endif; ?>
            <?php if (in_roles(['super_admin','logistics_manager','accounts'])): ?>
            <a href="<?= BASE_URL ?>/modules/vendor/vendors.php" class="module-tile">
                <span class="mt-label">Vendors</span>
                <span class="mt-sub">Suppliers | Purchase Orders</span>
            </a>
            <?php endif; ?>
            <?php if (in_roles(['super_admin','accounts','travel_coordinator'])): ?>
            <a href="<?= BASE_URL ?>/modules/crm/customers.php" class="module-tile">
                <span class="mt-label">CRM</span>
                <span class="mt-sub">Customers | Support Tickets</span>
            </a>
            <?php endif; ?>
            <?php if (in_roles(['super_admin','accounts','hr_manager','logistics_manager'])): ?>
            <a href="<?= BASE_URL ?>/modules/reports/index.php" class="module-tile">
                <span class="mt-label">Reports</span>
                <span class="mt-sub">Financial | HR | Logistics</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- RECENT BOOKINGS -->
<div class="card">
    <div class="card-header">
        <h2>Recent Bookings</h2>
        <a href="<?= BASE_URL ?>/modules/travel/bookings.php" class="btn btn-sm btn-default">View All</a>
    </div>
    <div class="card-body">
        <?php if (!$recent_bookings): ?>
            <p class="text-muted">No bookings found.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Ref</th><th>Passenger</th><th>Date</th><th>Time</th><th>Type</th><th>Vehicle</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent_bookings as $b): ?>
                <tr>
                    <td><strong><?= h($b['booking_ref']) ?></strong></td>
                    <td><?= h($b['passenger_name']) ?></td>
                    <td><?= fmt_date($b['pickup_date']) ?></td>
                    <td><?= h(substr($b['pickup_time'],0,5)) ?></td>
                    <td><?= h($b['booking_type']) ?></td>
                    <td><?= h($b['reg_no'] ?? '—') ?></td>
                    <td>
                        <?php $s = $b['status'];
                        $cls = match($s){
                            'completed'  => 'badge-success',
                            'cancelled'  => 'badge-danger',
                            'ongoing'    => 'badge-info',
                            default      => 'badge-secondary'
                        }; ?>
                        <span class="badge <?= $cls ?>"><?= h(ucfirst($s)) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
