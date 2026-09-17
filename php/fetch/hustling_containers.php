<?php
// Driver feed for the DICT-Hustling page: today's open hustling day + the
// containers logged so far (with capture photos) + running totals.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Driver') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}
$driverId = (int)($_SESSION['user_id'] ?? 0);
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

$stmt = $conn->prepare(
    "SELECT d_id, booking_no, d_truck, workflow_stage, control_no
       FROM dispatch
      WHERE driver_id = ? AND is_hustling = TRUE AND hustling_date = ?
      ORDER BY d_id DESC LIMIT 1"
);
$stmt->execute([$driverId, $today]);
$day = $stmt->fetch();

if (!$day) {
    echo json_encode(['status' => 'success', 'has_day' => false, 'rows' => []]);
    exit;
}
$dId = (int)$day['d_id'];

$rate = (float)($conn->query("SELECT total_rates FROM trip_rates WHERE UPPER(TRIM(trip_key)) = 'DICT HUSTLING' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);

$ts = $conn->prepare(
    "SELECT t.trip_id, t.trip_type, t.trip_container, t.trip_containerstat,
            t.trip_from, t.trip_to, t.piece_rate,
            pc.photo_path, pc.captured_at
       FROM trips t
       LEFT JOIN pickup_capture pc ON pc.trip_id = t.trip_id
      WHERE t.d_id = ?
      ORDER BY t.trip_id ASC"
);
$ts->execute([$dId]);
$rows = [];
foreach ($ts as $r) {
    $rows[] = [
        'trip_id'        => (int)$r['trip_id'],
        'trip_type'      => $r['trip_type'],
        'container_no'   => $r['trip_container'],
        'stat'           => $r['trip_containerstat'],
        'trip_from'      => $r['trip_from'],
        'trip_to'        => $r['trip_to'],
        'piece_rate'     => (float)$r['piece_rate'],
        'photo_path'     => $r['photo_path'],
        'captured_at'    => $r['captured_at'],
    ];
}

echo json_encode([
    'status'         => 'success',
    'has_day'        => true,
    'd_id'           => $dId,
    'booking_no'     => $day['booking_no'],
    'truck'          => $day['d_truck'],
    'workflow_stage' => $day['workflow_stage'],
    'control_no'     => $day['control_no'],
    'submitted'      => in_array($day['workflow_stage'], ['pending_verification','pod_captured','billing_closed','client_notified'], true),
    'rate'           => $rate,
    'count'          => count($rows),
    'total'          => $rate * count($rows),
    'rows'           => $rows,
]);
