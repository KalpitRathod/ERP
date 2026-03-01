<?php
// ================================================================
//  ERP – Tenant Bootstrap Middleware
//  /erp/includes/tenant.php
//
//  Usage: include after functions.php on every page.
//  Sets global $TENANT and $ACTIVE_MODULES for the session.
// ================================================================

if (!defined('ERP_ROOT')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

// ---- Load tenant from session ----------------------------------
function load_tenant(): array {
    // Cache in session after first load
    if (!empty($_SESSION['tenant'])) {
        return $_SESSION['tenant'];
    }
    // Resolve tenant: from subdomain, URL prefix, or user's tenant_id
    $pdo = db();
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    if (!$tenant_id) {
        // Fallback: use demo tenant
        $row = $pdo->query("SELECT * FROM tenants WHERE slug='demo' LIMIT 1")->fetch();
    } else {
        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$tenant_id]);
        $row  = $stmt->fetch();
    }
    if (!$row) {
        die('<div style="font-family:Arial;color:#660000;padding:20px;"><strong>Tenant not found or inactive.</strong></div>');
    }
    $_SESSION['tenant'] = $row;
    return $row;
}

// ---- Load enabled modules for current tenant -------------------
function load_active_modules(int $tenant_id): array {
    if (!empty($_SESSION['active_modules_'.$tenant_id])) {
        return $_SESSION['active_modules_'.$tenant_id];
    }
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT module_code, config_json FROM tenant_modules
         WHERE tenant_id=? AND is_enabled=1'
    );
    $stmt->execute([$tenant_id]);
    $rows = $stmt->fetchAll();

    // Always include core modules
    $active = ['core.auth' => [], 'core.dashboard' => [], 'core.audit' => []];
    foreach ($rows as $r) {
        $active[$r['module_code']] = $r['config_json'] ? json_decode($r['config_json'], true) : [];
    }
    $_SESSION['active_modules_'.$tenant_id] = $active;
    return $active;
}

/** Returns true if the given module is enabled for current tenant */
function module_enabled(string $code): bool {
    global $ACTIVE_MODULES;
    return isset($ACTIVE_MODULES[$code]);
}

/** Returns config array for a module (or []) */
function module_config(string $code): array {
    global $ACTIVE_MODULES;
    return $ACTIVE_MODULES[$code] ?? [];
}

// ---- Load custom field definitions for an entity type ----------
function get_custom_fields(int $tenant_id, string $entity_type): array {
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM custom_field_defs
         WHERE tenant_id=? AND entity_type=? AND is_active=1
         ORDER BY sort_order, id'
    );
    $stmt->execute([$tenant_id, $entity_type]);
    return $stmt->fetchAll();
}

/** Get all custom field values for a specific entity record */
function get_custom_field_values(int $tenant_id, string $entity_type, int $entity_id): array {
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT fd.field_code,
                COALESCE(fv.value_text, fv.value_num, fv.value_date) AS value
         FROM custom_field_defs fd
         LEFT JOIN custom_field_values fv
            ON fv.field_def_id=fd.id
           AND fv.entity_type=?
           AND fv.entity_id=?
         WHERE fd.tenant_id=? AND fd.entity_type=? AND fd.is_active=1
         ORDER BY fd.sort_order'
    );
    $stmt->execute([$entity_type,$entity_id,$tenant_id,$entity_type]);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['field_code']] = $row['value'];
    }
    return $result;
}

/** Render custom fields form inputs for a given entity type */
function render_custom_field_inputs(array $field_defs, array $values = []): string {
    if (!$field_defs) return '';
    $html = '<span class="section-title">Custom Fields</span><div class="row" style="flex-wrap:wrap;">';
    foreach ($field_defs as $f) {
        $val  = htmlspecialchars($values[$f['field_code']] ?? '', ENT_QUOTES);
        $name = 'cf_' . htmlspecialchars($f['field_code']);
        $req  = $f['is_required'] ? 'required' : '';
        $lbl  = htmlspecialchars($f['field_label']);
        $html .= '<div class="col">';
        $html .= "<label>$lbl" . ($f['is_required'] ? ' <span class="required">*</span>' : '') . "</label>";
        switch ($f['field_type']) {
            case 'select':
                $opts = json_decode($f['options_json'] ?? '[]', true);
                $html .= "<select name=\"$name\" $req>";
                $html .= '<option value="">-- Select --</option>';
                foreach ($opts as $o) {
                    $sel = ($val === $o['value']) ? 'selected' : '';
                    $html .= "<option value=\"{$o['value']}\" $sel>{$o['label']}</option>";
                }
                $html .= '</select>';
                break;
            case 'textarea':
                $html .= "<textarea name=\"$name\" $req>$val</textarea>";
                break;
            case 'date':
                $html .= "<input type=\"date\" name=\"$name\" value=\"$val\" $req>";
                break;
            case 'number':
                $html .= "<input type=\"number\" name=\"$name\" value=\"$val\" step=\"any\" $req>";
                break;
            case 'checkbox':
                $chk = $val ? 'checked' : '';
                $html .= "<input type=\"checkbox\" name=\"$name\" value=\"1\" $chk>";
                break;
            default:
                $ph = htmlspecialchars($f['placeholder'] ?? '');
                $html .= "<input type=\"text\" name=\"$name\" value=\"$val\" placeholder=\"$ph\" $req>";
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/** Save POSTed custom field values for an entity record */
function save_custom_field_values(int $tenant_id, string $entity_type, int $entity_id, array $field_defs): void {
    $pdo  = db();
    foreach ($field_defs as $f) {
        $key   = 'cf_' . $f['field_code'];
        if (!isset($_POST[$key])) continue;
        $raw   = trim((string)$_POST[$key]);
        $col   = match($f['field_type']) {'number'=>'value_num','date'=>'value_date',default=>'value_text'};
        $pdo->prepare(
            "INSERT INTO custom_field_values
             (tenant_id, field_def_id, entity_type, entity_id, $col)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE $col = VALUES($col), updated_at = NOW()"
        )->execute([$tenant_id, $f['id'], $entity_type, $entity_id, $raw ?: null]);
    }
}

// ---- Workflow rule checker (called after significant events) ----
function check_workflow_rules(int $tenant_id, string $event): void {
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM workflow_rules
         WHERE tenant_id=? AND trigger_event=? AND is_active=1'
    );
    $stmt->execute([$tenant_id, $event]);
    $rules = $stmt->fetchAll();
    foreach ($rules as $rule) {
        $cond   = json_decode($rule['condition_json'], true);
        $action = json_decode($rule['action_json'],    true);
        if (evaluate_rule_condition($tenant_id, $cond)) {
            execute_rule_action($tenant_id, $rule['id'], $action);
        }
    }
}

function evaluate_rule_condition(int $tenant_id, array $cond): bool {
    $pdo = db();
    // Simple metric evaluation – extend as needed
    $metric = $cond['metric']  ?? '';
    $op     = $cond['op']      ?? '>';
    $target = (float)($cond['value'] ?? 0);

    $actual = match($metric) {
        'ticket_sales' => (int)$pdo->query("SELECT COUNT(*) FROM cinema_tickets WHERE tenant_id=$tenant_id AND DATE(booked_at)=CURDATE()")->fetchColumn(),
        'stock_level'  => (float)$pdo->query("SELECT MIN(s.qty) FROM stock s JOIN items i ON i.id=s.item_id WHERE s.qty<=i.reorder_level")->fetchColumn(),
        'open_tickets' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress')")->fetchColumn(),
        default        => 0,
    };

    return match($op) {
        '>'  => $actual >  $target,
        '>=' => $actual >= $target,
        '<'  => $actual <  $target,
        '<=' => $actual <= $target,
        '='  => $actual == $target,
        default => false,
    };
}

function execute_rule_action(int $tenant_id, int $rule_id, array $action): void {
    $pdo  = db();
    $type = $action['type'] ?? 'log';
    // For now: log the action; extend with email/notification
    $detail = "Rule $rule_id triggered. Action: " . json_encode($action);
    $pdo->prepare(
        'INSERT INTO workflow_log (rule_id,tenant_id,result,detail) VALUES (?,?,?,?)'
    )->execute([$rule_id,$tenant_id,'success',$detail]);
    $pdo->prepare('UPDATE workflow_rules SET last_triggered=NOW(), trigger_count=trigger_count+1 WHERE id=?')
        ->execute([$rule_id]);
}

// ---- Bootstrap (called near top of every page) -----------------
global $TENANT, $ACTIVE_MODULES;
$TENANT         = load_tenant();
$ACTIVE_MODULES = load_active_modules((int)$TENANT['id']);
