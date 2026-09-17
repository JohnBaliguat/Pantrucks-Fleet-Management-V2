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
$toDate   = trim((string)($_GET['toDate'] ?? ''));

if ($driverId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'driver_id required']);
    exit;
}

$tripDateSql = '';
$tripDateBind = '';
$tripDateParams = [];
$attDateSql = '';
$attDateBind = '';
$attDateParams = [];

if ($fromDate !== '' && $toDate !== '') {
    $tripDateSql = " AND DATE(d.d_datetime) BETWEEN ? AND ?";
    $tripDateBind = "ss";
    $tripDateParams = [$fromDate, $toDate];
    $attDateSql = " AND DATE(da.da_date) BETWEEN ? AND ?";
    $attDateBind = "ss";
    $attDateParams = [$fromDate, $toDate];
}

$stmt = $conn->prepare(
    "SELECT driver_id,
            driver_idnumber,
            CONCAT(driver_fname, ' ', driver_lname) AS driver_name,
            driver_assignsegment AS segment,
            driver_status
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

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS active_violations,
            MAX(vr_date) AS last_violation_date
     FROM violation_record
     WHERE driver_id = ?
       AND vr_status = 'Active'"
);
$stmt->execute([$driverId]);
$violation = $stmt->fetch();
$sql = "SELECT
            COUNT(t.trip_id) AS total_trips,
            SUM(CASE WHEN t.trip_status = 'Done' THEN 1 ELSE 0 END) AS done_trips,
            SUM(CASE WHEN t.trip_status IS NOT NULL AND LOWER(t.trip_status) <> 'done' THEN 1 ELSE 0 END) AS pending_trips
        FROM dispatch d
        INNER JOIN trips t ON t.d_id = d.d_id
        WHERE d.driver_id = ?" . $tripDateSql;
$stmt = $conn->prepare($sql);
$bindTypes = "i" . $tripDateBind;
$params = array_merge([$driverId], $tripDateParams);
$stmt->execute($params);
$tripAgg = $stmt->fetch();
$sql = "SELECT
            SUM(CASE WHEN da.da_status = 'Present' THEN 1 ELSE 0 END) AS days_present,
            SUM(CASE WHEN da.da_status = 'Absent' THEN 1 ELSE 0 END) AS days_absent,
            SUM(CASE WHEN da.da_status IN ('VL','SL') THEN 1 ELSE 0 END) AS days_leave
        FROM drivers_attendance da
        WHERE da.driver_id = ?" . $attDateSql;
$stmt = $conn->prepare($sql);
$bindTypes = "i" . $attDateBind;
$params = array_merge([$driverId], $attDateParams);
$stmt->execute($params);
$attendance = $stmt->fetch();
$totalTrips = (int)($tripAgg['total_trips'] ?? 0);
$doneTrips = (int)($tripAgg['done_trips'] ?? 0);
$pendingTrips = (int)($tripAgg['pending_trips'] ?? 0);
$daysPresent = (int)($attendance['days_present'] ?? 0);
$daysAbsent = (int)($attendance['days_absent'] ?? 0);
$daysLeave = (int)($attendance['days_leave'] ?? 0);
$activeViolations = (int)($violation['active_violations'] ?? 0);

$efficiency = $totalTrips > 0 ? round(($doneTrips / $totalTrips) * 100, 1) : 0.0;
$attDays = $daysPresent + $daysAbsent + $daysLeave;
$presentPct = $attDays > 0 ? round(($daysPresent / $attDays) * 100, 1) : 0.0;

if ($totalTrips === 0 && $attDays === 0) {
    $recommendation = 'No Activity';
} elseif ($activeViolations >= 2 || $efficiency < 70 || $presentPct < 70) {
    $recommendation = 'Critical Review';
} elseif ($activeViolations >= 1 || $efficiency < 90 || $presentPct < 90) {
    $recommendation = 'Needs Coaching';
} else {
    $recommendation = 'Top Performer';
}

$segmentRows = [];
$sql = "SELECT COALESCE(NULLIF(TRIM(t.trip_haulingsegment), ''), 'Unknown') AS segment,
               COUNT(t.trip_id) AS trip_count
        FROM dispatch d
        INNER JOIN trips t ON t.d_id = d.d_id
        WHERE d.driver_id = ?" . $tripDateSql . "
        GROUP BY COALESCE(NULLIF(TRIM(t.trip_haulingsegment), ''), 'Unknown')
        ORDER BY trip_count DESC";
$stmt = $conn->prepare($sql);
$bindTypes = "i" . $tripDateBind;
$params = array_merge([$driverId], $tripDateParams);
$stmt->execute($params);
$res = $stmt;
while ($row = $res->fetch()) {
    $segmentRows[] = [
        'segment' => $row['segment'],
        'count' => (int)$row['trip_count'],
    ];
}
$trendRows = [];
$sql = "SELECT DATE(d.d_datetime) AS trip_date,
               COUNT(t.trip_id) AS total_count,
               SUM(CASE WHEN t.trip_status = 'Done' THEN 1 ELSE 0 END) AS done_count
        FROM dispatch d
        INNER JOIN trips t ON t.d_id = d.d_id
        WHERE d.driver_id = ?" . $tripDateSql . "
        GROUP BY DATE(d.d_datetime)
        ORDER BY DATE(d.d_datetime) ASC";
$stmt = $conn->prepare($sql);
$bindTypes = "i" . $tripDateBind;
$params = array_merge([$driverId], $tripDateParams);
$stmt->execute($params);
$res = $stmt;
while ($row = $res->fetch()) {
    $trendRows[] = [
        'date' => $row['trip_date'],
        'total' => (int)$row['total_count'],
        'done' => (int)$row['done_count'],
    ];
}
$recentTrips = [];
$sql = "SELECT
            t.trip_id,
            COALESCE(d.d_truck, '-') AS truck,
            COALESCE(t.costumer, '-') AS customer,
            COALESCE(NULLIF(TRIM(t.trip_haulingsegment), ''), '-') AS segment,
            COALESCE(t.trip_status, '-') AS status,
            TO_CHAR(d.d_datetime, 'YYYY-MM-DD HH12:MI AM') AS dispatched_at
        FROM dispatch d
        INNER JOIN trips t ON t.d_id = d.d_id
        WHERE d.driver_id = ?" . $tripDateSql . "
        ORDER BY d.d_datetime DESC, t.trip_id DESC
        LIMIT 20";
$stmt = $conn->prepare($sql);
$bindTypes = "i" . $tripDateBind;
$params = array_merge([$driverId], $tripDateParams);
$stmt->execute($params);
$res = $stmt;
while ($row = $res->fetch()) {
    $recentTrips[] = $row;
}
echo json_encode([
    'status' => 'success',
    'driver' => [
        'driver_id' => (int)$driver['driver_id'],
        'id_number' => $driver['driver_idnumber'],
        'name' => $driver['driver_name'],
        'segment' => $driver['segment'] ?: '-',
        'driver_status' => $driver['driver_status'] ?: 'Good',
        'active_violations' => $activeViolations,
        'last_violation_date' => $violation['last_violation_date'] ?? null,
        'total_trips' => $totalTrips,
        'done_trips' => $doneTrips,
        'pending_trips' => $pendingTrips,
        'days_present' => $daysPresent,
        'days_absent' => $daysAbsent,
        'days_leave' => $daysLeave,
        'efficiency_percent' => $efficiency,
        'present_percent' => $presentPct,
        'recommendation' => $recommendation,
    ],
    'attendance' => [
        'present' => $daysPresent,
        'absent' => $daysAbsent,
        'leave' => $daysLeave,
    ],
    'trip_status' => [
        'done' => $doneTrips,
        'pending' => $pendingTrips,
    ],
    'segments' => $segmentRows,
    'trend' => $trendRows,
    'recent_trips' => $recentTrips,
]);
