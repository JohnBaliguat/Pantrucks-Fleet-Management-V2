<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

$driverId = (int)($_GET['driver_id'] ?? 0);
if ($driverId <= 0) { echo json_encode(['status' => 'success', 'has_shift' => false]); exit; }

// We trust an open driver_shift row + a non-empty drivers.shift_truck.
// Also verify the truck itself isn't maintenance-blocked, otherwise the
// autofill would shove the dispatcher into a blocked unit.
$stmt = $conn->prepare(
    "SELECT d.shift_truck, d.shift_started_at,
            s.ds_id, s.started_at AS shift_open_since,
            u.unit_name AS truck_exists, u.maintenance_blocked
     FROM drivers d
     LEFT JOIN driver_shift s
            ON s.driver_id = d.driver_id AND s.ended_at IS NULL
     LEFT JOIN units u
            ON u.unit_name = d.shift_truck AND u.unit_type = 'truck'
     WHERE d.driver_id = ?
     ORDER BY s.ds_id DESC
     LIMIT 1"
);
$stmt->execute([$driverId]);
$row = $stmt->fetch();
if (!$row) { echo json_encode(['status' => 'success', 'has_shift' => false]); exit; }

$truck   = trim($row['shift_truck'] ?? '');
$openId  = (int)($row['ds_id'] ?? 0);
$blocked = (int)($row['maintenance_blocked'] ?? 0);

$payload = ['status' => 'success'];
if ($truck !== '' && $openId > 0 && $blocked === 0) {
    $payload['has_shift']        = true;
    $payload['shift_truck']      = $truck;
    $payload['shift_started_at'] = $row['shift_open_since'];
    $payload['ds_id']            = $openId;
} else {
    $payload['has_shift']  = false;
    if ($truck !== '' && $blocked === 1) {
        $payload['warning'] = 'Driver picked truck ' . $truck . ', but it is maintenance-blocked.';
    } elseif ($truck !== '' && $openId === 0) {
        $payload['warning'] = 'Driver has no open shift — they need to run the pre-departure checklist first.';
    }
}

echo json_encode($payload);
