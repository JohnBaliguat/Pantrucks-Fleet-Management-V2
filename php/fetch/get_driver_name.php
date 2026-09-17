<?php
// Look up driver by driver_IdNumber (used by the ticketing page on blur).
include __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided.']);
    exit;
}

$id = pt_pg_escape($conn, $_GET['id']);

// Check driver_image column existence — FM-New drivers table may not have it.
$hasImage = pt_column_exists($conn, 'drivers', 'driver_image');
$cols = $hasImage ? 'driver_fname, driver_lname, driver_image' : 'driver_fname, driver_lname';

$sql = "SELECT $cols FROM drivers WHERE driver_IdNumber = '$id' LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->rowCount() > 0) {
    $row = $result->fetch();
    $firstInitial = strtoupper(substr($row['driver_fname'], 0, 1));
    $lastName     = strtoupper($row['driver_lname']);
    $formattedName = "$lastName, $firstInitial";
    $imagePath = 'assets/images/profile/generator.png';
    if ($hasImage && !empty($row['driver_image'])) {
        $imagePath = 'php/operations/uploads/' . $row['driver_image'];
    }
    echo json_encode([
        'success'     => true,
        'driverName'  => $formattedName,
        'driverImage' => $imagePath
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Driver ID not found.']);
}
