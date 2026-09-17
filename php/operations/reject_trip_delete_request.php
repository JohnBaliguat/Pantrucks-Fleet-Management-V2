<?php
// Reject a pending Dispatcher-initiated trip-report deletion request.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/trip_deletion_requests.php';

$role = $_SESSION['user_type'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!in_array($role, ['Admin', 'Dispatch Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$dId    = (int)($_POST['d_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

try {
    td_ensure_table($conn);
    $stmt = $conn->prepare(
        "SELECT tdr_id FROM trip_report_deletion_request
         WHERE d_id = ? AND status = 'pending' LIMIT 1"
    );
    $stmt->execute([$dId]);
    $tdrId = (int)$stmt->fetchColumn();
    if ($tdrId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'No pending request for this row.']);
        exit;
    }

    td_mark_rejected($conn, $tdrId, $userId, $reason);
    echo json_encode(['status' => 'success', 'message' => 'Deletion request rejected.']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Reject failed: ' . $e->getMessage()]);
}
