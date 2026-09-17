<?php
include "../config/config.php";
header('Content-Type: application/json');

$fromDate = trim((string)($_GET['fromDate'] ?? ''));
$toDate = trim((string)($_GET['toDate'] ?? ''));

$dispatchWhere = [];
$dispatchBindTypes = '';
$dispatchBindValues = [];

if ($fromDate !== '') {
    $dispatchWhere[] = "DATE(d.d_datetime) >= ?";
    $dispatchBindTypes .= 's';
    $dispatchBindValues[] = $fromDate;
}

if ($toDate !== '') {
    $dispatchWhere[] = "DATE(d.d_datetime) <= ?";
    $dispatchBindTypes .= 's';
    $dispatchBindValues[] = $toDate;
}

$dispatchWhereSql = $dispatchWhere ? (' AND ' . implode(' AND ', $dispatchWhere)) : '';

$driverCount = 0;
$doneTripCount = 0;
$activeDispatchCount = 0;

$driverRes = $conn->query("SELECT COUNT(*) AS total_drivers FROM drivers");
if ($driverRes) {
    $driverCount = (int)(($driverRes)->fetch()['total_drivers'] ?? 0);
}

$doneSql = "
    SELECT COUNT(t.trip_id) AS done_trip_count
    FROM trips t
    INNER JOIN dispatch d ON d.d_id = t.d_id
    WHERE t.trip_status = 'Done'
    $dispatchWhereSql
";
$stmt = $conn->prepare($doneSql);
$stmt->execute($dispatchBindValues);
$doneTripCount = (int)($stmt->fetch()['done_trip_count'] ?? 0);
$activeSql = "
    SELECT COUNT(*) AS active_dispatch_count
    FROM dispatch d
    WHERE d.workflow_stage IN ('dispatcher_assigned', 'reassigned', 'driver_accepted', 'gate_cleared', 'en_route', 'delivered', 'pending_verification')
    $dispatchWhereSql
";
$stmt = $conn->prepare($activeSql);
$stmt->execute($dispatchBindValues);
$activeDispatchCount = (int)($stmt->fetch()['active_dispatch_count'] ?? 0);
echo json_encode([
    'driver_count' => $driverCount,
    'done_trip_count' => $doneTripCount,
    'active_dispatch_count' => $activeDispatchCount,
]);
