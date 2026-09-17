<?php
include '../config/config.php';

if (!isset($_POST['driver_id']) || $_POST['driver_id'] === '') {
    echo json_encode(['status' => 'clear']);
    exit;
}

$driver_id = pt_pg_escape($conn, $_POST['driver_id']);

$query = $conn->query("
  SELECT driver_account_status
  FROM drivers
  WHERE driver_id = '$driver_id'
  LIMIT 1
");

if ($query && ($query)->rowCount() > 0) {
    $row = ($query)->fetch();

    if ($row['driver_account_status'] === 'Fuel Violation') {
        echo json_encode(['status' => 'fuel_violation']);
        exit;
    }

    if ($row['driver_account_status'] === 'Performance Notes') {
        echo json_encode(['status' => 'performance_violation']);
        exit;
    }
}

echo json_encode(['status' => 'clear']);
