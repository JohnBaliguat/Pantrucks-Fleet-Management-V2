<?php
include "php/config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $driver_id = (int) trim($_POST['driver_id']);

    $stmt = $conn->prepare(
        "SELECT driver_id, driver_idnumber, driver_uname, driver_pass
         FROM drivers
         WHERE driver_idnumber = ?"
    );
    $stmt->execute([$driver_id]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(["status" => "not_found"]);
    } else {
        if (!empty($row['driver_uname']) && !empty($row['driver_pass'])) {
            echo json_encode(["status" => "has_account"]);
        } else {
            echo json_encode(["status" => "ok", "driver_id" => $row['driver_id']]);
        }
    }
    exit;
}
?>
