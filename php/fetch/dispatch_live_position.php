<?php
// Live position for one dispatch (container-tracking map modal).
// Uses the same source rule as the tracking board: prefer the truck's Geotab
// fix (units.last_*), fall back to the driver-phone heartbeat (drivers.last_*).
//
//   GET d_id = dispatch id

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Booker', 'Admin', 'Visual'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$dId = filter_input(INPUT_GET, 'd_id', FILTER_VALIDATE_INT);
if (!$dId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

try {
    $st = $conn->prepare(
        "SELECT d.d_truck AS truck, d.d_drivername AS driver,
                COALESCE(u.last_lat, drv.last_lat)             AS lat,
                COALESCE(u.last_lng, drv.last_lng)             AS lng,
                COALESCE(u.last_position_at, drv.last_seen_at) AS pos_at,
                CASE WHEN u.last_position_at IS NOT NULL THEN 'geotab' ELSE 'phone' END AS pos_source,
                u.current_location, u.last_speed, u.is_communicating
           FROM dispatch d
           LEFT JOIN units   u   ON u.unit_name  = d.d_truck
           LEFT JOIN drivers drv ON drv.driver_id = d.driver_id
          WHERE d.d_id = ? LIMIT 1"
    );
    $st->execute([$dId]);
    $r = $st->fetch();
    if (!$r) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Dispatch not found']);
        exit;
    }

    $lat = is_numeric($r['lat']) ? (float)$r['lat'] : null;
    $lng = is_numeric($r['lng']) ? (float)$r['lng'] : null;

    echo json_encode([
        'status'        => 'success',
        'truck'         => $r['truck'],
        'driver'        => $r['driver'],
        'lat'           => $lat,
        'lng'           => $lng,
        'pos_source'    => $r['pos_source'],
        'position_at'   => $r['pos_at'],
        'location'      => $r['current_location'],
        'speed'         => is_numeric($r['last_speed']) ? (float)$r['last_speed'] : null,
        'communicating' => (bool)$r['is_communicating'],
        'has_position'  => ($lat !== null && $lng !== null),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
