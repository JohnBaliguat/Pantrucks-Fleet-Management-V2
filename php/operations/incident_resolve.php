<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$incId = (int)($_POST['inc_id'] ?? 0);
$note  = trim($_POST['note'] ?? '');
$actor = (int)($_SESSION['user_id'] ?? 0);
if ($incId <= 0) { echo json_encode(['status' => 'error', 'message' => 'inc_id required']); exit; }

$stmt = $conn->prepare("UPDATE incident SET status = 'resolved', resolved_at = NOW() WHERE inc_id = ? AND status <> 'resolved'");
$stmt->execute([$incId]);
$affected = $stmt->rowCount();
if ($affected === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Incident not found or already resolved']);
    exit;
}

$notes = 'Incident #' . $incId . ' resolved' . ($note !== '' ? ' — ' . $note : '');
$stmt = $conn->prepare(
    "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
     SELECT i.d_id, COALESCE(d.booking_no, ''), 'incident_resolved', ?, ?, ?
     FROM incident i LEFT JOIN dispatch d ON d.d_id = i.d_id
     WHERE i.inc_id = ?"
);
$stmt->execute([$role, $actor, $notes, $incId]);
echo json_encode(['status' => 'success', 'message' => 'Incident resolved.']);
