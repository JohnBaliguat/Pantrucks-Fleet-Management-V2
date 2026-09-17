<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
$id   = (int)($_SESSION['user_id'] ?? 0);
if (!in_array($role, ['Driver', 'Dispatcher', 'Dispatch Admin', 'Admin', 'Rescue', 'Maintenance'], true) || $id <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$body  = trim($_POST['body'] ?? '');
$toRole= $_POST['to_role'] ?? null;
$toId  = isset($_POST['to_id']) && $_POST['to_id'] !== '' ? (int)$_POST['to_id'] : null;
$dId   = isset($_POST['d_id']) && $_POST['d_id'] !== '' ? (int)$_POST['d_id'] : null;
if ($body === '') { echo json_encode(['status' => 'error', 'message' => 'Empty message']); exit; }

// Normalise the sender role. Admins post as dispatcher in chat context.
$fromRole = strtolower($role);
if ($fromRole === 'admin')          $fromRole = 'dispatcher';
if ($fromRole === 'dispatch admin') $fromRole = 'dispatcher';

// Default routing if no to_role provided.
if (!$toRole) {
    $toRole = ($fromRole === 'driver') ? 'dispatcher' : 'driver';
}
$toRole = strtolower($toRole);

// Drivers may now also target rescue / maintenance from their chat UI.
$allowedDriverTargets     = ['dispatcher', 'rescue', 'maintenance'];
$allowedDispatcherTargets = ['driver', 'rescue', 'maintenance'];
if ($fromRole === 'driver' && !in_array($toRole, $allowedDriverTargets, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid recipient']); exit;
}
if ($fromRole === 'dispatcher' && !in_array($toRole, $allowedDispatcherTargets, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid recipient']); exit;
}
if (in_array($fromRole, ['rescue', 'maintenance'], true) && $toRole !== 'driver' && $toRole !== 'dispatcher') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid recipient']); exit;
}

$stmt = $conn->prepare(
    "INSERT INTO message (from_role, from_id, to_role, to_id, body, d_id) VALUES (?, ?, ?, ?, ?, ?) RETURNING msg_id"
);
$stmt->execute([$fromRole, $id, $toRole, $toId, $body, $dId]);
$msgId = (int)$stmt->fetchColumn();
echo json_encode(['status' => 'success', 'msg_id' => $msgId]);
