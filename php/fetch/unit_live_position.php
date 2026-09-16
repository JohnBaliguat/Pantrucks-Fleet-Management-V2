<?php
// Live position of one unit (for the equipment-locations map modal).
// Prefers the unit's Geotab fix (units.last_*); when the unit has no Geotab
// device/fix, falls back to the phone GPS of the driver currently on shift
// with this truck (else the unit's assigned driver).
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
    // Pick the phone-GPS driver: whoever is on shift with this truck, else the
    // unit's assigned driver_id.
    $st = $conn->prepare(
        "SELECT u.unit_name, u.current_location, u.geotab_device_id,
                u.last_lat  AS u_lat, u.last_lng AS u_lng, u.last_speed, u.bearing,
                u.last_position_at, u.is_communicating,
                drv.last_lat AS d_lat, drv.last_lng AS d_lng, drv.last_seen_at AS d_seen
           FROM units u
           LEFT JOIN drivers drv ON drv.driver_id = COALESCE(
                (SELECT driver_id FROM drivers
                  WHERE shift_truck = u.unit_name AND shift_ended_at IS NULL
                  ORDER BY shift_started_at DESC LIMIT 1),
                NULLIF(u.driver_id, 0))
          WHERE u.unit_name = ? LIMIT 1"
    );
    $st->execute([$code]);
    $r = $st->fetch();
    if (!$r) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Unit not found']);
        exit;
    }

    $uLat = is_numeric($r['u_lat']) ? (float)$r['u_lat'] : null;
    $uLng = is_numeric($r['u_lng']) ? (float)$r['u_lng'] : null;
    $dLat = is_numeric($r['d_lat']) ? (float)$r['d_lat'] : null;
    $dLng = is_numeric($r['d_lng']) ? (float)$r['d_lng'] : null;

    // Prefer Geotab (the truck itself); fall back to the driver's phone.
    if ($uLat !== null && $uLng !== null) {
        $lat = $uLat; $lng = $uLng; $src = 'geotab';
        $posAt = $r['last_position_at'];
    } elseif ($dLat !== null && $dLng !== null) {
        $lat = $dLat; $lng = $dLng; $src = 'phone';
        $posAt = $r['d_seen'];
    } else {
        $lat = null; $lng = null; $src = 'none';
        $posAt = null;
    }

    echo json_encode([
        'status'        => 'success',
        'code'          => $r['unit_name'],
        'location'      => $r['current_location'],
        'lat'           => $lat,
        'lng'           => $lng,
        'pos_source'    => $src,
        'speed'         => ($src === 'geotab' && is_numeric($r['last_speed'])) ? (float)$r['last_speed'] : null,
        'bearing'       => ($src === 'geotab' && is_numeric($r['bearing'])) ? (float)$r['bearing'] : null,
        'position_at'   => $posAt,
        'communicating' => (bool)$r['is_communicating'],
        'linked'        => !empty($r['geotab_device_id']),
        'has_position'  => ($lat !== null && $lng !== null),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
