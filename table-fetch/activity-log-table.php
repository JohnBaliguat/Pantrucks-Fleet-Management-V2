<?php
// DataTables server-side endpoint for the admin Activity Log.
// Merged timeline of activity_log (logins / high-value actions) and the
// workflow_event audit (dispatch lifecycle). Returns the DataTables envelope:
//   { draw, recordsTotal, recordsFiltered, data }
session_start();
include __DIR__ . '/../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
require_once __DIR__ . '/../php/helpers/activity_log_helper.php';
dt_install_safety_net();
dt_safe_log_target(__DIR__ . '/error_log.txt');

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    echo dt_envelope('Not authorised');
    exit;
}

pt_ensure_activity_log_table($conn);

// Custom filters (sent via DataTables ajax.data) + the built-in search box.
$from   = trim($_POST['from'] ?? '');
$to     = trim($_POST['to'] ?? '');
$role   = trim($_POST['role'] ?? '');
$search = trim($_POST['search']['value'] ?? '');

// Merged source. workflow_event actor names resolved per-role via correlated
// subqueries (drivers for 'driver', "user" otherwise).
$source = "
    SELECT * FROM (
        SELECT al.created_at AS ts, al.user_role AS role, al.user_name AS name,
               al.action AS action, al.details AS details, '' AS booking_no,
               al.ip_address AS ip, 'activity' AS source
        FROM activity_log al
        UNION ALL
        SELECT we.event_at AS ts, we.actor_role AS role,
               COALESCE(
                   CASE WHEN LOWER(we.actor_role) = 'driver'
                        THEN (SELECT CONCAT(d.driver_lname, ', ', d.driver_fname) FROM drivers d WHERE d.driver_id = we.actor_id)
                        ELSE (SELECT CONCAT_WS(' ', u.user_fname, u.user_lname) FROM \"user\" u WHERE u.user_id = we.actor_id)
                   END, '') AS name,
               we.stage AS action, we.notes AS details, we.booking_no AS booking_no,
               '' AS ip, 'workflow' AS source
        FROM workflow_event we
    ) t
";

// Custom (date/role) filters define the baseline set; the search box narrows it.
$baseWhere = " WHERE 1=1 ";
$baseParams = [];
if ($from !== '') { $baseWhere .= " AND t.ts >= ? "; $baseParams[] = $from . ' 00:00:00'; }
if ($to   !== '') { $baseWhere .= " AND t.ts <= ? "; $baseParams[] = $to   . ' 23:59:59'; }
if ($role !== '') { $baseWhere .= " AND LOWER(t.role) = LOWER(?) "; $baseParams[] = $role; }

$searchWhere = '';
$searchParams = [];
if ($search !== '') {
    $searchWhere = " AND (t.name ILIKE ? OR t.role ILIKE ? OR t.action ILIKE ? OR t.details ILIKE ? OR t.booking_no ILIKE ?) ";
    $like = '%' . $search . '%';
    $searchParams = [$like, $like, $like, $like, $like];
}

// recordsTotal = baseline (date/role) count; recordsFiltered = + search box.
$totStmt = $conn->prepare("SELECT COUNT(*) FROM ($source) t {$baseWhere}");
$totStmt->execute($baseParams);
$recordsTotal = (int)$totStmt->fetchColumn();

$filStmt = $conn->prepare("SELECT COUNT(*) FROM ($source) t {$baseWhere} {$searchWhere}");
$filStmt->execute(array_merge($baseParams, $searchParams));
$recordsFiltered = (int)$filStmt->fetchColumn();

// Ordering — map DataTables column index to a sortable expression.
$orderCols = [0 => 't.ts', 1 => 't.name', 2 => 't.role', 3 => 't.action', 5 => 't.booking_no'];
$orderBy = 't.ts DESC';
if (isset($_POST['order'][0]['column'])) {
    $ci = (int)$_POST['order'][0]['column'];
    if (isset($orderCols[$ci])) {
        $dir = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $orderCols[$ci] . ' ' . $dir;
    }
}

// Paging (Postgres LIMIT/OFFSET; length -1 means "all").
$start  = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 50);
$limitSql = $length < 0 ? '' : " LIMIT {$length} OFFSET {$start} ";

$rowsStmt = $conn->prepare("SELECT t.* FROM ($source) t {$baseWhere} {$searchWhere} ORDER BY {$orderBy} {$limitSql}");
$rowsStmt->execute(array_merge($baseParams, $searchParams));
$rows = $rowsStmt->fetchAll();

function al_esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$data = [];
foreach ($rows as $r) {
    $badge = ($r['source'] === 'workflow')
        ? '<span class="badge bg-info text-dark">workflow</span>'
        : '<span class="badge bg-secondary">activity</span>';
    $data[] = [
        al_esc($r['ts']),
        al_esc($r['name'] !== '' ? $r['name'] : '—'),
        al_esc($r['role'] !== '' ? $r['role'] : '—'),
        al_esc($r['action'] !== '' ? $r['action'] : '—'),
        al_esc($r['details']),
        al_esc($r['booking_no']),
        $badge,
    ];
}

echo json_encode([
    'draw'            => (int)($_POST['draw'] ?? 0),
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data,
]);
