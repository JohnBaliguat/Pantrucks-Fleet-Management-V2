<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin', 'HR-Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

$bn = trim($_GET['booking_no'] ?? '');
$dId = (int)($_GET['d_id'] ?? 0);
if ($bn === '' && $dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'booking_no or d_id required']); exit;
}

if ($dId > 0) {
    $stmt = $conn->prepare("SELECT booking_no FROM dispatch WHERE d_id = ? LIMIT 1");
    $stmt->execute([$dId]);
    $r = $stmt->fetch();
if ($r) $bn = $r['booking_no'];
}

// Booking summary.
$booking = null;
if ($bn !== '') {
    $stmt = $conn->prepare(
        "SELECT booking_no, booking_type, costumer, container, container_status,
                trip_from, trip_to, status, vessel_name, customs_cleared, client_notified_at
         FROM booking WHERE booking_no = ? LIMIT 1"
    );
    $stmt->execute([$bn]);
    $booking = $stmt->fetch();
}

// Dispatches under this booking.
$dispatches = [];
if ($bn !== '') {
    $stmt = $conn->prepare(
        "SELECT d_id, d_truck, d_drivername, workflow_stage, workflow_updated_at,
                driver_accepted_at, gate_cleared_at, billing_closed_at, client_notified_at,
                billing_amount, billing_currency
         FROM dispatch WHERE booking_no = ? ORDER BY d_id ASC"
    );
    $stmt->execute([$bn]);
    $res = $stmt;
    while ($r = $res->fetch()) { $dispatches[] = $r; }
}

// Append-only event log.
$events = [];
if ($bn !== '') {
    $stmt = $conn->prepare(
        "SELECT we_id, d_id, stage, actor_role, actor_id, notes, event_at,
                CASE
                    WHEN LOWER(actor_role) = 'driver' THEN (
                        SELECT TRIM(CONCAT(dr.driver_lname, ', ', dr.driver_fname))
                        FROM drivers dr
                        WHERE dr.driver_id = workflow_event.actor_id
                        LIMIT 1
                    )
                    WHEN LOWER(actor_role) IN ('dispatcher', 'admin', 'hr-admin', 'visual', 'maintenance', 'gate-guard') THEN (
                        SELECT COALESCE(
                            NULLIF(TRIM(CONCAT(u.user_lname, ', ', u.user_fname)), ','),
                            NULLIF(u.user_name, '')
                        )
                        FROM \"user\" u
                        WHERE u.user_id = workflow_event.actor_id
                        LIMIT 1
                    )
                    ELSE NULL
                END AS actor_name
         FROM workflow_event WHERE booking_no = ? ORDER BY event_at ASC, we_id ASC"
    );
    $stmt->execute([$bn]);
    $res = $stmt;
    while ($r = $res->fetch()) { $events[] = $r; }
}

echo json_encode([
    'status'     => 'success',
    'booking'    => $booking,
    'dispatches' => $dispatches,
    'events'     => $events,
]);
