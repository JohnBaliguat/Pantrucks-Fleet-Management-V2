<?php
// Dismiss a Geotab fault (acknowledge it so it drops off the active list).
// Admin-only, POST. The fault stays in the table for history.
//
//   POST fault_id (required)

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/activity_log_helper.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$faultId = trim((string)($_POST['fault_id'] ?? ''));
if ($faultId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'fault_id required']);
    exit;
}

try {
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $st = $conn->prepare(
        "UPDATE geotab_fault SET dismissed = TRUE, dismissed_by = ?, updated_at = NOW()
          WHERE fault_id = ?"
    );
    $st->execute([$actorId ?: null, $faultId]);
    if ($st->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Fault not found']);
        exit;
    }

    pt_log_activity($conn, $actorId, 'Admin', (string)($_SESSION['user_name'] ?? 'Admin'),
        'Dismissed Geotab fault', "fault=$faultId");

    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
