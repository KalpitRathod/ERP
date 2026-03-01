<?php
// ================================================================
//  ERP – Shared Helper Functions
//  /erp/includes/functions.php
// ================================================================

if (!defined('ERP_ROOT')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

// ---- Session bootstrap -----------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => SESSION_LIFETIME, 'httponly' => true]);
    session_start();
}

// ---- Auth guards -----------------------------------------------

/** Redirect to login if not logged in */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/** Redirect with 403 notice if user's role is not in allowed list */
function require_role(array $allowed_roles): void {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
        http_response_code(403);
        die(page_error('403 – Access Denied', 'You do not have permission to view this page.'));
    }
}

/** Returns true if current user has given role */
function has_role(string $role): bool {
    return ($_SESSION['role'] ?? '') === $role;
}

/** Returns true if current user role is one of the given roles */
function in_roles(array $roles): bool {
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

/** Get current logged-in user id */
function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/** Get current logged-in user name */
function current_user_name(): string {
    return $_SESSION['user_name'] ?? 'Guest';
}

/** Get current role */
function current_role(): string {
    return $_SESSION['role'] ?? '';
}

// ---- Sanitisation helpers --------------------------------------

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_int(mixed $val, int $default = 0): int {
    $v = filter_var($val, FILTER_VALIDATE_INT);
    return $v === false ? $default : (int)$v;
}

function sanitize_string(mixed $val): string {
    return trim(strip_tags((string)$val));
}

// ---- Flash messages --------------------------------------------

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function render_flash(): string {
    $f = get_flash();
    if (!$f) return '';
    $cls = match($f['type']) {
        'success' => 'alert-success',
        'danger'  => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert ' . $cls . '">' . h($f['msg']) . '</div>';
}

// ---- Audit log -------------------------------------------------

function audit(string $module, string $action, int $record_id = 0, string $detail = ''): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, module, action, record_id, detail, ip)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            current_user_id(), $module, $action,
            $record_id ?: null, $detail ?: null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Throwable) { /* non-fatal */ }
}

// ---- Pagination ------------------------------------------------

function paginate(int $total, int $per_page, int $page, string $base_url): array {
    $pages    = max(1, (int)ceil($total / $per_page));
    $page     = max(1, min($page, $pages));
    $offset   = ($page - 1) * $per_page;
    return compact('pages', 'page', 'offset', 'per_page', 'total', 'base_url');
}

function render_pagination(array $p): string {
    if ($p['pages'] <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $url = $p['base_url'] . '&page=' . $i;
        $cls = $i === $p['page'] ? ' class="active"' : '';
        $html .= "<a href=\"$url\"$cls>$i</a>";
    }
    $html .= '</div>';
    return $html;
}

// ---- Generic error page ----------------------------------------

function page_error(string $title, string $msg): string {
    return '<!DOCTYPE html><html><head><title>' . h($title) . '</title>
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/style.css">
    </head><body style="padding:40px;">
    <div class="alert alert-danger"><strong>' . h($title) . ':</strong> ' . h($msg) . '</div>
    <a href="' . BASE_URL . '/dashboard/index.php" class="btn btn-default">Back to Dashboard</a>
    </body></html>';
}

// ---- Date helpers ----------------------------------------------

function fmt_date(string $date): string {
    return $date ? date('d/m/Y', strtotime($date)) : '—';
}

function fmt_datetime(string $dt): string {
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
}

function fmt_money(float $amount): string {
    return 'Rs. ' . number_format($amount, 2);
}

function days_until(string $date): int {
    if (!$date) return 9999;
    return (int)round((strtotime($date) - time()) / 86400);
}

function expiry_class(string $date): string {
    $days = days_until($date);
    if ($days < 0)  return 'exp-warn';
    if ($days < 30) return 'exp-warn';
    return 'exp-ok';
}
