<?php
// ================================================================
//  Inventory – Stock
//  /erp/modules/inventory/stock.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','logistics_manager']);

$page_title    = 'Inventory – Stock';
$active_module = 'inventory';
$active_sub    = 'stock';
$pdo           = db();

// ---- Stock movement: IN / OUT ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_string($_POST['action'] ?? '');
    if ($action === 'move') {
        $item_id      = sanitize_int($_POST['item_id']      ?? 0);
        $warehouse_id = sanitize_int($_POST['warehouse_id'] ?? 0);
        $type         = in_array($_POST['type'], ['in','out','adjustment']) ? $_POST['type'] : 'in';
        $qty          = (float)($_POST['qty'] ?? 0);
        $remarks      = sanitize_string($_POST['remarks'] ?? '');

        if ($item_id && $warehouse_id && $qty > 0) {
            try {
                $pdo->beginTransaction();
                // Upsert stock record
                $pdo->prepare(
                    'INSERT INTO stock (item_id, warehouse_id, qty) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE
                     qty = qty + IF(?,?, -?))'
                )->execute([$item_id,$warehouse_id,($type==='in'?$qty:-$qty),$type==='in',1,1]);
                // Actually use a cleaner approach:
                $sign = ($type === 'out') ? -1 : 1;
                $pdo->prepare(
                    'INSERT INTO stock (item_id, warehouse_id, qty)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE qty = qty + ?'
                )->execute([$item_id, $warehouse_id, $qty * $sign, $qty * $sign]);

                $pdo->prepare(
                    'INSERT INTO stock_movements (item_id, warehouse_id, type, qty, moved_by, remarks)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$item_id,$warehouse_id,$type,$qty,current_user_id(),$remarks]);
                $pdo->commit();
                audit('inventory','stock_'.$type,$item_id,"qty:$qty,wh:$warehouse_id");
                set_flash('success', 'Stock movement recorded.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                set_flash('danger', 'Error: ' . $e->getMessage());
            }
        } else {
            set_flash('danger', 'All fields are required and quantity must be > 0.');
        }
        header('Location: ' . BASE_URL . '/modules/inventory/stock.php');
        exit;
    }
}

// ---- Data -------------------------------------------------------
$search   = sanitize_string($_GET['q'] ?? '');
$per_page = 25;
$page_no  = max(1, sanitize_int($_GET['page'] ?? 1));
$where    = $search ? "WHERE i.name LIKE ? OR i.sku LIKE ?" : "";
$params   = $search ? ["%$search%","%$search%"] : [];

$total = (int)$pdo->query("SELECT COUNT(DISTINCT i.id) FROM items i LEFT JOIN stock s ON s.item_id=i.id $where")->fetchColumn();
$p     = paginate($total, $per_page, $page_no, BASE_URL . '/modules/inventory/stock.php?q=' . urlencode($search));

$stmt = $pdo->prepare(
    "SELECT i.id,i.sku,i.name,i.unit,i.reorder_level,
            COALESCE(SUM(s.qty),0) AS total_stock,
            GROUP_CONCAT(w.name ORDER BY w.name SEPARATOR ', ') AS warehouses
     FROM items i
     LEFT JOIN stock s ON s.item_id = i.id
     LEFT JOIN warehouses w ON w.id = s.warehouse_id
     $where
     GROUP BY i.id
     ORDER BY i.name
     LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $per_page, $p['offset']]);
$stock_list = $stmt->fetchAll();

$items      = $pdo->query('SELECT id,sku,name,unit FROM items ORDER BY name')->fetchAll();
$warehouses = $pdo->query('SELECT id,name FROM warehouses ORDER BY name')->fetchAll();
$low_stock  = (int)$pdo->query('SELECT COUNT(*) FROM stock s JOIN items i ON i.id=s.item_id WHERE s.qty <= i.reorder_level')->fetchColumn();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<!-- KPI -->
<div class="stat-row">
    <div class="stat-cell"><span class="sv"><?= $total ?></span><span class="sl">Total Items</span></div>
    <div class="stat-cell"><span class="sv"><?= count($warehouses) ?></span><span class="sl">Warehouses</span></div>
    <div class="stat-cell"><span class="sv <?= $low_stock > 0 ? 'text-danger' : 'text-success' ?>"><?= $low_stock ?></span><span class="sl">Low Stock Items</span></div>
</div>

<?php if ($low_stock): ?>
<div class="alert alert-warning"><?= $low_stock ?> item(s) are at or below reorder level.</div>
<?php endif; ?>

<!-- STOCK MOVEMENT FORM -->
<div class="card">
    <div class="card-header"><h2>Record Stock Movement</h2>
        <button class="btn btn-sm btn-default" onclick="toggleSection('move-form')">Toggle</button>
    </div>
    <div class="card-body" id="move-form">
        <form method="post">
            <input type="hidden" name="action" value="move">
            <div class="row">
                <div class="col col-2">
                    <label>Item <span class="required">*</span></label>
                    <select name="item_id" required size="1">
                        <option value="">-- Select Item --</option>
                        <?php foreach ($items as $it): ?>
                            <option value="<?= $it['id'] ?>"><?= h($it['sku']) ?> – <?= h($it['name']) ?> (<?= h($it['unit']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label>Warehouse <span class="required">*</span></label>
                    <select name="warehouse_id" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= h($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label>Movement Type</label>
                    <select name="type">
                        <option value="in">Stock In</option>
                        <option value="out">Stock Out</option>
                        <option value="adjustment">Adjustment</option>
                    </select>
                </div>
                <div class="col">
                    <label>Quantity <span class="required">*</span></label>
                    <input type="number" name="qty" min="0.001" step="0.001" required>
                </div>
                <div class="col col-2">
                    <label>Remarks</label>
                    <input type="text" name="remarks">
                </div>
                <div class="col auto" style="align-self:flex-end;">
                    <input type="submit" class="btn btn-primary" value="Record">
                </div>
            </div>
        </form>
    </div>
</div>

<!-- STOCK TABLE -->
<div class="card">
    <div class="card-header">
        <h2>Current Stock (<?= $total ?> items)</h2>
        <a href="procurement.php" class="btn btn-sm btn-default">Procurement</a>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="col"><label>Search</label><input type="search" name="q" value="<?= h($search) ?>" placeholder="Name / SKU"></div>
                <div class="col auto"><input type="submit" class="btn btn-default" value="Search">
                    <?php if ($search): ?><a href="?" class="btn btn-sm">Clear</a><?php endif; ?></div>
            </form>
        </div>
        <?= render_pagination($p) ?>
        <table>
            <thead><tr><th>SKU</th><th>Item Name</th><th>Unit</th><th>Total Stock</th><th>Reorder Level</th><th>Warehouses</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($stock_list as $row): ?>
            <?php $is_low = $row['total_stock'] <= $row['reorder_level']; ?>
            <tr class="<?= $is_low ? 'warn-row' : '' ?>">
                <td><?= h($row['sku']) ?></td>
                <td><strong><?= h($row['name']) ?></strong></td>
                <td><?= h($row['unit']) ?></td>
                <td class="fw-bold <?= $is_low ? 'text-danger' : '' ?>"><?= number_format($row['total_stock'],3) ?></td>
                <td><?= number_format($row['reorder_level'],3) ?></td>
                <td><?= h($row['warehouses'] ?? '—') ?></td>
                <td><?= $is_low ? '<span class="badge badge-danger">Low Stock</span>' : '<span class="badge badge-success">OK</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= render_pagination($p) ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
