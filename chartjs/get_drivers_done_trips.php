<?php
include '../php/config/config.php';

$fromDate = $_GET['fromDate'] ?? '';
$toDate   = $_GET['toDate'] ?? '';

$sql = "
SELECT 
    CONCAT(dr.driver_lname, ', ', LEFT(dr.driver_fname, 1), '.') AS driver_name,
    COUNT(tr.trip_id) AS total_done_trips
FROM drivers dr
LEFT JOIN dispatch d 
    ON d.driver_id = dr.driver_id
LEFT JOIN trips tr 
    ON tr.d_id = d.d_id 
   AND tr.trip_status = 'Done'
WHERE 1=1
";

// Apply date filter if provided
if (!empty($fromDate) && !empty($toDate)) {
    $sql .= " AND DATE(d.d_datetime) BETWEEN '$fromDate' AND '$toDate'";
}

$sql .= " GROUP BY dr.driver_id, dr.driver_fname, dr.driver_lname
          ORDER BY total_done_trips DESC";

$res = $conn->query($sql);

$data = [];
while ($row = ($res)->fetch()) {
    $data[] = [
        'driver_name' => $row['driver_name'],
        'done_trips' => (int)$row['total_done_trips']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
