<?php
include '../php/config/config.php';

$fromDate = $_GET['fromDate'] ?? '';
$toDate   = $_GET['toDate'] ?? '';

$sql = "SELECT 
    u.unit_name,
    COUNT(t.trip_id) AS total_done_trips
FROM units u
LEFT JOIN dispatch d 
    ON d.d_truck = u.unit_name
LEFT JOIN trips t 
    ON t.d_id = d.d_id 
   AND t.trip_status = 'Done'
WHERE u.unit_name NOT LIKE 'GS%'";

// Apply date filter if provided
if (!empty($fromDate) && !empty($toDate)) {
    $sql .= " AND DATE(d.d_datetime) BETWEEN '$fromDate' AND '$toDate'";
}

$sql .= " GROUP BY u.unit_name
          ORDER BY total_done_trips DESC";

$res = $conn->query($sql);

$data = [];
while ($row = ($res)->fetch()) {
    $data[] = [
        'unit_name' => $row['unit_name'],
        'done_trips' => (int)$row['total_done_trips']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
