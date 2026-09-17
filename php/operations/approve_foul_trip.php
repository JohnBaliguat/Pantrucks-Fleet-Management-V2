<?php
// Dispatch admin confirms (or revokes) a foul trip for payroll. Only an
// approved foul trip's rate counts toward driver earnings.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$tripId = (int)($_POST['trip_id'] ?? 0);
$actor  = (int)($_SESSION['user_id'] ?? 0);
$revoke = !empty($_POST['revoke']);      // optional: undo a prior approval
if ($tripId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'trip_id required']);
    exit;
}

// Must be an actual foul trip.
$stmt = $conn->prepare(
    "SELECT t.foul_trip, t.foul_approved, t.d_id, d.booking_no
       FROM trips t LEFT JOIN dispatch d ON d.d_id = t.d_id
      WHERE t.trip_id = ? LIMIT 1"
);
$stmt->execute([$tripId]);
$row = $stmt->fetch();
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Trip not found']);
    exit;
}
if (!$row['foul_trip']) {
    echo json_encode(['status' => 'error', 'message' => 'This trip is not a foul trip.']);
    exit;
}

$conn->beginTransaction();
try {
    if ($revoke) {
        $conn->prepare(
            "UPDATE trips SET foul_approved = FALSE, foul_approved_by = NULL, foul_approved_at = NULL
              WHERE trip_id = ?"
        )->execute([$tripId]);
        $stage = 'foul_approval_revoked';
        $note  = 'Foul trip #' . $tripId . ' approval revoked';
        $msg   = 'Approval revoked — this foul trip will no longer count toward earnings.';
    } else {
        $conn->prepare(
            "UPDATE trips SET foul_approved = TRUE, foul_approved_by = ?, foul_approved_at = NOW()
              WHERE trip_id = ?"
        )->execute([$actor, $tripId]);
        $stage = 'foul_approved';
        $note  = 'Foul trip #' . $tripId . ' approved for payroll';
        $msg   = 'Foul trip approved — its rate now counts toward the driver\'s earnings.';
    }

    $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([(int)$row['d_id'], $row['booking_no'], $stage, $role, $actor, $note]);

    $conn->commit();
    echo json_encode(['status' => 'success', 'trip_id' => $tripId, 'approved' => !$revoke, 'message' => $msg]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
}
