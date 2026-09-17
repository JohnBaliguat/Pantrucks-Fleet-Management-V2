<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$tripId = isset($_POST['trip_id']) ? (int)$_POST['trip_id'] : 0;
$reason = trim($_POST['reason'] ?? '');
if ($tripId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'trip_id required']);
    exit;
}

// Foul-trip rule: a segment cancelled AFTER it was assigned (anything
// past 'Pending') counts as a foul trip. A still-Pending segment can
// be cancelled cleanly with foul_trip = FALSE.
$stmt = $conn->prepare("SELECT segment_status, foul_trip, d_id FROM trips WHERE trip_id = ? LIMIT 1");
$stmt->execute([$tripId]);
$res = $stmt;
$row = $res->fetch();
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Segment not found']);
    exit;
}

$alreadyAssigned = strtolower($row['segment_status'] ?? '') !== 'pending';
$newFoul = $alreadyAssigned ? 1 : 0;
$newStatus = $alreadyAssigned ? 'Foul' : 'Cancelled';

$stmt = $conn->prepare(
    "UPDATE trips
     SET segment_status = ?, foul_trip = ?, cancelled_at = NOW(), cancelled_reason = ?
     WHERE trip_id = ?"
);

$ok = $stmt->execute([$newStatus, $newFoul, $reason, $tripId]);
if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    exit;
}

// Audit on the workflow timeline so the cancellation is visible there.
$dId = (int)$row['d_id'];
$bookingNo = '';
$stmtBn = $conn->prepare("SELECT booking_no FROM dispatch WHERE d_id = ? LIMIT 1");
$stmtBn->execute([$dId]);
$rb = $stmtBn->fetch();
if ($rb) { $bookingNo = $rb['booking_no']; }

$stage = $alreadyAssigned ? 'segment_foul' : 'segment_cancelled';
$notes = trim('Segment ' . $tripId . ($reason !== '' ? ' — ' . $reason : ''));
$stmt = $conn->prepare(
    "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, notes) VALUES (?, ?, ?, 'dispatcher', ?)"
);
if ($stmt) {
    $stmt->execute([$dId, $bookingNo, $stage, $notes]);
}

echo json_encode([
    'status'    => 'success',
    'foul_trip' => $newFoul,
    'new_status'=> $newStatus,
    'message'   => $alreadyAssigned
        ? 'Segment marked as foul trip (cancelled after assignment).'
        : 'Segment cancelled cleanly (was still pending).',
]);
