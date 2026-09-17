<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trailer_ids = explode(",", $_POST['trailer_ids']);
    $location = pt_pg_escape($conn, $_POST['location']);
    $recordedBy = pt_pg_escape($conn, $_POST['recordedBy']);
    $approvedBy = pt_pg_escape($conn, $_POST['approvedBy']);
    $dateTime = pt_pg_escape($conn, $_POST['dateTime']);
    $container = !empty($_POST['container']) ? pt_pg_escape($conn, $_POST['container']) : "";
    $remarks   = !empty($_POST['remarks']) ? pt_pg_escape($conn, $_POST['remarks']) : "";

    foreach ($trailer_ids as $id) {
        // Update trailer table
        $update = "UPDATE trailer 
                   SET trailer_location='$location', 
                       t_recordedBy='$recordedBy', 
                       t_approvedBy='$approvedBy', 
                       t_date='$dateTime',
                       trailer_container='$container',
                       trailer_remarks='$remarks'
                   WHERE trailer_id='$id'";
        $conn->query($update);

        // Get trailer name
        $res = $conn->query("SELECT trailer_name FROM trailer WHERE trailer_id='$id'");
        $row = ($res)->fetch();
        $trailerName = $row['trailer_name'];

        // Insert into trailer_movement
        $insert = "INSERT INTO trailer_movement (tm_trailerName, tm_location, tm_recordedType, tm_recordedBy, tm_approvedBy, tm_date, tm_container, tm_remarks)
                   VALUES ('$trailerName', '$location', 'Inventory', '$recordedBy', '$approvedBy', '$dateTime', '$container', '$remarks')";
        $conn->query($insert);
    }

    echo "Trailer information updated successfully.";
}
?>
