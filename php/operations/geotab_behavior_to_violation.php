<?php
// Convert a Geotab driver-behavior event into a violation_record, using the
// same effect as insert_violation.php (block the driver + notify on WhatsApp).
// Admin / HR only, POST.
//
//   POST event_id     (required) geotab_driver_event.event_id
//   POST vr_type       optional label (defaults to the event's rule name)
//   POST description   optional detail

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/activity_log_helper.php';

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
    $st = $conn->prepare(
        "SELECT driver_id, rule_name, unit_name, occurred_at, violation_id
           FROM geotab_driver_event WHERE event_id = ?"
    );
    $st->execute([$eventId]);
    $ev = $st->fetch();
    if (!$ev) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Event not found']);
        exit;
    }
    if (!empty($ev['violation_id'])) {
        echo json_encode(['status' => 'success', 'message' => 'Already converted to a violation.']);
        exit;
    }
    if ($ev['driver_id'] === null) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'No driver attributed to this event — link the driver first.']);
        exit;
    }

    $driverId = (int)$ev['driver_id'];
    $vrType = trim((string)($_POST['vr_type'] ?? '')) ?: ('Geotab: ' . ((string)$ev['rule_name'] ?: 'Driver behavior'));
    $descr  = trim((string)($_POST['description'] ?? ''))
              ?: ('Geotab-detected on ' . (string)$ev['unit_name'] . ' at ' . (string)$ev['occurred_at']);
    $userId = (int)($_SESSION['user_id'] ?? 0);

    date_default_timezone_set('Asia/Manila');
    $today = date('Y-m-d');
    $now   = date('Y-m-d H:i:s');

    $conn->beginTransaction();

    // 1. Record the violation (immediate/Active), mirroring insert_violation.php.
    $ins = $conn->prepare(
        "INSERT INTO violation_record
            (driver_id, vr_type, vr_description, vr_status, vr_recordedby, vr_date, vr_done_date)
         VALUES (?, ?, ?, 'Active', ?, ?, ?)
         RETURNING vr_id"
    );
    $ins->execute([$driverId, $vrType, $descr, $userId, $today, $today]);
    $vrId = (int)$ins->fetchColumn();

    // 2. Block the driver (snapshot prior status so clearing restores it).
    try { pt_ensure_column($conn, 'drivers', 'driver_prev_status', 'VARCHAR(50) DEFAULT NULL'); }
    catch (Throwable $e) { /* best-effort */ }
    $conn->prepare(
        "UPDATE drivers
            SET driver_prev_status = driver_status, driver_status = 'With Violation'
          WHERE driver_id = ? AND driver_status IS DISTINCT FROM 'With Violation'"
    )->execute([$driverId]);

    // 3. Link the event to the violation.
    $conn->prepare(
        "UPDATE geotab_driver_event SET violation_id = ? WHERE event_id = ?"
    )->execute([$vrId, $eventId]);

    $conn->commit();

    // 4. Notify the driver (best-effort, after commit).
    $wa = ['skipped' => true];
    try {
        require_once __DIR__ . '/../lib/whatsapp.php';
        $wa = pt_wa_send_violation($conn, $driverId, $vrType, $descr);
    } catch (Throwable $e) { /* best-effort */ }

    pt_log_activity($conn, $userId, $role, (string)($_SESSION['user_name'] ?? ''),
        'Converted Geotab event to violation', "event=$eventId driver=$driverId vr=$vrId");

    $msg = 'Violation recorded from Geotab event. Driver is now blocked until cleared.';
    if (!empty($wa['ok'])) $msg .= ' A WhatsApp notice was sent.';

    echo json_encode(['status' => 'success', 'message' => $msg, 'vr_id' => $vrId, 'whatsapp' => $wa]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
