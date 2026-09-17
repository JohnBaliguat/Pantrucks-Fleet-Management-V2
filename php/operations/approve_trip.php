<?php
// Dispatcher "approve" step for the Trip Verification page.
//
// Replaces the old rate-picking verify flow: the dispatcher no longer
// assigns piece-rates here. Approving a POD:
//   * stamps a coupon control_no + public qr_token on the dispatch,
//   * completes the trip (workflow_stage=pod_captured, trip_status=Done),
//   * runs the trailer auto jack-up + booking advance that manual
//     completion used to do (idempotent — safe for driver-delivered trips
//     that already jacked up),
//   * hands the dispatch to Payroll (payroll_status='pending'),
//   * returns the coupon print URL so the page can auto-print it.
//
// Rejecting kicks the POD back to the driver to re-capture (unchanged
// behaviour, kept here so verifications.php has a single endpoint).
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';
require_once __DIR__ . '/_push_send.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$dId      = (int)($_POST['d_id'] ?? 0);
$decision = $_POST['decision'] ?? 'approved';   // 'approved' | 'rejected'
$notes    = trim($_POST['notes'] ?? '');
$actor    = (int)($_SESSION['user_id'] ?? 0);

if ($dId <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'd_id + decision required']); exit;
}

// DICT-hustling legs keep the trailer with the driver, so they're exempt
// from the auto jack-up (mirrors manual_complete_tracking.php).
function ap_is_dict_hustling_exempt(string $segment, string $tripTo): bool {
    foreach ([$segment, $tripTo] as $value) {
        $t = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));
        if ($t !== '' && strpos($t, 'DICT') !== false && strpos($t, 'HUSTLING') !== false) {
            return true;
        }
    }
    return false;
}

$stmt = $conn->prepare(
    "SELECT booking_no, driver_id, workflow_stage, d_trailer, d_truck, control_no, qr_token
     FROM dispatch WHERE d_id = ? LIMIT 1"
);
$stmt->execute([$dId]);
$d = $stmt->fetch();
if (!$d) { echo json_encode(['status' => 'error', 'message' => 'Dispatch not found']); exit; }
if ($d['workflow_stage'] !== 'pending_verification') {
    echo json_encode(['status' => 'error', 'message' => 'Not at pending_verification (currently: ' . $d['workflow_stage'] . ')']); exit;
}

pt_ensure_trailer_jackup_table($conn);

$conn->beginTransaction();
try {
    if ($decision === 'approved') {
        // ---- Coupon control number + public QR token ------------------
        // Reuse an already-assigned control_no on re-approval; otherwise
        // mint a YEAR-serial number (e.g. 2026-000125) from the per-year
        // coupon_counter — atomic UPSERT so concurrent approvals can't
        // collide and a rolled-back approval doesn't burn a serial.
        $controlNo = trim((string)($d['control_no'] ?? ''));
        if ($controlNo === '') {
            $yr = (int)date('Y');
            $cc = $conn->prepare(
                "INSERT INTO coupon_counter (yr, last_serial) VALUES (?, 1)
                 ON CONFLICT (yr) DO UPDATE SET last_serial = coupon_counter.last_serial + 1
                 RETURNING last_serial"
            );
            $cc->execute([$yr]);
            $serial = (int)$cc->fetchColumn();
            $controlNo = $yr . '-' . str_pad((string)$serial, 6, '0', STR_PAD_LEFT);
        }
        $qrToken = trim((string)($d['qr_token'] ?? ''));
        if ($qrToken === '') {
            $qrToken = bin2hex(random_bytes(16));
        }

        // ---- Complete the trip + hand off to payroll ------------------
        $upd = $conn->prepare(
            "UPDATE dispatch
                SET workflow_stage = 'pod_captured',
                    trip_completed_at = COALESCE(trip_completed_at, NOW()),
                    approved_by = ?, approved_at = NOW(),
                    verified_by = ?, verified_at = NOW(), verification_notes = ?,
                    control_no = ?, qr_token = ?,
                    payroll_status = CASE WHEN payroll_status = 'rated' THEN payroll_status ELSE 'pending' END,
                    workflow_updated_at = NOW()
              WHERE d_id = ?"
        );
        $upd->execute([$actor, $actor, $notes, $controlNo, $qrToken, $dId]);

        // Mirror verification stamp onto the pod_capture row.
        $conn->prepare(
            "UPDATE pod_capture SET verified_by = ?, verified_at = NOW(), verification_notes = ? WHERE d_id = ?"
        )->execute([$actor, $notes, $dId]);

        // Legacy trips.trip_status so driver dashboard moves it to Completed.
        $conn->prepare("UPDATE trips SET trip_status = 'Done' WHERE d_id = ? AND trip_status <> 'Done'")
             ->execute([$dId]);

        // ---- Trailer auto jack-up (idempotent) ------------------------
        // Driver-delivered trips already jacked up in the delivered flow;
        // manual-completed trips (which now stop at pending_verification)
        // have not — so attempt it here, guarded by the existing-row check.
        $tripStmt = $conn->prepare(
            "SELECT trip_haulingsegment, trip_to FROM trips WHERE d_id = ? ORDER BY trip_id DESC LIMIT 1"
        );
        $tripStmt->execute([$dId]);
        $trip = $tripStmt->fetch() ?: [];

        $bookingNo       = trim((string)($d['booking_no'] ?? ''));
        $driverId        = (int)($d['driver_id'] ?? 0);
        $dispatchTrailer = trim((string)($d['d_trailer'] ?? ''));
        $dispatchTruck   = trim((string)($d['d_truck'] ?? ''));
        $tripSegment     = trim((string)($trip['trip_haulingsegment'] ?? ''));
        $tripTo          = trim((string)($trip['trip_to'] ?? ''));

        if ($dispatchTrailer !== '' && !ap_is_dict_hustling_exempt($tripSegment, $tripTo)) {
            $jkChk = $conn->prepare("SELECT 1 FROM trailer_jackup WHERE d_id = ? AND trailer_code = ? LIMIT 1");
            $jkChk->execute([$dId, $dispatchTrailer]);
            if (!$jkChk->fetchColumn()) {
                $conn->prepare(
                    "INSERT INTO trailer_jackup (d_id, trailer_code, driver_id, lat, lng, photo_path, billing_active)
                     VALUES (?, ?, ?, NULL, NULL, '', TRUE)"
                )->execute([$dId, $dispatchTrailer, $driverId]);
                $conn->prepare(
                    "UPDATE trailer SET trailer_assignto = '', driver_id = 0, trailer_status = 'Good'
                     WHERE trailer_name = ? AND driver_id = ?"
                )->execute([$dispatchTrailer, $driverId]);
                if ($dispatchTruck !== '') {
                    $conn->prepare(
                        "UPDATE units SET unit_assigntrailer = '' WHERE unit_name = ? AND unit_assigntrailer = ?"
                    )->execute([$dispatchTruck, $dispatchTrailer]);
                }
                $jkNote = "Trailer $dispatchTrailer auto jacked-up on approval by $role #$actor | released_from_driver=1";
                $conn->prepare(
                    "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
                     VALUES (?, ?, 'trailer_jackup', ?, ?, ?)"
                )->execute([$dId, $bookingNo, $role, $actor, $jkNote]);
            }
        }

        if ($bookingNo !== '') {
            cl_advance_booking($conn, $bookingNo, 'pickup');
            cl_advance_booking($conn, $bookingNo, 'delivered');
        }

        $logNote = 'Trip approved by ' . $role . ' #' . $actor . ' — coupon ' . $controlNo;
        if ($notes !== '') $logNote .= ' — ' . $notes;
        $conn->prepare(
            "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'approved', ?, ?, ?)"
        )->execute([$dId, $bookingNo, $role, $actor, $logNote]);

        $conn->commit();

        if ($driverId > 0) {
            pt_notify_driver($conn, $driverId, 'Trip approved',
                ($bookingNo !== '' ? $bookingNo . ' ' : '') . 'approved. You are clear.', $dId);
        }

        echo json_encode([
            'status'     => 'success',
            'message'    => 'Trip approved. Coupon ' . $controlNo . ' generated.',
            'control_no' => $controlNo,
            'print_url'  => 'dispatch-coupon?d_id=' . $dId,
        ]);
        exit;
    }

    // ---- Rejected: kick back to en_route for re-capture ----------------
    $conn->prepare(
        "UPDATE dispatch
            SET workflow_stage = 'en_route',
                verified_by = ?, verified_at = NOW(), verification_notes = ?,
                workflow_updated_at = NOW()
          WHERE d_id = ?"
    )->execute([$actor, $notes, $dId]);

    $logNote = 'POD rejected — ' . ($notes !== '' ? $notes : 'no reason given');
    $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'pod_rejected', ?, ?, ?)"
    )->execute([$dId, $d['booking_no'], $role, $actor, $logNote]);

    $conn->commit();

    if (!empty($d['driver_id'])) {
        pt_notify_driver($conn, (int)$d['driver_id'], 'POD needs re-do',
            $d['booking_no'] . ' POD rejected. ' . ($notes !== '' ? $notes : 'Please re-capture.'), $dId);
    }

    echo json_encode(['status' => 'success', 'message' => 'POD rejected — driver notified to re-capture.']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Approve failed: ' . $e->getMessage()]); exit;
}
