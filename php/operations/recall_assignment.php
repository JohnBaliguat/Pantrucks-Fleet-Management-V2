<?php
// Recall an assigned-but-not-yet-accepted dispatch.
//
// Rules:
//   - Only dispatches in workflow_stage 'dispatcher_assigned' or
//     'reassigned' can be recalled. Once the driver accepts, the trip
//     is in flight and recall is not safe — dispatcher must use the
//     existing rescue / manual-complete flows instead.
//   - Sets workflow_stage = 'recalled'.
//   - Decrements booking.quantity_use so the booking flows back into
//     the Dispatch Board's available pool.
//   - Inserts a workflow_event row so the audit log + driver
//     notifications endpoint pick it up.

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

$dId    = (int)($_POST['d_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
$actor  = (int)($_SESSION['user_id'] ?? 0);

if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

try {
    $conn->beginTransaction();

    // Lock the dispatch row, validate stage.
    $stmt = $conn->prepare(
        "SELECT d_id, booking_no, driver_id, d_drivername, d_truck, workflow_stage
           FROM dispatch
          WHERE d_id = ?
          FOR UPDATE"
    );
    $stmt->execute([$dId]);
    $d = $stmt->fetch();
    if (!$d) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Dispatch not found']);
        exit;
    }
    if (!in_array($d['workflow_stage'], ['dispatcher_assigned', 'reassigned'], true)) {
        $conn->rollBack();
        echo json_encode([
            'status'  => 'error',
            'message' => "Cannot recall — already at stage '" . $d['workflow_stage'] . "'.",
        ]);
        exit;
    }

    // Flip the workflow stage.
    $stmt = $conn->prepare(
        "UPDATE dispatch
            SET workflow_stage = 'recalled',
                workflow_updated_at = NOW()
          WHERE d_id = ?"
    );
    $stmt->execute([$dId]);

    // Return one slot of the booking back to the pool.
    if (!empty($d['booking_no'])) {
        $stmt = $conn->prepare(
            "UPDATE booking
                SET quantity_use = GREATEST(0, quantity_use - 1),
                    status = CASE
                        WHEN status = 'Complete' AND quantity_use - 1 < quantity THEN 'Active'
                        ELSE status
                    END
              WHERE booking_no = ?"
        );
        $stmt->execute([$d['booking_no']]);
    }

    // Audit + drives the driver-side "Trip recalled" notification.
    $note = 'Recalled by ' . $role . ' #' . $actor
          . ($reason !== '' ? ' — ' . $reason : '');
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, 'recalled', ?, ?, ?)"
    );
    $stmt->execute([$dId, $d['booking_no'], $role, $actor, $note]);

    $conn->commit();

    echo json_encode([
        'status'       => 'success',
        'message'      => 'Assignment recalled. Booking returned to the pool.',
        'd_id'         => $dId,
        'booking_no'   => $d['booking_no'],
        'former_driver'=> $d['d_drivername'],
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Recall failed: ' . $e->getMessage()]);
}
