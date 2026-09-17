<?php
// Driver logs one container under their open DICT-Hustling day.
// Each container = one child trips row (flat piece_rate) + one pickup_capture
// row (photo + GPS). Reuses the driver pickup capture pattern.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/upload_error_helper.php';

function hc_out($p, $code = 200) { http_response_code($code); echo json_encode($p); exit; }

if (($_SESSION['user_type'] ?? '') !== 'Driver') hc_out(['status' => 'error', 'message' => 'Not authorised'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')       hc_out(['status' => 'error', 'message' => 'POST required'], 405);
if (($overflow = upload_post_overflow()) !== null) hc_out(['status' => 'error', 'message' => $overflow], 413);

$driverId    = (int)($_SESSION['user_id'] ?? 0);
$containerNo = strtoupper(trim($_POST['container_no'] ?? ''));
$stat        = trim($_POST['container_stat'] ?? '');   // Loaded | Empty
$from        = trim($_POST['trip_from'] ?? '');
$to          = trim($_POST['trip_to'] ?? '');
$lat         = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
$lng         = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;

if (!preg_match('/^[A-Z]{4}[0-9]{7}$/', $containerNo)) {
    hc_out(['status' => 'error', 'message' => 'Container number must be 4 capital letters followed by 7 numbers.'], 400);
}
if (!in_array($stat, ['Loaded', 'Empty'], true)) {
    hc_out(['status' => 'error', 'message' => 'Select Loaded or Empty.'], 400);
}

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// The driver's open hustling day.
$stmt = $conn->prepare(
    "SELECT d_id, booking_no FROM dispatch
      WHERE driver_id = ? AND is_hustling = TRUE AND hustling_date = ?
        AND workflow_stage NOT IN ('pod_captured','billing_closed','client_notified')
      ORDER BY d_id DESC LIMIT 1"
);
$stmt->execute([$driverId, $today]);
$day = $stmt->fetch();
if (!$day) hc_out(['status' => 'error', 'message' => 'No open hustling day. Ask your dispatcher to create one.'], 409);
$dId = (int)$day['d_id'];

// Duplicate container guard within the day.
$dup = $conn->prepare("SELECT 1 FROM trips WHERE d_id = ? AND UPPER(TRIM(trip_container)) = ? LIMIT 1");
$dup->execute([$dId, $containerNo]);
if ($dup->fetchColumn()) hc_out(['status' => 'error', 'message' => 'Container ' . $containerNo . ' is already logged today.'], 409);

// Photo (required).
if (!isset($_FILES['container_photo']) || (int)$_FILES['container_photo']['error'] !== UPLOAD_ERR_OK) {
    $code = isset($_FILES['container_photo']) ? (int)$_FILES['container_photo']['error'] : UPLOAD_ERR_NO_FILE;
    hc_out(['status' => 'error', 'message' => upload_friendly_error($code, 'A container photo is required.')], upload_error_http_status($code));
}
$dir = __DIR__ . '/../assets/uploads/pickup';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$ext = strtolower(pathinfo($_FILES['container_photo']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) $ext = 'jpg';
$name = 'hustle_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
if (!move_uploaded_file($_FILES['container_photo']['tmp_name'], $dir . '/' . $name)) {
    hc_out(['status' => 'error', 'message' => 'Failed to save container photo.'], 500);
}
$photoPath = 'php/assets/uploads/pickup/' . $name;

// Flat per-container rate.
$rate = (float)($conn->query("SELECT total_rates FROM trip_rates WHERE UPPER(TRIM(trip_key)) = 'DICT HUSTLING' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);

// Driver id-number (for the capture row).
$driverIdNumber = (string)($conn->query("SELECT driver_idnumber FROM drivers WHERE driver_id = " . (int)$driverId . " LIMIT 1")->fetchColumn() ?: '');

$conn->beginTransaction();
try {
    // Sequence number for this container within the day.
    $seq = (int)$conn->query("SELECT COUNT(*) FROM trips WHERE d_id = " . $dId)->fetchColumn() + 1;

    $ins = $conn->prepare(
        "INSERT INTO trips
            (d_id, trip_type, trip_purpose, costumer, trip_haulingsegment, trip_haulingtype,
             container_activity, trip_containerstat, trip_container, trip_from, trip_to,
             piece_rate, trip_status, segment_status)
         VALUES (?, ?, 'Hustling', 'DICT HUSTLING', 'DICT HUSTLING', '',
             'HUSTLING', ?, ?, ?, ?, ?, 'Active', 'Assigned')
         RETURNING trip_id"
    );
    $ins->execute([$dId, 'Container ' . $seq, $stat, $containerNo, $from, $to, $rate]);
    $tripId = (int)$ins->fetchColumn();

    pt_ensure_pickup_capture_table($conn);
    pt_ensure_column($conn, 'pickup_capture', 'driver_id_number', "VARCHAR(100) NOT NULL DEFAULT ''");
    $conn->prepare(
        "INSERT INTO pickup_capture (d_id, trip_id, driver_id, driver_id_number, container_no, photo_path, lat, lng, captured_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
    )->execute([$dId, $tripId, $driverId, $driverIdNumber, $containerNo, $photoPath, $lat, $lng]);

    $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, 'hustling_container', 'driver', ?, ?)"
    )->execute([$dId, $day['booking_no'], $driverId, 'Container ' . $containerNo . ' (' . $stat . ')']);

    // Running totals for the day.
    $cnt = (int)$conn->query("SELECT COUNT(*) FROM trips WHERE d_id = " . $dId)->fetchColumn();

    $conn->commit();
    hc_out([
        'status'  => 'success',
        'message' => 'Container ' . $containerNo . ' added.',
        'trip_id' => $tripId,
        'count'   => $cnt,
        'rate'    => $rate,
        'total'   => $rate * $cnt,
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    @unlink($dir . '/' . $name);
    hc_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
