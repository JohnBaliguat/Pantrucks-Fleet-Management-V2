<?php
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_push_send.php';
require_once __DIR__ . '/../helpers/upload_error_helper.php';
require_once __DIR__ . '/../helpers/idempotency_helper.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
// Breakdown photo is optional by default; Admin can make it required.
$breakdownPhotoRequired = pt_setting_bool($conn, 'require_breakdown_photo', false);

if (($overflow = upload_post_overflow()) !== null) {
    json_out(['status' => 'error', 'message' => $overflow], 413);
}

$idemKey = idempotency_replay_and_exit($conn);

$dId        = (int)($_POST['d_id'] ?? 0);
$type       = trim($_POST['incident_type'] ?? 'breakdown');
$severity   = trim($_POST['severity'] ?? 'med');
$assistance = trim($_POST['assistance'] ?? '');
$desc       = trim($_POST['description'] ?? '');
$lat        = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng        = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;

if ($desc === '') json_out(['status' => 'error', 'message' => 'description required'], 400);

// Photo upload (optional).
$dir = __DIR__ . '/../assets/uploads/incidents';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
$photoPath = '';
if (isset($_FILES['photo'])) {
    $photoErr = (int)$_FILES['photo']['error'];
    if ($photoErr === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) { $ext = 'jpg'; }
        $name = 'bd_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $name)) {
            $photoPath = 'php/assets/uploads/incidents/' . $name;
        }
    } elseif ($photoErr !== UPLOAD_ERR_NO_FILE) {
        // Photo was attached but failed (likely oversize) — surface the real reason.
        json_out(['status' => 'error', 'message' => upload_friendly_error($photoErr, 'Photo upload failed.')], upload_error_http_status($photoErr));
    }
}
if ($breakdownPhotoRequired && $photoPath === '') {
    json_out(['status' => 'error', 'message' => 'A photo is required for breakdown reports.'], 400);
}

$stmt = $conn->prepare(
    "INSERT INTO incident (d_id, driver_id, incident_type, severity, description, lat, lng, photo_path, assistance, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
     RETURNING inc_id"
);
$stmt->execute([$dId, $driverId, $type, $severity, $desc, $lat, $lng, $photoPath, $assistance]);
$incId = (int)$stmt->fetchColumn();
// Workflow audit on the dispatch timeline.
$bn = '';
if ($dId > 0) {
    $r = $conn->query("SELECT booking_no FROM dispatch WHERE d_id = " . (int)$dId . " LIMIT 1");
    if ($r && $row = $r->fetch()) { $bn = $row['booking_no']; }
}
$notes = "Driver breakdown #$incId — $type" . ($assistance !== '' ? " (assistance: $assistance)" : '') . ($desc !== '' ? " — $desc" : '');
$stmt = $conn->prepare("INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'incident_flagged', 'driver', ?, ?)");
$stmt->execute([$dId, $bn, $driverId, $notes]);
// Phase 6 — push to dispatchers' notification log.
pt_notify_dispatchers($conn, ($severity === 'high' ? '🚨 ' : '') . "Driver $type", $desc, $dId ?: null);

idempotency_json_out($conn, $idemKey, 'save_breakdown', $driverId, ['status' => 'success', 'message' => 'Reported. Dispatcher has been notified.', 'inc_id' => $incId]);
