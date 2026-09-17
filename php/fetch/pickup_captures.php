<?php
// Admin / Dispatcher feed for the Field Captures page → Pickups tab.
// Returns one row per pickup_capture record, joined to dispatch + booking
// so the UI can show booking_no / customer / truck instead of bare IDs.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$from   = trim($_GET['from']     ?? '');  // YYYY-MM-DD
$to     = trim($_GET['to']       ?? '');
$driver = trim($_GET['driver']   ?? '');
$cust   = trim($_GET['customer'] ?? '');

$where  = [];
$params = [];
if ($from !== '') { $where[] = "pc.captured_at >= ?";              $params[] = $from . ' 00:00:00'; }
if ($to   !== '') { $where[] = "pc.captured_at <= ?";              $params[] = $to   . ' 23:59:59'; }
if ($driver !== '') { $where[] = "pc.driver_id = ?";               $params[] = (int)$driver; }
if ($cust   !== '') { $where[] = "b.costumer = ?";                 $params[] = $cust; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

try {
    $sql = "
        SELECT pc.pc_id, pc.d_id, pc.trip_id, pc.driver_id, pc.driver_id_number,
               pc.container_no,
               COALESCE(pc.container_seal, '') AS container_seal,
               COALESCE(pc.shipping_line,  '') AS shipping_line,
               pc.photo_path, pc.lat, pc.lng, pc.captured_at,
               d.booking_no, d.d_truck, d.d_drivername,
               b.costumer, b.customer_segment, b.trip_from, b.trip_to,
               drv.driver_fname, drv.driver_lname
        FROM pickup_capture pc
        LEFT JOIN dispatch d ON d.d_id = pc.d_id
        LEFT JOIN booking  b ON b.booking_no = d.booking_no
        LEFT JOIN drivers  drv ON drv.driver_id = pc.driver_id
        $whereSql
        ORDER BY pc.pc_id DESC
        LIMIT 500
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'pc_id'        => (int)$r['pc_id'],
            'd_id'         => (int)$r['d_id'],
            'booking_no'   => $r['booking_no'],
            'customer'     => $r['costumer'],
            'customer_segment' => $r['customer_segment'],
            'truck'        => $r['d_truck'],
            'driver_id'    => (int)$r['driver_id'],
            'driver_name'  => $r['d_drivername']
                ?: trim(($r['driver_fname'] ?? '') . ' ' . ($r['driver_lname'] ?? '')),
            'container_no'   => $r['container_no'],
            'container_seal' => $r['container_seal'],
            'shipping_line'  => $r['shipping_line'],
            // photo_path is stored with the 'php/assets/uploads/...' prefix
            // baked in by driver_update_status.php — use as-is.
            'photo_url'      => $r['photo_path'] ?: null,
            'trip_from'    => $r['trip_from'],
            'trip_to'      => $r['trip_to'],
            'lat'          => is_numeric($r['lat']) ? (float)$r['lat'] : null,
            'lng'          => is_numeric($r['lng']) ? (float)$r['lng'] : null,
            'captured_at'  => $r['captured_at'],
        ];
    }
    echo json_encode(['status' => 'success', 'rows' => $rows, 'count' => count($rows)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
