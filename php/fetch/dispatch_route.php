<?php
// Route + arrival for one dispatch (container-tracking map modal).
//
// Returns the trip's origin and destination (from the `location` table by
// name), the OSRM road-route geometry between them, the truck's current
// position, and whether it has arrived — using the Geotab zone the truck is
// in (matches the destination) with a distance fallback.
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

function dr_haversine_m($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

try {
    $st = $conn->prepare(
        "SELECT COALESCE(t.trip_from, b.trip_from) AS trip_from,
                COALESCE(t.trip_to,   b.trip_to)   AS trip_to,
                d.d_truck,
                COALESCE(u.last_lat, drv.last_lat) AS cur_lat,
                COALESCE(u.last_lng, drv.last_lng) AS cur_lng,
                CASE WHEN u.last_position_at IS NOT NULL THEN 'geotab' ELSE 'phone' END AS pos_source,
                z.name AS zone_name
           FROM dispatch d
           LEFT JOIN trips t ON t.trip_id = (
                SELECT t2.trip_id FROM trips t2 WHERE t2.d_id = d.d_id
                ORDER BY (t2.trip_type = 'Trip 1') DESC, t2.trip_id DESC LIMIT 1)
           LEFT JOIN booking b ON b.booking_no = d.booking_no
           LEFT JOIN units   u ON u.unit_name  = d.d_truck
           LEFT JOIN drivers drv ON drv.driver_id = d.driver_id
           LEFT JOIN geotab_zone z ON z.zone_id = u.current_zone_id
          WHERE d.d_id = ? LIMIT 1"
    );
    $st->execute([$dId]);
    $r = $st->fetch();
    if (!$r) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Dispatch not found']);
        exit;
    }

    // Resolve origin/destination coordinates from the location table by name.
    // Prefer a row that actually has numeric coordinates (there can be
    // duplicate location rows for a name, some blank).
    $locStmt = $conn->prepare(
        "SELECT latitude, longitude FROM location
          WHERE location_name = ? AND latitude ~ '^-?[0-9.]+$' AND longitude ~ '^-?[0-9.]+$'
          LIMIT 1"
    );
    $resolve = function (?string $name) use ($locStmt) {
        $name = trim((string)$name);
        if ($name === '') return null;
        $locStmt->execute([$name]);
        $row = $locStmt->fetch();
        if (!$row || !is_numeric($row['latitude']) || !is_numeric($row['longitude'])) return null;
        return ['name' => $name, 'lat' => (float)$row['latitude'], 'lng' => (float)$row['longitude']];
    };
    $origin = $resolve($r['trip_from']);
    $dest   = $resolve($r['trip_to']);

    $curLat = is_numeric($r['cur_lat']) ? (float)$r['cur_lat'] : null;
    $curLng = is_numeric($r['cur_lng']) ? (float)$r['cur_lng'] : null;

    // OSRM road route (origin -> destination), as [lat,lng] pairs for Leaflet.
    $route = [];
    if ($origin && $dest) {
        $url = "http://router.project-osrm.org/route/v1/driving/"
             . "{$origin['lng']},{$origin['lat']};{$dest['lng']},{$dest['lat']}"
             . "?overview=full&geometries=geojson";
        $ctx = stream_context_create(['http' => ['timeout' => 8]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp !== false) {
            $data = json_decode($resp, true);
            $coords = $data['routes'][0]['geometry']['coordinates'] ?? [];
            // Downsample very dense routes to keep the payload small.
            $step = max(1, (int)floor(count($coords) / 500));
            for ($i = 0; $i < count($coords); $i += $step) {
                $route[] = [(float)$coords[$i][1], (float)$coords[$i][0]]; // [lat,lng]
            }
            if ($coords) { $last = end($coords); $route[] = [(float)$last[1], (float)$last[0]]; }
        }
    }

    // Arrival: truck is in a Geotab zone whose name matches the destination,
    // or (fallback) within 300 m of the destination coordinates.
    $arrived = false; $arrivedBy = null;
    $destName = strtolower(trim((string)$r['trip_to']));
    $zoneName = strtolower(trim((string)($r['zone_name'] ?? '')));
    if ($destName !== '' && $zoneName !== '' &&
        (strpos($zoneName, $destName) !== false || strpos($destName, $zoneName) !== false)) {
        $arrived = true; $arrivedBy = 'zone';
    } elseif ($dest && $curLat !== null && $curLng !== null &&
              dr_haversine_m($curLat, $curLng, $dest['lat'], $dest['lng']) <= 300) {
        $arrived = true; $arrivedBy = 'distance';
    }

    echo json_encode([
        'status'      => 'success',
        'truck'       => $r['d_truck'],
        'origin'      => $origin,
        'destination' => $dest,
        'route'       => $route,
        'current'     => ($curLat !== null && $curLng !== null)
                          ? ['lat' => $curLat, 'lng' => $curLng, 'pos_source' => $r['pos_source']] : null,
        'current_zone'=> $r['zone_name'],
        'arrived'     => $arrived,
        'arrived_by'  => $arrivedBy,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
