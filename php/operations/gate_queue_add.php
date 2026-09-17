<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$truckPlate = strtoupper(trim($_POST['truck_plate'] ?? ''));
$direction  = strtoupper(trim($_POST['direction'] ?? 'IN'));
$notes      = trim($_POST['notes'] ?? '');

if ($truckPlate === '') {
    echo json_encode(['status' => 'error', 'message' => 'truck_plate required']);
    exit;
}

// Try to attach the most recent dispatch for that truck.
$dId = null;
$driverId = null;
$stmt = $conn->prepare("SELECT d_id, driver_id FROM dispatch WHERE d_truck = ? ORDER BY d_id DESC LIMIT 1");
$stmt->execute([$truckPlate]);
$row = $stmt->fetch();
if ($row) { $dId = (int)$row['d_id']; $driverId = (int)$row['driver_id']; }

$stmt = $conn->prepare(
    "INSERT INTO gate_queue (d_id, truck_plate, driver_id, direction, decision, notes)
     VALUES (?, ?, ?, ?, 'pending', ?)"
);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . ($conn->errorInfo()[2] ?? '')]);
    exit;
}

$ok = $stmt->execute([$dId, $truckPlate, $driverId, $direction, $notes]);
if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Insert failed']);
    exit;
}
echo json_encode(['status' => 'success', 'message' => 'Added to dispatcher queue.']);
