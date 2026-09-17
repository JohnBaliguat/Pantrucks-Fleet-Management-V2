<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

// Prevent stray PHP warnings from corrupting the JSON DataTables expects.
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

// retrieve filters
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
    // use in joined subqueries
    $dateCondTrips   = " AND DATE(ds.d_datetime) BETWEEN '$from' AND '$to'";
    $dateCondAttend  = " AND DATE(da.da_date) BETWEEN '$from' AND '$to'";
}

if (!empty($segment)) {
    $seg = pt_pg_escape($conn, $segment);
    $whereMain[] = "d.driver_assignSegment = '$seg'";
}

if (!empty($driver)) {
    $drv = intval($driver);
    $whereMain[] = "d.driver_id = $drv";
}

// build where clause for main driver table
$whereSql = '';
if (!empty($whereMain)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereMain);
}

// prepare the main query using aggregated subqueries to avoid duplication
$sql = "
SELECT
    d.driver_id,
    CONCAT(d.driver_fname, ' ', d.driver_lname) AS driver_name,
    d.driver_assignSegment AS segment,
    COALESCE(tot.total_trips,0)    AS total_trips,
    COALESCE(tot.trips_done,0)     AS trips_done,
    COALESCE(tot.trips_pending,0)  AS trips_pending,
    COALESCE(att.days_present,0)   AS days_present,
    COALESCE(att.days_absent,0)    AS days_absent,
    COALESCE(att.days_leave,0)     AS days_leave,
    -- calculate trip efficiency percentage (rounded to 1 decimal)
    CASE 
        WHEN COALESCE(tot.total_trips,0) = 0 THEN 0
        ELSE ROUND((COALESCE(tot.trips_done,0) / COALESCE(tot.total_trips,0)) * 100, 1)
    END AS efficiency_percent,
    -- calculate present percentage (rounded to 1 decimal)
    CASE 
        WHEN (COALESCE(att.days_present,0)+COALESCE(att.days_absent,0)+COALESCE(att.days_leave,0)) = 0 THEN 0
        ELSE ROUND((COALESCE(att.days_present,0) / (COALESCE(att.days_present,0)+COALESCE(att.days_absent,0)+COALESCE(att.days_leave,0))) * 100, 1)
    END AS percent_present
FROM drivers d

/* trips subquery ------------------------------------------------*/
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

/* attendance subquery -------------------------------------------*/
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

$whereSql
ORDER BY d.driver_lname ASC
";

$result = $conn->query($sql);

$data = [];
$no = 1;
while ($row = ($result)->fetch()) {
    $data[] = [
        $row['driver_id'],
        $row['driver_name'],
        $row['segment'] ?: '-',
        (int) $row['total_trips'],
        (int) $row['trips_done'],
        (int) $row['trips_pending'],
        (int) $row['days_present'],
        (int) $row['days_absent'],
        (int) $row['days_leave'],
        number_format($row['efficiency_percent'],1),
        number_format($row['percent_present'],1)
    ];
}

// return in DataTables expected format
echo json_encode(["data" => $data]);
?>
