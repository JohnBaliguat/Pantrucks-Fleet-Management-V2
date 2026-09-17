<?php
include "../config/config.php";

$sql = "SELECT unit_name 
        FROM units
        WHERE  unit_name NOT LIKE 'GS%'
        ORDER BY unit_name ASC";
$result = $conn->query($sql);

$trailers = [];
while ($row = ($result)->fetch()) {
    $trailers[] = $row['unit_name'];
}

header('Content-Type: application/json');
echo json_encode($trailers);
