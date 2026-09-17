<?php
// Phase 13 — smart dispatch recommendation.
//
// Given a booking number, rank every driver who is currently on shift
// by haversine distance from their last reported GPS fix to the
// booking's pickup location. The dispatcher sees the top N suggestions
// as a badge on each booking tile + a sorted recommendation modal.
//
// Why haversine and not OSRM road distance:
//   • We have to score every active driver per booking on the
//     dashboard refresh tick — N×M round-trips to OSRM would melt
//     the demo router. Great-circle is "good enough" to surface
//     "this driver is nearby" vs "this driver is 60 km away."
//   • The existing assign flow still calls OSRM once at commit
//     time for billable km_run, so trip cost stays accurate.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$booking_no = trim($_GET['booking_no'] ?? '');
$limit      = max(1, min(20, (int)($_GET['limit'] ?? 5)));

if ($booking_no === '') {
    echo json_encode(['status' => 'error', 'message' => 'booking_no required']);
    exit;
}

// Pickup location for the booking.
$stmt = $conn->prepare(
    "SELECT b.trip_from,
            l.latitude  AS pickup_lat,
            l.longitude AS pickup_lng
     FROM booking b
     LEFT JOIN location l ON l.location_name = b.trip_from
     WHERE b.booking_no = ? LIMIT 1"
);
$stmt->execute([$booking_no]);
$bk = $stmt->fetch();
if (!$bk) {
    echo json_encode(['status' => 'error', 'message' => 'Booking not found.']);
    exit;
}

$pickup_lat = is_numeric($bk['pickup_lat']) ? (float)$bk['pickup_lat'] : null;
$pickup_lng = is_numeric($bk['pickup_lng']) ? (float)$bk['pickup_lng'] : null;
$pickupKnown = ($pickup_lat !== null && $pickup_lng !== null);

// Candidate drivers — every shift-open driver with a usable last GPS
// fix. We also surface drivers whose GPS is stale (>2h) but still on
// shift; the UI flags those so dispatch can decide whether to trust the
// distance number.
$sql = "SELECT d.driver_id,
               CONCAT(d.driver_lname, ', ', d.driver_fname) AS driver_name,
               d.shift_truck,
               d.driver_assignsegment AS segment,
               d.last_lat, d.last_lng, d.last_seen_at,
               (SELECT s.started_at FROM driver_shift s
                  WHERE s.driver_id = d.driver_id AND s.ended_at IS NULL
                  ORDER BY s.ds_id DESC LIMIT 1) AS shift_open_since,
               (SELECT COUNT(*)
                  FROM dispatch dd
                  WHERE dd.driver_id = d.driver_id
                    AND dd.workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')
               ) AS active_trips
        FROM drivers d
        WHERE EXISTS (
                SELECT 1 FROM driver_shift s
                WHERE s.driver_id = d.driver_id AND s.ended_at IS NULL
              )
          AND TRIM(d.shift_truck) <> ''
          AND NOT EXISTS (
                SELECT 1
                FROM violation_record v
                WHERE v.driver_id = d.driver_id
                  AND v.vr_status = 'Active'
          )";

$res = $conn->query($sql);
$candidates = [];
while ($r = $res->fetch()) {
    $hasGps = is_numeric($r['last_lat']) && is_numeric($r['last_lng']);
    $distance = null;
    if ($pickupKnown && $hasGps) {
        $distance = haversineKm((float)$r['last_lat'], (float)$r['last_lng'], $pickup_lat, $pickup_lng);
    }
    $stale = false;
    if (!empty($r['last_seen_at'])) {
        $stale = (time() - strtotime($r['last_seen_at'])) > (2 * 3600);
    } else {
        $stale = true;
    }
    $candidates[] = [
        'driver_id'        => (int)$r['driver_id'],
        'driver_name'      => $r['driver_name'],
        'shift_truck'      => $r['shift_truck'],
        'segment'          => $r['segment'],
        'shift_open_since' => $r['shift_open_since'],
        'active_trips'     => (int)$r['active_trips'],
        'has_gps'          => $hasGps,
        'gps_stale'        => $stale,
        'distance_km'      => $distance,
    ];
}

// Sort:
//   1. Drivers WITH a usable distance, ascending by distance.
//   2. Drivers without GPS / no pickup coords, by fewest active trips
//      (so dispatch still gets a reasonable fallback ordering).
usort($candidates, function($a, $b) {
    $aHas = $a['distance_km'] !== null;
    $bHas = $b['distance_km'] !== null;
    if ($aHas && !$bHas) return -1;
    if (!$aHas && $bHas) return 1;
    if ($aHas && $bHas) return $a['distance_km'] <=> $b['distance_km'];
    return $a['active_trips'] <=> $b['active_trips'];
});

$top = array_slice($candidates, 0, $limit);

echo json_encode([
    'status'      => 'success',
    'booking_no'  => $booking_no,
    'pickup'      => [
        'name'  => $bk['trip_from'],
        'lat'   => $pickup_lat,
        'lng'   => $pickup_lng,
        'known' => $pickupKnown,
    ],
    'count'       => count($candidates),
    'recommended' => $top,
]);
