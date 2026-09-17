<?php
// Admin / Dispatcher feed for the Field Captures page → PODs tab.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$from   = trim($_GET['from']     ?? '');
$to     = trim($_GET['to']       ?? '');
$driver = trim($_GET['driver']   ?? '');
$cust   = trim($_GET['customer'] ?? '');

$where  = [];
$params = [];
if ($from !== '') { $where[] = "pd.captured_at >= ?"; $params[] = $from . ' 00:00:00'; }
if ($to   !== '') { $where[] = "pd.captured_at <= ?"; $params[] = $to   . ' 23:59:59'; }
if ($driver !== '') { $where[] = "pd.driver_id = ?"; $params[] = (int)$driver; }
if ($cust   !== '') { $where[] = "b.costumer = ?";   $params[] = $cust; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

try {
    $sql = "
        SELECT pd.pod_id, pd.d_id, pd.trip_id, pd.driver_id,
               pd.photo1_path, pd.photo2_path, pd.photo3_path, pd.signature_path,
               pd.signed_by, pd.lat, pd.lng, pd.captured_at,
               d.booking_no, d.d_truck, d.d_drivername,
               b.costumer, b.customer_segment, b.trip_from, b.trip_to,
               drv.driver_fname, drv.driver_lname
        FROM pod_capture pd
        LEFT JOIN dispatch d ON d.d_id = pd.d_id
        LEFT JOIN booking  b ON b.booking_no = d.booking_no
        LEFT JOIN drivers  drv ON drv.driver_id = pd.driver_id
        $whereSql
        ORDER BY pd.pod_id DESC
        LIMIT 500
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    // photo_path / signature_path are stored with the 'php/assets/uploads/...'
    // prefix baked in by save_pod.php — use as-is.
    $photo = function ($p) {
        return $p ?: null;
    };
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'pod_id'         => (int)$r['pod_id'],
            'd_id'           => (int)$r['d_id'],
            'booking_no'     => $r['booking_no'],
            'customer'       => $r['costumer'],
            'truck'          => $r['d_truck'],
            'driver_id'      => (int)$r['driver_id'],
            'driver_name'    => $r['d_drivername']
                ?: trim(($r['driver_fname'] ?? '') . ' ' . ($r['driver_lname'] ?? '')),
            'photos'         => array_values(array_filter([
                                    $photo($r['photo1_path']),
                                    $photo($r['photo2_path']),
                                    $photo($r['photo3_path']),
                                ])),
            'signature_url'  => $photo($r['signature_path']),
            'signed_by'      => $r['signed_by'],
            'trip_from'      => $r['trip_from'],
            'trip_to'        => $r['trip_to'],
            'lat'            => is_numeric($r['lat']) ? (float)$r['lat'] : null,
            'lng'            => is_numeric($r['lng']) ? (float)$r['lng'] : null,
            'captured_at'    => $r['captured_at'],
        ];
    }
    echo json_encode(['status' => 'success', 'rows' => $rows, 'count' => count($rows)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
