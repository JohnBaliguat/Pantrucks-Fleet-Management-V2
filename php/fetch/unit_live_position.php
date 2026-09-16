<?php
// Live position of one unit (for the equipment-locations map modal).
// Returns the latest Geotab-polled coordinates + context so the modal can
// keep the marker updated while it's open.
//
//   GET code = unit_name

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin', 'Gate-Guard'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'code required']);
    exit;
}

try {
    $st = $conn->prepare(
        "SELECT unit_name, current_location, last_lat, last_lng, last_speed, bearing,
                last_position_at, is_communicating, geotab_device_id
           FROM units WHERE unit_name = ? LIMIT 1"
    );
    $st->execute([$code]);
    $r = $st->fetch();
    if (!$r) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Unit not found']);
        exit;
    }

    $lat = is_numeric($r['last_lat']) ? (float)$r['last_lat'] : null;
    $lng = is_numeric($r['last_lng']) ? (float)$r['last_lng'] : null;

    echo json_encode([
        'status'        => 'success',
        'code'          => $r['unit_name'],
        'location'      => $r['current_location'],
        'lat'           => $lat,
        'lng'           => $lng,
        'speed'         => is_numeric($r['last_speed']) ? (float)$r['last_speed'] : null,
        'bearing'       => is_numeric($r['bearing']) ? (float)$r['bearing'] : null,
        'position_at'   => $r['last_position_at'],
        'communicating' => (bool)$r['is_communicating'],
        'linked'        => !empty($r['geotab_device_id']),
        'has_position'  => ($lat !== null && $lng !== null),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
