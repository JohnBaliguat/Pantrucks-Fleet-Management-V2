<?php
include "../config/config.php";

if (isset($_POST['s_id'])) {
    $s_id = intval($_POST['s_id']);


    // 1. Update rescue_unit (set status to Good, close end time)
    $updateRescue = "UPDATE shop_unit SET s_enddatetime = NOW(), s_status = 'Good' WHERE s_id = ?";
    $stmt = $conn->prepare($updateRescue);
    $stmt->execute([$s_id]);

    // 2. Also update units table (set unit_status = Good)
    $sqlGetUnit = "SELECT s_unit FROM shop_unit WHERE s_id = ?";
    $stmt2 = $conn->prepare($sqlGetUnit);
    $stmt2->execute([$s_id]);
    $result = $stmt2;
    $rescue = $result->fetch();

    if ($rescue) {
        $unit_name = $rescue['s_unit'];

        $updateUnit = "UPDATE units SET unit_status = 'Good' WHERE unit_name = ?";
        $stmt3 = $conn->prepare($updateUnit);
        $stmt3->execute([$unit_name]);
    }

    echo "Unit has been marked as Good.";
} else {
    echo "Invalid request.";
}
?>
