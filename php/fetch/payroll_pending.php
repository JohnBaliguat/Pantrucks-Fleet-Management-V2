<?php
// Queue of approved trips still awaiting a payroll rate.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Payroll', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised', 'rows' => []]);
    exit;
}

try {
    $sql = "
        SELECT d.d_id, d.control_no, d.booking_no, d.d_drivername, d.d_truck,
               d.approved_at, d.d_datetime,
               t.trip_haulingsegment, t.trip_from, t.trip_to,
               t.container_activity, t.trip_sku, t.trip_containerstat
          FROM dispatch d
          LEFT JOIN LATERAL (
                SELECT trip_haulingsegment, trip_from, trip_to, container_activity, trip_sku, trip_containerstat
                  FROM trips
                 WHERE d_id = d.d_id
                 ORDER BY CASE WHEN trip_type = 'Trip 1' THEN 0 ELSE 1 END, trip_id ASC
                 LIMIT 1
          ) t ON TRUE
         WHERE d.payroll_status = 'pending' AND d.control_no <> ''
         ORDER BY d.approved_at DESC NULLS LAST, d.d_id DESC
         LIMIT 300
    ";
    $stmt = $conn->query($sql);
    $rows = [];
    while ($r = $stmt->fetch()) {
        $dateSrc = $r['approved_at'] ?: ($r['d_datetime'] ?? null);
        $rows[] = [
            'd_id'                => (int)$r['d_id'],
            'control_no'          => $r['control_no'],
            'booking_no'          => $r['booking_no'],
            'driver'              => $r['d_drivername'],
            'truck'               => $r['d_truck'],
            'date'                => $dateSrc ? date('M d, Y H:i', strtotime((string)$dateSrc)) : '—',
            'trip_haulingsegment' => $r['trip_haulingsegment'],
            'trip_from'           => $r['trip_from'],
            'trip_to'             => $r['trip_to'],
            'container_activity'  => $r['container_activity'],
            'trip_sku'            => $r['trip_sku'],
            'trip_containerstat'  => $r['trip_containerstat'],
        ];
    }
    echo json_encode(['status' => 'success', 'rows' => $rows, 'count' => count($rows)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'rows' => []]);
}
