<?php
// Feed for the "Verified Trips" tab on the Trip Verification page.
// Returns trips on dispatches whose workflow_stage has reached the
// verified-and-beyond stages, so the dispatcher can audit / backfill /
// fix piece_rate after the fact.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised', 'rows' => []]);
    exit;
}

$from   = trim($_GET['from']     ?? '');
$to     = trim($_GET['to']       ?? '');
$cust   = trim($_GET['customer'] ?? '');
$onlyMissing = !empty($_GET['only_missing']);   // optional: only rows with piece_rate = 0

$where  = ["d.workflow_stage IN ('pod_captured', 'billing_closed', 'client_notified')"];
$params = [];
if ($from !== '') { $where[] = "d.verified_at >= ?"; $params[] = $from . ' 00:00:00'; }
if ($to   !== '') { $where[] = "d.verified_at <= ?"; $params[] = $to   . ' 23:59:59'; }
if ($cust !== '') { $where[] = "b.costumer = ?";    $params[] = $cust; }
if ($onlyMissing) { $where[] = "(t.piece_rate IS NULL OR t.piece_rate = 0)"; }
$whereSql = ' WHERE ' . implode(' AND ', $where);

try {
    $sql = "
        SELECT t.trip_id, t.trip_type, t.d_id, t.trip_from, t.trip_to,
               t.trip_haulingsegment, t.container_activity, t.trip_sku,
               t.trip_container, t.trip_containerstat,
               t.piece_rate, t.trip_status,
               d.booking_no, d.d_truck, d.d_drivername, d.driver_id,
               d.verified_at, d.workflow_stage, d.control_no, d.payroll_status,
               b.costumer, b.customer_segment,
               drv.driver_fname, drv.driver_lname
          FROM trips t
          JOIN dispatch d ON d.d_id = t.d_id
          LEFT JOIN booking b ON b.booking_no = d.booking_no
          LEFT JOIN drivers drv ON drv.driver_id = d.driver_id
          $whereSql
          ORDER BY d.verified_at DESC NULLS LAST, t.trip_id DESC
          LIMIT 500
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'trip_id'             => (int)$r['trip_id'],
            'trip_type'           => $r['trip_type'],
            'd_id'                => (int)$r['d_id'],
            'booking_no'          => $r['booking_no'],
            'customer'            => $r['costumer'],
            'customer_segment'    => $r['customer_segment'],
            'truck'               => $r['d_truck'],
            'driver_id'           => (int)$r['driver_id'],
            'driver_name'         => $r['d_drivername']
                ?: trim(($r['driver_fname'] ?? '') . ' ' . ($r['driver_lname'] ?? '')),
            'trip_from'           => $r['trip_from'],
            'trip_to'             => $r['trip_to'],
            'trip_haulingsegment' => $r['trip_haulingsegment'],
            'container_activity'  => $r['container_activity'],
            'trip_sku'            => $r['trip_sku'],
            'trip_container'      => $r['trip_container'],
            'trip_containerstat'  => $r['trip_containerstat'],
            'piece_rate'          => (float)$r['piece_rate'],
            'workflow_stage'      => $r['workflow_stage'],
            'verified_at'         => $r['verified_at'],
            'control_no'          => $r['control_no'],
            'payroll_status'      => $r['payroll_status'],
        ];
    }
    echo json_encode([
        'status' => 'success',
        'rows'   => $rows,
        'count'  => count($rows),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'rows' => []]);
}
