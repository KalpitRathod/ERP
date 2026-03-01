<?php
// ================================================================
//  Travel – Bookings
//  /erp/modules/travel/bookings.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','travel_coordinator','employee']);

$page_title    = 'Travel – Bookings';
$active_module = 'travel';
$active_sub    = 'bookings';
$pdo           = db();

// ---- Add Booking POST ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_booking') {
    $ref       = 'BK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    $company   = sanitize_int($_POST['company_id']      ?? 0) ?: null;
    $pax_name  = sanitize_string($_POST['passenger_name']  ?? '');
    $pax_phone = sanitize_string($_POST['passenger_phone'] ?? '');
    $btype     = sanitize_string($_POST['booking_type']    ?? '');
    $pdate     = sanitize_string($_POST['pickup_date']     ?? '');
    $ptime     = sanitize_string($_POST['pickup_time']     ?? '');
    $from      = sanitize_string($_POST['pickup_location'] ?? '');
    $to        = sanitize_string($_POST['drop_location']   ?? '');
    $veh       = sanitize_int($_POST['vehicle_id']         ?? 0) ?: null;
    $drv       = sanitize_int($_POST['driver_id']          ?? 0) ?: null;
    $rate      = (float)($_POST['base_rate'] ?? 0);

    if (!$pax_name || !$pax_phone || !$btype || !$pdate || !$ptime || !$from || !$to) {
        set_flash('danger', 'All passenger and trip details are required.');
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO travel_bookings
                 (booking_ref,company_id,passenger_name,passenger_phone,booking_type,
                  pickup_date,pickup_time,pickup_location,drop_location,vehicle_id,driver_id,base_rate,booked_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$ref,$company,$pax_name,$pax_phone,$btype,$pdate,$ptime,$from,$to,$veh,$drv,$rate,current_user_id()]);
            audit('travel','add_booking',0,"ref:$ref");
            set_flash('success', "Booking $ref created.");
        } catch (Throwable $e) {
            set_flash('danger', 'Error: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/modules/travel/bookings.php');
    exit;
}

// ---- List -------------------------------------------------------
$date_from    = sanitize_string($_GET['from']   ?? date('Y-m-01'));
$date_to      = sanitize_string($_GET['to']     ?? date('Y-m-t'));
$status_f     = sanitize_string($_GET['status'] ?? '');
$per_page = 20; $page_no = max(1,sanitize_int($_GET['page']??1));

$where_parts = ["b.pickup_date BETWEEN ? AND ?"];
$params      = [$date_from, $date_to];
if ($status_f) { $where_parts[] = "b.status=?"; $params[]=$status_f; }
$where = 'WHERE ' . implode(' AND ', $where_parts);

$total = (int)$pdo->query("SELECT COUNT(*) FROM travel_bookings b $where")->fetchColumn();
$p     = paginate($total,$per_page,$page_no, BASE_URL.'/modules/travel/bookings.php?from='.urlencode($date_from).'&to='.urlencode($date_to).'&status='.urlencode($status_f));

$stmt = $pdo->prepare(
    "SELECT b.*,v.reg_no,d.name AS driver_name,c.name AS company_name
     FROM travel_bookings b
     LEFT JOIN vehicles v ON v.id=b.vehicle_id
     LEFT JOIN drivers  d ON d.id=b.driver_id
     LEFT JOIN companies c ON c.id=b.company_id
     $where ORDER BY b.pickup_date DESC, b.pickup_time DESC LIMIT ? OFFSET ?"
);
$stmt->execute([...$params,$per_page,$p['offset']]);
$bookings = $stmt->fetchAll();

$vehicles  = $pdo->query("SELECT id,reg_no,type FROM vehicles WHERE status='active' ORDER BY reg_no")->fetchAll();
$drivers   = $pdo->query("SELECT id,name FROM drivers WHERE status='active' ORDER BY name")->fetchAll();
$companies = $pdo->query("SELECT id,name FROM companies ORDER BY name")->fetchAll();
$today_count = (int)$pdo->query("SELECT COUNT(*) FROM travel_bookings WHERE pickup_date=CURDATE()")->fetchColumn();
$month_count = (int)$pdo->query("SELECT COUNT(*) FROM travel_bookings WHERE MONTH(pickup_date)=MONTH(CURDATE()) AND YEAR(pickup_date)=YEAR(CURDATE())")->fetchColumn();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<div class="stat-row">
    <div class="stat-cell"><span class="sv"><?= $today_count ?></span><span class="sl">Today's Trips</span></div>
    <div class="stat-cell"><span class="sv"><?= $month_count ?></span><span class="sl">This Month</span></div>
    <div class="stat-cell"><span class="sv"><?= $total ?></span><span class="sl">Filtered Records</span></div>
</div>

<!-- ADD BOOKING -->
<div class="card">
    <div class="card-header"><h2>Add Booking</h2>
        <button class="btn btn-sm btn-default" onclick="toggleSection('bk-form')">Toggle</button>
    </div>
    <div class="card-body" id="bk-form">
        <form method="post">
            <input type="hidden" name="action" value="add_booking">
            <span class="section-title">Passenger Details</span>
            <div class="row">
                <div class="col col-2"><label>Passenger Name <span class="required">*</span></label><input type="text" name="passenger_name" required></div>
                <div class="col"><label>Phone <span class="required">*</span></label><input type="tel" name="passenger_phone" required></div>
                <div class="col"><label>Company / Client</label>
                    <select name="company_id">
                        <option value="">-- Walk-in --</option>
                        <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <span class="section-title">Trip Details</span>
            <div class="row">
                <div class="col"><label>Booking Type <span class="required">*</span></label>
                    <select name="booking_type" required>
                        <option value="">-- Select --</option>
                        <?php foreach (['Full Day','Local','Airport Pickup','Airport Drop','Outstation'] as $t): ?>
                            <option><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label>Pickup Date <span class="required">*</span></label><input type="date" name="pickup_date" required></div>
                <div class="col"><label>Pickup Time <span class="required">*</span></label><input type="time" name="pickup_time" required></div>
                <div class="col"><label>Base Rate (Rs.)</label><input type="number" name="base_rate" step="0.01" min="0"></div>
            </div>
            <div class="row">
                <div class="col col-2"><label>Pickup Location <span class="required">*</span></label><input type="text" name="pickup_location" required></div>
                <div class="col col-2"><label>Drop Location <span class="required">*</span></label><input type="text" name="drop_location" required></div>
            </div>
            <div class="row">
                <div class="col"><label>Vehicle</label>
                    <select name="vehicle_id">
                        <option value="">-- TBA --</option>
                        <?php foreach ($vehicles as $v): ?><option value="<?= $v['id'] ?>"><?= h($v['reg_no']) ?> – <?= h($v['type']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label>Driver</label>
                    <select name="driver_id">
                        <option value="">-- TBA --</option>
                        <?php foreach ($drivers as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col auto" style="align-self:flex-end;"><input type="submit" class="btn btn-primary" value="Confirm Booking"></div>
            </div>
        </form>
    </div>
</div>

<!-- BOOKINGS LIST -->
<div class="card">
    <div class="card-header"><h2>Bookings (<?= $total ?> records)</h2>
        <a href="expense.php" class="btn btn-sm btn-default">Duty Slips / Invoices</a>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="col"><label>From</label><input type="date" name="from" value="<?= h($date_from) ?>"></div>
                <div class="col"><label>To</label><input type="date" name="to" value="<?= h($date_to) ?>"></div>
                <div class="col"><label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <?php foreach (['confirmed','ongoing','completed','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status_f===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col auto"><input type="submit" class="btn btn-default" value="Filter"></div>
            </form>
        </div>
        <?= render_pagination($p) ?>
        <table>
            <thead><tr><th>Ref</th><th>Passenger</th><th>Company</th><th>Date</th><th>Time</th><th>Type</th><th>Vehicle</th><th>Driver</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr>
                <td><strong><?= h($b['booking_ref']) ?></strong></td>
                <td><?= h($b['passenger_name']) ?><br><small><?= h($b['passenger_phone']) ?></small></td>
                <td><?= h($b['company_name'] ?? '—') ?></td>
                <td><?= fmt_date($b['pickup_date']) ?></td>
                <td><?= substr($b['pickup_time'],0,5) ?></td>
                <td><?= h($b['booking_type']) ?></td>
                <td><?= h($b['reg_no'] ?? '—') ?></td>
                <td><?= h($b['driver_name'] ?? '—') ?></td>
                <td><?php $s=$b['status']; $cls=match($s){'completed'=>'badge-success','cancelled'=>'badge-danger','ongoing'=>'badge-info',default=>'badge-secondary'};
                    echo "<span class='badge $cls'>".ucfirst($s)."</span>"; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= render_pagination($p) ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
