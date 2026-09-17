<?php
include "../config/config.php";

$query = "SELECT unit_id, unit_name FROM units WHERE unit_name IS NOT NULL AND unit_name <> ''";
$result = $conn->query($query);

$data = [];
while ($row = ($result)->fetch()) {
    $data[] = $row;
}
echo json_encode($data);
?>
