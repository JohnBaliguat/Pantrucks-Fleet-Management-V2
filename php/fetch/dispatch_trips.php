<?php
// Returns every trips row for one dispatch, ordered Trip 1 → Trip 2 …
// The trip-verification modal calls this to render a per-trip activity
// picker so the dispatcher can stamp piece_rate independently for each leg.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised', 'rows' => []]);
    exit;
}

$dId = (int)($_GET['d_id'] ?? 0);
if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required', 'rows' => []]);
    exit;
}

try {
    // Only the *booked* legs should be rate-able in the verification modal.
    // The booking leg is always persisted as 'Trip 1'. Multi-leg assignments
    // (assign_booking_dnd.php) also insert repositioning / return pre-legs as
    // 'Trip 2'..'Trip N' WITHOUT a container_activity — those are not billable
    // customer trips and must not show up for rate assignment. Genuine second
    // booked legs (older dispatch/mybooking flows) do carry a container_activity,
    // so keeping any leg with a non-empty activity preserves those.
    $stmt = $conn->prepare(
        "SELECT trip_id, trip_type, trip_from, trip_to,
                trip_haulingsegment, trip_haulingtype,
                container_activity, trip_container, trip_containerstat,
                piece_rate, trip_status
           FROM trips
          WHERE d_id = ?
            AND (trip_type = 'Trip 1' OR TRIM(COALESCE(container_activity, '')) <> '')
          ORDER BY trip_id ASC"
    );
    $stmt->execute([$dId]);
    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'trip_id'             => (int)$r['trip_id'],
            'trip_type'           => $r['trip_type'],         // e.g. 'Trip 1'
            'trip_from'           => $r['trip_from'],
            'trip_to'             => $r['trip_to'],
            'trip_haulingsegment' => $r['trip_haulingsegment'],
            'trip_haulingtype'    => $r['trip_haulingtype'],
            'container_activity'  => $r['container_activity'],
            'trip_container'      => $r['trip_container'],
            'trip_containerstat'  => $r['trip_containerstat'],
            'piece_rate'          => (float)$r['piece_rate'],
            'trip_status'         => $r['trip_status'],
        ];
    }
    echo json_encode(['status' => 'success', 'd_id' => $dId, 'rows' => $rows, 'count' => count($rows)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'rows' => []]);
}
