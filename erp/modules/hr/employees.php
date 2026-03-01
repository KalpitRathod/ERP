<?php
// ================================================================
//  HR Module – Employees
//  /erp/modules/hr/employees.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','hr_manager']);

$page_title    = 'Employees';
$active_module = 'hr';
$active_sub    = 'employees';
$pdo           = db();

// ---- Add Employee POST -----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name        = sanitize_string($_POST['name']        ?? '');
    $email       = sanitize_string($_POST['email']       ?? '');
    $emp_code    = sanitize_string($_POST['emp_code']    ?? '');
    $designation = sanitize_string($_POST['designation'] ?? '');
    $dept_id     = sanitize_int($_POST['department_id']  ?? 0);
    $doj         = sanitize_string($_POST['doj']         ?? '');
    $phone       = sanitize_string($_POST['phone']       ?? '');
    $basic       = (float)($_POST['salary_basic']        ?? 0);

    if (!$name || !$email || !$doj) {
        set_flash('danger', 'Name, Email, and Date of Joining are required.');
    } else {
        try {
            $pdo->beginTransaction();
            // Create user account (default pass = Emp@12345)
            $hash = password_hash('Emp@12345', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (emp_code,name,email,password_hash,role_id,department_id,phone)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->execute([$emp_code,$name,$email,$hash,6,$dept_id ?: null,$phone]);
            $uid = (int)$pdo->lastInsertId();

            $stmt2 = $pdo->prepare(
                'INSERT INTO employees (user_id,designation,doj,salary_basic) VALUES (?,?,?,?)'
            );
            $stmt2->execute([$uid,$designation,$doj,$basic]);
            $pdo->commit();
            audit('hr','add_employee',$uid,"code:$emp_code");
            set_flash('success', 'Employee added. Default password: Emp@12345');
        } catch (Throwable $e) {
            $pdo->rollBack();
            set_flash('danger', 'Error: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/modules/hr/employees.php');
    exit;
}

// ---- List ---------------------------------------------------
$search   = sanitize_string($_GET['q']  ?? '');
$per_page = 20;
$page_no  = max(1, sanitize_int($_GET['page'] ?? 1));
$where    = $search ? "AND (u.name LIKE ? OR u.email LIKE ? OR u.emp_code LIKE ?)" : "";
$params   = $search ? ["%$search%","%$search%","%$search%"] : [];

$total = (int)$pdo->query(
    "SELECT COUNT(*) FROM employees e JOIN users u ON u.id = e.user_id WHERE e.status='active' $where",
    $params ? PDO::FETCH_NUM : null
)->fetchColumn();

$p = paginate($total, $per_page, $page_no, BASE_URL . '/modules/hr/employees.php?q=' . urlencode($search));

$stmt = $pdo->prepare(
    "SELECT u.emp_code, u.name, u.email, u.phone, e.id, e.designation, e.doj, e.salary_basic, e.status,
            d.name AS dept
     FROM employees e
     JOIN users u ON u.id = e.user_id
     LEFT JOIN departments d ON d.id = u.department_id
     WHERE e.status = 'active' $where
     ORDER BY u.name
     LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $per_page, $p['offset']]);
$employees = $stmt->fetchAll();

$departments = $pdo->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<!-- STAT ROW -->
<div class="stat-row">
    <div class="stat-cell">
        <span class="sv"><?= $total ?></span>
        <span class="sl">Active Employees</span>
    </div>
    <div class="stat-cell">
        <span class="sv"><?= count($departments) ?></span>
        <span class="sl">Departments</span>
    </div>
</div>

<!-- ADD FORM -->
<div class="card">
    <div class="card-header">
        <h2>Add New Employee</h2>
        <button class="btn btn-sm btn-default" onclick="toggleSection('add-form')">Toggle Form</button>
    </div>
    <div class="card-body" id="add-form">
        <form method="post" action="">
            <input type="hidden" name="action" value="add">
            <span class="section-title">Personal Details</span>
            <div class="row">
                <div class="col"><label>Employee Code</label><input type="text" name="emp_code" placeholder="EMP010"></div>
                <div class="col col-2"><label>Full Name <span class="required">*</span></label><input type="text" name="name" required></div>
                <div class="col col-2"><label>Email <span class="required">*</span></label><input type="email" name="email" required></div>
                <div class="col"><label>Phone</label><input type="tel" name="phone"></div>
            </div>
            <span class="section-title">Job Details</span>
            <div class="row">
                <div class="col"><label>Designation <span class="required">*</span></label><input type="text" name="designation" required></div>
                <div class="col"><label>Department</label>
                    <select name="department_id">
                        <option value="">-- Select --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label>Date of Joining <span class="required">*</span></label><input type="date" name="doj" required></div>
                <div class="col"><label>Basic Salary (Rs.)</label><input type="number" name="salary_basic" step="0.01" min="0"></div>
            </div>
            <div class="row">
                <div class="col auto">
                    <input type="submit" class="btn btn-primary" value="Add Employee">
                </div>
            </div>
        </form>
    </div>
</div>

<!-- LIST -->
<div class="card">
    <div class="card-header">
        <h2>Employee List (<?= $total ?> records)</h2>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="col"><label>Search</label>
                    <input type="search" name="q" value="<?= h($search) ?>" placeholder="Name / Email / Code">
                </div>
                <div class="col auto">
                    <input type="submit" class="btn btn-default" value="Search">
                    <?php if ($search): ?><a href="?" class="btn btn-sm btn-default">Clear</a><?php endif; ?>
                </div>
            </form>
        </div>
        <?php if (!$employees): ?>
            <div class="alert alert-info">No employees found.</div>
        <?php else: ?>
        <?= render_pagination($p) ?>
        <table>
            <thead><tr>
                <th>Code</th><th>Name</th><th>Designation</th><th>Department</th>
                <th>Email</th><th>Phone</th><th>DOJ</th><th>Basic (Rs.)</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($employees as $e): ?>
            <tr>
                <td><?= h($e['emp_code'] ?? '—') ?></td>
                <td><strong><?= h($e['name']) ?></strong></td>
                <td><?= h($e['designation']) ?></td>
                <td><?= h($e['dept'] ?? '—') ?></td>
                <td><?= h($e['email']) ?></td>
                <td><?= h($e['phone'] ?? '—') ?></td>
                <td><?= fmt_date($e['doj']) ?></td>
                <td class="text-right"><?= number_format($e['salary_basic'],2) ?></td>
                <td class="gap-8">
                    <a href="employee_view.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-info">View</a>
                    <a href="attendance.php?emp_id=<?= $e['id'] ?>" class="btn btn-sm btn-default">Attendance</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= render_pagination($p) ?>
        <?php endif; ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
