<?php
include "../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['trailer_id1'];
    $trailer_name = $_POST['trailer_name_update'] ?? '';
    $trailer_plateNo = $_POST['trailer_plateNo_update'] ?? '';
    $trailer_assignTo = $_POST['trailer_assignTo_update'] ?? '';
    $trailer_location = $_POST['trailer_location_update'] ?? '';
    $trailer_status = "Good";

    $sql = "UPDATE trailer 
            SET trailer_name='$trailer_name', 
                trailer_plateNo='$trailer_plateNo', 
                trailer_assignTo='$trailer_assignTo', 
                trailer_location='$trailer_location',
                trailer_status='$trailer_status'
            WHERE trailer_id='$id'";

    if ($conn->query($sql)) {
        echo "Trailer updated successfully!";
    } else {
        $error_message = "Error: " . (($conn->errorInfo()[2]) ?? "");
        http_response_code(500);
        echo $error_message;
        error_log($error_message, 0);
    }
}
?>
