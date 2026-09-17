<?php
// JSON safety net (display_errors off + exception/shutdown handlers)
// is installed by require_driver_session() in _driver_auth.php.
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';
require_once __DIR__ . '/../helpers/upload_error_helper.php';
require_once __DIR__ . '/../helpers/idempotency_helper.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
// Admin can make the container pickup photo optional from the Settings page.
$pickupPhotoRequired = pt_setting_bool($conn, 'require_pickup_photo', true);

if (($overflow = upload_post_overflow()) !== null) {
    json_out(['status' => 'error', 'message' => $overflow], 413);
}

$idemKey = idempotency_replay_and_exit($conn);

function pt_normalize_segment_token(string $value): string
{
    return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));
}

function pt_is_dict_hustling_exempt(string $segment, string $tripTo): bool
{
    foreach ([$segment, $tripTo] as $value) {
        $normalized = pt_normalize_segment_token($value);
        if ($normalized !== '' && strpos($normalized, 'DICT') !== false && strpos($normalized, 'HUSTLING') !== false) {
            return true;
        }
    }

    return false;
}

pt_ensure_trailer_jackup_table($conn);

$dId         = (int)($_POST['d_id'] ?? 0);
$status      = strtolower(trim($_POST['status'] ?? ''));   // picked_up | on_the_way | arrived | delivered | issue
$lat         = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng         = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
$note        = trim($_POST['note'] ?? '');
$containerNo = strtoupper(trim($_POST['container_no'] ?? ''));
if ($containerNo !== '' && !preg_match('/^[A-Z]{4}[0-9]{7}$/', $containerNo)) {
    json_out(['status' => 'error', 'message' => 'Container number must be exactly 4 capital letters followed by 7 numbers.'], 400);
}
// Container seal — optional, capped at 50 chars to match column width.
$containerSeal = mb_substr(strtoupper(trim($_POST['container_seal'] ?? '')), 0, 50);
// Shipping line — optional, capped to 50 chars (matches container_monitoring schema).
$shippingLine = mb_substr(strtoupper(trim($_POST['shipping_line'] ?? '')), 0, 50);

// Optional back-dated time (datetime-local: YYYY-MM-DDTHH:MM). Used when the
// driver records a step late from the base because there was no signal on
// site. When empty the event is stamped NOW() as usual.
$eventAt = null;
$eventTimeRaw = trim($_POST['event_time'] ?? '');
if ($eventTimeRaw !== '') {
    $normalized = str_replace('T', ' ', $eventTimeRaw);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $normalized)
        ?: DateTime::createFromFormat('Y-m-d H:i:s', $normalized);
    if (!$dt) {
        json_out(['status' => 'error', 'message' => 'Invalid time provided.'], 400);
    }
    // Allow 5 min of clock skew, but no genuinely future times.
    if ($dt->getTimestamp() > time() + 300) {
        json_out(['status' => 'error', 'message' => "The actual time can't be in the future."], 400);
    }
    $eventAt = $dt->format('Y-m-d H:i:s');
}

$valid = ['picked_up', 'on_the_way', 'arrived', 'delivered', 'issue'];
if ($dId <= 0 || !in_array($status, $valid, true)) {
    json_out(['status' => 'error', 'message' => 'd_id and a valid status required'], 400);
}

$stmt = $conn->prepare(
    "SELECT d.booking_no, d.driver_id, d.workflow_stage, d.d_trailer, drv.driver_idnumber
     FROM dispatch d
     LEFT JOIN drivers drv ON drv.driver_id = d.driver_id
     WHERE d.d_id = ?
     LIMIT 1"
);
$stmt->execute([$dId]);
$dispatch = $stmt->fetch();
if (!$dispatch || (int)$dispatch['driver_id'] !== $driverId) {
    json_out(['status' => 'error', 'message' => 'Dispatch not assigned to you'], 403);
}

// Enforce step order server-side — the driver can't skip ahead (e.g. mark
// delivered or arrived without first doing pickup). Exact replays are handled
// by idempotency above; this rejects genuine out-of-order taps.
if (in_array($status, ['picked_up', 'on_the_way', 'arrived', 'delivered'], true)) {
    $stageOrder = ['picked_up' => 0, 'on_the_way' => 1, 'arrived' => 2, 'delivered' => 3];
    $stmt = $conn->prepare(
        "SELECT DISTINCT stage FROM workflow_event
         WHERE d_id = ? AND actor_role = 'driver'
           AND stage IN ('picked_up','on_the_way','arrived','delivered')"
    );
    $stmt->execute([$dId]);
    $maxIdx = -1;
    while ($s = $stmt->fetchColumn()) {
        if (isset($stageOrder[$s])) { $maxIdx = max($maxIdx, $stageOrder[$s]); }
    }
    $newIdx = $stageOrder[$status];
    if ($newIdx <= $maxIdx) {
        json_out(['status' => 'error', 'message' => 'That step is already done.'], 409);
    }
    if ($newIdx > $maxIdx + 1) {
        $needed = array_search($maxIdx + 1, $stageOrder, true);
        $label = ['picked_up' => 'Picked up', 'on_the_way' => 'On the way', 'arrived' => 'Arrived', 'delivered' => 'Delivered'][$needed] ?? $needed;
        json_out(['status' => 'error', 'message' => 'Complete the previous step first (' . $label . ').'], 409);
    }
}

// A back-dated step must not land before an earlier recorded step — keeps the
// movement timeline in order even when filled in late.
if ($eventAt !== null) {
    $stmt = $conn->prepare(
        "SELECT MAX(event_at) FROM workflow_event
         WHERE d_id = ? AND actor_role = 'driver'
           AND stage IN ('picked_up','on_the_way','arrived','delivered')"
    );
    $stmt->execute([$dId]);
    $prevEventAt = $stmt->fetchColumn();
    if ($prevEventAt && $eventAt < $prevEventAt) {
        json_out(['status' => 'error', 'message' => 'That time is before an earlier step (' . $prevEventAt . '). Times must move forward.'], 400);
    }
}

// Phase 6 — receipt acknowledgement gate before going en-route.
if (in_array($status, ['picked_up', 'on_the_way'], true)) {
    $stmt = $conn->prepare(
        "SELECT r.dr_id, r.title, r.receipt_type
         FROM dispatch_receipt r
         LEFT JOIN receipt_acknowledge a ON a.dr_id = r.dr_id AND a.driver_id = ?
         WHERE r.d_id = ? AND r.requires_ack = TRUE AND a.ra_id IS NULL"
    );
    $stmt->execute([$driverId, $dId]);
    $unackd = $stmt->fetchAll();
if (!empty($unackd)) {
        json_out([
            'status' => 'error',
            'message' => 'Acknowledge ' . count($unackd) . ' receipt(s) before going en-route.',
            'unacknowledged' => $unackd,
        ], 409);
    }
}

$stageMap = [
    'picked_up'  => 'en_route',
    'on_the_way' => 'en_route',
    'arrived'    => 'en_route',
    'delivered'  => 'delivered',
    'issue'      => null,
];
$newStage = $stageMap[$status];
$bookingNo = (string)($dispatch['booking_no'] ?? '');
$driverIdNumber = trim((string)($dispatch['driver_idnumber'] ?? ''));
$dispatchTrailer = trim((string)($dispatch['d_trailer'] ?? ''));

$trip = null;
if ($status === 'picked_up') {
    $stmt = $conn->prepare(
        "SELECT trip_id, trip_container
         FROM trips
         WHERE d_id = ?
         ORDER BY trip_id DESC
         LIMIT 1"
    );
    $stmt->execute([$dId]);
    $trip = $stmt->fetch();
if (!$trip) {
        json_out(['status' => 'error', 'message' => 'No trip found for this dispatch'], 404);
    }

    if ($containerNo === '') {
        $containerNo = strtoupper(trim((string)($trip['trip_container'] ?? '')));
    }
    if ($containerNo === '') {
        json_out(['status' => 'error', 'message' => 'Container number is required for pickup.'], 400);
    }
    // Pickup photo: required by default, but Admin can switch it to optional.
    // A NO_FILE error just means none was attached; a real upload error is
    // always surfaced regardless of the requirement.
    $pickupPhotoUploaded = isset($_FILES['pickup_photo']) && (int)$_FILES['pickup_photo']['error'] === UPLOAD_ERR_OK;
    $pickupPhotoErr      = isset($_FILES['pickup_photo']) ? (int)$_FILES['pickup_photo']['error'] : UPLOAD_ERR_NO_FILE;
    if ($pickupPhotoRequired && !$pickupPhotoUploaded) {
        json_out(['status' => 'error', 'message' => upload_friendly_error($pickupPhotoErr, 'Pickup photo is required.')], upload_error_http_status($pickupPhotoErr));
    }
    if (!$pickupPhotoRequired && isset($_FILES['pickup_photo'])
        && (int)$_FILES['pickup_photo']['error'] !== UPLOAD_ERR_OK
        && (int)$_FILES['pickup_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Photo was attached but failed (e.g. oversize) — surface the real reason.
        $code = (int)$_FILES['pickup_photo']['error'];
        json_out(['status' => 'error', 'message' => upload_friendly_error($code, 'Pickup photo upload failed.')], upload_error_http_status($code));
    }
}

// Driver may opt out of the jackup gate by passing skip_jackup=1
// (set by the dashboard's "Jackup trailer? No" path).
$skipJackup = ((string)($_POST['skip_jackup'] ?? '') === '1');

if ($status === 'delivered' && $dispatchTrailer !== '' && !$skipJackup) {
    $stmt = $conn->prepare(
        "SELECT trip_haulingsegment, trip_to
         FROM trips
         WHERE d_id = ?
         ORDER BY trip_id DESC
         LIMIT 1"
    );
    $stmt->execute([$dId]);
    $tripForDelivery = $stmt->fetch();
if (!$tripForDelivery) {
        json_out(['status' => 'error', 'message' => 'No trip found for this dispatch'], 404);
    }

    $tripSegment = trim((string)($tripForDelivery['trip_haulingsegment'] ?? ''));
    $tripTo = trim((string)($tripForDelivery['trip_to'] ?? ''));
    if (!pt_is_dict_hustling_exempt($tripSegment, $tripTo)) {
        $stmt = $conn->prepare(
            "SELECT tj_id
             FROM trailer_jackup
             WHERE d_id = ? AND trailer_code = ?
             ORDER BY tj_id DESC
             LIMIT 1"
        );
        $stmt->execute([$dId, $dispatchTrailer]);
        $jackupRow = $stmt->fetch();
if (!$jackupRow) {
            json_out([
                'status' => 'error',
                'message' => 'Jack-up the trailer before marking this trip delivered.',
            ], 409);
        }
    }
}

$pickupPhotoPath = '';
if ($status === 'picked_up' && !empty($pickupPhotoUploaded)) {
    $dir = __DIR__ . '/../assets/uploads/pickup';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $ext = strtolower(pathinfo($_FILES['pickup_photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) {
        $ext = 'jpg';
    }
    $name = 'pickup_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    if (!move_uploaded_file($_FILES['pickup_photo']['tmp_name'], $dir . '/' . $name)) {
        json_out(['status' => 'error', 'message' => 'Failed to save pickup photo.'], 500);
    }
    $pickupPhotoPath = 'php/assets/uploads/pickup/' . $name;
}

$conn->beginTransaction();
try {
    if ($status === 'picked_up') {
        pt_ensure_pickup_capture_table($conn);
        pt_ensure_column($conn, 'pickup_capture', 'driver_id_number', "VARCHAR(100) NOT NULL DEFAULT ''");
        // Optional columns added in later migrations — ensure they exist
        // so older deployments grow them automatically on the first pickup.
        pt_ensure_column($conn, 'pickup_capture', 'container_seal', "VARCHAR(50) NOT NULL DEFAULT ''");
        pt_ensure_column($conn, 'pickup_capture', 'shipping_line',  "VARCHAR(50) NOT NULL DEFAULT ''");
        pt_ensure_column($conn, 'booking',        'shipping_line',  "VARCHAR(50) NOT NULL DEFAULT ''");

        $stmt = $conn->prepare("UPDATE trips SET trip_container = ? WHERE trip_id = ?");
        $stmt->execute([$containerNo, $trip['trip_id']]);
$stmt = $conn->prepare(
            "INSERT INTO pickup_capture (d_id, trip_id, driver_id, driver_id_number, container_no, container_seal, shipping_line, photo_path, lat, lng, captured_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, CURRENT_TIMESTAMP))"
        );
        $stmt->execute([$dId, $trip['trip_id'], $driverId, $driverIdNumber, $containerNo, $containerSeal, $shippingLine, $pickupPhotoPath, $lat, $lng, $eventAt]);
        // Mirror onto the booking when the booking didn't already have one
        // — dispatcher then sees what the driver actually picked up.
        if ($bookingNo !== '' && $containerSeal !== '') {
            $stmt = $conn->prepare(
                "UPDATE booking
                    SET container_seal = ?
                  WHERE booking_no = ?
                    AND (container_seal IS NULL OR TRIM(container_seal) = '')"
            );
            $stmt->execute([$containerSeal, $bookingNo]);
        }
        if ($bookingNo !== '' && $shippingLine !== '') {
            $stmt = $conn->prepare(
                "UPDATE booking
                    SET shipping_line = ?
                  WHERE booking_no = ?
                    AND (shipping_line IS NULL OR TRIM(shipping_line) = '')"
            );
            $stmt->execute([$shippingLine, $bookingNo]);
        }
        if ($bookingNo !== '') {
            cl_advance_booking($conn, $bookingNo, 'pickup');
        }
    }

    if ($newStage !== null) {
        $stmt = $conn->prepare("UPDATE dispatch SET workflow_stage = ?, workflow_updated_at = NOW() WHERE d_id = ?");
        $stmt->execute([$newStage, $dId]);
}

    $notes = "Status: $status";
    if ($containerNo !== '') {
        $notes .= " | container=$containerNo";
    }
    if ($driverIdNumber !== '') {
        $notes .= " | driver_IdNumber=$driverIdNumber";
    }
    if ($pickupPhotoPath !== '') {
        $notes .= " | photo=$pickupPhotoPath";
    }
    if ($note !== '') {
        $notes .= " | note=$note";
    }
    if ($eventAt !== null) {
        $notes .= " | back-dated to $eventAt";
    }

    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes, event_at)
         VALUES (?, ?, ?, 'driver', ?, ?, COALESCE(?, CURRENT_TIMESTAMP))"
    );
    $stmt->execute([$dId, $bookingNo, $status, $driverId, $notes, $eventAt]);
if ($lat !== null && $lng !== null) {
        $stmt = $conn->prepare("UPDATE drivers SET last_lat = ?, last_lng = ?, last_seen_at = NOW() WHERE driver_id = ?");
        $stmt->execute([$lat, $lng, $driverId]);
}

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    if ($pickupPhotoPath !== '') {
        @unlink(__DIR__ . '/../assets/uploads/pickup/' . basename($pickupPhotoPath));
    }
    json_out(['status' => 'error', 'message' => 'Status save failed: ' . $e->getMessage()], 500);
}

idempotency_json_out($conn, $idemKey, 'driver_update_status', $driverId, ['status' => 'success', 'message' => 'Status saved.']);
