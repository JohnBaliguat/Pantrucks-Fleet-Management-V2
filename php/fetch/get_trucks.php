<?php
include "../config/config.php";

$sql = "SELECT unit_name
        FROM units
        WHERE unit_name NOT LIKE 'GS%'
          AND unit_status = 'Available'
          AND maintenance_blocked = FALSE
          AND dispatch_blocked = FALSE
        ORDER BY unit_name ASC";
$result = $conn->query($sql);

$trucks = [];
while ($row = ($result)->fetch()) {
    $trucks[] = $row['unit_name'];
}

header('Content-Type: application/json');
echo json_encode($trucks);
