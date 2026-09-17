<?php
// Driver submits the day's DICT-Hustling containers for dispatcher
// verification — drops the day dispatch into the pending_verification queue.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Driver') {
    http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}
$driverId = (int)($_SESSION['user_id'] ?? 0);
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

$stmt = $conn->prepare(
    "SELECT d_id, booking_no FROM dispatch
      WHERE driver_id = ? AND is_hustling = TRUE AND hustling_date = ?
        AND workflow_stage NOT IN ('pending_verification','pod_captured','billing_closed','client_notified')
      ORDER BY d_id DESC LIMIT 1"
);
$stmt->execute([$driverId, $today]);
$day = $stmt->fetch();
if (!$day) { echo json_encode(['status' => 'error', 'message' => 'No open hustling day to submit.']); exit; }
$dId = (int)$day['d_id'];

$cnt = (int)$conn->query("SELECT COUNT(*) FROM trips WHERE d_id = " . $dId)->fetchColumn();
if ($cnt === 0) { echo json_encode(['status' => 'error', 'message' => 'Add at least one container before submitting.']); exit; }

$conn->beginTransaction();
try {
    $conn->prepare(
        "UPDATE dispatch SET workflow_stage = 'pending_verification', workflow_updated_at = NOW() WHERE d_id = ?"
    )->execute([$dId]);
    $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, 'pending_verification', 'driver', ?, ?)"
    )->execute([$dId, $day['booking_no'], $driverId, 'Hustling day submitted — ' . $cnt . ' container(s).']);
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Submitted for verification (' . $cnt . ' container' . ($cnt === 1 ? '' : 's') . ').']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
