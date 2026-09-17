<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$dId = (int)($_POST['d_id'] ?? 0);
$tripFrom = trim($_POST['trip_from'] ?? '');
$tripTo = trim($_POST['trip_to'] ?? '');
$dTrailer = trim($_POST['d_trailer'] ?? '');
$tripContainer = strtoupper(trim($_POST['trip_container'] ?? ''));
$dGenset = trim($_POST['d_genset'] ?? '');
// Optional driver correction (fix a wrong driver selection).
$newDriverId = (int)($_POST['new_driver_id'] ?? 0);
$actorId = (int)($_SESSION['user_id'] ?? 0);

if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}
if ($tripTo === '') {
    echo json_encode(['status' => 'error', 'message' => 'Delivered location is required.']);
    exit;
}
if ($tripContainer !== '' && !preg_match('/^[A-Z]{4}[0-9]{7}$/', $tripContainer)) {
    echo json_encode(['status' => 'error', 'message' => 'Container number must be exactly 4 capital letters followed by 7 numbers.']);
    exit;
}

$conn->beginTransaction();
try {
    $stmt = $conn->prepare("SELECT d_id, workflow_stage, driver_id, d_drivername, booking_no FROM dispatch WHERE d_id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$dId]);
    $dispatch = $stmt->fetch();
if (!$dispatch) {
        throw new Exception('Dispatch not found.');
    }

    $wf = (string)($dispatch['workflow_stage'] ?? '');
    if (!in_array($wf, ['dispatcher_assigned', 'reassigned', 'driver_accepted', 'gate_cleared', 'en_route'], true)) {
        throw new Exception('Only in-process transactions can be updated.');
    }

    // ---- Driver correction -------------------------------------------------
    // Move the dispatch to a different driver (and their truck) when the wrong
    // one was selected. Allowed only before the trip is physically moving.
    $oldDriverId = (int)($dispatch['driver_id'] ?? 0);
    if ($newDriverId > 0 && $newDriverId !== $oldDriverId) {
        if (!in_array($wf, ['dispatcher_assigned', 'reassigned', 'driver_accepted'], true)) {
            throw new Exception('Driver can only be changed before the trip is gate-cleared / en route.');
        }
        if (pt_driver_has_active_violation($conn, $newDriverId)) {
            throw new Exception(pt_driver_violation_block_message());
        }
        $stmtNd = $conn->prepare(
            "SELECT CONCAT(d.driver_lname, ', ', d.driver_fname) AS name, d.shift_truck,
                    EXISTS(SELECT 1 FROM driver_shift s WHERE s.driver_id = d.driver_id AND s.ended_at IS NULL) AS on_shift,
                    (SELECT COUNT(*) FROM dispatch dp
                       WHERE dp.driver_id = d.driver_id
                         AND dp.workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification')
                    ) AS active_trips
             FROM drivers d WHERE d.driver_id = ? LIMIT 1"
        );
        $stmtNd->execute([$newDriverId]);
        $nd = $stmtNd->fetch();
        if (!$nd)                                       throw new Exception('New driver not found.');
        if (!(int)$nd['on_shift'])                      throw new Exception('New driver is not on an active shift.');
        if (trim((string)$nd['shift_truck']) === '')    throw new Exception('New driver has no truck selected for this shift.');
        if ((int)$nd['active_trips'] >= 2)              throw new Exception('New driver already has 2 active trips.');

        $newTruck = trim((string)$nd['shift_truck']);
        $newName  = (string)$nd['name'];

        // Move the dispatch onto the new driver + their truck; reset to
        // 'reassigned' so the new driver must accept it.
        $upd = $conn->prepare(
            "UPDATE dispatch
             SET driver_id = ?, d_drivername = ?, d_truck = ?,
                 workflow_stage = 'reassigned', workflow_updated_at = NOW()
             WHERE d_id = ?"
        );
        $upd->execute([$newDriverId, $newName, $newTruck, $dId]);

        // New driver → Dispatch; old driver → Active if they have no other job.
        $conn->prepare("UPDATE drivers SET driver_status = 'Dispatch' WHERE driver_id = ?")->execute([$newDriverId]);
        if ($oldDriverId > 0) {
            $stmtCnt = $conn->prepare(
                "SELECT COUNT(*) FROM dispatch
                 WHERE driver_id = ? AND d_id <> ?
                   AND workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification')"
            );
            $stmtCnt->execute([$oldDriverId, $dId]);
            if ((int)$stmtCnt->fetchColumn() === 0) {
                $conn->prepare("UPDATE drivers SET driver_status = 'Active' WHERE driver_id = ?")->execute([$oldDriverId]);
            }
        }

        $note = 'Driver corrected: ' . trim((string)$dispatch['d_drivername']) . ' → ' . $newName . ' by ' . $role . ' #' . $actorId;
        $conn->prepare(
            "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
             VALUES (?, ?, 'reassigned', ?, ?, ?)"
        )->execute([$dId, (string)$dispatch['booking_no'], $role, $actorId, $note]);
    }

    if ($dTrailer !== '') {
        $stmt = $conn->prepare("SELECT 1 FROM trailer WHERE trailer_name = ? LIMIT 1");
        $stmt->execute([$dTrailer]);
        $exists = $stmt->fetch();
if (!$exists) throw new Exception('Trailer not found.');
    }

    if ($dGenset !== '') {
        $stmt = $conn->prepare("SELECT 1 FROM units WHERE unit_name = ? AND unit_type = 'genset' LIMIT 1");
        $stmt->execute([$dGenset]);
        $exists = $stmt->fetch();
if (!$exists) throw new Exception('Genset not found.');
    }

    $stmt = $conn->prepare(
        "SELECT trip_id, trip_from FROM trips
         WHERE d_id = ?
         ORDER BY trip_id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$dId]);
    $trip = $stmt->fetch();
if (!$trip) {
        throw new Exception('Trip not found for dispatch.');
    }

    $tripId = (int)$trip['trip_id'];
    // Keep the existing pickup when the dispatcher left the From field blank.
    $finalTripFrom = $tripFrom !== '' ? $tripFrom : (string)($trip['trip_from'] ?? '');

    $stmt = $conn->prepare("UPDATE dispatch SET d_trailer = ?, d_genset = ? WHERE d_id = ?");
    $stmt->execute([$dTrailer, $dGenset, $dId]);
$stmt = $conn->prepare("UPDATE trips SET trip_from = ?, trip_to = ?, trip_container = ? WHERE trip_id = ?");
    $stmt->execute([$finalTripFrom, $tripTo, $tripContainer, $tripId]);
$conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Tracking transaction updated.']);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
