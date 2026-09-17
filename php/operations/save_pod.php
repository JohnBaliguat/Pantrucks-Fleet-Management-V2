<?php
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';
require_once __DIR__ . '/../helpers/upload_error_helper.php';
require_once __DIR__ . '/../helpers/idempotency_helper.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
// Admin can make POD photos optional from the Settings page.
$podPhotosRequired = pt_setting_bool($conn, 'require_pod_photos', true);

if (($overflow = upload_post_overflow()) !== null) {
    json_out(['status' => 'error', 'message' => $overflow], 413);
}

$idemKey = idempotency_replay_and_exit($conn);

$dId       = (int)($_POST['d_id'] ?? 0);
$tripId    = (int)($_POST['trip_id'] ?? 0);
$signedBy  = trim($_POST['signed_by'] ?? '');
$lat       = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng       = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
$signatureDataUrl = $_POST['signature'] ?? '';

if ($dId <= 0) json_out(['status' => 'error', 'message' => 'd_id required'], 400);

foreach (['photo1', 'photo2'] as $required) {
    if (isset($_FILES[$required]) && (int)$_FILES[$required]['error'] !== UPLOAD_ERR_OK
        && (int)$_FILES[$required]['error'] !== UPLOAD_ERR_NO_FILE) {
        $code = (int)$_FILES[$required]['error'];
        json_out(['status' => 'error', 'message' => upload_friendly_error($code, 'At least 2 photos required.')], upload_error_http_status($code));
    }
}

// Confirm dispatch belongs to this driver.
$stmt = $conn->prepare("SELECT booking_no, driver_id, workflow_stage FROM dispatch WHERE d_id = ? LIMIT 1");
$stmt->execute([$dId]);
$row = $stmt->fetch();
if (!$row || (int)$row['driver_id'] !== $driverId) {
    json_out(['status' => 'error', 'message' => 'Dispatch not assigned to you'], 403);
}

// Duplicate-submission guard. A POD can only be captured while the trip is
// still en_route / delivered. Once it's submitted the stage moves to
// pending_verification, so any repeat POST (slow network re-taps, offline
// queue replays with a fresh key) is treated as already-done instead of
// inserting a second pod_capture row. A rejected POD sends the stage back to
// en_route, so legitimate re-capture still works.
$wfStage = strtolower(trim((string)($row['workflow_stage'] ?? '')));
if (!in_array($wfStage, ['en_route', 'delivered'], true)) {
    json_out(['status' => 'success', 'message' => 'POD already submitted. Awaiting dispatcher verification.']);
}

$dir = __DIR__ . '/../assets/uploads/pod';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

function save_upload(string $field, string $dir): string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return '';
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','heic'], true)) $ext = 'jpg';
    $name = 'pod_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '_' . $field . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) return '';
    return 'php/assets/uploads/pod/' . $name;
}

$p1 = save_upload('photo1', $dir);
$p2 = save_upload('photo2', $dir);
$p3 = save_upload('photo3', $dir);

if ($podPhotosRequired && ($p1 === '' || $p2 === '')) {
    json_out(['status' => 'error', 'message' => 'At least 2 photos required.']);
}

// Decode signature dataURL.
$sigPath = '';
if (preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $signatureDataUrl, $m)) {
    $bin = base64_decode($m[2]);
    if ($bin !== false) {
        $name = 'pod_sig_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.png';
        file_put_contents($dir . '/' . $name, $bin);
        $sigPath = 'php/assets/uploads/pod/' . $name;
    }
}

$stmt = $conn->prepare(
    "INSERT INTO pod_capture (d_id, trip_id, driver_id, photo1_path, photo2_path, photo3_path, signature_path, signed_by, lat, lng)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([$dId, $tripId, $driverId, $p1, $p2, $p3, $sigPath, $signedBy, $lat, $lng]);
// Phase 7 — POD now lands at 'pending_verification'. The dispatcher
// must explicitly verify before the trip flips to pod_captured /
// completed. Workflow stays here so it shows up on the verification
// queue.
$stmt = $conn->prepare(
    "UPDATE dispatch
     SET workflow_stage = 'pending_verification', workflow_updated_at = NOW()
     WHERE d_id = ? AND workflow_stage IN ('en_route', 'delivered')"
);
$stmt->execute([$dId]);
$bn = $row['booking_no'];
$stmt = $conn->prepare("INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'pending_verification', 'driver', ?, 'POD captured — awaiting dispatcher verification')");
$stmt->execute([$dId, $bn, $driverId]);
// Phase 14 — POD capture moves the container status to *Delivered.
// The dispatcher's later "verify" step closes billing but doesn't move
// container_status; it's already physically delivered.
if ($bn !== '') {
    cl_advance_booking($conn, $bn, 'delivered');
}

idempotency_json_out($conn, $idemKey, 'save_pod', $driverId, ['status' => 'success', 'message' => 'POD submitted. Awaiting dispatcher verification.']);
