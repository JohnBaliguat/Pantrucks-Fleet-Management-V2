<?php
include "../config/config.php"; // adjust path

$driver_id = $_POST['driver_id'];


if (!$driver_id) {
    http_response_code(400);
    echo "Missing required fields";
    exit;
}


// 1. Get latest dispatch for this unit
$sql = $conn->prepare("SELECT d_id, d_truck FROM dispatch WHERE driver_id = ? ORDER BY d_datetime DESC LIMIT 1");
$sql->execute([$driver_id]);
$res = $sql;
$dispatch = $res->fetch();

$unit_name = $dispatch['d_truck'];

// 2. Update unit status
$update = $conn->prepare("UPDATE units SET unit_status = 'Rescue' WHERE unit_name = ?");
$update->execute([$unit_name]);

if ($dispatch) {
    $d_id = $dispatch['d_id'];
    $unit = $dispatch['d_truck'];

    // 3. Check if this unit already has an active rescue
    $check = $conn->prepare("SELECT r_id FROM rescue_unit WHERE r_unit = ? AND r_status = 'Active' LIMIT 1");
    $check->execute([$unit_name]);
    $checkRes = $check;

    if ($checkRes->rowCount() > 0) {
        echo "⚠️ Already has send an SOS.";
        exit;
    }

    // 4. Insert into rescue_unit
    $now = date("Y-m-d H:i:s");
    $insert = $conn->prepare("INSERT INTO rescue_unit (r_unit, driver_id, r_startdatetime, r_status) VALUES (?, ?, ?, 'Active')");
    $insert->execute([$unit_name, $driver_id, $now]);

    echo "Unit is now in Rescue status!";
} else {
    echo "No active dispatch found for this unit.";
}
?>
