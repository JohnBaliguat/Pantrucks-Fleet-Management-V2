<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

$query = "SELECT driver_id, driver_fname, driver_mname, driver_lname FROM drivers ORDER BY driver_lname ASC";
$result = $conn->query($query);

$data = [];
while ($row = ($result)->fetch()) {
    $fullname = $row['driver_fname'] . ' ' . $row['driver_mname'] . ' ' . $row['driver_lname'];
    $data[] = [
        'driver_id' => $row['driver_id'],
        'driver_name' => $fullname
    ];
}

echo json_encode($data);
?>
