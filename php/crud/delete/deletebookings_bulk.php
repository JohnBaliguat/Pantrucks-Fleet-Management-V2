<?php
// Bulk delete bookings. Only deletes rows whose quantity_use = 0, matching
// the visibility rule used by the per-row delete button in booking-table.php.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'msg' => 'POST required']);
    exit;
}

$rawIds = $_POST['booking_ids'] ?? [];
if (!is_array($rawIds)) {
    $rawIds = [$rawIds];
}

$ids = [];
foreach ($rawIds as $v) {
    $n = (int)$v;
    if ($n > 0) $ids[] = $n;
}
$ids = array_values(array_unique($ids));

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'msg' => 'No bookings selected.']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "DELETE FROM booking
         WHERE booking_id IN ($placeholders)
           AND COALESCE(quantity_use, 0) = 0"
    );
    $stmt->execute($ids);
    $deleted = $stmt->rowCount();
    $skipped = count($ids) - $deleted;

    $msg = "Deleted $deleted booking" . ($deleted === 1 ? '' : 's') . '.';
    if ($skipped > 0) {
        $msg .= " $skipped row" . ($skipped === 1 ? ' was' : 's were') . " skipped (already in use).";
    }

    echo json_encode([
        'status'  => $deleted > 0 ? 'success' : 'warning',
        'msg'     => $msg,
        'deleted' => $deleted,
        'skipped' => $skipped,
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'msg' => 'Bulk delete failed: ' . $e->getMessage()]);
}
