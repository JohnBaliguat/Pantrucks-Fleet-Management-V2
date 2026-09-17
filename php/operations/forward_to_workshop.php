<?php
include "../config/config.php";

if (isset($_POST['r_id'])) {
    $r_id = intval($_POST['r_id']);

    // Get rescue_unit record
    $sql = "SELECT * FROM rescue_unit WHERE r_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$r_id]);
    $result = $stmt;
    $rescue = $result->fetch();

    if ($rescue) {
        $unit      = $rescue['r_unit'];
        $driver_id = !empty($rescue['driver_id']) ? $rescue['driver_id'] : 0;
        $start     = $rescue['r_startdatetime'];

        // 1. Update rescue_unit
        $update = "UPDATE rescue_unit 
                   SET r_enddatetime = NOW(), r_status = 'Good' 
                   WHERE r_id = ?";
        $stmt2 = $conn->prepare($update);
        $stmt2->execute([$r_id]);

        // 2. Insert into shop_unit
        $insert = "INSERT INTO shop_unit (s_unit, driver_id, s_startdatetime, s_status, s_remarks) 
                   VALUES (?, ?, NOW(), 'Active', 'Forwarded from Rescue Unit')";
        $stmt3 = $conn->prepare($insert);
        $stmt3->execute([$unit, $driver_id]);

        echo "Unit forwarded to workshop successfully.";
    } else {
        echo "Rescue record not found.";
    }
} else {
    echo "Invalid request.";
}
?>
