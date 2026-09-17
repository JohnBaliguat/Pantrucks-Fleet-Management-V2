<?php
include "../config/config.php";

$from = $_POST['from'];
$to   = $_POST['to'];

$response = [];

// Get "from" location
$sql1 = $conn->query("SELECT latitude, longitude FROM location WHERE location_name = '$from' LIMIT 1");
$response['from'] = $sql1->fetch();

// Get "to" location
$sql2 = $conn->query("SELECT latitude, longitude FROM location WHERE location_name = '$to' LIMIT 1");
$response['to'] = $sql2->fetch();

echo json_encode($response);
?>
