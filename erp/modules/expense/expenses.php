<?php
// ================================================================
//  Expense – My Expenses & Approval Queue
//  /erp/modules/expense/expenses.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin','accounts','employee','hr_manager']);

$page_title    = 'Expense Management';
$active_module = 'expenses';
$active_sub    = 'my_expenses';
$pdo           = db();
$is_approver   = in_roles(['super_admin','accounts','hr_manager']);

// ---- Submit Expense POST ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_string($_POST['action'] ?? '');

    if ($action === 'add_expense') {
        // Get employee id for current user
        $emp = $pdo->prepare('SELECT id FROM employees WHERE user_id = ?');
        $emp->execute([current_user_id()]);
        $emp_row = $emp->fetch();

        if (!$emp_row) {
            set_flash('danger', 'Only employees can submit expenses.');
        } else {
            $cat    = sanitize_string($_POST['category'] ?? 'Other');
            $desc   = sanitize_string($_POST['description'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $date   = sanitize_string($_POST['exp_date'] ?? '');
            $mode   = sanitize_string($_POST['payment_mode'] ?? 'Cash');
            $ref    = sanitize_string($_POST['project_ref'] ?? '');

            if (!$amount || !$date) {
                set_flash('danger', 'Amount and date are required.');
            } else {
                $pdo->prepare(
                    'INSERT INTO expenses (emp_id,category,description,amount,exp_date,payment_mode,project_ref,status,submitted_at)
                     VALUES (?,?,?,?,?,?,?,"submitted",NOW())'
                )->execute([$emp_row['id'],$cat,$desc,$amount,$date,$mode,$ref]);
                audit('expense','submit',0,"amt:$amount");
                set_flash('success', 'Expense submitted for approval.');
            }
        }
        header('Location: ' . BASE_URL . '/modules/expense/expenses.php');
        exit;
    }

    if ($action === 'approve' && $is_approver) {
        $exp_id = sanitize_int($_POST['expense_id'] ?? 0);
        $act    = in_array($_POST['act'],['approved','rejected']) ? $_POST['act'] : 'approved';
        $remark = sanitize_string($_POST['remark'] ?? '');

        $pdo->prepare(
            'INSERT INTO expense_approvals (expense_id, approver_id, action, remark)
             VALUES (?,?,?,?)'
        )->execute([$exp_id,current_user_id(),$act,$remark]);
        $pdo->prepare('UPDATE expenses SET status=? WHERE id=?')->execute([$act,$exp_id]);
        audit('expense','approval',$exp_id,"action:$act");
        set_flash('success', 'Expense ' . $act . '.');
        header('Location: ' . BASE_URL . '/modules/expense/expenses.php');
        exit;
    }
}

// ---- My Expenses -----------------------------------------------
$uid = current_user_id();
$emp_stmt = $pdo->prepare('SELECT id FROM employees WHERE user_id = ?');
$emp_stmt->execute([$uid]);
$emp_me = $emp_stmt->fetchColumn();

$my_expenses = $emp_me ? $pdo->prepare(
    'SELECT * FROM expenses WHERE emp_id=? ORDER BY exp_date DESC LIMIT 30'
) : null;
if ($my_expenses) { $my_expenses->execute([$emp_me]); $my_expenses = $my_expenses->fetchAll(); } else { $my_expenses = []; }

// ---- Pending Approvals (approver only) -------------------------
$pending = [];
if ($is_approver) {
    $pending = $pdo->query(
        'SELECT e.*, u.name AS emp_name, u.emp_code
         FROM expenses e
         JOIN employees emp ON emp.id = e.emp_id
         JOIN users u ON u.id = emp.user_id
         WHERE e.status = "submitted"
         ORDER BY e.submitted_at ASC'
    )->fetchAll();
}

// KPI
$total_submitted = (int)$pdo->query('SELECT COUNT(*) FROM expenses WHERE status="submitted"')->fetchColumn();
$total_month     = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE MONTH(exp_date)=MONTH(CURDATE()) AND status != 'rejected'")->fetchColumn();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<div class="stat-row">
    <div class="stat-cell"><span class="sv"><?= $total_submitted ?></span><span class="sl">Pending Approvals</span></div>
    <div class="stat-cell"><span class="sv"><?= fmt_money((float)$total_month) ?></span><span class="sl">This Month's Expenses</span></div>
</div>

<!-- SUBMIT EXPENSE -->
<?php if ($emp_me): ?>
<div class="card">
    <div class="card-header"><h2>Submit Expense</h2>
        <button class="btn btn-sm btn-default" onclick="toggleSection('exp-form')">Toggle</button>
    </div>
    <div class="card-body" id="exp-form">
        <form method="post">
            <input type="hidden" name="action" value="add_expense">
            <div class="row">
                <div class="col"><label>Category</label>
                    <select name="category">
                        <?php foreach (['Travel','Fuel','Accommodation','Food','Office','Other'] as $c): ?>
                            <option><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col col-2"><label>Description</label><input type="text" name="description" placeholder="Brief description"></div>
                <div class="col"><label>Amount (Rs.) <span class="required">*</span></label><input type="number" name="amount" step="0.01" min="0" required></div>
                <div class="col"><label>Date <span class="required">*</span></label><input type="date" name="exp_date" required></div>
            </div>
            <div class="row">
                <div class="col"><label>Payment Mode</label>
                    <select name="payment_mode">
                        <?php foreach (['Cash','Card','UPI','Reimbursement'] as $m): ?><option><?= $m ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label>Project Ref</label><input type="text" name="project_ref"></div>
                <div class="col auto" style="align-self:flex-end;"><input type="submit" class="btn btn-primary" value="Submit Expense"></div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- PENDING APPROVALS (for approver) -->
<?php if ($is_approver && $pending): ?>
<div class="card">
    <div class="card-header"><h2>Pending Approvals (<?= count($pending) ?>)</h2></div>
    <div class="card-body">
        <table>
            <thead><tr><th>Emp</th><th>Category</th><th>Description</th><th>Amount</th><th>Date</th><th>Mode</th><th>Submitted</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $ex): ?>
            <tr>
                <td><?= h($ex['emp_code']??'') ?> <?= h($ex['emp_name']) ?></td>
                <td><?= h($ex['category']) ?></td>
                <td><?= h($ex['description'] ?? '—') ?></td>
                <td class="fw-bold"><?= fmt_money($ex['amount']) ?></td>
                <td><?= fmt_date($ex['exp_date']) ?></td>
                <td><?= h($ex['payment_mode']) ?></td>
                <td><?= fmt_datetime($ex['submitted_at']) ?></td>
                <td>
                    <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="expense_id" value="<?= $ex['id'] ?>">
                        <input type="text" name="remark" placeholder="Remark" style="width:100px;">
                        <button name="act" value="approved" class="btn btn-sm btn-success">Approve</button>
                        <button name="act" value="rejected" class="btn btn-sm btn-danger">Reject</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- MY EXPENSES -->
<div class="card">
    <div class="card-header"><h2>My Recent Expenses</h2></div>
    <div class="card-body">
        <?php if (!$my_expenses): ?>
            <p class="text-muted">No expenses found. <?= !$emp_me ? 'Link your user to an employee record first.' : '' ?></p>
        <?php else: ?>
        <table>
            <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Mode</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($my_expenses as $ex): ?>
            <tr>
                <td><?= fmt_date($ex['exp_date']) ?></td>
                <td><?= h($ex['category']) ?></td>
                <td><?= h($ex['description'] ?? '—') ?></td>
                <td class="fw-bold"><?= fmt_money($ex['amount']) ?></td>
                <td><?= h($ex['payment_mode']) ?></td>
                <td><?php $s=$ex['status']; $cls=match($s){'approved'=>'badge-success','rejected'=>'badge-danger','reimbursed'=>'badge-info','submitted'=>'badge-warning',default=>'badge-secondary'};
                    echo "<span class='badge $cls'>".ucfirst($s)."</span>"; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
