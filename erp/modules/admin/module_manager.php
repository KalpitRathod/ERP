<?php
// ================================================================
//  Admin – Module Manager
//  /erp/modules/admin/module_manager.php
//  Super Admin UI: enable/disable modules per tenant.
// ================================================================
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_role(['super_admin']);

$page_title    = 'Module Manager';
$active_module = 'admin';
$active_sub    = 'module_manager';
$pdo           = db();

// ---- Toggle module for tenant ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id   = sanitize_int($_POST['tenant_id']   ?? 0);
    $module_code = sanitize_string($_POST['module_code'] ?? '');
    $enable      = (int)($_POST['enable'] ?? 0);

    if ($tenant_id && $module_code) {
        $pdo->prepare(
            'INSERT INTO tenant_modules (tenant_id, module_code, is_enabled, enabled_by)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled), enabled_by=VALUES(enabled_by)'
        )->execute([$tenant_id, $module_code, $enable, current_user_id()]);

        // Invalidate cached module list in session
        unset($_SESSION['active_modules_'.$tenant_id]);
        audit('admin','toggle_module',0,"tenant:$tenant_id,module:$module_code,enabled:$enable");
        set_flash('success', ($enable ? 'Enabled' : 'Disabled') . ": $module_code for tenant #$tenant_id");
    }
    header('Location: ' . BASE_URL . '/modules/admin/module_manager.php?tid=' . $tenant_id);
    exit;
}

// ---- Load data --------------------------------------------------
$tenants       = $pdo->query('SELECT id,slug,name,industry,plan FROM tenants ORDER BY name')->fetchAll();
$all_modules   = $pdo->query('SELECT * FROM modules WHERE is_active=1 ORDER BY industry,code')->fetchAll();

$sel_tenant_id = sanitize_int($_GET['tid'] ?? ($tenants[0]['id'] ?? 0));
$sel_tenant    = null;
$enabled_map   = [];

if ($sel_tenant_id) {
    foreach ($tenants as $t) { if ((int)$t['id'] === $sel_tenant_id) { $sel_tenant = $t; break; } }
    $rows = $pdo->prepare('SELECT module_code, is_enabled, config_json FROM tenant_modules WHERE tenant_id=?');
    $rows->execute([$sel_tenant_id]);
    foreach ($rows->fetchAll() as $r) {
        $enabled_map[$r['module_code']] = ['on' => (bool)$r['is_enabled'], 'cfg' => $r['config_json']];
    }
}

// Group modules by industry
$by_industry = [];
foreach ($all_modules as $m) {
    $by_industry[$m['industry']][] = $m;
}

include ERP_ROOT . '/includes/header.php';
include ERP_ROOT . '/includes/nav.php';
?>
<main>
<?= render_flash() ?>

<div class="card">
    <div class="card-header"><h2>Module Manager – Tenant Configuration</h2></div>
    <div class="card-body">
        <!-- Tenant Selector -->
        <form method="get" style="display:flex;gap:14px;align-items:flex-end;margin-bottom:20px;">
            <div class="col">
                <label>Select Tenant</label>
                <select name="tid" onchange="this.form.submit()">
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= (int)$t['id']===$sel_tenant_id?'selected':'' ?>>
                            <?= h($t['name']) ?> (<?= h($t['slug']) ?>) – <?= h($t['plan']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($sel_tenant): ?>
        <div class="alert alert-info">
            Managing: <strong><?= h($sel_tenant['name']) ?></strong> |
            Industry: <strong><?= h(ucfirst(str_replace('_',' ',$sel_tenant['industry']))) ?></strong> |
            Plan: <strong><?= h(ucfirst($sel_tenant['plan'])) ?></strong>
        </div>

        <?php foreach ($by_industry as $industry => $modules): ?>
        <div class="accordion-item">
            <div class="accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">
                <?= h(ucwords(str_replace('_',' ',$industry))) ?> Modules
                <span><?= count($modules) ?> modules</span>
            </div>
            <div class="accordion-body">
                <table>
                    <thead><tr>
                        <th>Code</th><th>Module Name</th><th>Description</th><th>Core</th><th>Status</th><th>Action</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($modules as $mod): ?>
                    <?php $is_on = $mod['is_core'] || ($enabled_map[$mod['code']]['on'] ?? false); ?>
                    <tr>
                        <td><code><?= h($mod['code']) ?></code></td>
                        <td><strong><?= h($mod['name']) ?></strong></td>
                        <td><?= h($mod['description'] ?? '') ?></td>
                        <td><?= $mod['is_core'] ? '<span class="badge badge-info">Core</span>' : '' ?></td>
                        <td><?= $is_on
                            ? '<span class="badge badge-success">Enabled</span>'
                            : '<span class="badge badge-secondary">Disabled</span>' ?>
                        </td>
                        <td>
                        <?php if (!$mod['is_core']): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="tenant_id"   value="<?= $sel_tenant_id ?>">
                                <input type="hidden" name="module_code" value="<?= h($mod['code']) ?>">
                                <?php if ($is_on): ?>
                                    <input type="hidden" name="enable" value="0">
                                    <button class="btn btn-sm btn-danger"
                                        data-confirm="Disable <?= h($mod['name']) ?>?">Disable</button>
                                <?php else: ?>
                                    <input type="hidden" name="enable" value="1">
                                    <button class="btn btn-sm btn-success">Enable</button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">Always on</span>
                        <?php endif; ?>
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
