<?php
include "../config/config.php";

$sql = "SELECT smp_id, smp_fname, smp_mname, smp_lname, smp_role
        FROM shop_manpower
        WHERE smp_status = 'Active'";

$result = $conn->query($sql);
$data = [];

while ($row = $result->fetch()) {
    $data[] = $row;
}

echo json_encode($data);
