<?php
include "../config/config.php";

$sql = "SELECT driver_id, driver_fname, driver_mname, driver_lname
        FROM drivers
        WHERE (driver_status = 'Good' OR driver_status = '')
          AND NOT EXISTS (
              SELECT 1
              FROM violation_record v
              WHERE v.driver_id = drivers.driver_id
                AND v.vr_status = 'Active'
          )
        ORDER BY driver_lname ASC";
$result = $conn->query($sql);

$drivers = [];
while ($row = ($result)->fetch()) {
    $formattedName = strtoupper($row['driver_lname']) . ", " . strtoupper($row['driver_fname']) . ".";

    

    $drivers[] = [
        'id' => $row['driver_id'],
        'name' => $formattedName
    ];
}

header('Content-Type: application/json');
echo json_encode($drivers);
