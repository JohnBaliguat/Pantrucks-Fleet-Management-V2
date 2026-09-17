<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

// Prevent stray PHP warnings from corrupting the JSON DataTables expects.
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

$fromDate = $_POST['fromDate'] ?? '';
$toDate   = $_POST['toDate'] ?? '';
$segment  = $_POST['segmentFilter'] ?? '';
$driver   = $_POST['driverFilter'] ?? '';

$dateCondTrips = '';
$dateCondAttend = '';
$whereMain = [];

if (!empty($fromDate) && !empty($toDate)) {
    $from = pt_pg_escape($conn, $fromDate);
    $to   = pt_pg_escape($conn, $toDate);
    $dateCondTrips  = " AND DATE(ds.d_datetime) BETWEEN '$from' AND '$to'";
    $dateCondAttend = " AND DATE(da.da_date) BETWEEN '$from' AND '$to'";
}

if (!empty($segment)) {
    $seg = pt_pg_escape($conn, $segment);
    $whereMain[] = "d.driver_assignSegment = '$seg'";
}

if (!empty($driver)) {
    $drv = (int)$driver;
    $whereMain[] = "d.driver_id = $drv";
}

$whereSql = '';
if (!empty($whereMain)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereMain);
}

$sql = "
SELECT
    d.driver_id,
    CONCAT(d.driver_fname, ' ', d.driver_lname) AS driver_name,
    d.driver_assignSegment AS segment,
    COALESCE(tot.total_trips, 0)   AS total_trips,
    COALESCE(tot.trips_done, 0)    AS trips_done,
    COALESCE(tot.trips_pending, 0) AS trips_pending,
    COALESCE(att.days_present, 0)  AS days_present,
    COALESCE(att.days_absent, 0)   AS days_absent,
    COALESCE(att.days_leave, 0)    AS days_leave,
    COALESCE(vio.active_violations, 0) AS active_violations,
    CASE
        WHEN COALESCE(tot.total_trips, 0) = 0 THEN 0
        ELSE ROUND((COALESCE(tot.trips_done, 0) / COALESCE(tot.total_trips, 0)) * 100, 1)
    END AS efficiency_percent,
    CASE
        WHEN (COALESCE(att.days_present,0) + COALESCE(att.days_absent,0) + COALESCE(att.days_leave,0)) = 0 THEN 0
        ELSE ROUND((COALESCE(att.days_present,0) / (COALESCE(att.days_present,0) + COALESCE(att.days_absent,0) + COALESCE(att.days_leave,0))) * 100, 1)
    END AS percent_present
FROM drivers d
LEFT JOIN (
    SELECT
        ds.driver_id,
        COUNT(t.trip_id) AS total_trips,
        SUM(CASE WHEN t.trip_status = 'Done' THEN 1 ELSE 0 END) AS trips_done,
        SUM(CASE WHEN t.trip_status IS NOT NULL AND LOWER(t.trip_status) <> 'done' THEN 1 ELSE 0 END) AS trips_pending
    FROM dispatch ds
    JOIN trips t ON t.d_id = ds.d_id
    WHERE 1=1
    $dateCondTrips
    GROUP BY ds.driver_id
) tot ON tot.driver_id = d.driver_id
LEFT JOIN (
    SELECT
        da.driver_id,
        SUM(CASE WHEN da.da_status = 'Present' THEN 1 ELSE 0 END) AS days_present,
        SUM(CASE WHEN da.da_status = 'Absent' THEN 1 ELSE 0 END) AS days_absent,
        SUM(CASE WHEN da.da_status IN ('VL','SL') THEN 1 ELSE 0 END) AS days_leave
    FROM drivers_attendance da
    WHERE 1=1
    $dateCondAttend
    GROUP BY da.driver_id
) att ON att.driver_id = d.driver_id
LEFT JOIN (
    SELECT driver_id, COUNT(*) AS active_violations
    FROM violation_record
    WHERE vr_status = 'Active'
    GROUP BY driver_id
) vio ON vio.driver_id = d.driver_id
$whereSql
ORDER BY d.driver_lname ASC, d.driver_fname ASC
";

$result = $conn->query($sql);
$data = [];

while ($row = ($result)->fetch()) {
    $totalTrips = (int)$row['total_trips'];
    $doneTrips = (int)$row['trips_done'];
    $pendingTrips = (int)$row['trips_pending'];
    $daysPresent = (int)$row['days_present'];
    $daysAbsent = (int)$row['days_absent'];
    $daysLeave = (int)$row['days_leave'];
    $activeViolations = (int)$row['active_violations'];
    $efficiency = (float)$row['efficiency_percent'];
    $presentPct = (float)$row['percent_present'];

    if ($totalTrips === 0 && ($daysPresent + $daysAbsent + $daysLeave) === 0) {
        $recommendation = 'No Activity';
    } elseif ($activeViolations >= 2 || $efficiency < 70 || $presentPct < 70) {
        $recommendation = 'Critical Review';
    } elseif ($activeViolations >= 1 || $efficiency < 90 || $presentPct < 90) {
        $recommendation = 'Needs Coaching';
    } else {
        $recommendation = 'Top Performer';
    }

    $data[] = [
        'driver_id' => (int)$row['driver_id'],
        'driver_name' => $row['driver_name'],
        'segment' => $row['segment'] ?: '-',
        'total_trips' => $totalTrips,
        'trips_done' => $doneTrips,
        'trips_pending' => $pendingTrips,
        'days_present' => $daysPresent,
        'days_absent' => $daysAbsent,
        'days_leave' => $daysLeave,
        'active_violations' => $activeViolations,
        'efficiency_percent' => number_format($efficiency, 1),
        'percent_present' => number_format($presentPct, 1),
        'recommendation' => $recommendation,
    ];
}

echo json_encode(['data' => $data]);
