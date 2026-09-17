<?php
// Resend the WhatsApp violation notice for a driver.
//
// Used by the "Resend WhatsApp" button on the HR / HR-Admin driver list. Looks
// up the driver's most recent ACTIVE violation and re-sends the notice to their
// registered contact number. If the driver has no contact number, nothing is
// sent and a clear message is returned.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/whatsapp.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['HR', 'HR-Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$driverId = (int)($_POST['driver_id'] ?? 0);
if ($driverId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'driver_id required']);
    exit;
}

if (!pt_wa_enabled($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'WhatsApp is not configured. Ask an Admin to set it up in Settings.']);
    exit;
}

// Driver contact + latest active violation.
$st = $conn->prepare(
    "SELECT d.driver_contact,
            v.vr_type, v.vr_description
       FROM drivers d
       LEFT JOIN LATERAL (
            SELECT vr_type, vr_description
              FROM violation_record
             WHERE driver_id = d.driver_id AND vr_status = 'Active'
             ORDER BY vr_id DESC
             LIMIT 1
       ) v ON TRUE
      WHERE d.driver_id = ?
      LIMIT 1"
);
$st->execute([$driverId]);
$row = $st->fetch();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Driver not found.']);
    exit;
}
if (trim((string)($row['driver_contact'] ?? '')) === '') {
    echo json_encode(['status' => 'error', 'message' => 'This driver has no contact number on file — nothing sent.']);
    exit;
}
if (empty($row['vr_type'])) {
    echo json_encode(['status' => 'error', 'message' => 'No active violation to notify about.']);
    exit;
}

$res = pt_wa_send_violation($conn, $driverId, (string)$row['vr_type'], (string)$row['vr_description'], 'violation_resend');

echo json_encode([
    'status'  => !empty($res['ok']) ? 'success' : 'error',
    'message' => $res['message'] ?? 'Unknown result.',
]);
