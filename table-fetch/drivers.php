<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

// Stop stray PHP warnings from contaminating the JSON DataTables expects.
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

// ---------------- Get Filters ---------------- //
$segmentFilter = $_POST['segmentFilter'] ?? [];
$statusFilter  = $_POST['statusFilter'] ?? [];
$unitFilter    = $_POST['unitFilter'] ?? [];

// ---------------- Subqueries ---------------- //

// ✅ Latest dispatch per driver
$dispatchSub = "
    SELECT d1.*
    FROM dispatch d1
    INNER JOIN (
        SELECT driver_id, MAX(d_datetime) AS max_date
        FROM dispatch
        GROUP BY driver_id
    ) d2 
    ON d1.driver_id = d2.driver_id AND d1.d_datetime = d2.max_date
";

// ✅ Latest trip per dispatch
$tripSub = "
    SELECT t1.d_id, t1.trip_status, t1.trip_haulingsegment
    FROM trips t1
    INNER JOIN (
        SELECT d_id, MAX(trip_id) AS max_trip
        FROM trips
        GROUP BY d_id
    ) t2 
    ON t1.d_id = t2.d_id AND t1.trip_id = t2.max_trip
";

// ✅ Latest attendance per driver (today only)
$attendanceSub = "
    SELECT driver_id, da_status, da_date, vl_sl_dateprepared, vl_sl_date
    FROM (
        SELECT da1.*, 
               ROW_NUMBER() OVER (PARTITION BY driver_id ORDER BY da_date DESC) AS rn
        FROM drivers_attendance da1
        WHERE DATE(da1.da_date) = CURRENT_DATE
    ) ranked
    WHERE rn = 1
";

// ---------------- Base Query ---------------- //
$sql = "
    SELECT 
        dr.driver_id,
        CONCAT(dr.driver_fname, ' ', dr.driver_lname) AS driver_name,
        dr.driver_assignUnit AS assignUnit,
        dr.driver_assignSegment AS segment,
        
        -- ✅ Determine driver status: prioritize VL/SL if within valid range
        CASE 
            WHEN da.da_status IN ('VL', 'SL') 
                 AND CURRENT_DATE BETWEEN da.vl_sl_datePrepared AND da.vl_sl_date
                 THEN da.da_status
            ELSE dr.driver_status
        END AS driver_status,

        COALESCE(ds.d_dispatcher, 'No Dispatch Yet') AS dispatch_by,
        ds.d_truck AS dispatchedUnit,
        ds.d_datetime AS dispatch_datetime,
        ts.trip_status,
        ts.trip_haulingSegment,
        u.unit_status,

        CASE 
            WHEN ts.trip_status IS NULL THEN 0
            WHEN LOWER(ts.trip_status) = 'done' THEN 0
            ELSE 1
        END AS isDispatched
    FROM drivers dr
    LEFT JOIN ($dispatchSub) ds ON dr.driver_id = ds.driver_id
    LEFT JOIN ($tripSub) ts ON ds.d_id = ts.d_id
    LEFT JOIN ($attendanceSub) da ON dr.driver_id = da.driver_id
    LEFT JOIN units u ON ds.d_truck = u.unit_name
";

// ---------------- Apply Filters ---------------- //
$where = [];

// Segment filter
if (!empty($segmentFilter)) {
    $segments = "'" . implode("','", array_map(fn($v) => pt_pg_escape($conn, $v), $segmentFilter)) . "'";
    $where[] = " (ts.trip_haulingSegment IN ($segments) OR dr.driver_assignSegment IN ($segments)) ";
}

// Status filter
if (!empty($statusFilter)) {
    $hasDispatch = in_array("Dispatch", $statusFilter);
    $hasNotDispatch = in_array("Not Dispatch", $statusFilter);

    if ($hasDispatch && !$hasNotDispatch) {
        $where[] = " (ts.trip_status IS NOT NULL AND LOWER(ts.trip_status) <> 'done') ";
    } elseif ($hasNotDispatch && !$hasDispatch) {
        $where[] = " (ts.trip_status IS NULL OR LOWER(ts.trip_status) = 'done') ";
    }
}

// Unit filter
if (!empty($unitFilter)) {
    $units = "'" . implode("','", array_map(fn($v) => pt_pg_escape($conn, $v), $unitFilter)) . "'";
    $where[] = " ds.d_truck IN ($units) ";
}

// Combine filters
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

// Sort by dispatch status then name
$sql .= " ORDER BY isDispatched DESC, dr.driver_lname ASC";

// ---------------- Execute Query ---------------- //
$result = $conn->query($sql);

$data = [];
$no = 1;

while ($row = ($result)->fetch()) {
    // Postgres folds unquoted SQL aliases to lowercase, so PDO returns
    // keys like 'assignunit' / 'dispatchedunit' regardless of the AS case.
    $assignUnit     = ($row['assignunit'] ?? '') ?: '-';
    $dispatchBy     = ($row['dispatch_by'] ?? '') ?: 'No Dispatch Yet';
    $dispatchedUnit = ($row['dispatchedunit'] ?? '') ?: '-';
    $unitStatus     = ($row['unit_status'] ?? '') ?: '-';
    $driverStatus   = ($row['driver_status'] ?? '') ?: 'Good';
    $segment        = ($row['trip_haulingsegment'] ?? '') ?: ($row['segment'] ?? '');
    $dispatchDate   = !empty($row['dispatch_datetime'])
                        ? date('M d, Y h:i A', strtotime($row['dispatch_datetime']))
                        : '-';

    // If trip is DONE → reset info
    if (strtolower($row['trip_status']) === 'done') {
        $dispatchedUnit = '-';
        $unitStatus     = '-';
        $driverStatus   = 'Good';
        $dispatchBy     = 'No Dispatch Yet';
        $dispatchDate   = '-';
    }

    $data[] = [
        $no++,
        $row['driver_name'],
        $assignUnit,
        $dispatchedUnit,
        $unitStatus,
        $driverStatus,
        $dispatchDate,
        $dispatchBy,
        $segment
    ];
}

// ---------------- Return as JSON ---------------- //
echo json_encode(["data" => $data]);
?>
