<?php
include '../php/config/config.php';

$fromDate = $_GET['fromDate'] ?? '';
$toDate   = $_GET['toDate'] ?? '';

$sql = "
SELECT 
    t.unit_name,
    COUNT(tr.trip_id) AS done_trips
FROM trailer t
LEFT JOIN dispatch d 
    ON d.d_truck = t.unit_name
LEFT JOIN trips tr 
    ON tr.d_id = d.d_id 
   AND tr.trip_status = 'Done'
WHERE t.unit_name NOT LIKE 'GS%'";

// Apply date filter if provided
if (!empty($fromDate) && !empty($toDate)) {
    $sql .= " AND DATE(d.d_datetime) BETWEEN '$fromDate' AND '$toDate'";
}

$sql .= " GROUP BY t.unit_name
          ORDER BY done_trips DESC";

$res = $conn->query($sql);

$data = [];
while ($row = ($res)->fetch()) {
    $data[] = [
        'unit_name' => $row['unit_name'],
        'done_trips' => (int)$row['done_trips']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
