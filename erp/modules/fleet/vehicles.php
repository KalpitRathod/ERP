<?php
// ================================================================
//  Fleet – Vehicles
//  /erp/modules/fleet/vehicles.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','logistics_manager']);

$page_title    = 'Fleet – Vehicles';
$active_module = 'fleet';
$active_sub    = 'vehicles';
$pdo           = db();

// ---- Add / Update Vehicle POST ---------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_string($_POST['action'] ?? '');
    if ($action === 'add_vehicle') {
        $reg_no       = sanitize_string($_POST['reg_no']      ?? '');
        $type         = sanitize_string($_POST['type']        ?? '');
        $model        = sanitize_string($_POST['model']       ?? '');
        $fuel_type    = sanitize_string($_POST['fuel_type']   ?? 'Diesel');
        $seating      = sanitize_int($_POST['seating']        ?? 4);
        $driver_id    = sanitize_int($_POST['driver_id']      ?? 0) ?: null;
        $ins_exp      = sanitize_string($_POST['insurance_exp'] ?? '') ?: null;
        $puc_exp      = sanitize_string($_POST['puc_exp']     ?? '') ?: null;
        $fitness_exp  = sanitize_string($_POST['fitness_exp'] ?? '') ?: null;
        $permit_exp   = sanitize_string($_POST['permit_exp']  ?? '') ?: null;
        $odometer     = sanitize_int($_POST['odometer_km']    ?? 0);

        if (!$reg_no || !$type) {
            set_flash('danger', 'Registration Number and Vehicle Type are required.');
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO vehicles
                     (reg_no,type,model,fuel_type,seating,driver_id,insurance_exp,puc_exp,fitness_exp,permit_exp,odometer_km)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$reg_no,$type,$model,$fuel_type,$seating,$driver_id,$ins_exp,$puc_exp,$fitness_exp,$permit_exp,$odometer]);
                audit('fleet','add_vehicle',0,"reg:$reg_no");
                set_flash('success', "Vehicle $reg_no added.");
            } catch (Throwable $e) {
                set_flash('danger', 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/modules/fleet/vehicles.php');
        exit;
    }
}

// ---- List -------------------------------------------------------
$status_filter = sanitize_string($_GET['status'] ?? 'active');
$search        = sanitize_string($_GET['q']   ?? '');
$per_page      = 20;
$page_no       = max(1, sanitize_int($_GET['page'] ?? 1));

$where_parts = ["v.status = ?"];
$params      = [$status_filter];
if ($search) { $where_parts[] = "(v.reg_no LIKE ? OR v.type LIKE ? OR v.model LIKE ?)"; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }
$where = 'WHERE ' . implode(' AND ', $where_parts);

$total = (int)$pdo->query("SELECT COUNT(*) FROM vehicles v $where")->fetchColumn();
$p     = paginate($total, $per_page, $page_no,
            BASE_URL . '/modules/fleet/vehicles.php?status=' . urlencode($status_filter) . '&q=' . urlencode($search));

$stmt = $pdo->prepare(
    "SELECT v.*, d.name AS driver_name
     FROM vehicles v
     LEFT JOIN drivers d ON d.id = v.driver_id
     $where ORDER BY v.reg_no LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $per_page, $p['offset']]);
$vehicles = $stmt->fetchAll();

$drivers = $pdo->query("SELECT id,name,licence_no FROM drivers WHERE status='active' ORDER BY name")->fetchAll();

// KPI
$total_active = (int)$pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='active'")->fetchColumn();
$expiring_soon = (int)$pdo->query(
    "SELECT COUNT(*) FROM vehicles WHERE status='active' AND (
        insurance_exp BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
        puc_exp       BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))"
)->fetchColumn();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<!-- KPI -->
<div class="stat-row">
    <div class="stat-cell"><span class="sv"><?= $total_active ?></span><span class="sl">Active Vehicles</span></div>
    <div class="stat-cell"><span class="sv <?= $expiring_soon?'text-danger':'text-success' ?>"><?= $expiring_soon ?></span><span class="sl">Docs Expiring in 30d</span></div>
    <div class="stat-cell"><span class="sv"><?= count($drivers) ?></span><span class="sl">Available Drivers</span></div>
</div>

<?php if ($expiring_soon): ?>
<div class="alert alert-warning"><?= $expiring_soon ?> vehicle(s) have documents expiring within 30 days.</div>
<?php endif; ?>

<!-- ADD VEHICLE -->
<div class="card">
    <div class="card-header"><h2>Add Vehicle</h2>
        <button class="btn btn-sm btn-default" onclick="toggleSection('veh-form')">Toggle</button>
    </div>
    <div class="card-body" id="veh-form">
        <form method="post">
            <input type="hidden" name="action" value="add_vehicle">
            <span class="section-title">Vehicle Details</span>
            <div class="row">
                <div class="col"><label>Reg. Number <span class="required">*</span></label><input type="text" name="reg_no" required placeholder="MH 01 AB 1234"></div>
                <div class="col"><label>Type <span class="required">*</span></label><input type="text" name="type" required placeholder="Innova Crysta"></div>
                <div class="col"><label>Model / Year</label><input type="text" name="model" placeholder="2022"></div>
                <div class="col"><label>Fuel</label>
                    <select name="fuel_type">
                        <?php foreach (['Diesel','Petrol','CNG','Electric','Other'] as $f): ?>
                            <option><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label>Seating</label><input type="number" name="seating" value="4" min="1"></div>
            </div>
            <span class="section-title">Documents</span>
            <div class="row">
                <div class="col"><label>Insurance Expiry</label><input type="date" name="insurance_exp"></div>
                <div class="col"><label>PUC Expiry</label><input type="date" name="puc_exp"></div>
                <div class="col"><label>Fitness Expiry</label><input type="date" name="fitness_exp"></div>
                <div class="col"><label>Permit Expiry</label><input type="date" name="permit_exp"></div>
                <div class="col"><label>Current Odometer (km)</label><input type="number" name="odometer_km" value="0" min="0"></div>
            </div>
            <div class="row">
                <div class="col">
                    <label>Assign Driver</label>
                    <select name="driver_id">
                        <option value="">-- None --</option>
                        <?php foreach ($drivers as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= h($d['name']) ?> (<?= h($d['licence_no']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col auto" style="align-self:flex-end;"><input type="submit" class="btn btn-primary" value="Add Vehicle"></div>
            </div>
        </form>
    </div>
</div>

<!-- LIST -->
<div class="card">
    <div class="card-header"><h2>Vehicle List (<?= $total ?>)</h2>
        <a href="maintenance.php" class="btn btn-sm btn-default">Maintenance Log</a>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="col"><label>Search</label><input type="search" name="q" value="<?= h($search) ?>" placeholder="Reg No / Type"></div>
                <div class="col"><label>Status</label>
                    <select name="status">
                        <?php foreach (['active','maintenance','inactive'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col auto"><input type="submit" class="btn btn-default" value="Filter"></div>
            </form>
        </div>
        <?= render_pagination($p) ?>
        <table>
            <thead><tr>
                <th>Reg No</th><th>Type / Model</th><th>Fuel</th><th>Seats</th><th>Driver</th>
                <th>Insurance Exp</th><th>PUC Exp</th><th>Odometer</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($vehicles as $v): ?>
            <tr class="<?= (days_until($v['insurance_exp']) < 30 || days_until($v['puc_exp']) < 30) ? 'warn-row' : '' ?>">
                <td><strong><?= h($v['reg_no']) ?></strong></td>
                <td><?= h($v['type']) ?> <?= h($v['model'] ?? '') ?></td>
                <td><?= h($v['fuel_type']) ?></td>
                <td><?= (int)$v['seating'] ?></td>
                <td><?= h($v['driver_name'] ?? '—') ?></td>
                <td class="<?= expiry_class($v['insurance_exp'] ?? '') ?>"><?= fmt_date($v['insurance_exp'] ?? '') ?></td>
                <td class="<?= expiry_class($v['puc_exp'] ?? '') ?>"><?= fmt_date($v['puc_exp'] ?? '') ?></td>
                <td><?= number_format($v['odometer_km']) ?> km</td>
                <td><?php $s=$v['status']; echo '<span class="badge '.match($s){'active'=>'badge-success','maintenance'=>'badge-warning',default=>'badge-secondary'}.'">'.$s.'</span>'; ?></td>
                <td class="gap-8">
                    <a href="maintenance.php?vehicle_id=<?= $v['id'] ?>" class="btn btn-sm btn-info">Maintenance</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= render_pagination($p) ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
