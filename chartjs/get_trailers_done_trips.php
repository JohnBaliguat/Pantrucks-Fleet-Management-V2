<?php
include '../php/config/config.php';

$fromDate = $_GET['fromDate'] ?? '';
$toDate   = $_GET['toDate'] ?? '';

$sql = "
SELECT 
    t.trailer_name,
    COUNT(tr.trip_id) AS total_done_trips
FROM trailer t
LEFT JOIN dispatch d 
    ON d.d_trailer = t.trailer_name
LEFT JOIN trips tr 
    ON tr.d_id = d.d_id 
   AND tr.trip_status = 'Done'
WHERE t.trailer_name NOT LIKE 'GS%'";

// Apply date filter if provided
if (!empty($fromDate) && !empty($toDate)) {
    $sql .= " AND DATE(d.d_datetime) BETWEEN '$fromDate' AND '$toDate'";
}

$sql .= " GROUP BY t.trailer_name
          ORDER BY total_done_trips DESC";

$res = $conn->query($sql);

$data = [];
while ($row = ($res)->fetch()) {
    $data[] = [
        'trailer_name' => $row['trailer_name'],
        'done_trips' => (int)$row['total_done_trips']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
