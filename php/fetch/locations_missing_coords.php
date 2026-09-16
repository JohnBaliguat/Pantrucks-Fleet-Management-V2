<?php
// Trip origins/destinations that can't be mapped because the location has no
// valid coordinates (blank lat/lng, or no `location` row at all). Powers the
// admin Location Coordinates cleanup page. Ordered most-used first.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}

try {
    // Distinct from/to used by trips and bookings (grouped by NAME only, so
    // duplicate location rows don't split a name). A name counts as mapped if
    // ANY location row for it has numeric coordinates.
    $numeric = "l.latitude ~ '^-?[0-9.]+$' AND l.longitude ~ '^-?[0-9.]+$'";
    $sql = "
        SELECT n.name, n.uses,
               EXISTS(SELECT 1 FROM location l WHERE l.location_name = n.name) AS has_row,
               EXISTS(SELECT 1 FROM location l WHERE l.location_name = n.name AND $numeric) AS has_coords
          FROM (
                SELECT name, SUM(uses) AS uses FROM (
                    SELECT trip_from AS name, COUNT(*) uses FROM trips   WHERE COALESCE(trip_from,'') <> '' GROUP BY trip_from
                    UNION ALL
                    SELECT trip_to   AS name, COUNT(*) uses FROM trips   WHERE COALESCE(trip_to,'')   <> '' GROUP BY trip_to
                    UNION ALL
                    SELECT trip_from AS name, COUNT(*) uses FROM booking WHERE COALESCE(trip_from,'') <> '' GROUP BY trip_from
                    UNION ALL
                    SELECT trip_to   AS name, COUNT(*) uses FROM booking WHERE COALESCE(trip_to,'')   <> '' GROUP BY trip_to
                ) x GROUP BY name
          ) n
    ";
    $rows = $conn->query($sql)->fetchAll();

    $isTrue = fn($v) => ($v === true || $v === 't' || $v === 1 || $v === '1');
    $missing = [];
    $okCount = 0;
    foreach ($rows as $r) {
        if ($isTrue($r['has_coords'])) { $okCount++; continue; }
        $missing[] = [
            'name'    => $r['name'],
            'uses'    => (int)$r['uses'],
            'has_row' => $isTrue($r['has_row']),
        ];
    }
    // Most-used first so cleanup effort goes where it matters.
    usort($missing, fn($a, $b) => $b['uses'] <=> $a['uses']);

    echo json_encode([
        'status'    => 'success',
        'missing'   => $missing,
        'missing_count' => count($missing),
        'ok_count'  => $okCount,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
