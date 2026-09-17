<?php
include "../config/config.php";

$sql = "
SELECT 
    b.costumer,
    SUM(b.quantity) AS total_book,
    COALESCE(SUM(d.quantity_use), 0) AS assigned,
    COALESCE(SUM(CASE WHEN t.trip_status = 'Done' THEN 1 ELSE 0 END), 0) AS done
FROM booking b
LEFT JOIN dispatch d 
    ON d.costumer = b.costumer
LEFT JOIN trips t 
    ON t.costumer = b.costumer
GROUP BY b.costumer
ORDER BY b.costumer
";

$result = $conn->query($sql);

$data = [];

while ($row = ($result)->fetch()) {
    $data[] = $row;
}

header("Content-Type: application/json");
echo json_encode($data);
