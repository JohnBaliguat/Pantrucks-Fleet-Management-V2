<?php
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';
require_once __DIR__ . '/../helpers/upload_error_helper.php';
require_once __DIR__ . '/../helpers/idempotency_helper.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
require_once __DIR__ . '/../lib/geotab_geofence.php';
// Admin can make gate photos optional from the Settings page.
$gatePhotosRequired = pt_setting_bool($conn, 'require_gate_photos', true);

if (($overflow = upload_post_overflow()) !== null) {
    json_out(['status' => 'error', 'message' => $overflow], 413);
}

$idemKey = idempotency_replay_and_exit($conn);

$dId        = (int)($_POST['d_id'] ?? 0);
$tripId     = (int)($_POST['trip_id'] ?? 0);
$lat        = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng        = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
$accuracy   = (int)($_POST['accuracy_m'] ?? 0);
$capturedAt = trim($_POST['captured_at'] ?? '');
$offline    = !empty($_POST['offline']) ? 1 : 0;

if ($dId <= 0 || $lat === null || $lng === null) {
    json_out(['status' => 'error', 'message' => 'd_id and GPS required'], 400);
}

// Normalise captured_at (ISO -> MySQL DATETIME). Defaults to now.
$capturedAtMysql = null;
if ($capturedAt !== '') {
    try { $capturedAtMysql = (new DateTime($capturedAt))->format('Y-m-d H:i:s'); } catch (Exception $e) { /* fall through */ }
}
if (!$capturedAtMysql) { $capturedAtMysql = date('Y-m-d H:i:s'); }

// Confirm dispatch ownership.
$stmt = $conn->prepare("SELECT booking_no, driver_id, workflow_stage FROM dispatch WHERE d_id = ? LIMIT 1");
$stmt->execute([$dId]);
$row = $stmt->fetch();
if (!$row || (int)$row['driver_id'] !== $driverId) {
    json_out(['status' => 'error', 'message' => 'Dispatch not assigned to you'], 403);
}

// Duplicate-submission guard. If a gateless completion already exists for
// this dispatch, treat repeat POSTs (slow-network re-taps, offline replays
// with a fresh key) as already-done instead of inserting a duplicate row.
// An existence check is used rather than workflow_stage because gateless
// itself sets the stage to 'delivered', which would otherwise be ambiguous.
try {
    $dupStmt = $conn->prepare("SELECT 1 FROM gateless_completion WHERE d_id = ? LIMIT 1");
    $dupStmt->execute([$dId]);
    if ($dupStmt->fetchColumn()) {
        json_out(['status' => 'success', 'message' => 'Gateless completion already saved.']);
    }
} catch (Throwable $e) {
    // Table may not exist on a legacy deployment — fall through to the insert,
    // which will create/populate it as before.
}

// Photo uploads.
$dir = __DIR__ . '/../assets/uploads/gateless';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

function gl_save(string $field, string $dir): string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) { return ''; }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) { $ext = 'jpg'; }
    $name = 'gl_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '_' . $field . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) { return ''; }
    return 'php/assets/uploads/gateless/' . $name;
}
foreach (['photo1', 'photo2'] as $required) {
    if (isset($_FILES[$required])) {
        $code = (int)$_FILES[$required]['error'];
        if ($code !== UPLOAD_ERR_OK && $code !== UPLOAD_ERR_NO_FILE) {
            json_out(['status' => 'error', 'message' => upload_friendly_error($code, 'Two photos required.')], upload_error_http_status($code));
        }
    }
}
$p1 = gl_save('photo1', $dir);
$p2 = gl_save('photo2', $dir);
if ($gatePhotosRequired && ($p1 === '' || $p2 === '')) {
    json_out(['status' => 'error', 'message' => 'Two photos required.']);
}

$stmt = $conn->prepare(
    "INSERT INTO gateless_completion
        (d_id, trip_id, driver_id, lat, lng, accuracy_m, photo1_path, photo2_path, captured_at, offline)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
// Types: d_id=i, trip_id=i, driver_id=i, lat=d, lng=d, accuracy_m=i, photo1=s, photo2=s, captured_at=s, offline=i
$stmt->execute([$dId, $tripId, $driverId, $lat, $lng, $accuracy, $p1, $p2, $capturedAtMysql, $offline]);
// Workflow advance — gateless completion is an end-state for the run.
$stmt = $conn->prepare(
    "UPDATE dispatch SET workflow_stage = 'delivered', workflow_updated_at = NOW()
     WHERE d_id = ? AND workflow_stage IN ('en_route', 'gate_cleared')"
);
$stmt->execute([$dId]);
$bn = $row['booking_no'];
// Geotab geofence context: name the zone the completion happened in, if any.
// Best-effort — a lookup failure must not block completion.
$note = 'Gateless completion (GPS + 2 photos)';
try {
    $z = pt_geofence_zone_for_point($conn, $lat, $lng);
    $note .= $z ? (' · inside zone "' . $z['name'] . '"') : ' · outside known zones';
} catch (Throwable $e) {
    // Zones not configured / table missing — leave the base note.
}
$stmt = $conn->prepare(
    "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
     VALUES (?, ?, 'delivered', 'driver', ?, ?)"
);
$stmt->execute([$dId, $bn, $driverId, $note]);
// Phase 14 — gateless completion is an end-state, container is delivered.
if ($bn !== '') {
    cl_advance_booking($conn, $bn, 'delivered');
}

idempotency_json_out($conn, $idemKey, 'save_gateless', $driverId, ['status' => 'success', 'message' => 'Gateless completion saved.']);
