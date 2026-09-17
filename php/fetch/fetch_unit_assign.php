<?php
include "../config/config.php";

if (isset($_GET['unit_id'])) {
    $unit_id = intval($_GET['unit_id']);

    $sql = "SELECT unit_assign, driver_id, unit_assigngenset, unit_assigntrailer 
            FROM units WHERE unit_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$unit_id]);
    $result = $stmt;

    $data = $result->fetch();
    echo json_encode($data);
}
?>
