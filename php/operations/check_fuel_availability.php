<?php
include __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid method.']);
    exit;
}

if (!pt_table_exists($conn, 'fuel_inventory')) {
    echo json_encode(['status' => 'error', 'message' => 'Fuel inventory table missing.']);
    exit;
}

$required = floatval($_POST['noLiter'] ?? 0);
$result = $conn->query("SELECT COALESCE(SUM(fi_consumableltr), 0) AS total_available FROM fuel_inventory WHERE fi_consumableltr > 0");
if ($result && $row = ($result)->fetch()) {
    $avail = floatval($row['total_available']);
    if ($avail >= $required) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'not_enough', 'available' => $avail]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to query inventory.']);
}
