<?php
// Admin-only: delete a container-tracking record.
//
// Removes a dispatch and its trip legs (the row shown on the Container Tracking
// page), frees the truck / trailer / genset it holds, and returns the booking
// slot to the pool — an administrative correction for a mis-created dispatch.
//
// Guardrails:
//   * ADMIN ONLY. Dispatchers/others get 401 even though they never see the
//     button (the UI check is only a convenience).
//   * Equipment is freed conditionally — only units/trailers still pointing at
//     THIS dispatch's driver in 'Dispatch' status are released, so a unit that
//     has already moved on to another trip is left untouched.
//   * The driver is returned to 'Standby' only when they have no other active
//     dispatch left.
//   * A 'deleted' workflow_event is written for the audit trail before the rows
//     are removed (workflow_event has no FK to dispatch, so it survives).

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/activity_log_helper.php';

$role = $_SESSION['user_type'] ?? '';
// Deletion is destructive — restrict to Admin only.
if ($role !== 'Admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Only an Admin can delete records.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$dId   = (int)($_POST['d_id'] ?? 0);
$actor = (int)($_SESSION['user_id'] ?? 0);
if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

// Full "First Middle Last" for the activity log / audit note.
$actorName = 'Admin';
if ($actor > 0) {
    $stmtName = $conn->prepare("SELECT user_fname, user_mname, user_lname FROM \"user\" WHERE user_id = ? LIMIT 1");
    $stmtName->execute([$actor]);
    $uRow = $stmtName->fetch();
    if ($uRow) {
        $parts = array_filter([
            trim((string)$uRow['user_fname']),
            trim((string)$uRow['user_mname']),
            trim((string)$uRow['user_lname']),
        ], 'strlen');
        if (!empty($parts)) $actorName = implode(' ', $parts);
    }
}

try {
    $conn->beginTransaction();

    // Lock the dispatch row + read what it holds.
    $stmt = $conn->prepare(
        "SELECT d_id, booking_no, driver_id, d_drivername, d_truck, d_trailer, d_genset, workflow_stage
           FROM dispatch WHERE d_id = ? FOR UPDATE"
    );
    $stmt->execute([$dId]);
    $d = $stmt->fetch();
    if (!$d) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Record not found (already deleted?).']);
        exit;
    }

    $bookingNo = (string)$d['booking_no'];
    $driverId  = (int)$d['driver_id'];
    $truck     = (string)$d['d_truck'];
    $trailer   = (string)$d['d_trailer'];
    $genset    = (string)$d['d_genset'];
    $stage     = (string)$d['workflow_stage'];

    // Return one booking slot to the pool — unless this dispatch was already
    // uncounted (recalled/declined/cancelled never held a slot at delete time).
    if ($bookingNo !== '' && !in_array($stage, ['recalled', 'declined', 'cancelled'], true)) {
        $stmt = $conn->prepare(
            "UPDATE booking
                SET quantity_use = GREATEST(0, quantity_use - 1),
                    status = CASE
                        WHEN status = 'Complete' AND quantity_use - 1 < quantity THEN 'Active'
                        ELSE status
                    END
              WHERE booking_no = ?"
        );
        $stmt->execute([$bookingNo]);
    }

    // Free the truck — only if it's still on this driver's dispatch.
    if ($truck !== '' && $driverId > 0) {
        $conn->prepare(
            "UPDATE units
                SET unit_assign = '', driver_id = 0, unit_assigngenset = '', unit_assigntrailer = '', unit_status = 'Good'
              WHERE unit_name = ? AND driver_id = ? AND unit_status = 'Dispatch'"
        )->execute([$truck, $driverId]);
    }
    // Free the genset — same guard.
    if ($genset !== '' && $driverId > 0) {
        $conn->prepare(
            "UPDATE units
                SET unit_assign = '', driver_id = 0, unit_status = 'Good'
              WHERE unit_name = ? AND driver_id = ? AND unit_status = 'Dispatch'"
        )->execute([$genset, $driverId]);
    }
    // Free the trailer.
    if ($trailer !== '' && $driverId > 0) {
        $conn->prepare(
            "UPDATE trailer SET trailer_assignto = '', driver_id = 0
              WHERE trailer_name = ? AND driver_id = ?"
        )->execute([$trailer, $driverId]);
    }

    // Free the driver when they have no other active dispatch left. Mirrors the
    // trip-completion / decline flows, which return the driver to 'Active'
    // (on shift, no active trip) — not 'Dispatch'.
    if ($driverId > 0) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM dispatch
              WHERE driver_id = ? AND d_id <> ?
                AND workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')"
        );
        $stmt->execute([$driverId, $dId]);
        if ((int)$stmt->fetchColumn() === 0) {
            $conn->prepare("UPDATE drivers SET driver_status = 'Active' WHERE driver_id = ?")->execute([$driverId]);
        }
    }

    // Audit trail (kept — workflow_event has no FK to dispatch).
    $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, 'deleted', 'admin', ?, ?)"
    )->execute([$dId, $bookingNo, $actor, 'Tracking record deleted by ' . $actorName . '.']);

    // Remove the trip legs then the dispatch.
    $conn->prepare("DELETE FROM trips WHERE d_id = ?")->execute([$dId]);
    $conn->prepare("DELETE FROM dispatch WHERE d_id = ?")->execute([$dId]);

    $conn->commit();

    pt_log_activity($conn, $actor, $role, $actorName, 'Deleted tracking record',
        'd_id ' . $dId . ($bookingNo !== '' ? ' (' . $bookingNo . ')' : ''));

    echo json_encode([
        'status'     => 'success',
        'message'    => 'Record deleted.',
        'd_id'       => $dId,
        'booking_no' => $bookingNo,
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
}
