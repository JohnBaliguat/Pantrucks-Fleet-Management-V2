<?php
// Admin / Dispatcher feed for the Field Captures page → Jackups tab.
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
$active = trim($_GET['active']   ?? 'all'); // all | active | returned

$where  = [];
$params = [];
if ($from !== '') { $where[] = "tj.detached_at >= ?"; $params[] = $from . ' 00:00:00'; }
if ($to   !== '') { $where[] = "tj.detached_at <= ?"; $params[] = $to   . ' 23:59:59'; }
if ($driver !== '') { $where[] = "tj.driver_id = ?"; $params[] = (int)$driver; }
if ($cust   !== '') { $where[] = "b.costumer = ?";   $params[] = $cust; }
if ($active === 'active')   { $where[] = "tj.billing_active = TRUE"; }
if ($active === 'returned') { $where[] = "tj.billing_active = FALSE"; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

try {
    $sql = "
        SELECT tj.tj_id, tj.d_id, tj.trailer_code, tj.driver_id,
               tj.photo_path, tj.lat, tj.lng,
               tj.detached_at, tj.returned_at, tj.billing_active,
               d.booking_no, d.d_truck, d.d_drivername,
               b.costumer, b.customer_segment, b.trip_from, b.trip_to,
               drv.driver_fname, drv.driver_lname
        FROM trailer_jackup tj
        LEFT JOIN dispatch d ON d.d_id = tj.d_id
        LEFT JOIN booking  b ON b.booking_no = d.booking_no
        LEFT JOIN drivers  drv ON drv.driver_id = tj.driver_id
        $whereSql
        ORDER BY tj.tj_id DESC
        LIMIT 500
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'tj_id'         => (int)$r['tj_id'],
            'd_id'          => (int)($r['d_id'] ?? 0),
            'booking_no'    => $r['booking_no'],
            'customer'      => $r['costumer'],
            'truck'         => $r['d_truck'],
            'driver_id'     => (int)$r['driver_id'],
            'driver_name'   => $r['d_drivername']
                ?: trim(($r['driver_fname'] ?? '') . ' ' . ($r['driver_lname'] ?? '')),
            'trailer_code'  => $r['trailer_code'],
            // photo_path is stored with the 'php/assets/uploads/...' prefix
            // baked in by save_trailer_jackup.php — use as-is.
            'photo_url'     => $r['photo_path'] ?: null,
            'trip_from'     => $r['trip_from'],
            'trip_to'       => $r['trip_to'],
            'lat'           => is_numeric($r['lat']) ? (float)$r['lat'] : null,
            'lng'           => is_numeric($r['lng']) ? (float)$r['lng'] : null,
            'detached_at'   => $r['detached_at'],
            'returned_at'   => $r['returned_at'],
            'billing_active'=> (bool)$r['billing_active'],
        ];
    }
    echo json_encode(['status' => 'success', 'rows' => $rows, 'count' => count($rows)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
