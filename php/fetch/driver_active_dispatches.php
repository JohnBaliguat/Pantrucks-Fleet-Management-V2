<?php
// Returns the active (in-flight) dispatch transactions for one driver,
// for the Dispatch Board's "click a driver to see their active work" UI.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$driverId = (int)($_GET['driver_id'] ?? 0);
if ($driverId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'driver_id required']);
    exit;
}

// In-flight workflow stages — completed (pod_captured/billing_closed/client_notified)
// are intentionally excluded so the panel only shows what the driver still owes.
$activeStages = "'dispatcher_assigned', 'reassigned', 'driver_accepted', 'gate_cleared', 'en_route', 'delivered', 'pending_verification'";

$sql = "
    SELECT
        d.d_id,
        d.booking_no,
        d.d_truck,
        d.d_trailer,
        d.d_genset,
        d.d_drivername,
        d.workflow_stage,
        d.workflow_updated_at,
        d.d_datetime,
        d.d_tripreceipt,
        b.costumer,
        b.customer_segment,
        b.container,
        b.container_seal,
        b.container_status,
        COALESCE(t.trip_from, b.trip_from) AS trip_from,
        COALESCE(t.trip_to,   b.trip_to)   AS trip_to,
        COALESCE(t.trip_container, b.container) AS trip_container,
        drv.driver_fname,
        drv.driver_lname,
        drv.last_lat,
        drv.last_lng,
        drv.last_seen_at
    FROM dispatch d
    LEFT JOIN booking b ON b.booking_no = d.booking_no
    -- Show the driver the booking leg ('Trip 1'). Multi-leg trip tickets add
    -- extra legs (Trip 2..N) with higher trip_id, so a plain MAX(trip_id) would
    -- surface a repositioning/return leg instead of the booking route. Fall back
    -- to the newest trip only when a dispatch has no 'Trip 1'.
    LEFT JOIN trips t
        ON t.trip_id = (
            SELECT t2.trip_id FROM trips t2
            WHERE t2.d_id = d.d_id
            ORDER BY (t2.trip_type = 'Trip 1') DESC, t2.trip_id DESC
            LIMIT 1
        )
    LEFT JOIN drivers drv ON drv.driver_id = d.driver_id
    WHERE d.driver_id = ?
      AND d.workflow_stage IN ($activeStages)
    ORDER BY d.workflow_updated_at DESC, d.d_id DESC
    LIMIT 50
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$driverId]);
    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'd_id'                => (int)$r['d_id'],
            'booking_no'          => $r['booking_no'],
            'truck'               => $r['d_truck'],
            'trailer'             => $r['d_trailer'],
            'genset'              => $r['d_genset'],
            'driver_name'         => $r['d_drivername']
                ?: trim(($r['driver_fname'] ?? '') . ' ' . ($r['driver_lname'] ?? '')),
            'workflow_stage'      => $r['workflow_stage'],
            'workflow_updated_at' => $r['workflow_updated_at'],
            'dispatched_at'       => $r['d_datetime'],
            'trip_receipt'        => $r['d_tripreceipt'],
            'customer'            => $r['costumer'],
            'customer_segment'    => $r['customer_segment'],
            'container'           => trim((string)($r['trip_container'] ?? '')) !== ''
                                       ? $r['trip_container']
                                       : $r['container'],
            'container_seal'      => $r['container_seal'],
            'container_status'    => $r['container_status'],
            'trip_from'           => $r['trip_from'],
            'trip_to'             => $r['trip_to'],
            'last_lat'            => is_numeric($r['last_lat']) ? (float)$r['last_lat'] : null,
            'last_lng'            => is_numeric($r['last_lng']) ? (float)$r['last_lng'] : null,
            'last_seen_at'        => $r['last_seen_at'],
        ];
    }
    echo json_encode([
        'status' => 'success',
        'driver_id' => $driverId,
        'rows' => $rows,
        'count' => count($rows),
        'fetched_at' => date('Y-m-d H:i:s'),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
