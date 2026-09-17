<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_push_send.php';

// Dispatcher / Dispatch Admin / Admin only.
$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$gqId     = (int)($_POST['gq_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$decider  = (int)($_SESSION['user_id'] ?? 0);

if ($gqId <= 0 || !in_array($decision, ['approved', 'denied'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'gq_id and decision (approved|denied) required']);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE gate_queue SET decision = ?, decided_by = ?, decided_at = NOW() WHERE gq_id = ? AND decision = 'pending'"
);
$stmt->execute([$decision, $decider, $gqId]);
$affected = $stmt->rowCount();
if ($affected === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Queue row not found or already decided']);
    exit;
}

// Phase 6 — notify the driver of the gate decision so they don't sit idling.
$stmt = $conn->prepare("SELECT q.d_id, q.truck_plate, d.driver_id, d.booking_no FROM gate_queue q LEFT JOIN dispatch d ON d.d_id = q.d_id WHERE q.gq_id = ? LIMIT 1");
$stmt->execute([$gqId]);
$ctx = $stmt->fetch();
if ($ctx && !empty($ctx['driver_id'])) {
    $title = $decision === 'approved' ? 'Gate approved' : 'Gate denied';
    $body  = ($ctx['booking_no'] ? $ctx['booking_no'] . ' — ' : '') . 'Truck ' . ($ctx['truck_plate'] ?? '');
    pt_notify_driver($conn, (int)$ctx['driver_id'], $title, $body, $ctx['d_id'] ? (int)$ctx['d_id'] : null);
}

echo json_encode(['status' => 'success', 'message' => "Queue entry $decision."]);
