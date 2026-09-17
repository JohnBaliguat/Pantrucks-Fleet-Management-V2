<?php
include "../config/config.php";

$unitId = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

/* 🔹 Get unit status */
$unitStatus = "Unknown";
$statusSql = "SELECT unit_status FROM units WHERE unit_id = $unitId";
$statusRes = $conn->query($statusSql);
if ($statusRes && $row = ($statusRes)->fetch()) {
    $unitStatus = $row['unit_status'];
}

/* 🔹 Get dispatch data */
$sql = "
  SELECT 
    d.d_id, d.booking_no, d.d_datetime, d.d_dispatcher, 
    d.d_driverName, d.d_truck, d.d_trailer, 
    t.trip_id, t.trip_type, t.trip_container, t.trip_containerStat,
    t.trip_haulingSegment, t.trip_haulingType, 
    t.trip_from, t.trip_to, 
    t.deliver_location, t.deliver_dateTime, 
    t.withdraw_location, t.withdraw_dateTime,
    t.required_date, t.trip_status
  FROM dispatch d
  INNER JOIN trips t ON d.d_id = t.d_id
  INNER JOIN units u ON u.unit_name = d.d_truck
  WHERE u.unit_id = $unitId 
    AND t.trip_status != 'Done'
  ORDER BY d.d_datetime DESC
";

$res = $conn->query($sql);

$data = [];
if ($res) {
    while ($row = ($res)->fetch()) {
        $data[] = $row;
    }
}

/* 🔹 Final JSON response */
header('Content-Type: application/json');
echo json_encode([
    "unit_status" => $unitStatus,
    "dispatch" => $data
]);
