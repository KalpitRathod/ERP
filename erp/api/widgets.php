<?php
// ================================================================
//  Widget Data API
//  /erp/api/widgets.php
//  Returns JSON data for a named dashboard widget.
//  Called by dashboard.js via fetch().
// ================================================================
require_once dirname(__DIR__) . '/config/config.php';
require_once ERP_ROOT . '/config/db.php';
require_once ERP_ROOT . '/includes/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$w         = sanitize_string($_GET['w'] ?? '');
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
$pdo       = db();

$response = ['widget' => $w, 'tenant_id' => $tenant_id, 'value' => null, 'label' => '', 'data' => []];

try {
    switch ($w) {

        case 'total_employees':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
            $response['label'] = 'Active Employees';
            break;

        case 'today_bookings':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM travel_bookings WHERE pickup_date=CURDATE()")->fetchColumn();
            $response['label'] = "Today's Bookings";
            break;

        case 'low_stock':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM stock s JOIN items i ON i.id=s.item_id WHERE s.qty<=i.reorder_level")->fetchColumn();
            $response['label'] = 'Low Stock Items';
            $response['alert'] = $response['value'] > 0;
            break;

        case 'open_tickets':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress')")->fetchColumn();
            $response['label'] = 'Open Support Tickets';
            break;

        case 'pending_expenses':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM expenses WHERE status='submitted'")->fetchColumn();
            $response['label'] = 'Pending Expense Approvals';
            break;

        case 'monthly_revenue':
            $val = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND status='paid'")->fetchColumn();
            $response['value'] = number_format((float)$val, 2);
            $response['label'] = 'Revenue This Month (Rs.)';
            break;

        case 'fleet_expiry':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='active' AND (insurance_exp<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) OR puc_exp<=DATE_ADD(CURDATE(),INTERVAL 30 DAY))")->fetchColumn();
            $response['label'] = 'Documents Expiring (30d)';
            break;

        case 'cinema_today':
            $rows = $pdo->query("SELECT movie_title, show_time, status FROM cinema_shows WHERE show_date=CURDATE() ORDER BY show_time")->fetchAll();
            $response['value'] = count($rows);
            $response['label'] = "Today's Shows";
            $response['data']  = $rows;
            break;

        case 'ticket_sales':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM cinema_tickets WHERE DATE(booked_at)=CURDATE() AND status='booked'")->fetchColumn();
            $response['label'] = 'Tickets Sold Today';
            break;

        case 'active_jobs':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM job_orders WHERE tenant_id=$tenant_id AND status IN ('queued','in_progress')")->fetchColumn();
            $response['label'] = 'Active Job Orders';
            break;

        case 'bom_count':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM bom_items WHERE tenant_id=$tenant_id AND status='approved'")->fetchColumn();
            $response['label'] = 'Approved BOMs';
            break;

        case 'asset_alerts':
            $response['value'] = (int)$pdo->query("SELECT COUNT(*) FROM assets_heavy WHERE tenant_id=$tenant_id AND (next_inspection<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) OR status!='operational')")->fetchColumn();
            $response['label'] = 'Asset Alerts (30d)';
            break;

        // ---- Layout: fetch user's dashboard config ---------
        case '__layout':
            $stmt = $pdo->prepare(
                'SELECT layout_json FROM tenant_dashboards WHERE tenant_id=? AND user_id=?'
            );
            $stmt->execute([$tenant_id, current_user_id()]);
            $row = $stmt->fetch();
            $response['layout'] = $row ? json_decode($row['layout_json'], true) : null;
            break;

        // ---- Save user's dashboard layout ------------------
        case '__save_layout':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $layout = json_decode(file_get_contents('php://input'), true);
                if ($layout) {
                    $pdo->prepare(
                        'INSERT INTO tenant_dashboards (tenant_id, user_id, layout_json)
                         VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE layout_json=VALUES(layout_json), updated_at=NOW()'
                    )->execute([$tenant_id, current_user_id(), json_encode($layout)]);
                    $response['saved'] = true;
                }
            }
            break;

        default:
            http_response_code(404);
            $response['error'] = "Unknown widget: $w";
    }
} catch (Throwable $e) {
    http_response_code(500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
