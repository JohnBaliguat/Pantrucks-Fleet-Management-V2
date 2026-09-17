<?php
// Update a single trip's container_activity + piece_rate after the fact.
// Called from the "Verified Trips" tab when the dispatcher backfills or
// corrects a rate that was missed during verification.

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

$tripId   = (int)($_POST['trip_id'] ?? 0);
$activity = trim((string)($_POST['activity'] ?? ''));
$actor    = (int)($_SESSION['user_id'] ?? 0);

if ($tripId <= 0 || $activity === '') {
    echo json_encode(['status' => 'error', 'message' => 'trip_id + activity required']);
    exit;
}

try {
    // Resolve activity → rate.
    $rateStmt = $conn->prepare(
        "SELECT total_rates FROM trip_rates
          WHERE LOWER(TRIM(activity)) = LOWER(TRIM(?))
          ORDER BY id DESC LIMIT 1"
    );
    $rateStmt->execute([$activity]);
    $rate = $rateStmt->fetchColumn();
    if ($rate === false) {
        echo json_encode(['status' => 'error', 'message' => 'Unknown activity: ' . $activity]);
        exit;
    }

    // Capture previous values for the audit row.
    $prevStmt = $conn->prepare(
        "SELECT t.d_id, t.container_activity, t.piece_rate, d.booking_no
           FROM trips t
           LEFT JOIN dispatch d ON d.d_id = t.d_id
          WHERE t.trip_id = ? LIMIT 1"
    );
    $prevStmt->execute([$tripId]);
    $prev = $prevStmt->fetch();
    if (!$prev) {
        echo json_encode(['status' => 'error', 'message' => 'Trip not found']);
        exit;
    }

    $conn->beginTransaction();
    // Stamp only the money — container_activity keeps its operational verb; the
    // SKU is in trips.trip_sku. The picked activity is captured in the audit note.
    $upd = $conn->prepare(
        "UPDATE trips SET piece_rate = ? WHERE trip_id = ?"
    );
    $upd->execute([(float)$rate, $tripId]);

    // Audit. Keep it short — the dispatcher's change is reconstructable
    // from this single row.
    $note = sprintf(
        'Trip rate updated by %s #%d — trip#%d: %s ₱%s (was %s ₱%s)',
        $role, $actor, $tripId,
        $activity, number_format((float)$rate, 2),
        $prev['container_activity'] ?: '—',
        number_format((float)$prev['piece_rate'], 2)
    );
    $log = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, 'rate_corrected', ?, ?, ?)"
    );
    $log->execute([(int)$prev['d_id'], $prev['booking_no'], $role, $actor, $note]);

    $conn->commit();

    echo json_encode([
        'status'     => 'success',
        'trip_id'    => $tripId,
        'activity'   => $activity,
        'piece_rate' => (float)$rate,
        'message'    => 'Rate updated to ₱' . number_format((float)$rate, 2),
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
}
