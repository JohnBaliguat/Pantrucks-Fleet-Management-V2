<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$q    = trim($_GET['q'] ?? '');
$mode = $_GET['mode'] ?? 'plate';        // plate | qr
if ($q === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty query']);
    exit;
}

// QR payloads can carry the d_id, the booking_no, or fall through to a
// plate. We try each in order so the gate-guard scanner just works.
$dispatchRow = null;

function fetchOne(PDO $conn, string $sql, string $types, $param) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->execute([$param]);
    $r = $stmt->fetch();
return $r ?: null;
}

if ($mode === 'qr') {
    // Try d_id first if QR is purely numeric.
    if (ctype_digit($q)) {
        $dispatchRow = fetchOne($conn, "SELECT * FROM dispatch WHERE d_id = ? LIMIT 1", "i", (int)$q);
    }
    // Otherwise look it up as a booking_no, taking the most recent active dispatch.
    if (!$dispatchRow) {
        $dispatchRow = fetchOne($conn,
            "SELECT * FROM dispatch WHERE booking_no = ? ORDER BY d_id DESC LIMIT 1",
            "s", $q);
    }
    // Fall through to plate.
    if (!$dispatchRow) {
        $dispatchRow = fetchOne($conn,
            "SELECT * FROM dispatch WHERE d_truck = ? ORDER BY d_id DESC LIMIT 1",
            "s", strtoupper($q));
    }
} else {
    // Plate lookup: most recent dispatch on that truck.
    $dispatchRow = fetchOne($conn,
        "SELECT * FROM dispatch WHERE d_truck = ? ORDER BY d_id DESC LIMIT 1",
        "s", strtoupper($q));
}

if (!$dispatchRow) {
    echo json_encode(['status' => 'error', 'message' => 'No dispatch found for ' . $q]);
    exit;
}

// Pull booking-level fields too (booking_type, customs_cleared) so the
// guard sees the whole picture.
$booking = null;
$bn = $dispatchRow['booking_no'] ?? '';
if ($bn !== '') {
    $stmt = $conn->prepare(
        "SELECT booking_type, customs_cleared, vessel_name, port_location FROM booking WHERE booking_no = ? LIMIT 1"
    );
    $stmt->execute([$bn]);
    $booking = $stmt->fetch();
}

$response = $dispatchRow;
$response['booking_type']     = $booking['booking_type']    ?? 'Local';
$response['customs_cleared']  = $booking['customs_cleared'] ?? 0;
$response['vessel_name']      = $booking['vessel_name']     ?? '';
$response['port_location']    = $booking['port_location']   ?? '';
// Authorised = workflow has progressed past dispatcher_assigned, OR the
// vehicle has an explicit gate_queue 'approved' record.
$authorised = in_array($dispatchRow['workflow_stage'] ?? '', ['driver_accepted', 'gate_cleared', 'en_route', 'delivered', 'pod_captured', 'billing_closed', 'client_notified'], true) ? 1 : 0;
if (!$authorised) {
    $stmt = $conn->prepare("SELECT 1 FROM gate_queue WHERE d_id = ? AND decision = 'approved' LIMIT 1");
    $stmt->execute([$dispatchRow['d_id']]);
    if ($stmt->fetch()) $authorised = TRUE;
}
$response['authorised'] = $authorised;
// Pass scanned payload through so the front-end can echo it on log.
$response['_scanned_payload'] = $q;
echo json_encode(['status' => 'success', 'dispatch' => $response]);
