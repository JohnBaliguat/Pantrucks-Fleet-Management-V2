<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_push_send.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$incId        = (int)($_POST['inc_id']        ?? 0);
$origDId      = (int)($_POST['d_id']          ?? 0);
$newDriverId  = (int)($_POST['new_driver_id'] ?? 0);
$newTruck     = strtoupper(trim($_POST['new_truck'] ?? ''));
$newTrailer   = strtoupper(trim($_POST['new_trailer'] ?? ''));
$newGenset    = strtoupper(trim($_POST['new_genset'] ?? ''));
$notes        = trim($_POST['notes']          ?? '');
$actor        = (int)($_SESSION['user_id'] ?? 0);

if ($incId <= 0 || $newDriverId <= 0 || $newTruck === '') {
    echo json_encode(['status' => 'error', 'message' => 'inc_id, new_driver_id, new_truck required']);
    exit;
}

// Hydrate the original dispatch (we need booking_no, customer, etc.).
$origDispatch = null;
if ($origDId > 0) {
    $stmt = $conn->prepare("SELECT * FROM dispatch WHERE d_id = ? LIMIT 1");
    $stmt->execute([$origDId]);
    $origDispatch = $stmt->fetch();
}

// If the incident has no parent dispatch, we can still reassign — just
// create a new dispatch row tied to whatever booking the incident pointed
// at (none in that case → we refuse).
if (!$origDispatch) {
    echo json_encode(['status' => 'error', 'message' => 'Original dispatch not found; nothing to reassign.']);
    exit;
}

// Resolve driver name for the legacy d_driverName column.
$dnRow = null;
$stmt = $conn->prepare(
    "SELECT CONCAT(driver_lname, ', ', SUBSTRING(driver_fname, 1, 1), '.') AS dn FROM drivers WHERE driver_id = ? LIMIT 1"
);
$stmt->execute([$newDriverId]);
$dnRow = $stmt->fetch();
if (!$dnRow) {
    echo json_encode(['status' => 'error', 'message' => 'New driver not found']);
    exit;
}
$newDriverName = $dnRow['dn'];

// Carry over fields if not overridden in the form.
$trailerToUse = $newTrailer !== '' ? $newTrailer : ($origDispatch['d_trailer'] ?? '');
$gensetToUse  = $newGenset  !== '' ? $newGenset  : ($origDispatch['d_genset']  ?? '');

$conn->beginTransaction();
try {
    // Insert new dispatch row, mirroring booking + dispatch metadata.
    $stmt = $conn->prepare(
        "INSERT INTO dispatch
            (booking_no, booking_sn, booking_do, cth_broker, cth_eirout,
             d_datetime, d_dispatcher, d_dispatchhub, d_drivername, driver_id,
             d_truck, d_trailer, d_genset, d_tripreceipt, d_ecs, costumer,
             workflow_stage, workflow_updated_at)
         VALUES
            (?, ?, ?, ?, ?,
             NOW(), ?, ?, ?, ?,
             ?, ?, ?, '', '', ?,
             'dispatcher_assigned', NOW())"
    );
    $dispatcherName = $_SESSION['user_name'] ?? ($_SESSION['user_type'] ?? 'system');
    $hub = $origDispatch['d_dispatchhub'] ?? '';

    $stmt->execute([
        $origDispatch['booking_no'],
        $origDispatch['booking_sn'],
        $origDispatch['booking_do'],
        $origDispatch['cth_broker'],
        $origDispatch['cth_eirout'],
        $dispatcherName,
        $hub,
        $newDriverName,
        $newDriverId,
        $newTruck,
        $trailerToUse,
        $gensetToUse,
        $origDispatch['costumer']
    ]);
    $newDId = $conn->lastInsertId();

    // Point the incident at the new dispatch and acknowledge it.
    $stmt = $conn->prepare("UPDATE incident SET reassigned_d_id = ?, status = 'acknowledged' WHERE inc_id = ?");
    $stmt->execute([$newDId, $incId]);

    // Mark the original dispatch as superseded — workflow_stage = 'reassigned'.
    $stmt = $conn->prepare("UPDATE dispatch SET workflow_stage = 'reassigned', workflow_updated_at = NOW() WHERE d_id = ?");
    $stmt->execute([$origDId]);

    // Write workflow_event rows on both dispatches for the timeline.
    $eventNote = "Re-assigned from dispatch #$origDId due to incident #$incId" . ($notes !== '' ? " — $notes" : '');
    $bn = $origDispatch['booking_no'];

    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'reassigned_from', ?, ?, ?)"
    );
    $stmt->execute([$origDId, $bn, $role, $actor, $eventNote]);

    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'dispatcher_assigned', ?, ?, ?)"
    );
    $newNote = "Created via re-assignment from dispatch #$origDId (incident #$incId)";
    $stmt->execute([$newDId, $bn, $role, $actor, $newNote]);

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// Phase 6 — push the new driver about the reassignment.
pt_notify_driver(
    $conn,
    $newDriverId,
    'New job assigned',
    "Booking {$origDispatch['booking_no']} re-assigned to you (incident #$incId).",
    $newDId
);
pt_notify_dispatchers($conn, "Re-assigned: {$origDispatch['booking_no']}", "Dispatch #$origDId &rarr; #$newDId via incident #$incId", $newDId);

echo json_encode([
    'status'   => 'success',
    'message'  => "Re-assigned. New dispatch #$newDId created.",
    'new_d_id' => $newDId,
]);
