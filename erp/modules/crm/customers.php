<?php
// ================================================================
//  CRM – Customers
//  /erp/modules/crm/customers.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','accounts','travel_coordinator']);

$page_title    = 'CRM – Customers';
$active_module = 'crm';
$active_sub    = 'customers';
$pdo           = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_customer') {
    $company  = sanitize_string($_POST['company_name']    ?? '');
    $contact  = sanitize_string($_POST['contact_person']  ?? '');
    $email    = sanitize_string($_POST['email']           ?? '');
    $phone    = sanitize_string($_POST['phone']           ?? '');
    $tier     = sanitize_string($_POST['tier']            ?? 'Standard');
    if (!$company) {
        set_flash('danger', 'Company name is required.');
    } else {
        $pdo->prepare(
            'INSERT INTO customers (company_name,contact_person,email,phone,tier,assigned_to) VALUES (?,?,?,?,?,?)'
        )->execute([$company,$contact,$email,$phone,$tier,current_user_id()]);
        audit('crm','add_customer');
        set_flash('success', "Customer '$company' added.");
    }
    header('Location: ' . BASE_URL . '/modules/crm/customers.php');
    exit;
}

$search = sanitize_string($_GET['q'] ?? '');
$per_page = 20; $page_no = max(1,sanitize_int($_GET['page']??1));
$where  = $search ? "WHERE company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?" : "";
$params = $search ? ["%$search%","%$search%","%$search%"] : [];
$total  = (int)$pdo->query("SELECT COUNT(*) FROM customers $where")->fetchColumn();
$p      = paginate($total,$per_page,$page_no, BASE_URL.'/modules/crm/customers.php?q='.urlencode($search));
$stmt   = $pdo->prepare("SELECT c.*,u.name AS assigned_name FROM customers c LEFT JOIN users u ON u.id=c.assigned_to $where ORDER BY company_name LIMIT ? OFFSET ?");
$stmt->execute([...$params,$per_page,$p['offset']]);
$customers = $stmt->fetchAll();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>
<div class="card">
    <div class="card-header"><h2>Add Customer</h2>
        <button class="btn btn-sm btn-default" onclick="toggleSection('cust-form')">Toggle</button>
    </div>
    <div class="card-body" id="cust-form">
        <form method="post"><input type="hidden" name="action" value="add_customer">
            <div class="row">
                <div class="col col-2"><label>Company Name <span class="required">*</span></label><input type="text" name="company_name" required></div>
                <div class="col"><label>Contact Person</label><input type="text" name="contact_person"></div>
                <div class="col"><label>Email</label><input type="email" name="email"></div>
                <div class="col"><label>Phone</label><input type="tel" name="phone"></div>
                <div class="col"><label>Tier</label>
                    <select name="tier">
                        <?php foreach(['Standard','Silver','Gold','Platinum'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col auto" style="align-self:flex-end;"><input type="submit" class="btn btn-primary" value="Add Customer"></div>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>Customers (<?= $total ?>)</h2>
        <a href="tickets.php" class="btn btn-sm btn-default">Support Tickets</a>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="col"><label>Search</label><input type="search" name="q" value="<?= h($search) ?>"></div>
                <div class="col auto"><input type="submit" class="btn btn-default" value="Search">
                    <?php if($search): ?><a href="?" class="btn btn-sm">Clear</a><?php endif; ?></div>
            </form>
        </div>
        <?= render_pagination($p) ?>
        <table>
            <thead><tr><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th>Tier</th><th>Assigned To</th><th>Tickets</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
                <td><strong><?= h($c['company_name']) ?></strong></td>
                <td><?= h($c['contact_person'] ?? '—') ?></td>
                <td><?= h($c['email'] ?? '—') ?></td>
                <td><?= h($c['phone'] ?? '—') ?></td>
                <td><?php $t=$c['tier']; $cls=match($t){'Platinum'=>'badge-info','Gold'=>'badge-warning','Silver'=>'badge-secondary',default=>''};
                    echo "<span class='badge $cls'>$t</span>"; ?></td>
                <td><?= h($c['assigned_name'] ?? '—') ?></td>
                <td><a href="tickets.php?customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-info">Tickets</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= render_pagination($p) ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
