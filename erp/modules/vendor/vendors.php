<?php
// ================================================================
//  Vendor Management
//  /erp/modules/vendor/vendors.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','logistics_manager','accounts']);

$page_title    = 'Vendors';
$active_module = 'vendor';
$active_sub    = 'vendors';
$pdo           = db();

// ---- Add Vendor POST -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_vendor') {
    $name    = sanitize_string($_POST['name']     ?? '');
    $gst     = sanitize_string($_POST['gst_no']   ?? '');
    $pan     = sanitize_string($_POST['pan_no']   ?? '');
    $contact = sanitize_string($_POST['contact']  ?? '');
    $email   = sanitize_string($_POST['email']    ?? '');
    $address = sanitize_string($_POST['address']  ?? '');
    $cat     = sanitize_string($_POST['category'] ?? '');
    $terms   = sanitize_string($_POST['payment_terms'] ?? '');

    if (!$name) {
        set_flash('danger', 'Vendor name is required.');
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO vendors (name,gst_no,pan_no,contact,email,address,category,payment_terms)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$name,$gst,$pan,$contact,$email,$address,$cat,$terms]);
            audit('vendor','add_vendor');
            set_flash('success', "Vendor '$name' added.");
        } catch (Throwable $e) {
            set_flash('danger', 'Error: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/modules/vendor/vendors.php');
    exit;
}

// ---- List -------------------------------------------------------
$search = sanitize_string($_GET['q'] ?? '');
$per_page = 20; $page_no = max(1,sanitize_int($_GET['page']??1));
$where = $search ? "WHERE name LIKE ? OR gst_no LIKE ? OR category LIKE ?" : "";
$params = $search ? ["%$search%","%$search%","%$search%"] : [];

$total = (int)$pdo->query("SELECT COUNT(*) FROM vendors $where")->fetchColumn();
$p = paginate($total,$per_page,$page_no, BASE_URL.'/modules/vendor/vendors.php?q='.urlencode($search));

$stmt = $pdo->prepare("SELECT * FROM vendors $where ORDER BY name LIMIT ? OFFSET ?");
$stmt->execute([...$params,$per_page,$p['offset']]);
$vendors = $stmt->fetchAll();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<!-- ADD FORM -->
<div class="card">
    <div class="card-header"><h2>Add Vendor</h2>
        <button class="btn btn-sm btn-default" onclick="toggleSection('vnd-form')">Toggle</button>
    </div>
    <div class="card-body" id="vnd-form">
        <form method="post">
            <input type="hidden" name="action" value="add_vendor">
            <span class="section-title">Vendor Details</span>
            <div class="row">
                <div class="col col-2"><label>Vendor Name <span class="required">*</span></label><input type="text" name="name" required></div>
                <div class="col"><label>Category</label><input type="text" name="category" placeholder="Fuel / Maintenance / IT"></div>
                <div class="col"><label>GST No.</label><input type="text" name="gst_no" placeholder="29AABCU9603R1ZX"></div>
                <div class="col"><label>PAN No.</label><input type="text" name="pan_no"></div>
            </div>
            <div class="row">
                <div class="col"><label>Contact Person</label><input type="text" name="contact"></div>
                <div class="col"><label>Email</label><input type="email" name="email"></div>
                <div class="col"><label>Payment Terms</label><input type="text" name="payment_terms" placeholder="Net 30"></div>
            </div>
            <div class="row">
                <div class="col col-2"><label>Address</label><textarea name="address" rows="2"></textarea></div>
                <div class="col auto" style="align-self:flex-end;"><input type="submit" class="btn btn-primary" value="Add Vendor"></div>
            </div>
        </form>
    </div>
</div>

<!-- LIST -->
<div class="card">
    <div class="card-header"><h2>Vendor List (<?= $total ?>)</h2>
        <a href="purchase_orders.php" class="btn btn-sm btn-default">Purchase Orders</a>
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
            <thead><tr><th>Name</th><th>Category</th><th>GST No.</th><th>Contact</th><th>Email</th><th>Payment Terms</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($vendors as $v): ?>
            <tr>
                <td><strong><?= h($v['name']) ?></strong></td>
                <td><?= h($v['category'] ?? '—') ?></td>
                <td><?= h($v['gst_no'] ?? '—') ?></td>
                <td><?= h($v['contact'] ?? '—') ?></td>
                <td><?= h($v['email'] ?? '—') ?></td>
                <td><?= h($v['payment_terms'] ?? '—') ?></td>
                <td><?= str_repeat('★', (int)$v['rating']) . str_repeat('☆', 5 - (int)$v['rating']) ?></td>
                <td><?php $s=$v['status']; $cls=match($s){'active'=>'badge-success','blacklisted'=>'badge-danger',default=>'badge-secondary'};
                    echo "<span class='badge $cls'>".ucfirst($s)."</span>"; ?></td>
                <td><a href="purchase_orders.php?vendor_id=<?= $v['id'] ?>" class="btn btn-sm btn-info">POs</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= render_pagination($p) ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
