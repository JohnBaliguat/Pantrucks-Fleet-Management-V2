<?php
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_push_send.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';

$dId    = (int)($_POST['d_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
if ($dId <= 0) json_out(['status' => 'error', 'message' => 'd_id required'], 400);

// Lock the dispatch row up front so two declines can't race.
$conn->beginTransaction();
try {
    $stmt = $conn->prepare("SELECT booking_no, driver_id, d_truck, d_trailer, d_genset, workflow_stage FROM dispatch WHERE d_id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$dId]);
    $row = $stmt->fetch();
if (!$row || (int)$row['driver_id'] !== $driverId) {
        $conn->rollBack();
        json_out(['status' => 'error', 'message' => 'Dispatch not assigned to you'], 403);
    }
    $bn          = $row['booking_no'];
    $truck       = $row['d_truck'];
    $trailer     = $row['d_trailer'];
    $genset      = $row['d_genset'];
    $priorStage  = $row['workflow_stage'];

    // 1. Mark dispatch as declined. Also clear dispatch_ref so the
    //    Jollibee-style receipt token (e.g. -EV2) is no longer attached
    //    to a declined slot.
    $stmt = $conn->prepare(
        "UPDATE dispatch
         SET workflow_stage = 'driver_declined', driver_declined_at = NOW(),
             decline_reason = ?, workflow_updated_at = NOW(),
             dispatch_ref = ''
         WHERE d_id = ? AND workflow_stage IN ('dispatcher_assigned', 'reassigned')"
    );
    $stmt->execute([$reason, $dId]);
    $affected = $stmt->rowCount();
if ($affected === 0) {
        $conn->rollBack();
        json_out(['status' => 'error', 'message' => 'Dispatch not in a declinable state']);
    }

    // 2. Roll the booking's quantity_use back by 1, and re-open it
    //    if the empty/loaded leg had been auto-closed.
    //    Example: qty=5 with one assigned → quantity_use=1, remaining=4.
    //    Driver declines → quantity_use=0, remaining=5, booking re-Active.
    $stmt = $conn->prepare(
        "UPDATE booking
         SET quantity_use = GREATEST(quantity_use - 1, 0),
             status = CASE WHEN status = 'Complete' THEN 'Active' ELSE status END
         WHERE booking_no = ?"
    );
    $stmt->execute([$bn]);
// 3. Roll the container_status back to its lane base — but only if
    //    this decline frees up the *only* in-flight assignment for the
    //    booking. If other dispatches under the same booking are still
    //    in Pickup/On-Trip/Delivered, leave it alone.
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM dispatch
         WHERE booking_no = ?
           AND workflow_stage IN ('dispatcher_assigned','driver_accepted','gate_cleared','en_route','delivered','pod_captured','pending_verification')"
    );
    $stmt->execute([$bn]);
    $others = (int)$stmt->fetch()['c'];
if ($others === 0) {
        // Bring container_status back from *Pickup to its base.
        $stmt = $conn->prepare("SELECT container_status FROM booking WHERE booking_no = ? LIMIT 1");
        $stmt->execute([$bn]);
        $cs = (string)($stmt->fetch()['container_status'] ?? '');
$lane = cl_normalise_lane($cs);
        $stage = cl_normalise_stage($cs);
        if ($stage === 'pickup') {
            $base = ($lane === 'loaded') ? 'Loaded' : 'Empty';
            $stmt = $conn->prepare("UPDATE booking SET container_status = ? WHERE booking_no = ?");
            $stmt->execute([$base, $bn]);
}
    }

    // 4. Free the truck — but only if this driver has no other active
    //    dispatches still holding the truck.
    if ($truck !== '') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM dispatch
             WHERE driver_id = ? AND d_truck = ?
               AND workflow_stage NOT IN ('driver_declined','billing_closed','client_notified')"
        );
        $stmt->execute([$driverId, $truck]);
        $stillUsing = (int)$stmt->fetch()['c'];
if ($stillUsing === 0) {
            $stmt = $conn->prepare(
                "UPDATE units
                 SET unit_assign = '', driver_id = 0, unit_assigngenset = '', unit_assigntrailer = '', unit_status = 'good'
                 WHERE unit_name = ? AND driver_id = ?"
            );
            $stmt->execute([$truck, $driverId]);
}
    }

    // 4a. Free the trailer if the driver no longer has any active
    //     dispatch holding it.
    if ($trailer !== '') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM dispatch
             WHERE driver_id = ? AND d_trailer = ?
               AND workflow_stage NOT IN ('driver_declined','billing_closed','client_notified')"
        );
        $stmt->execute([$driverId, $trailer]);
        $stillUsing = (int)$stmt->fetch()['c'];
if ($stillUsing === 0) {
            $stmt = $conn->prepare(
                "UPDATE trailer
                 SET trailer_assignto = '', driver_id = 0, trailer_status = 'Good'
                 WHERE trailer_name = ?"
            );
            $stmt->execute([$trailer]);
if ($truck !== '') {
                $stmt = $conn->prepare(
                    "UPDATE units
                     SET unit_assigntrailer = ''
                     WHERE unit_name = ? AND unit_assigntrailer = ?"
                );
                $stmt->execute([$truck, $trailer]);
}
        }
    }

    // 4b. Free the genset similarly.
    if ($genset !== '') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM dispatch
             WHERE driver_id = ? AND d_genset = ?
               AND workflow_stage NOT IN ('driver_declined','billing_closed','client_notified')"
        );
        $stmt->execute([$driverId, $genset]);
        $stillUsing = (int)$stmt->fetch()['c'];
if ($stillUsing === 0) {
            $stmt = $conn->prepare(
                "UPDATE units
                 SET unit_assign = '', driver_id = 0, unit_status = 'Good'
                 WHERE unit_name = ?"
            );
            $stmt->execute([$genset]);
if ($truck !== '') {
                $stmt = $conn->prepare(
                    "UPDATE units
                     SET unit_assigngenset = ''
                     WHERE unit_name = ? AND unit_assigngenset = ?"
                );
                $stmt->execute([$truck, $genset]);
}
        }
    }

    // 5. Free the driver — only if they have no other active dispatches.
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM dispatch
         WHERE driver_id = ?
           AND workflow_stage NOT IN ('driver_declined','billing_closed','client_notified')"
    );
    $stmt->execute([$driverId]);
    $stillBusy = (int)$stmt->fetch()['c'];
if ($stillBusy === 0) {
        $stmt = $conn->prepare("UPDATE drivers SET driver_status = 'Active' WHERE driver_id = ?");
        $stmt->execute([$driverId]);
}

    // 6. Workflow audit.
    $notes = 'Driver declined' . ($reason !== '' ? ' — ' . $reason : '') . ' (quantity returned to booking)';
    $stmt = $conn->prepare("INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'driver_declined', 'driver', ?, ?)");
    $stmt->execute([$dId, $bn, $driverId, $notes]);
$conn->commit();

    // 7. Push to dispatchers (best-effort, outside transaction).
    pt_notify_dispatchers($conn, "Driver declined: $bn", $notes, $dId);

    json_out(['status' => 'success', 'message' => 'Declined. Booking quantity returned; dispatcher notified.']);
} catch (Exception $e) {
    $conn->rollBack();
    json_out(['status' => 'error', 'message' => 'Decline failed: ' . $e->getMessage()]);
}
