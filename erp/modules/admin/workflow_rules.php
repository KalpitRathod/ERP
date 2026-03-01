<?php
// ================================================================
//  Admin – Workflow Rule Builder
//  /erp/modules/admin/workflow_rules.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin']);

$page_title    = 'Workflow Rule Builder';
$active_module = 'admin';
$pdo           = db();

$TRIGGER_EVENTS = ['ticket_sale_count','stock_level','open_tickets','booking_created','expense_submitted','po_received'];
$OPERATORS      = ['>','>=','<','<=','='];
$ACTION_TYPES   = ['notify_email','notify_dashboard','create_task','send_webhook','log_only'];
$METRICS        = ['ticket_sales','stock_level','open_tickets'];

// ---- Add Rule POST ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'add_rule') {
    $tenant_id = sanitize_int($_POST['tenant_id'] ?? 1);
    $name      = sanitize_string($_POST['name'] ?? '');
    $event     = sanitize_string($_POST['trigger_event'] ?? '');
    $metric    = sanitize_string($_POST['metric'] ?? '');
    $op        = in_array($_POST['op'],$OPERATORS) ? $_POST['op'] : '>';
    $val       = (float)($_POST['value'] ?? 0);
    $act_type  = sanitize_string($_POST['action_type'] ?? 'log_only');
    $message   = sanitize_string($_POST['message'] ?? '');
    $target    = sanitize_string($_POST['target'] ?? '');

    if (!$name || !$event) {
        set_flash('danger','Rule name and trigger event are required.');
    } else {
        $condition = json_encode(['metric'=>$metric,'op'=>$op,'value'=>$val]);
        $action    = json_encode(['type'=>$act_type,'message'=>$message,'target'=>$target]);
        $pdo->prepare(
            'INSERT INTO workflow_rules (tenant_id,name,trigger_event,condition_json,action_json,created_by)
             VALUES (?,?,?,?,?,?)'
        )->execute([$tenant_id,$name,$event,$condition,$action,current_user_id()]);
        audit('admin','add_workflow_rule',0,"name:$name,event:$event");
        set_flash('success',"Rule '$name' created.");
    }
    header('Location: ' . BASE_URL . '/modules/admin/workflow_rules.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'toggle_rule') {
    $rid    = sanitize_int($_POST['rule_id'] ?? 0);
    $status = sanitize_int($_POST['is_active'] ?? 0);
    $pdo->prepare('UPDATE workflow_rules SET is_active=? WHERE id=?')->execute([$status,$rid]);
    set_flash('success','Rule '.($status?'activated':'deactivated').'.');
    header('Location: ' . BASE_URL . '/modules/admin/workflow_rules.php');
    exit;
}

$tenants  = $pdo->query('SELECT id,name,slug FROM tenants ORDER BY name')->fetchAll();
$sel_tid  = sanitize_int($_GET['tid'] ?? ($tenants[0]['id'] ?? 1));
$rules    = $pdo->prepare('SELECT wr.*,u.name AS creator FROM workflow_rules wr LEFT JOIN users u ON u.id=wr.created_by WHERE wr.tenant_id=? ORDER BY wr.is_active DESC,wr.created_at DESC');
$rules->execute([$sel_tid]);
$rules = $rules->fetchAll();

$log = $pdo->query('SELECT wl.*,wr.name AS rule_name FROM workflow_log wl JOIN workflow_rules wr ON wr.id=wl.rule_id ORDER BY wl.triggered_at DESC LIMIT 20')->fetchAll();

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>
<div class="card">
    <div class="card-header"><h2>Workflow Rule Builder</h2></div>
    <div class="card-body">
        <!-- Tenant selector -->
        <form method="get" style="display:flex;gap:14px;align-items:flex-end;margin-bottom:20px;">
            <div class="col"><label>Tenant</label>
                <select name="tid" onchange="this.form.submit()">
                    <?php foreach($tenants as $t): ?><option value="<?=$t['id']?>" <?=$t['id']==$sel_tid?'selected':''?>><?=h($t['name'])?></option><?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Add Rule -->
        <div style="background:#e8f0f8;border:1px solid #99bbdd;padding:16px;margin-bottom:20px;">
            <strong style="display:block;margin-bottom:12px;color:#003366;">Add Rule</strong>
            <form method="post">
                <input type="hidden" name="action" value="add_rule">
                <input type="hidden" name="tenant_id" value="<?= $sel_tid ?>">
                <div class="row">
                    <div class="col col-2"><label>Rule Name <span class="required">*</span></label><input type="text" name="name" required placeholder='e.g. "Low Popcorn Stock Alert"'></div>
                    <div class="col"><label>Trigger Event <span class="required">*</span></label>
                        <select name="trigger_event" required>
                            <option value="">-- Select --</option>
                            <?php foreach($TRIGGER_EVENTS as $e): ?><option><?=$e?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <span class="section-title">Condition: IF [metric] [op] [value]</span>
                <div class="row">
                    <div class="col"><label>Metric</label>
                        <select name="metric">
                            <?php foreach($METRICS as $m): ?><option><?=$m?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col"><label>Operator</label>
                        <select name="op"><?php foreach($OPERATORS as $o): ?><option><?=$o?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col"><label>Value</label><input type="number" name="value" step="any" value="100"></div>
                </div>
                <span class="section-title">Action: THEN [action]</span>
                <div class="row">
                    <div class="col"><label>Action Type</label>
                        <select name="action_type"><?php foreach($ACTION_TYPES as $a): ?><option><?=$a?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col"><label>Target (email/role)</label><input type="text" name="target" placeholder="manager@company.com OR role:hr_manager"></div>
                    <div class="col col-2"><label>Message</label><input type="text" name="message" placeholder="Ticket sales exceeded threshold!"></div>
                    <div class="col auto" style="align-self:flex-end;"><input type="submit" class="btn btn-primary" value="Save Rule"></div>
                </div>
            </form>
        </div>

        <!-- Rules table -->
        <?php if (!$rules): ?>
            <p class="text-muted">No rules yet. Add your first rule above.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Name</th><th>Event</th><th>Condition</th><th>Action</th><th>Triggers</th><th>Last Run</th><th>Status</th><th>Control</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $r): $cond=json_decode($r['condition_json'],true); $act=json_decode($r['action_json'],true); ?>
            <tr>
                <td><strong><?=h($r['name'])?></strong><br><small>by <?=h($r['creator']??'—')?></small></td>
                <td><code><?=h($r['trigger_event'])?></code></td>
                <td><?=h($cond['metric']??'—')?> <?=h($cond['op']??'')?> <?=h($cond['value']??'')?></td>
                <td><?=h($act['type']??'—')?> → <?=h($act['target']??'log')?></td>
                <td><?=(int)$r['trigger_count']?></td>
                <td><?=fmt_datetime($r['last_triggered']??'')?:'-'?></td>
                <td><?=$r['is_active']?'<span class="badge badge-success">Active</span>':'<span class="badge badge-secondary">Off</span>'?></td>
                <td><form method="post">
                    <input type="hidden" name="action" value="toggle_rule">
                    <input type="hidden" name="rule_id" value="<?=$r['id']?>">
                    <input type="hidden" name="is_active" value="<?=$r['is_active']?0:1?>">
                    <button class="btn btn-sm <?=$r['is_active']?'btn-danger':'btn-success'?>"><?=$r['is_active']?'Pause':'Activate'?></button>
                </form></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Workflow Log -->
<div class="card">
    <div class="card-header"><h2>Recent Workflow Log (20)</h2></div>
    <div class="card-body">
        <?php if (!$log): ?><p class="text-muted">No workflow activity yet.</p><?php else: ?>
        <table>
            <thead><tr><th>Rule</th><th>Triggered At</th><th>Result</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($log as $l): ?>
            <tr>
                <td><?=h($l['rule_name'])?></td>
                <td><?=fmt_datetime($l['triggered_at'])?></td>
                <td><?=$l['result']==='success'?'<span class="badge badge-success">Success</span>':'<span class="badge badge-danger">Failed</span>'?></td>
                <td><?=h(substr($l['detail']??'',0,80))?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
