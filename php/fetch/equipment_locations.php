<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin', 'Gate-Guard'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

// Trucks + Gensets from units, Trailers from trailer. Bucket by
// current_location (or current_base for trailers), keeping unknown
// values in their own bucket.
$buckets = [];

function ensure(array &$buckets, string $key) {
    if (!isset($buckets[$key])) $buckets[$key] = ['truck' => [], 'genset' => [], 'trailer' => []];
    return $key;
}

$res = $conn->query("SELECT unit_name, unit_type, current_location, current_location_updated_at, unit_status, last_lat, last_lng, last_position_at FROM units WHERE unit_type IN ('truck','genset') ORDER BY unit_name ASC");
while ($r = $res->fetch()) {
    $loc = trim($r['current_location'] ?: 'Unknown');
    ensure($buckets, $loc);
    $buckets[$loc][$r['unit_type']][] = [
        'code'       => $r['unit_name'],
        'updated_at' => $r['current_location_updated_at'],
        'status'     => $r['unit_status'],
        // Live Geotab position (null until a device is linked + polled).
        'lat'        => is_numeric($r['last_lat']) ? (float)$r['last_lat'] : null,
        'lng'        => is_numeric($r['last_lng']) ? (float)$r['last_lng'] : null,
        'position_at'=> $r['last_position_at'],
    ];
}

$res = $conn->query("SELECT trailer_name, current_base, current_base_updated_at, trailer_status FROM trailer ORDER BY trailer_name ASC");
while ($r = $res->fetch()) {
    $loc = trim($r['current_base'] ?: 'Unknown');
    ensure($buckets, $loc);
    $buckets[$loc]['trailer'][] = [
        'code'       => $r['trailer_name'],
        'updated_at' => $r['current_base_updated_at'],
        'status'     => $r['trailer_status'],
    ];
}

// Sort buckets so "PTSI Base" / "Consol Base" appear first, then In Transit, then others.
$priority = ['PTSI Base' => 0, 'Consol Base' => 1, 'In Transit' => 2];
uksort($buckets, function ($a, $b) use ($priority) {
    return ($priority[$a] ?? 99) - ($priority[$b] ?? 99) ?: strcmp($a, $b);
});

echo json_encode(['status' => 'success', 'buckets' => $buckets]);
