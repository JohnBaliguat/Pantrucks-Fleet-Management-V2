<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

// Total Drivers
$totalDriversQuery = "SELECT COUNT(*) as total FROM drivers";
$totalDriversResult = $conn->query($totalDriversQuery);
$totalDrivers = ($totalDriversResult)->fetch()['total'];

// Dispatch
$dispatchQuery = "SELECT COUNT(*) as total FROM drivers WHERE driver_status='Dispatch'";
$dispatchResult = $conn->query($dispatchQuery);
$dispatch = ($dispatchResult)->fetch()['total'];

// N-Dispatch
$ndispatchQuery = "SELECT COUNT(*) as total FROM drivers WHERE driver_status !='Dispatch'";
$ndispatchResult = $conn->query($ndispatchQuery);
$ndispatch = ($ndispatchResult)->fetch()['total'];

// VL/SL
$today = date('Y-m-d');
$vlslQuery = "
    SELECT COUNT(*) AS total 
    FROM drivers_attendance 
    WHERE (da_status='VL' OR da_status='SL')
      AND vl_sl_dateStart IS NOT NULL 
      AND vl_sl_date IS NOT NULL
      AND '$today' BETWEEN vl_sl_dateStart AND vl_sl_date
";
$vlslResult = $conn->query($vlslQuery);
$vlsl = ($vlslResult)->fetch()['total'];

// Others
$othersQuery = "SELECT COUNT(*) AS total 
    FROM drivers_attendance 
    WHERE da_status='Others'
      AND da_date = CURRENT_DATE";
$othersResult = $conn->query($othersQuery);
$others = ($othersResult)->fetch()['total'];

// Rest Day
$restDayQuery = "SELECT COUNT(*) as total FROM drivers_attendance WHERE da_status='Absent' AND da_date = CURRENT_DATE";
$restDayResult = $conn->query($restDayQuery);
$restDay = ($restDayResult)->fetch()['total'];

echo json_encode([
    "totalDrivers" => $totalDrivers,
    "dispatch" => $dispatch,
    "ndispatch" => $ndispatch,
    "vlsl" => $vlsl,
    "others" => $others,
    "restDay" => $restDay
]);
?>
