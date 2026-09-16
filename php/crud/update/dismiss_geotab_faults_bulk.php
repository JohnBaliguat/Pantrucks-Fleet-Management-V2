<?php
// Bulk dismiss Geotab faults in one action. Admin-only, POST.
//
//   POST fault_ids = JSON array of fault_id strings

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

$raw = $_POST['fault_ids'] ?? '';
$ids = is_array($raw) ? $raw : json_decode((string)$raw, true);
$ids = is_array($ids) ? array_values(array_filter(array_map('strval', $ids), fn($s) => $s !== '')) : [];
if (!$ids) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No faults selected']);
    exit;
}

try {
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "UPDATE geotab_fault
            SET dismissed = TRUE, dismissed_by = ?, updated_at = NOW()
          WHERE fault_id IN ($ph) AND dismissed = FALSE"
    );
    $stmt->execute(array_merge([$actorId ?: null], $ids));
    $dismissed = $stmt->rowCount();

    pt_log_activity($conn, $actorId, 'Admin', (string)($_SESSION['user_name'] ?? 'Admin'),
        'Bulk dismissed Geotab faults', "count=$dismissed");

    echo json_encode(['status' => 'success', 'dismissed' => $dismissed]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
