<?php
// /erp/includes/nav.php
// Set $active_module and $active_sub before including to highlight nav items
// e.g. $active_module = 'hr'; $active_sub = 'employees';
$active_module = $active_module ?? '';
$active_sub    = $active_sub    ?? '';
$role          = current_role();

// Helper: return 'active' class if matches
function nav_active(string $module, string $check): string {
    return $module === $check ? ' class="active"' : '';
}
function sub_active(string $sub, string $check): string {
    return $sub === $check ? ' class="active"' : '';
}
?>
<nav>
<ul>
    <li<?= nav_active($active_module, 'dashboard') ?>>
        <a href="<?= BASE_URL ?>/dashboard/index.php">Dashboard</a>
    </li>

    <?php if (in_roles(['super_admin','hr_manager'])): ?>
    <li<?= nav_active($active_module, 'hr') ?>>
        <a href="<?= BASE_URL ?>/modules/hr/employees.php">HR &amp; Payroll &#9662;</a>
        <ul class="dropdown">
            <li<?= sub_active($active_sub,'employees') ?>><a href="<?= BASE_URL ?>/modules/hr/employees.php">Employees</a></li>
            <li<?= sub_active($active_sub,'attendance') ?>><a href="<?= BASE_URL ?>/modules/hr/attendance.php">Attendance</a></li>
            <li<?= sub_active($active_sub,'payroll') ?>><a href="<?= BASE_URL ?>/modules/hr/payroll.php">Payroll</a></li>
        </ul>
    </li>
    <?php endif; ?>

    <?php if (in_roles(['super_admin','logistics_manager'])): ?>
    <li<?= nav_active($active_module, 'inventory') ?>>
        <a href="<?= BASE_URL ?>/modules/inventory/stock.php">Inventory &#9662;</a>
        <ul class="dropdown">
            <li<?= sub_active($active_sub,'stock') ?>><a href="<?= BASE_URL ?>/modules/inventory/stock.php">Stock</a></li>
            <li<?= sub_active($active_sub,'procurement') ?>><a href="<?= BASE_URL ?>/modules/inventory/procurement.php">Procurement</a></li>
        </ul>
    </li>

    <li<?= nav_active($active_module, 'fleet') ?>>
        <a href="<?= BASE_URL ?>/modules/fleet/vehicles.php">Fleet &#9662;</a>
        <ul class="dropdown">
            <li<?= sub_active($active_sub,'vehicles') ?>><a href="<?= BASE_URL ?>/modules/fleet/vehicles.php">Vehicles</a></li>
            <li<?= sub_active($active_sub,'maintenance') ?>><a href="<?= BASE_URL ?>/modules/fleet/maintenance.php">Maintenance</a></li>
        </ul>
    </li>
    <?php endif; ?>

    <?php if (in_roles(['super_admin','travel_coordinator','employee'])): ?>
    <li<?= nav_active($active_module, 'travel') ?>>
        <a href="<?= BASE_URL ?>/modules/travel/bookings.php">Travel &#9662;</a>
        <ul class="dropdown">
            <li<?= sub_active($active_sub,'bookings') ?>><a href="<?= BASE_URL ?>/modules/travel/bookings.php">Bookings</a></li>
            <li<?= sub_active($active_sub,'expense') ?>><a href="<?= BASE_URL ?>/modules/travel/expense.php">Duty Slips &amp; Invoices</a></li>
        </ul>
    </li>
    <?php endif; ?>

    <?php if (in_roles(['super_admin','accounts','employee'])): ?>
    <li<?= nav_active($active_module, 'expenses') ?>>
        <a href="<?= BASE_URL ?>/modules/expense/expenses.php">Expenses &#9662;</a>
        <ul class="dropdown">
            <li<?= sub_active($active_sub,'my_expenses') ?>><a href="<?= BASE_URL ?>/modules/expense/expenses.php">My Expenses</a></li>
            <li<?= sub_active($active_sub,'approvals') ?>><a href="<?= BASE_URL ?>/modules/expense/approvals.php">Approvals</a></li>
        </ul>
    </li>
    <?php endif; ?>

    <?php if (in_roles(['super_admin','logistics_manager','accounts'])): ?>
    <li<?= nav_active($active_module, 'vendor') ?>>
        <a href="<?= BASE_URL ?>/modules/vendor/vendors.php">Vendors &#9662;</a>
        <ul class="dropdown">
            <li<?= sub_active($active_sub,'vendors') ?>><a href="<?= BASE_URL ?>/modules/vendor/vendors.php">Vendors</a></li>
            <li<?= sub_active($active_sub,'purchase_orders') ?>><a href="<?= BASE_URL ?>/modules/vendor/purchase_orders.php">Purchase Orders</a></li>
        </ul>
    </li>
    <?php endif; ?>

    <?php if (in_roles(['super_admin','accounts','travel_coordinator'])): ?>
    <li<?= nav_active($active_module, 'crm') ?>>
        <a href="<?= BASE_URL ?>/modules/crm/customers.php">CRM &#9662;</a>
        <ul class="dropdown">
            <li<?= sub_active($active_sub,'customers') ?>><a href="<?= BASE_URL ?>/modules/crm/customers.php">Customers</a></li>
            <li<?= sub_active($active_sub,'tickets') ?>><a href="<?= BASE_URL ?>/modules/crm/tickets.php">Support Tickets</a></li>
        </ul>
    </li>
    <?php endif; ?>

    <?php if (in_roles(['super_admin','accounts','hr_manager','logistics_manager'])): ?>
    <li<?= nav_active($active_module, 'reports') ?>>
        <a href="<?= BASE_URL ?>/modules/reports/index.php">Reports</a>
    </li>
    <?php endif; ?>

    <?php if (in_roles(['super_admin'])): ?>
    <li<?= nav_active($active_module, 'admin') ?>>
        <a href="<?= BASE_URL ?>/modules/admin/users.php">Admin &#9662;</a>
        <ul class="dropdown">
            <li<?= sub_active($active_sub,'users') ?>><a href="<?= BASE_URL ?>/modules/admin/users.php">Users &amp; Roles</a></li>
            <li><a href="<?= BASE_URL ?>/modules/admin/settings.php">Settings</a></li>
        </ul>
    </li>
    <?php endif; ?>
</ul>
</nav>
