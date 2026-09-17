<?php
include "../config/config.php";

$query = "SELECT hauling_id, hauling_segment, hauling_type FROM hauling";
$result = $conn->query($query);

$hauling = [];
while ($row = ($result)->fetch()) {
    $hauling[] = $row;
}

echo json_encode($hauling);
?>
