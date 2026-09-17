<?php
// Trips for one piece of equipment in a date range — powers the Equipment
// Utilization modal. For Admin / Dispatch Admin / Dispatcher.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$type = strtolower(trim($_GET['type'] ?? ''));
$name = trim($_GET['name'] ?? '');
$fieldMap = ['truck' => 'd_truck', 'trailer' => 'd_trailer', 'genset' => 'd_genset'];
if (!isset($fieldMap[$type]) || $name === '') {
    echo json_encode(['status' => 'error', 'message' => 'type and name are required.']);
    exit;
}
$field = $fieldMap[$type];

function et_date(?string $v, string $default): string {
    $v = trim((string)$v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $default;
}
$today = date('Y-m-d');
$from  = et_date($_GET['from'] ?? '', $today);
$to    = et_date($_GET['to'] ?? '', $today);
if ($from > $to) { [$from, $to] = [$to, $from]; }

$sql = "SELECT d.booking_no, d.d_datetime, d.d_drivername, d.d_truck, d.d_trailer, d.d_genset,
               d.d_tripreceipt, d.costumer,
               t.trip_type, t.trip_from, t.trip_to, t.trip_container, t.trip_status,
               t.trip_haulingsegment, t.required_date
        FROM trips t
        JOIN dispatch d ON d.d_id = t.d_id
        WHERE UPPER(TRIM(d.$field)) = UPPER(TRIM(?))
          AND d.d_datetime::date BETWEEN ? AND ?
        ORDER BY d.d_datetime DESC, t.trip_id ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$name, $from, $to]);

$rows = [];
while ($r = $stmt->fetch()) {
    $rows[] = [
        'booking_no'   => $r['booking_no'],
        'trip_receipt' => $r['d_tripreceipt'],
        'dispatched'   => $r['d_datetime'],
        'driver'       => $r['d_drivername'],
        'customer'     => $r['costumer'],
        'trip_type'    => $r['trip_type'],
        'route'        => trim((string)$r['trip_from']) . ' → ' . trim((string)$r['trip_to']),
        'container'    => $r['trip_container'],
        'segment'      => $r['trip_haulingsegment'],
        'status'       => $r['trip_status'],
        'required'     => $r['required_date'],
        'truck'        => $r['d_truck'],
        'trailer'      => $r['d_trailer'],
        'genset'       => $r['d_genset'],
    ];
}

echo json_encode([
    'status' => 'success',
    'type'   => $type,
    'name'   => $name,
    'from'   => $from,
    'to'     => $to,
    'rows'   => $rows,
]);
