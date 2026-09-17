<?php
include "../../config/config.php";  // Database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['unit_id1']; 
    $unit_name = $_POST['unit_name_update'];
    $unit_std = $_POST['unit_std_update'];
    $unit_plate = $_POST['unit_plate_update'];
    $unit_address = $_POST['unit_location_update'];
    

    // Update query
    $sql = "UPDATE units 
            SET unit_name='$unit_name', unit_std='$unit_std', unit_plate='$unit_plate', unit_address='$unit_address' 
            WHERE unit_id='$id'";

    $result = $conn->query($sql);

    if ($result) {
        echo "Truck unit updated successfully!";
    } else {
        $error_message = "Error: " . (($conn->errorInfo()[2]) ?? "");
        http_response_code(500);
        echo $error_message;
        error_log($error_message, 0);
    }
}
?>
