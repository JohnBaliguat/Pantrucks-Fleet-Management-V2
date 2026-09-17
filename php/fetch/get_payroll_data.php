<?php
session_start();
include __DIR__ . "/../config/config.php";
header('Content-Type: application/json; charset=utf-8');

// Reject anonymous callers. Drivers may only see their own row.
$role = $_SESSION['user_type'] ?? '';
$me   = (int)($_SESSION['user_id'] ?? 0);
if (!$role || $me <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to']   ?? ''));

if ($dateFrom === '' || $dateTo === '') {
    echo json_encode(['success' => false, 'message' => 'Date range is required.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit;
}
if ($dateFrom > $dateTo) {
    echo json_encode(['success' => false, 'message' => 'Date from must not be later than date to.']);
    exit;
}

// Drivers are auto-scoped to themselves; other roles see everyone unless
// they pass driver_id (used by the per-driver detail drill-down).
$driverFilter = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
if ($role === 'Driver') {
    $driverFilter = $me;
}

// Earnings only count once the dispatch has been verified. Unverified
// dispatches stay invisible to payroll/earnings reporting.
$where  = [
    "DATE(d.d_datetime) BETWEEN ? AND ?",
    "d.verified_at IS NOT NULL",
    "(t.cancelled_at IS NULL OR (t.foul_trip = TRUE AND t.foul_approved = TRUE))",   // exclude clean cancellations; foul trips pay only once a dispatch admin approves
];
$params = [$dateFrom, $dateTo];

if ($driverFilter > 0) {
    $where[] = "d.driver_id = ?";
    $params[] = $driverFilter;
}

$whereSql = implode(' AND ', $where);

// Per-driver aggregate.
$sqlAgg = "
    SELECT d.driver_id,
           COALESCE(NULLIF(TRIM(d.d_drivername), ''), 'Unassigned') AS driver_name,
           COUNT(t.trip_id) AS total_trips,
           COALESCE(SUM(t.piece_rate), 0) AS total_earnings
    FROM trips t
    INNER JOIN dispatch d ON d.d_id = t.d_id
    WHERE $whereSql
    GROUP BY d.driver_id, d.d_drivername
    ORDER BY 4 DESC, 2 ASC
";

try {
    $stmt = $conn->prepare($sqlAgg);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Drilldown — list of individual trips for the filter (returned only when
// driver_id was explicitly passed so we don't dump everything).
$details = [];
if ($driverFilter > 0) {
    $sqlDet = "
        SELECT t.trip_id, t.trip_type, t.costumer, t.trip_haulingsegment,
               t.container_activity, t.trip_sku, t.trip_from, t.trip_to,
               t.trip_status, t.piece_rate,
               d.d_id, d.booking_no, d.d_datetime, d.d_truck
        FROM trips t
        INNER JOIN dispatch d ON d.d_id = t.d_id
        WHERE $whereSql
        ORDER BY d.d_datetime DESC, t.trip_id DESC
    ";
    $stmt = $conn->prepare($sqlDet);
    $stmt->execute($params);
    $details = $stmt->fetchAll();
}

$grandTotal = array_sum(array_map(fn($r) => (float)$r['total_earnings'], $rows));
$grandTrips = array_sum(array_map(fn($r) => (int)$r['total_trips'], $rows));

echo json_encode([
    'success'        => true,
    'date_from'      => $dateFrom,
    'date_to'        => $dateTo,
    'rows'           => array_map(fn($r) => [
        'driver_id'      => (int)$r['driver_id'],
        'driver_name'    => $r['driver_name'],
        'total_trips'    => (int)$r['total_trips'],
        'total_earnings' => number_format((float)$r['total_earnings'], 2, '.', ''),
    ], $rows),
    'summary'        => [
        'total_drivers'    => count($rows),
        'total_trips'      => $grandTrips,
        'grand_total'      => number_format($grandTotal, 2, '.', ''),
    ],
    'details'        => $details,
]);
