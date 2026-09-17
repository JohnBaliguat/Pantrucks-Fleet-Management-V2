<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$source   = $_GET['source']   ?? 'all';   // gate | all
$status   = $_GET['status']   ?? 'all';   // open | acknowledged | resolved | all
$severity = $_GET['severity'] ?? 'all';   // high | med | low | all
$limit    = (int)($_GET['limit'] ?? 25);
if ($limit > 200) $limit = 200;

$where = [];
if ($source === 'gate') {
    $where[] = "i.incident_type IN ('unauthorised', 'damaged_goods', 'access_violation', 'cargo', 'exception')";
}
if ($status !== 'all' && in_array($status, ['open', 'acknowledged', 'resolved'], true)) {
    $where[] = "i.status = '" . pt_pg_escape($conn, $status) . "'";
}
if ($severity !== 'all' && in_array($severity, ['high', 'med', 'low'], true)) {
    $where[] = "i.severity = '" . pt_pg_escape($conn, $severity) . "'";
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT i.inc_id, i.d_id, i.trip_id, i.driver_id, i.incident_type, i.severity, i.description,
               i.lat, i.lng, i.photo_path, i.assistance, i.reassigned_d_id, i.status,
               i.reported_at, i.resolved_at,
               d.d_truck AS truck_plate, d.booking_no, d.d_driverName
        FROM incident i
        LEFT JOIN dispatch d ON d.d_id = i.d_id
        $whereSql
        ORDER BY i.inc_id DESC LIMIT $limit";
$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch()) { $rows[] = $r; }
echo json_encode(['status' => 'success', 'rows' => $rows]);
