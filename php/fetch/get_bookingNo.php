<?php
include "../config/config.php";

$sql = "SELECT 
            booking_id, 
            booking_no, 
            booking_date, 
            booking_daterequired,
            costumer, 
            container, 
            booking_activity,
            container_status,   
            hauling_segment, 
            hauling_type, 
            trip_from, 
            trip_to, 
            quantity, 
            quantity_use, 
            status,
            (quantity - quantity_use) AS available_qty
        FROM booking
        WHERE (quantity - quantity_use) > 0 AND costumer != 'CTH'
        ORDER BY booking_daterequired ASC";
$result = $conn->query($sql);

$drivers = [];
while ($row = ($result)->fetch()) {
    $formattedName = strtoupper("(".$row['booking_no']) . ") " . strtoupper($row['costumer']) ." - ". strtoupper($row['container']) . "(" . strtoupper($row['container_status']) ."/". strtoupper($row['booking_activity']) .") - ". strtoupper($row['hauling_segment']) ."/". strtoupper($row['hauling_type']) .".";

    

    $drivers[] = [
        'id' => $row['booking_no'],
        'name' => $formattedName
    ];
}

header('Content-Type: application/json');
echo json_encode($drivers);
