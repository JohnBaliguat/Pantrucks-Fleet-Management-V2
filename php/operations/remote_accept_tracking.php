<?php
// TEMPORARY FEATURE — remote accept.
// Lets a dispatcher/admin accept a "To Be Accepted" dispatch on the driver's
// behalf (e.g. driver's phone is offline). Mirrors driver_accept_job.php but
// is keyed to the dispatcher's session and works on any dispatch.

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_push_send.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'You are not allowed to remotely accept trips.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$dId   = (int)($_POST['d_id'] ?? 0);
$actor = (int)($_SESSION['user_id'] ?? 0);
if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

$stmt = $conn->prepare("SELECT booking_no, driver_id, workflow_stage FROM dispatch WHERE d_id = ? LIMIT 1");
$stmt->execute([$dId]);
$row = $stmt->fetch();
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Dispatch not found.']);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE dispatch
     SET workflow_stage = 'driver_accepted', driver_accepted_at = NOW(), workflow_updated_at = NOW()
     WHERE d_id = ? AND workflow_stage IN ('dispatcher_assigned', 'reassigned')"
);
$stmt->execute([$dId]);
if ($stmt->rowCount() === 0) {
    echo json_encode(['status' => 'error', 'message' => 'This trip is not awaiting acceptance.']);
    exit;
}

$bn = (string)($row['booking_no'] ?? '');
$stmt = $conn->prepare(
    "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
     VALUES (?, ?, 'driver_accepted', ?, ?, ?)"
);
$stmt->execute([$dId, $bn, $role, $actor, 'Remotely accepted by ' . $role . ' #' . $actor]);

// Let the driver know their trip was accepted on their behalf.
if (!empty($row['driver_id'])) {
    pt_notify_driver(
        $conn,
        (int)$row['driver_id'],
        'Trip accepted for you',
        ($bn !== '' ? $bn : 'Your trip') . ' was accepted on your behalf by ' . $role . '.',
        $dId
    );
}

echo json_encode(['status' => 'success', 'message' => 'Trip accepted.']);
