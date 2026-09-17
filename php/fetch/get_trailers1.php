<?php
include "../config/config.php";

$sql = "SELECT trailer_name 
        FROM trailer 
        ORDER BY trailer_name ASC";
$result = $conn->query($sql);

$trailers = [];
while ($row = ($result)->fetch()) {
    $trailers[] = $row['trailer_name'];
}

header('Content-Type: application/json');
echo json_encode($trailers);
