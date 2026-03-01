<?php
// ================================================================
//  Admin – Users & Roles
//  /erp/modules/admin/users.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin']);

$page_title    = 'Admin – Users & Roles';
$active_module = 'admin';
$active_sub    = 'users';
$pdo           = db();

// ---- Add User POST ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    $name    = sanitize_string($_POST['name']     ?? '');
    $email   = sanitize_string($_POST['email']    ?? '');
    $role_id = sanitize_int($_POST['role_id']     ?? 0);
    $dept_id = sanitize_int($_POST['department_id'] ?? 0) ?: null;
    $pass    = trim($_POST['password'] ?? 'Erp@12345');

    if (!$name || !$email || !$role_id) {
        set_flash('danger', 'Name, email and role are required.');
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO users (name,email,password_hash,role_id,department_id,status)
                 VALUES (?,?,?,?,?,"active")'
            )->execute([$name,$email,password_hash($pass,PASSWORD_BCRYPT),$role_id,$dept_id]);
            audit('admin','add_user',0,"$name <$email>");
            set_flash('success',"User '$name' created. Password: $pass");
        } catch (Throwable $e) {
            set_flash('danger','Error: '.$e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/modules/admin/users.php');
    exit;
}

// ---- Toggle Status POST ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    $uid    = sanitize_int($_POST['user_id'] ?? 0);
    $status = sanitize_string($_POST['status'] ?? '');
    if ($uid && in_array($status,['active','inactive','suspended'],true)) {
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status,$uid]);
        audit('admin','toggle_user_status',$uid,"status:$status");
        set_flash('success','User status updated.');
    }
    header('Location: ' . BASE_URL . '/modules/admin/users.php');
    exit;
}

$users       = $pdo->query(
    'SELECT u.*,r.name AS role_name,r.label AS role_label,d.name AS dept_name
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN departments d ON d.id=u.department_id
     ORDER BY u.status,u.name'
)->fetchAll();
$roles       = $pdo->query('SELECT id,name,label FROM roles ORDER BY label')->fetchAll();
$departments = $pdo->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<div class="notice-row">
    <div class="notice-card info">
        <div class="nc-title">Quick Links</div>
        <div class="nc-msg">
            <a href="module_manager.php" class="btn btn-sm btn-primary">Module Manager</a>
            <a href="custom_fields.php" class="btn btn-sm btn-default" style="margin-left:6px;">Custom Fields</a>
            <a href="workflow_rules.php" class="btn btn-sm btn-default" style="margin-left:6px;">Workflow Rules</a>
        </div>
    </div>
</div>

<!-- ADD USER -->
<div class="card">
    <div class="card-header"><h2>Add User</h2>
        <button class="btn btn-sm btn-default" onclick="toggleSection('user-form')">Toggle</button>
    </div>
    <div class="card-body" id="user-form">
        <form method="post"><input type="hidden" name="action" value="add_user">
            <div class="row">
                <div class="col col-2"><label>Full Name <span class="required">*</span></label><input type="text" name="name" required></div>
                <div class="col col-2"><label>Email <span class="required">*</span></label><input type="email" name="email" required></div>
            </div>
            <div class="row">
                <div class="col"><label>Role <span class="required">*</span></label>
                    <select name="role_id" required>
                        <option value="">-- Select Role --</option>
                        <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= h($r['label']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label>Department</label>
                    <select name="department_id">
                        <option value="">-- None --</option>
                        <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label>Password</label><input type="text" name="password" value="Erp@12345"></div>
                <div class="col auto" style="align-self:flex-end;"><input type="submit" class="btn btn-primary" value="Create User"></div>
            </div>
        </form>
    </div>
</div>

<!-- USER LIST -->
<div class="card">
    <div class="card-header"><h2>All Users (<?= count($users) ?>)</h2></div>
    <div class="card-body">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><strong><?= h($u['name']) ?></strong><?= $u['emp_code'] ? '<br><small>'.h($u['emp_code']).'</small>' : '' ?></td>
                <td><?= h($u['email']) ?></td>
                <td><?= h($u['role_label']) ?></td>
                <td><?= h($u['dept_name'] ?? '—') ?></td>
                <td><?= fmt_datetime($u['last_login'] ?? '') ?: '—' ?></td>
                <td><?php $s=$u['status']; $cls=match($s){'active'=>'badge-success','suspended'=>'badge-danger',default=>'badge-secondary'};
                    echo "<span class='badge $cls'>".ucfirst($s)."</span>"; ?></td>
                <td>
                    <?php if ((int)$u['id'] !== current_user_id()): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <?php if ($u['status']==='active'): ?>
                            <input type="hidden" name="status" value="suspended">
                            <button class="btn btn-sm btn-danger">Suspend</button>
                        <?php else: ?>
                            <input type="hidden" name="status" value="active">
                            <button class="btn btn-sm btn-success">Activate</button>
                        <?php endif; ?>
                    </form>
                    <?php else: ?>
                        <span class="text-muted">You</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
