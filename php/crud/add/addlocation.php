<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $location = pt_pg_escape($conn, $_POST['location_name']);
    $latitude = pt_pg_escape($conn, $_POST['latitude']);
    $longitude = pt_pg_escape($conn, $_POST['longitude']);

    if (empty($location)) {
        echo "Please fill all fields.";
        exit;
    } 

    $sql = "INSERT INTO location (location_name, latitude, longitude) VALUES ('$location', '$latitude', '$longitude')";

    if ($conn->query($sql)) {
        echo "location data inserted successfully.";
    } else {
        echo "Error: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
