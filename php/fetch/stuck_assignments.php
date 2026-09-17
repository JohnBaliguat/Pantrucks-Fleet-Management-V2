<?php
// Dispatch Board feed: assignments stuck in `dispatcher_assigned` or
// `reassigned` for >= PT_STUCK_ASSIGN_MINUTES minutes (default 5).
//
// Returned to the dispatch board so it can render the Stuck Assignments
// strip and tag the driver tile of any driver who's holding a stuck job.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

const PT_STUCK_ASSIGN_MINUTES = 5;

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised', 'rows' => [], 'count' => 0]);
    exit;
}

$minutes = isset($_GET['minutes']) ? max(1, (int)$_GET['minutes']) : PT_STUCK_ASSIGN_MINUTES;

try {
    $sql = "
        SELECT
            d.d_id,
            d.booking_no,
            d.driver_id,
            d.d_truck,
            d.d_drivername,
            d.workflow_stage,
            d.workflow_updated_at,
            EXTRACT(EPOCH FROM (NOW() - d.workflow_updated_at)) / 60.0 AS minutes_pending,
            b.costumer,
            b.customer_segment,
            b.trip_from, b.trip_to,
            b.container, b.container_status,
            drv.driver_fname, drv.driver_lname
        FROM dispatch d
        LEFT JOIN booking b ON b.booking_no = d.booking_no
        LEFT JOIN drivers drv ON drv.driver_id = d.driver_id
        WHERE d.workflow_stage IN ('dispatcher_assigned', 'reassigned')
          AND d.workflow_updated_at < NOW() - (? || ' minutes')::interval
        ORDER BY d.workflow_updated_at ASC
        LIMIT 100
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([(string)$minutes]);

    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'd_id'            => (int)$r['d_id'],
            'booking_no'      => $r['booking_no'],
            'driver_id'       => (int)$r['driver_id'],
            'driver_name'     => $r['d_drivername']
                ?: trim(($r['driver_fname'] ?? '') . ' ' . ($r['driver_lname'] ?? '')),
            'truck'           => $r['d_truck'],
            'workflow_stage'  => $r['workflow_stage'],
            'minutes_pending' => (int)round((float)$r['minutes_pending']),
            'assigned_at'     => $r['workflow_updated_at'],
            'customer'        => $r['costumer'],
            'customer_segment'=> $r['customer_segment'],
            'trip_from'       => $r['trip_from'],
            'trip_to'         => $r['trip_to'],
            'container'       => $r['container'],
            'container_status'=> $r['container_status'],
        ];
    }
    echo json_encode([
        'status'    => 'success',
        'threshold' => $minutes,
        'count'     => count($rows),
        'rows'      => $rows,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'rows' => [], 'count' => 0]);
}
