<?php
include "../config/config.php";

$driver_id = $_GET['driver_id'] ?? '';

$data = [];

$sql = "
SELECT 
    d.booking_no,
    t.trip_haulingSegment,
    t.trip_from,
    t.trip_to,
    t.trip_status
FROM dispatch d
INNER JOIN trips t ON t.d_id = d.d_id
WHERE d.driver_id = '$driver_id'
  AND t.trip_status = 'Active'
ORDER BY d.d_datetime DESC
";

$result = $conn->query($sql);

while ($row = ($result)->fetch()) {
    $data[] = $row;
}

echo json_encode($data);
