<?php
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/upload_error_helper.php';
require_once __DIR__ . '/../helpers/idempotency_helper.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
// Admin can make the jack-up photo optional from the Settings page.
$jackupPhotoRequired = pt_setting_bool($conn, 'require_jackup_photo', true);

if (($overflow = upload_post_overflow()) !== null) {
    json_out(['status' => 'error', 'message' => $overflow], 413);
}

$idemKey = idempotency_replay_and_exit($conn);

pt_ensure_trailer_jackup_table($conn);

$dId         = (int)($_POST['d_id'] ?? 0);
$trailerCode = strtoupper(trim($_POST['trailer_code'] ?? ''));
$lat         = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng         = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;

if ($trailerCode === '') {
    json_out(['status' => 'error', 'message' => 'trailer_code required'], 400);
}

$stmt = $conn->prepare(
    "SELECT booking_no, d_truck, d_trailer
     FROM dispatch
     WHERE d_id = ? AND driver_id = ?
     LIMIT 1"
);
$stmt->execute([$dId, $driverId]);
$dispatch = $stmt->fetch();
if (!$dispatch) {
    json_out(['status' => 'error', 'message' => 'Dispatch not assigned to you'], 403);
}

$bookingNo = trim((string)($dispatch['booking_no'] ?? ''));
$truckCode = trim((string)($dispatch['d_truck'] ?? ''));
$dispatchTrailer = strtoupper(trim((string)($dispatch['d_trailer'] ?? '')));
if ($dispatchTrailer !== '' && $trailerCode !== $dispatchTrailer) {
    json_out(['status' => 'error', 'message' => 'Trailer code does not match the assigned trailer.'], 400);
}

// A real upload is one that arrived without error. NO_FILE just means the
// driver didn't attach a photo — only an error when it's required.
$photoUploaded = isset($_FILES['photo']) && (int)$_FILES['photo']['error'] === UPLOAD_ERR_OK;
$photoErr      = isset($_FILES['photo']) ? (int)$_FILES['photo']['error'] : UPLOAD_ERR_NO_FILE;
if ($jackupPhotoRequired && !$photoUploaded) {
    json_out(['status' => 'error', 'message' => upload_friendly_error($photoErr, 'Photo of the detached trailer is required.')], upload_error_http_status($photoErr));
}

// Save the photo when one was attached (required or not).
$photoPath = '';
if ($photoUploaded) {
    $dir = __DIR__ . '/../assets/uploads/jackup';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) { $ext = 'jpg'; }
    $name = 'tj_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $name)) {
        $photoPath = 'php/assets/uploads/jackup/' . $name;
    }
    if ($photoPath === '') {
        json_out(['status' => 'error', 'message' => 'Failed to save trailer photo.'], 500);
    }
}

$conn->beginTransaction();
try {
    $stmt = $conn->prepare(
        "INSERT INTO trailer_jackup (d_id, trailer_code, driver_id, lat, lng, photo_path, billing_active)
         VALUES (?, ?, ?, ?, ?, ?, TRUE)
         RETURNING tj_id"
    );
    $stmt->execute([$dId, $trailerCode, $driverId, $lat, $lng, $photoPath]);
    $tjId = (int)$stmt->fetchColumn();
$stmt = $conn->prepare(
        "UPDATE trailer
         SET trailer_assignto = '',
             driver_id = 0,
             trailer_status = 'Good'
         WHERE trailer_name = ? AND driver_id = ?"
    );
    $stmt->execute([$trailerCode, $driverId]);
if ($truckCode !== '') {
        $stmt = $conn->prepare(
            "UPDATE units
             SET unit_assigntrailer = ''
             WHERE unit_name = ? AND unit_assigntrailer = ?"
        );
        $stmt->execute([$truckCode, $trailerCode]);
}

    $notes = "Trailer $trailerCode jacked up at " . ($lat !== null ? "$lat,$lng" : 'unknown location') . " | released_from_driver=1";
    $stmt = $conn->prepare("INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'trailer_jackup', 'driver', ?, ?)");
    $stmt->execute([$dId, $bookingNo, $driverId, $notes]);
$conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    if ($photoPath !== '') {
        @unlink(__DIR__ . '/../assets/uploads/jackup/' . basename($photoPath));
    }
    json_out(['status' => 'error', 'message' => 'Jack-up failed: ' . $e->getMessage()], 500);
}

idempotency_json_out($conn, $idemKey, 'save_trailer_jackup', $driverId, ['status' => 'success', 'message' => 'Trailer jacked up and released from the driver. Billing continues until compound return.', 'tj_id' => $tjId]);
