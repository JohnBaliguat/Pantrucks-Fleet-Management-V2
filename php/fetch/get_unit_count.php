<?php
include "../config/config.php";

$query = "
    SELECT 
        SUM(CASE WHEN unit_status = 'Rescue' THEN 1 ELSE 0 END) AS rescue_count,
        SUM(CASE WHEN unit_status = 'Shop Unit' THEN 1 ELSE 0 END) AS shop_count
    FROM units
";

$result = $conn->query($query);
$data = ($result)->fetch();

echo json_encode($data);
