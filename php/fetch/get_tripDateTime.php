<?php
include "../config/config.php";
header('Content-Type: application/json');

$trip_id = $_GET['trip_id'] ?? null;

if (!$trip_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing trip ID']);
    exit;
}

$sql = "SELECT trip_arrivaldatetime, trip_departuredatetime, trip_pharrivaldatetime 
        FROM trips WHERE trip_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$trip_id]);
$result = $stmt;

if ($row = $result->fetch()) {
    echo json_encode([
        'status' => 'success',
        'trip' => $row
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Trip not found'
    ]);
}

?>
