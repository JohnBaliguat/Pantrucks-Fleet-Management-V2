<?php
// Create the single daily DICT-Hustling dispatch for a driver.
//
// Unlike a normal dispatch this has NO pre-set trip legs — the driver adds
// each container through the day (add_hustling_container.php). Modelled on
// assign_service_trip.php (a booking-less, dispatcher-created dispatch).
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$driver_id = (int)($_POST['driver_id'] ?? 0);
$truck     = strtoupper(trim($_POST['truck'] ?? ''));
$trailer   = trim($_POST['trailer'] ?? '');
$genset    = trim($_POST['genset'] ?? '');

if ($driver_id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Driver is required.']); exit; }
if ($truck === '')   { echo json_encode(['status' => 'error', 'message' => 'Truck is required.']); exit; }

if (pt_driver_has_active_violation($conn, $driver_id)) {
    echo json_encode(['status' => 'error', 'message' => pt_driver_violation_block_message()]); exit;
}

date_default_timezone_set('Asia/Manila');
$now   = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// Driver name.
$stmt = $conn->prepare("SELECT CONCAT(driver_lname, ', ', driver_fname) AS nm FROM drivers WHERE driver_id = ? LIMIT 1");
$stmt->execute([$driver_id]);
$driverName = (string)($stmt->fetchColumn() ?: '');
if ($driverName === '') { echo json_encode(['status' => 'error', 'message' => 'Driver not found.']); exit; }

// Dispatcher name.
$dispatcherId = (int)($_SESSION['user_id'] ?? 0);
$dispatcherName = 'system';
if ($dispatcherId > 0) {
    $s = $conn->prepare("SELECT user_fname, user_mname, user_lname FROM \"user\" WHERE user_id = ? LIMIT 1");
    $s->execute([$dispatcherId]);
    if ($u = $s->fetch()) {
        $parts = array_filter([trim((string)$u['user_fname']), trim((string)$u['user_mname']), trim((string)$u['user_lname'])], 'strlen');
        if ($parts) $dispatcherName = implode(' ', $parts);
    }
}

// One open hustling day per driver.
$chk = $conn->prepare(
    "SELECT d_id FROM dispatch
      WHERE driver_id = ? AND is_hustling = TRUE AND hustling_date = ?
        AND workflow_stage NOT IN ('pod_captured','billing_closed','client_notified')
      LIMIT 1"
);
$chk->execute([$driver_id, $today]);
if ($openId = $chk->fetchColumn()) {
    echo json_encode(['status' => 'error', 'message' => 'This driver already has an open hustling day today (dispatch #' . $openId . ').', 'd_id' => (int)$openId]);
    exit;
}

$bookingNo = 'HUSTLE-' . $driver_id . '-' . date('Ymd');

$conn->beginTransaction();
try {
    $ins = $conn->prepare(
        "INSERT INTO dispatch
            (booking_no, d_datetime, d_dispatcher, d_dispatchhub, d_drivername, driver_id,
             d_truck, d_trailer, d_genset, d_tripreceipt, costumer,
             is_hustling, hustling_date,
             workflow_stage, workflow_updated_at, driver_accepted_at)
         VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, '', 'DICT HUSTLING',
             TRUE, ?, 'driver_accepted', ?, ?)
         RETURNING d_id"
    );
    $ins->execute([$bookingNo, $now, $dispatcherName, $driverName, $driver_id,
        $truck, $trailer, $genset, $today, $now, $now]);
    $d_id = (int)$ins->fetchColumn();

    // Driver + truck status flips.
    $conn->prepare("UPDATE drivers SET driver_status = 'Dispatch' WHERE driver_id = ?")->execute([$driver_id]);
    $conn->prepare(
        "UPDATE units SET unit_assign = ?, driver_id = ?, unit_assigngenset = ?, unit_assigntrailer = ?, unit_status = 'Dispatch' WHERE unit_name = ?"
    )->execute([$driverName, $driver_id, $genset, $trailer, $truck]);
    if ($trailer !== '') {
        $conn->prepare("UPDATE trailer SET trailer_assignto = ?, driver_id = ? WHERE trailer_name = ?")
             ->execute([$driverName, $driver_id, $trailer]);
    }

    $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, 'dispatcher_assigned', 'dispatcher', ?, 'DICT Hustling day created.')"
    )->execute([$d_id, $bookingNo, $dispatcherId]);

    $conn->commit();
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Hustling day created. The driver can now add containers.',
        'd_id'      => $d_id,
        'booking_no'=> $bookingNo,
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
