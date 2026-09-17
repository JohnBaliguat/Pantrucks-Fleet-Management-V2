<?php
session_start();
header('Content-Type: application/json');
include '../config/config.php';

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['HR-Admin', 'Visual'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$driverId = (int)($_GET['driver_id'] ?? 0);
$fromDate = trim((string)($_GET['fromDate'] ?? ''));
$toDate = trim((string)($_GET['toDate'] ?? ''));
$violationType = trim((string)($_GET['violation'] ?? ''));

if ($driverId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'driver_id required']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT driver_id,
            driver_idnumber,
            CONCAT(driver_fname, ' ', driver_lname) AS driver_name,
            COALESCE(NULLIF(TRIM(driver_assignunit), ''), '-') AS driver_unit,
            COALESCE(NULLIF(TRIM(driver_assignsegment), ''), '-') AS driver_segment
     FROM drivers
     WHERE driver_id = ?
     LIMIT 1"
);
$stmt->execute([$driverId]);
$driver = $stmt->fetch();
if (!$driver) {
    echo json_encode(['status' => 'error', 'message' => 'Driver not found']);
    exit;
}

$where = ["v.driver_id = ?"];
$bindTypes = "i";
$bindValues = [$driverId];

if ($fromDate !== '' && $toDate !== '') {
    $where[] = "DATE(v.vr_date) BETWEEN ? AND ?";
    $bindTypes .= "ss";
    $bindValues[] = $fromDate;
    $bindValues[] = $toDate;
}

if ($violationType !== '') {
    $where[] = "v.vr_type = ?";
    $bindTypes .= "s";
    $bindValues[] = $violationType;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_violations,
        SUM(v.vr_status = 'Active') AS active_violations,
        SUM(v.vr_status = 'Done') AS resolved_violations,
        MAX(v.vr_date) AS last_violation_date
     FROM violation_record v
     $whereSql"
);
$stmt->execute($bindValues);
$summary = $stmt->fetch();
$types = [];
$stmt = $conn->prepare(
    "SELECT v.vr_type, COUNT(*) AS violation_count
     FROM violation_record v
     $whereSql
     GROUP BY v.vr_type
     ORDER BY violation_count DESC, v.vr_type ASC"
);
$stmt->execute($bindValues);
$res = $stmt;
while ($row = $res->fetch()) {
    $types[] = [
        'type' => $row['vr_type'],
        'count' => (int)$row['violation_count'],
    ];
}
$trend = [];
$stmt = $conn->prepare(
    "SELECT DATE(v.vr_date) AS violation_date, COUNT(*) AS violation_count
     FROM violation_record v
     $whereSql
     GROUP BY DATE(v.vr_date)
     ORDER BY DATE(v.vr_date) ASC"
);
$stmt->execute($bindValues);
$res = $stmt;
while ($row = $res->fetch()) {
    $trend[] = [
        'date' => $row['violation_date'],
        'count' => (int)$row['violation_count'],
    ];
}
$totalViolations = (int)($summary['total_violations'] ?? 0);
$activeViolations = (int)($summary['active_violations'] ?? 0);
$resolvedViolations = (int)($summary['resolved_violations'] ?? 0);
$activePercent = $totalViolations > 0 ? round(($activeViolations / $totalViolations) * 100, 1) : 0.0;
$resolvedPercent = $totalViolations > 0 ? round(($resolvedViolations / $totalViolations) * 100, 1) : 0.0;

echo json_encode([
    'status' => 'success',
    'driver' => [
        'driver_id' => (int)$driver['driver_id'],
        'id_number' => $driver['driver_idnumber'],
        'name' => $driver['driver_name'],
        'unit' => $driver['driver_unit'],
        'segment' => $driver['driver_segment'],
        'total_violations' => $totalViolations,
        'active_violations' => $activeViolations,
        'resolved_violations' => $resolvedViolations,
        'last_violation_date' => $summary['last_violation_date'] ?? null,
        'top_violation_type' => $types[0]['type'] ?? null,
    ],
    'status_split' => [
        'active' => $activeViolations,
        'done' => $resolvedViolations,
    ],
    'percentages' => [
        'active_percent' => $activePercent,
        'resolved_percent' => $resolvedPercent,
    ],
    'types' => $types,
    'trend' => $trend,
]);
