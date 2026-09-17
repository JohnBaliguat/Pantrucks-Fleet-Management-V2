<?php
// DataTables server-side endpoint for the Service Trips list.
// Service trips = dispatch rows with costumer = 'INTERNAL' + a trips row with
// trip_purpose = 'Service'. For Admin / Dispatch Admin / Dispatcher.
session_start();
include __DIR__ . '/../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();
dt_safe_log_target(__DIR__ . '/error_log.txt');

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    echo dt_envelope('Not authorised');
    exit;
}

// Trip Ticket print route depends on the role.
$printHref = ($role === 'Admin') ? 'print' : 'dispatch-print';

// Custom filters (sent via ajax.data) + the built-in search box.
$from   = trim($_POST['from'] ?? '');
$to     = trim($_POST['to'] ?? '');
$status = trim($_POST['status'] ?? '');
$search = trim($_POST['search']['value'] ?? '');

$source = "
    FROM dispatch d
    JOIN trips t ON t.d_id = d.d_id AND t.trip_purpose = 'Service'
    WHERE d.costumer = 'INTERNAL'
";

// Baseline (date/status) filters; the search box narrows further.
$baseWhere = '';
$baseParams = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $baseWhere .= " AND d.d_datetime::date >= ? "; $baseParams[] = $from; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $baseWhere .= " AND d.d_datetime::date <= ? "; $baseParams[] = $to; }
if ($status === 'Active' || $status === 'Done') { $baseWhere .= " AND t.trip_status = ? "; $baseParams[] = $status; }

$searchWhere = '';
$searchParams = [];
if ($search !== '') {
    $searchWhere = " AND (d.d_drivername ILIKE ? OR d.d_truck ILIKE ? OR d.d_tripreceipt ILIKE ?
                          OR t.service_reason ILIKE ? OR t.service_remarks ILIKE ?
                          OR t.trip_from ILIKE ? OR t.trip_to ILIKE ?) ";
    $like = '%' . $search . '%';
    $searchParams = array_fill(0, 7, $like);
}

$totStmt = $conn->prepare("SELECT COUNT(*) $source $baseWhere");
$totStmt->execute($baseParams);
$recordsTotal = (int)$totStmt->fetchColumn();

$filStmt = $conn->prepare("SELECT COUNT(*) $source $baseWhere $searchWhere");
$filStmt->execute(array_merge($baseParams, $searchParams));
$recordsFiltered = (int)$filStmt->fetchColumn();

// Ordering.
$orderCols = [0 => 'd.d_datetime', 1 => 'd.d_tripreceipt', 2 => 'd.d_drivername', 3 => 't.trip_to', 4 => 't.service_reason', 6 => 't.trip_status'];
$orderBy = 'd.d_datetime DESC';
if (isset($_POST['order'][0]['column'])) {
    $ci = (int)$_POST['order'][0]['column'];
    if (isset($orderCols[$ci])) {
        $dir = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $orderCols[$ci] . ' ' . $dir;
    }
}

$start  = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 25);
$limitSql = $length < 0 ? '' : " LIMIT {$length} OFFSET {$start} ";

$sql = "SELECT d.d_id, d.d_datetime, d.d_drivername, d.d_truck, d.d_trailer, d.d_genset,
               d.d_tripreceipt, t.trip_from, t.trip_to, t.service_reason, t.service_remarks,
               t.trip_container, t.trip_status
        $source $baseWhere $searchWhere
        ORDER BY {$orderBy} {$limitSql}";
$stmt = $conn->prepare($sql);
$stmt->execute(array_merge($baseParams, $searchParams));
$rows = $stmt->fetchAll();

function st_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$data = [];
foreach ($rows as $r) {
    $dt = $r['d_datetime'] ? date('Y-m-d H:i', strtotime((string)$r['d_datetime'])) : '—';
    $status = (string)($r['trip_status'] ?? '');
    $statusCls = strcasecmp($status, 'Done') === 0 ? 'bg-success' : (strcasecmp($status, 'Active') === 0 ? 'bg-primary' : 'bg-secondary');
    $equip = [];
    if (trim((string)$r['d_trailer']) !== '') $equip[] = 'Trailer: ' . st_e($r['d_trailer']);
    if (trim((string)$r['d_genset'])  !== '') $equip[] = 'Genset: '  . st_e($r['d_genset']);
    $equipHtml = $equip ? implode('<br>', $equip) : '<span class="text-muted">—</span>';
    $reason = st_e($r['service_reason'] ?: '—');
    if (trim((string)$r['service_remarks']) !== '') {
        $reason .= '<br><small class="text-muted">' . st_e($r['service_remarks']) . '</small>';
    }
    $printUrl = $printHref . '?id=' . (int)$r['d_id'];
    $isDone = strcasecmp($status, 'Done') === 0;
    $editBtn = $isDone ? '' :
        '<button class="btn btn-sm btn-outline-secondary text-nowrap ms-1 st-edit"'
        . ' data-d-id="' . (int)$r['d_id'] . '"'
        . ' data-from="' . st_e($r['trip_from']) . '"'
        . ' data-to="' . st_e($r['trip_to']) . '"'
        . ' data-reason="' . st_e($r['service_reason']) . '"'
        . ' data-remarks="' . st_e($r['service_remarks']) . '"'
        . ' data-container="' . st_e($r['trip_container']) . '"'
        . ' data-trailer="' . st_e($r['d_trailer']) . '"'
        . ' data-genset="' . st_e($r['d_genset']) . '">'
        . '<i class="ti ti-edit"></i> Update</button>';
    $doneBtn = $isDone ? '' :
        '<button class="btn btn-sm btn-success text-nowrap ms-1 st-done" data-d-id="' . (int)$r['d_id'] . '"><i class="ti ti-check"></i> Done</button>';

    $data[] = [
        '<small>' . st_e($dt) . '</small>',
        '<span class="font-monospace">' . st_e($r['d_tripreceipt'] ?: '—') . '</span>',
        st_e($r['d_drivername'] ?: '—') . '<br><code>' . st_e($r['d_truck'] ?: '—') . '</code>',
        st_e($r['trip_from'] ?: '—') . ' &rarr; ' . st_e($r['trip_to'] ?: '—'),
        $reason,
        '<small>' . $equipHtml . '</small>',
        '<span class="badge ' . $statusCls . '">' . st_e($status ?: '—') . '</span>',
        '<div class="d-flex">' .
            '<a class="btn btn-sm btn-outline-primary text-nowrap" target="_blank" rel="noopener" href="' . st_e($printUrl) . '"><i class="ti ti-file-text"></i> Trip Ticket</a>' .
            $editBtn .
            $doneBtn .
        '</div>',
    ];
}

echo json_encode([
    'draw'            => (int)($_POST['draw'] ?? 0),
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data,
]);
