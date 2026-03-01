<?php
// ================================================================
//  Admin – Custom Fields Builder
//  /erp/modules/admin/custom_fields.php
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin']);

$page_title    = 'Custom Field Builder';
$active_module = 'admin';
$pdo           = db();

$ENTITY_TYPES = ['employee','vehicle','booking','inventory_item','vendor','customer','job_order'];
$FIELD_TYPES  = ['text','number','date','select','checkbox','textarea','file'];

// ---- Add Field POST --------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'add_field') {
    $tenant_id   = sanitize_int($_POST['tenant_id']   ?? 1);
    $entity_type = sanitize_string($_POST['entity_type'] ?? '');
    $field_code  = preg_replace('/[^a-z0-9_]/', '_', strtolower(sanitize_string($_POST['field_code']??'')));
    $field_label = sanitize_string($_POST['field_label'] ?? '');
    $field_type  = in_array($_POST['field_type'],$FIELD_TYPES) ? $_POST['field_type'] : 'text';
    $placeholder = sanitize_string($_POST['placeholder'] ?? '');
    $is_required = (int)isset($_POST['is_required']);
    $options     = sanitize_string($_POST['options_json'] ?? '');

    if (!$entity_type || !$field_code || !$field_label) {
        set_flash('danger','Entity type, code and label are required.');
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO custom_field_defs
                 (tenant_id,entity_type,field_code,field_label,field_type,options_json,placeholder,is_required)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$tenant_id,$entity_type,$field_code,$field_label,$field_type,$options?:null,$placeholder,$is_required]);
            set_flash('success',"Field '$field_code' added to $entity_type.");
        } catch (Throwable $e) {
            set_flash('danger','Error: '.$e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/modules/admin/custom_fields.php');
    exit;
}

// ---- Delete field -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete_field') {
    $fid = sanitize_int($_POST['field_id'] ?? 0);
    $pdo->prepare('UPDATE custom_field_defs SET is_active=0 WHERE id=?')->execute([$fid]);
    set_flash('success','Field deactivated.');
    header('Location: ' . BASE_URL . '/modules/admin/custom_fields.php');
    exit;
}

$tenants = $pdo->query('SELECT id,name,slug FROM tenants ORDER BY name')->fetchAll();
$sel_tid = sanitize_int($_GET['tid'] ?? ($tenants[0]['id'] ?? 1));

$fields_stmt = $pdo->prepare(
    'SELECT * FROM custom_field_defs WHERE tenant_id=? AND is_active=1 ORDER BY entity_type,sort_order,id'
);
$fields_stmt->execute([$sel_tid]);
$fields = $fields_stmt->fetchAll();

// Group by entity type
$by_entity = [];
foreach ($fields as $f) $by_entity[$f['entity_type']][] = $f;

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<div class="card">
    <div class="card-header"><h2>Custom Field Builder</h2>
        <a href="module_manager.php" class="btn btn-sm btn-default">Module Manager</a>
    </div>
    <div class="card-body">
        <!-- Tenant picker -->
        <form method="get" style="display:flex;gap:14px;align-items:flex-end;margin-bottom:20px;">
            <div class="col"><label>Tenant</label>
                <select name="tid" onchange="this.form.submit()">
                    <?php foreach($tenants as $t): ?>
                        <option value="<?=$t['id']?>" <?=$t['id']==$sel_tid?'selected':''?>><?=h($t['name'])?> (<?=h($t['slug'])?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Add Field Form -->
        <div style="background:#e8f0f8;border:1px solid #99bbdd;padding:16px;margin-bottom:20px;">
            <strong style="display:block;margin-bottom:12px;color:#003366;">Add Custom Field</strong>
            <form method="post">
                <input type="hidden" name="action" value="add_field">
                <input type="hidden" name="tenant_id" value="<?= $sel_tid ?>">
                <div class="row">
                    <div class="col"><label>Entity Type <span class="required">*</span></label>
                        <select name="entity_type" required>
                            <option value="">-- Select --</option>
                            <?php foreach($ENTITY_TYPES as $et): ?><option><?=$et?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col"><label>Field Code <span class="required">*</span></label>
                        <input type="text" name="field_code" placeholder="metal_gauge" pattern="[a-z0-9_]+" required>
                        <small>Lowercase letters, numbers, underscores only</small>
                    </div>
                    <div class="col"><label>Field Label <span class="required">*</span></label>
                        <input type="text" name="field_label" placeholder="Metal Gauge" required>
                    </div>
                    <div class="col"><label>Field Type</label>
                        <select name="field_type">
                            <?php foreach($FIELD_TYPES as $ft): ?><option><?=$ft?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col"><label>Placeholder</label><input type="text" name="placeholder"></div>
                    <div class="col">
                        <label>Required?</label>
                        <label style="font-weight:normal;margin-top:8px;display:block;">
                            <input type="checkbox" name="is_required" value="1"> Yes
                        </label>
                    </div>
                </div>
                <div class="row">
                    <div class="col col-2"><label>Options (JSON, for select type) <small>e.g. [{"value":"A","label":"Option A"}]</small></label>
                        <input type="text" name="options_json" placeholder='[{"value":"hot","label":"Hot"},{"value":"cold","label":"Cold"}]'>
                    </div>
                    <div class="col auto" style="align-self:flex-end;"><input type="submit" class="btn btn-primary" value="Add Field"></div>
                </div>
            </form>
        </div>

        <!-- Existing Fields -->
        <?php if (!$fields): ?>
            <p class="text-muted">No custom fields defined for this tenant yet.</p>
        <?php else: ?>
        <?php foreach ($by_entity as $entity => $flist): ?>
        <div class="accordion-item">
            <div class="accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">
                Entity: <strong><?= h($entity) ?></strong>
                <span><?= count($flist) ?> fields</span>
            </div>
            <div class="accordion-body">
                <table>
                    <thead><tr><th>Code</th><th>Label</th><th>Type</th><th>Required</th><th>Placeholder</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($flist as $f): ?>
                    <tr>
                        <td><code><?= h($f['field_code']) ?></code></td>
                        <td><?= h($f['field_label']) ?></td>
                        <td><span class="badge badge-secondary"><?= h($f['field_type']) ?></span></td>
                        <td><?= $f['is_required'] ? '<span class="badge badge-danger">Yes</span>' : 'No' ?></td>
                        <td><?= h($f['placeholder'] ?? '—') ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="delete_field">
                                <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                <button class="btn btn-sm btn-danger" data-confirm="Deactivate '<?= h($f['field_code']) ?>'?">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</main>
<?php include ERP_ROOT . '/includes/footer.php'; ?>
