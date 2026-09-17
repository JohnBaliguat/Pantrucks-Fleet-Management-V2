<?php
header('Content-Type: application/json');
session_start();
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$tripId    = (int)($_POST['trip_id'] ?? 0);
$arrivalCy = trim((string)($_POST['arrival_cy'] ?? ''));
$departure = trim((string)($_POST['departure'] ?? ''));
$arrivalPh = trim((string)($_POST['arrival_ph'] ?? ''));
$action    = trim((string)($_POST['action'] ?? ''));
$role      = $_SESSION['user_type'] ?? 'Dispatcher';
$actorId   = (int)($_SESSION['user_id'] ?? 0);

if ($tripId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing trip ID']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT t.trip_id, t.d_id, t.trip_status, t.segment_status,
            d.booking_no, d.workflow_stage
     FROM trips t
     INNER JOIN dispatch d ON d.d_id = t.d_id
     WHERE t.trip_id = ?
     LIMIT 1"
);
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => ($conn->errorInfo()[2] ?? '')]);
    exit;
}
$stmt->execute([$tripId]);
$tripRow = $stmt->fetch();
if (!$tripRow) {
    echo json_encode(['status' => 'error', 'message' => 'Trip not found']);
    exit;
}

$conn->beginTransaction();
try {
    $stmt = $conn->prepare(
        "UPDATE trips
         SET trip_arrivaldatetime = ?,
             trip_departuredatetime = ?,
             trip_pharrivaldatetime = ?
         WHERE trip_id = ?"
    );
    $stmt->execute([$arrivalCy, $departure, $arrivalPh, $tripId]);
$message = 'Trip timeline updated successfully';

    if ($action === 'done') {
        $dId = (int)$tripRow['d_id'];

        $stmt = $conn->prepare(
            "UPDATE trips
             SET segment_status = 'Delivered',
                 deliver_datetime = COALESCE(NULLIF(?, ''), NOW())
             WHERE trip_id = ?"
        );
        $stmt->execute([$arrivalPh, $tripId]);
$allowedStages = ['driver_accepted', 'gate_cleared', 'en_route', 'delivered'];
        if (in_array($tripRow['workflow_stage'], $allowedStages, true)) {
            $stmt = $conn->prepare(
                "UPDATE dispatch
                 SET workflow_stage = 'delivered',
                     workflow_updated_at = NOW()
                 WHERE d_id = ?"
            );
            $stmt->execute([$dId]);
}

        $bookingNo = (string)$tripRow['booking_no'];
        $notes = 'Monitoring marked trip delivered';
        if ($arrivalPh !== '') {
            $notes .= ' (arrival PH: ' . $arrivalPh . ')';
        }

        $stage = 'delivered';
        $stmt = $conn->prepare(
            "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$dId, $bookingNo, $stage, $role, $actorId, $notes]);
$message = 'Trip marked as delivered. Await POD / verification to complete the dispatch.';
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => $message]);
} catch (Throwable $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Failed to update trip: ' . $e->getMessage()]);
}
?>
