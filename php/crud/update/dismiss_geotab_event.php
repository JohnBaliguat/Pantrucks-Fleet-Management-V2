<?php
// Dismiss a Geotab driver-behavior event (acknowledge without a violation).
// Admin / HR only, POST.  POST event_id (required)

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/activity_log_helper.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'HR-Admin', 'User'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$eventId = trim((string)($_POST['event_id'] ?? ''));
if ($eventId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'event_id required']);
    exit;
}

try {
    $st = $conn->prepare("UPDATE geotab_driver_event SET dismissed = TRUE WHERE event_id = ? AND violation_id IS NULL");
    $st->execute([$eventId]);
    if ($st->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Event not found or already actioned']);
        exit;
    }
    pt_log_activity($conn, (int)($_SESSION['user_id'] ?? 0), $role, (string)($_SESSION['user_name'] ?? ''),
        'Dismissed Geotab behavior event', "event=$eventId");
    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
