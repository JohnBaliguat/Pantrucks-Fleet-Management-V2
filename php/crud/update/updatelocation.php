<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = pt_pg_escape($conn, $_POST['location_id']);
    $location = pt_pg_escape($conn, $_POST['location_name']);
    $latitude = pt_pg_escape($conn, $_POST['latitude']);
    $longitude = pt_pg_escape($conn, $_POST['longitude']);
    if (empty($id) || empty($location)) {
        echo "All fields are required.";
        exit;
    }

    $sql = "UPDATE location SET location_name = '$location', latitude = '$latitude', longitude = '$longitude' WHERE location_id = '$id'";

    if ($conn->query($sql)) {
        echo "Location record updated successfully.";
    } else {
        echo "Update failed: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
