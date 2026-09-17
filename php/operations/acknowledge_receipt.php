<?php
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';

$drId = (int)($_POST['dr_id'] ?? 0);
if ($drId <= 0) json_out(['status' => 'error', 'message' => 'dr_id required'], 400);

// Confirm the receipt is attached to a dispatch this driver owns.
$stmt = $conn->prepare(
    "SELECT r.dr_id, d.d_id, d.driver_id, d.booking_no
     FROM dispatch_receipt r
     INNER JOIN dispatch d ON d.d_id = r.d_id
     WHERE r.dr_id = ? LIMIT 1"
);
$stmt->execute([$drId]);
$row = $stmt->fetch();
if (!$row || (int)$row['driver_id'] !== $driverId) {
    json_out(['status' => 'error', 'message' => 'Receipt not on a dispatch assigned to you'], 403);
}

$stmt = $conn->prepare("INSERT IGNORE INTO receipt_acknowledge (dr_id, driver_id) VALUES (?, ?)");
$stmt->execute([$drId, $driverId]);
json_out(['status' => 'success', 'message' => 'Acknowledged.']);
