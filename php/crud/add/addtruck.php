<?php
include "../../config/config.php"; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $unit_name = $_POST['unit_name'];
    $unit_std = trim($_POST['unit_std'] ?? '');
    $unit_plate =  trim($_POST['plate_number'] ?? '');
    $unit_address = trim($_POST['unit_location'] ?? '');
    $unit_status = "Good";
    // Insert into units table
    $sql = "INSERT INTO units (unit_name, unit_plate, unit_address, unit_std, unit_status) VALUES ('$unit_name', '$unit_plate', '$unit_address', '$unit_std', '$unit_status')";
    $result = $conn->query($sql);

    if ($result) {
        echo "Truck unit added successfully!";
    } else {
        echo "Error: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
