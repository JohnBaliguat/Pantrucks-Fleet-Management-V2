<?php
// Read-only feed for the zones admin page (admin/geotab-zones.php).
// Lists synced Geotab zones with our classification and how many trucks are
// currently inside each.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}

try {
    $rows = $conn->query(
        "SELECT z.zone_id, z.name, z.kind, z.active, z.synced_at,
                jsonb_array_length(z.points) AS vertices,
                (SELECT COUNT(*) FROM units u WHERE u.current_zone_id = z.zone_id) AS units_inside
           FROM geotab_zone z
          ORDER BY z.name"
    )->fetchAll();

    $zones = array_map(function ($r) {
        return [
            'zone_id'      => $r['zone_id'],
            'name'         => $r['name'],
            'kind'         => $r['kind'],
            'active'       => (bool)$r['active'],
            'vertices'     => (int)$r['vertices'],
            'units_inside' => (int)$r['units_inside'],
            'synced_at'    => $r['synced_at'],
        ];
    }, $rows);

    echo json_encode(['status' => 'success', 'zones' => $zones, 'count' => count($zones)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
