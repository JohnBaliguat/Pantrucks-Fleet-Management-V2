<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';
require_once __DIR__ . '/../helpers/upload_error_helper.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
require_once __DIR__ . '/_push_send.php';

// Manual completion honours the same admin photo toggles as the driver app.
// When a requirement is switched off, the dispatcher isn't forced to supply
// that photo for the missing capture either.
$pickupPhotoRequired = pt_setting_bool($conn, 'require_pickup_photo', true);
$podPhotosRequired   = pt_setting_bool($conn, 'require_pod_photos', true);

$role = $_SESSION['user_type'] ?? '';
$allowedRoles = ['Admin', 'Dispatch Admin', 'Dispatcher'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'You are not allowed to manually complete trips.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

if (($overflow = upload_post_overflow()) !== null) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => $overflow]);
    exit;
}

$dId   = (int)($_POST['d_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$signedBy = trim($_POST['signed_by'] ?? '');
$actor = (int)($_SESSION['user_id'] ?? 0);

if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

if ($notes === '') {
    echo json_encode(['status' => 'error', 'message' => 'Completion reason is required.']);
    exit;
}

// ---- Movement timestamps (datetime-local: YYYY-MM-DDTHH:MM) -----------------
function mc_parse_ts($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('T', ' ', $value);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
}

// DICT-hustling legs keep the trailer with the driver, so they're exempt from
// the auto jack-up (mirrors pt_is_dict_hustling_exempt in driver_update_status).
function mc_is_dict_hustling_exempt(string $segment, string $tripTo): bool {
    foreach ([$segment, $tripTo] as $value) {
        $t = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));
        if ($t !== '' && strpos($t, 'DICT') !== false && strpos($t, 'HUSTLING') !== false) {
            return true;
        }
    }
    return false;
}

$tsPickedUp  = mc_parse_ts($_POST['ts_picked_up'] ?? '');
$tsOnTheWay  = mc_parse_ts($_POST['ts_on_the_way'] ?? '');
$tsArrived   = mc_parse_ts($_POST['ts_arrived'] ?? '');
$tsDelivered = mc_parse_ts($_POST['ts_delivered'] ?? '');

// Admin can make the four movement timestamps optional.
$movementRequired = pt_setting_bool($conn, 'require_movement_timestamps', true);

if ($movementRequired) {
    if (!$tsPickedUp || !$tsOnTheWay || !$tsArrived || !$tsDelivered) {
        echo json_encode(['status' => 'error', 'message' => 'All four movement timestamps are required and must be valid dates.']);
        exit;
    }
    if (!($tsPickedUp <= $tsOnTheWay && $tsOnTheWay <= $tsArrived && $tsArrived <= $tsDelivered)) {
        echo json_encode(['status' => 'error', 'message' => 'Timestamps must be in order: Picked up ≤ On the way ≤ Arrived ≤ Delivered.']);
        exit;
    }
} else {
    // Not required — any blank time defaults to the completion time so the
    // downstream capture rows / workflow events still get a valid timestamp.
    $fallbackTs = date('Y-m-d H:i:s');
    $tsPickedUp  = $tsPickedUp  ?: $fallbackTs;
    $tsOnTheWay  = $tsOnTheWay  ?: $fallbackTs;
    $tsArrived   = $tsArrived   ?: $fallbackTs;
    $tsDelivered = $tsDelivered ?: $fallbackTs;
    // Only enforce ordering when the dispatcher actually supplied all four.
    $allProvided = mc_parse_ts($_POST['ts_picked_up'] ?? '') && mc_parse_ts($_POST['ts_on_the_way'] ?? '')
                && mc_parse_ts($_POST['ts_arrived'] ?? '') && mc_parse_ts($_POST['ts_delivered'] ?? '');
    if ($allProvided && !($tsPickedUp <= $tsOnTheWay && $tsOnTheWay <= $tsArrived && $tsArrived <= $tsDelivered)) {
        echo json_encode(['status' => 'error', 'message' => 'Timestamps must be in order: Picked up ≤ On the way ≤ Arrived ≤ Delivered.']);
        exit;
    }
}

// ---- What did the driver already capture? -----------------------------------
// If a pickup / POD already exists for this dispatch, the dispatcher doesn't
// have to re-provide it — we reuse what's there instead of inserting again.
$hasPickup = false;
$hasPod = false;
try {
    $q = $conn->prepare("SELECT 1 FROM pickup_capture WHERE d_id = ? LIMIT 1");
    $q->execute([$dId]);
    $hasPickup = (bool)$q->fetchColumn();
} catch (Throwable $e) { /* table may not exist on legacy DB */ }
try {
    $q = $conn->prepare("SELECT 1 FROM pod_capture WHERE d_id = ? LIMIT 1");
    $q->execute([$dId]);
    $hasPod = (bool)$q->fetchColumn();
} catch (Throwable $e) { /* table may not exist on legacy DB */ }

// ---- Photo validation (only what's still missing) ---------------------------
$hasPickupUpload = isset($_FILES['pickup_photo']) && (int)$_FILES['pickup_photo']['error'] === UPLOAD_ERR_OK;
if ($pickupPhotoRequired && !$hasPickup && !$hasPickupUpload) {
    $code = isset($_FILES['pickup_photo']) ? (int)$_FILES['pickup_photo']['error'] : UPLOAD_ERR_NO_FILE;
    http_response_code(upload_error_http_status($code));
    echo json_encode(['status' => 'error', 'message' => upload_friendly_error($code, 'A pickup photo is required.')]);
    exit;
}
if ($podPhotosRequired && !$hasPod) {
    foreach (['pod_photo1', 'pod_photo2'] as $required) {
        if (!isset($_FILES[$required]) || (int)$_FILES[$required]['error'] !== UPLOAD_ERR_OK) {
            $code = isset($_FILES[$required]) ? (int)$_FILES[$required]['error'] : UPLOAD_ERR_NO_FILE;
            http_response_code(upload_error_http_status($code));
            echo json_encode(['status' => 'error', 'message' => upload_friendly_error($code, 'At least 2 POD photos are required.')]);
            exit;
        }
    }
}

// ---- Save uploaded files (before the DB transaction) ------------------------
function mc_save_upload(string $field, string $dir, string $prefix): string {
    if (!isset($_FILES[$field]) || (int)$_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) {
        $ext = 'jpg';
    }
    $name = $prefix . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        return '';
    }
    return $name;
}

$pickupDir = __DIR__ . '/../assets/uploads/pickup';
$podDir    = __DIR__ . '/../assets/uploads/pod';

$savedFiles = []; // absolute paths for rollback cleanup

// Pickup photo — only save a new one when the driver hasn't already.
$pickupPath = '';
if (!$hasPickup && $hasPickupUpload) {
    $pickupName = mc_save_upload('pickup_photo', $pickupDir, 'pickup');
    if ($pickupName === '') {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save pickup photo.']);
        exit;
    }
    $savedFiles[] = $pickupDir . '/' . $pickupName;
    $pickupPath = 'php/assets/uploads/pickup/' . $pickupName;
}

// POD photos — only save new ones when the driver hasn't already submitted POD.
$podRel = ['pod_photo1' => '', 'pod_photo2' => '', 'pod_photo3' => ''];
if (!$hasPod) {
    foreach (array_keys($podRel) as $field) {
        $n = mc_save_upload($field, $podDir, 'pod');
        if ($n !== '') {
            $savedFiles[] = $podDir . '/' . $n;
            $podRel[$field] = 'php/assets/uploads/pod/' . $n;
        }
    }
    if ($podPhotosRequired && ($podRel['pod_photo1'] === '' || $podRel['pod_photo2'] === '')) {
        foreach ($savedFiles as $f) { @unlink($f); }
        echo json_encode(['status' => 'error', 'message' => 'At least 2 POD photos are required.']);
        exit;
    }
}

$cleanup = function () use ($savedFiles) {
    foreach ($savedFiles as $f) { @unlink($f); }
};

// Ensure the jack-up table exists before the transaction (DDL outside the tx).
pt_ensure_trailer_jackup_table($conn);

$conn->beginTransaction();

try {
    // Lock the dispatch row only — no join, so it stays portable across
    // MySQL/MariaDB and PostgreSQL (Postgres rejects FOR UPDATE on the
    // nullable side of an outer join).
    $stmt = $conn->prepare(
        "SELECT booking_no, driver_id, workflow_stage, d_trailer, d_truck
         FROM dispatch
         WHERE d_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$dId]);
    $dispatch = $stmt->fetch();

    if (!$dispatch) {
        throw new Exception('Dispatch not found.');
    }

    // Driver ID number is fetched separately (it lives on the nullable side).
    $dispatch['driver_idnumber'] = '';
    if (!empty($dispatch['driver_id'])) {
        $drvStmt = $conn->prepare("SELECT driver_idnumber FROM drivers WHERE driver_id = ? LIMIT 1");
        $drvStmt->execute([(int)$dispatch['driver_id']]);
        $dispatch['driver_idnumber'] = (string)($drvStmt->fetchColumn() ?: '');
    }

    $wf = (string)($dispatch['workflow_stage'] ?? '');
    if (!in_array($wf, ['driver_accepted', 'gate_cleared', 'en_route', 'delivered', 'pending_verification'], true)) {
        throw new Exception('Only active or awaiting-verification trips can be manually completed.');
    }

    $bookingNo = trim((string)($dispatch['booking_no'] ?? ''));
    $driverId  = (int)($dispatch['driver_id'] ?? 0);
    $driverIdNumber = trim((string)($dispatch['driver_idnumber'] ?? ''));

    // Latest trip for this dispatch — used for pickup/POD capture rows.
    $stmt = $conn->prepare("SELECT trip_id, trip_container, trip_haulingsegment, trip_to FROM trips WHERE d_id = ? ORDER BY trip_id DESC LIMIT 1");
    $stmt->execute([$dId]);
    $trip = $stmt->fetch();
    $tripId    = (int)($trip['trip_id'] ?? 0);
    // Container: a dispatcher-entered value (optional) takes precedence over the
    // trip's recorded container. Never required — empty trips can complete blank.
    $containerInput = strtoupper(trim((string)($_POST['container_no'] ?? '')));
    $containerNo = $containerInput !== '' ? $containerInput : strtoupper(trim((string)($trip['trip_container'] ?? '')));
    if ($containerInput !== '' && $tripId > 0) {
        $conn->prepare("UPDATE trips SET trip_container = ? WHERE trip_id = ?")->execute([$containerInput, $tripId]);
    }

    // --- Pickup capture — only create one if the driver didn't already. ------
    if (!$hasPickup && $pickupPath !== '') {
        pt_ensure_pickup_capture_table($conn);
        pt_ensure_column($conn, 'pickup_capture', 'driver_id_number', "VARCHAR(100) NOT NULL DEFAULT ''");
        $stmt = $conn->prepare(
            "INSERT INTO pickup_capture (d_id, trip_id, driver_id, driver_id_number, container_no, photo_path, captured_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$dId, $tripId, $driverId, $driverIdNumber, $containerNo, $pickupPath, $tsPickedUp]);
    }

    // --- Movement workflow events — only fill stages with no event yet. ------
    $existingStages = [];
    $q = $conn->prepare(
        "SELECT DISTINCT stage FROM workflow_event
         WHERE d_id = ? AND stage IN ('picked_up','on_the_way','arrived','delivered')"
    );
    $q->execute([$dId]);
    while ($s = $q->fetchColumn()) { $existingStages[$s] = true; }

    $pickupNote = 'Manually back-filled by ' . $role . ' #' . $actor;
    if ($pickupPath !== '') { $pickupNote .= ' | photo=' . $pickupPath; }
    $events = [
        ['picked_up',  $tsPickedUp,  $pickupNote],
        ['on_the_way', $tsOnTheWay,  'Manually back-filled by ' . $role . ' #' . $actor],
        ['arrived',    $tsArrived,   'Manually back-filled by ' . $role . ' #' . $actor],
        ['delivered',  $tsDelivered, 'Manually back-filled by ' . $role . ' #' . $actor],
    ];
    $evStmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes, event_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($events as $ev) {
        if (isset($existingStages[$ev[0]])) { continue; } // keep the driver's original time
        $evStmt->execute([$dId, $bookingNo, $ev[0], $role, $actor, $ev[2], $ev[1]]);
    }

    // --- POD capture — only create one if the driver didn't already. ---------
    if (!$hasPod && $podRel['pod_photo1'] !== '') {
        // Left unverified on purpose — the dispatcher approves it in the
        // verification queue, which stamps verified_by/at then.
        $stmt = $conn->prepare(
            "INSERT INTO pod_capture (d_id, trip_id, driver_id, photo1_path, photo2_path, photo3_path, signed_by, captured_at, verification_notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $dId, $tripId, $driverId,
            $podRel['pod_photo1'], $podRel['pod_photo2'], $podRel['pod_photo3'],
            $signedBy, $tsDelivered, $notes,
        ]);
    } elseif ($hasPod && $signedBy !== '') {
        // Keep the recipient name in sync if the dispatcher filled/edited it.
        $stmt = $conn->prepare(
            "UPDATE pod_capture SET signed_by = ?
             WHERE d_id = ? AND (signed_by IS NULL OR TRIM(signed_by) = '')"
        );
        $stmt->execute([$signedBy, $dId]);
    }

    // --- Submit for verification (NOT completion) ----------------------------
    // Manual "complete" no longer closes the trip — it back-fills the missing
    // captures and drops the dispatch into the dispatcher's verification queue,
    // exactly like a driver-submitted POD. The dispatcher then Approves, which
    // generates the coupon and runs the completion side-effects (trailer
    // jack-up, booking advance, trip_status='Done') in approve_trip.php.
    $stmt = $conn->prepare(
        "UPDATE dispatch
         SET workflow_stage = 'pending_verification',
             workflow_updated_at = NOW()
         WHERE d_id = ?"
    );
    $stmt->execute([$dId]);

    $eventNote = sprintf('Manually submitted for verification by %s #%d - %s', $role, $actor, $notes);
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes, event_at)
         VALUES (?, ?, 'pending_verification', ?, ?, ?, NOW())"
    );
    $stmt->execute([$dId, $bookingNo, $role, $actor, $eventNote]);

    $conn->commit();

    if ($driverId > 0) {
        pt_notify_driver(
            $conn,
            $driverId,
            'Trip submitted for verification',
            $bookingNo !== ''
                ? ($bookingNo . ' was submitted for verification by ' . $role . '. Reason: ' . $notes)
                : ($role . ' submitted your trip for verification. Reason: ' . $notes),
            $dId
        );
    }

    echo json_encode(['status' => 'success', 'message' => 'Trip submitted for verification.']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $cleanup();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
