<?php
// Returns the dispatch + (up to 2) trip rows for the Trip Report edit modal.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$dId = (int)($_GET['d_id'] ?? 0);
if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT d_id, booking_no, d_datetime, d_dispatchhub, d_drivername, d_truck, costumer
         FROM dispatch
         WHERE d_id = ?
         LIMIT 1"
    );
    $stmt->execute([$dId]);
    $dispatch = $stmt->fetch();
    if (!$dispatch) {
        echo json_encode(['status' => 'error', 'message' => 'Dispatch not found.']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT trip_id, trip_type, trip_from, trip_to, trip_container, trip_containerstat,
                trip_haulingsegment, trip_haulingtype, trip_status
         FROM trips
         WHERE d_id = ?
         ORDER BY trip_type ASC, trip_id ASC"
    );
    $stmt->execute([$dId]);
    $trips = $stmt->fetchAll();

    $byType = ['Trip 1' => null, 'Trip 2' => null];
    foreach ($trips as $t) {
        $type = $t['trip_type'] ?? '';
        if (isset($byType[$type]) && $byType[$type] === null) {
            $byType[$type] = $t;
        }
    }

    echo json_encode([
        'status'   => 'success',
        'dispatch' => $dispatch,
        'trip1'    => $byType['Trip 1'],
        'trip2'    => $byType['Trip 2'],
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Fetch failed: ' . $e->getMessage()]);
}
