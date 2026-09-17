<?php
include "../../config/config.php";  // Database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['unit_id1']; 
    $unit_name = $_POST['unit_name_update'];
    $unit_std = $_POST['unit_std_update'];
    $unit_plate = $_POST['unit_plate_update'];
    $unit_address = $_POST['unit_location_update'];
    $unit_status = "Rescue"; 

    // Update query
    $sql = "UPDATE units 
            SET unit_name='$unit_name', unit_std='$unit_std', unit_plate='$unit_plate', unit_address='$unit_address', unit_status='$unit_status' 
            WHERE unit_id='$id'";

    $result = $conn->query($sql);

    if ($result) {
        $r_status = "Active";
        date_default_timezone_set("Asia/Manila");
        $now = date("Y-m-d H:i:s");

        $insert_sql = "INSERT INTO rescue_unit 
                        (r_unit, r_startDateTime, r_status) 
                       VALUES 
                        ('$unit_name', '$now', '$r_status')";


        $insert_result = $conn->query($insert_sql);

        if ($insert_result) {
            echo "Truck unit updated and rescue unit inserted successfully!";
        } else {
            $error_message = "Error inserting rescue unit: " . (($conn->errorInfo()[2]) ?? "");
            http_response_code(500);
            echo $error_message;
            error_log($error_message, 0);
        }
    } else {
        $error_message = "Error: " . (($conn->errorInfo()[2]) ?? "");
        http_response_code(500);
        echo $error_message;
        error_log($error_message, 0);
    }
}
?>
