<?php
// Read-only feed for admin/geotab-behavior.php.
// Open behavior events (not dismissed, not yet converted), newest first,
// with the attributed driver's name.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'HR-Admin', 'User'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

try {
    $rows = $conn->query(
        "SELECT e.event_id, e.unit_name, e.driver_id, e.rule_name, e.occurred_at,
                e.duration_s, e.distance_km,
                TRIM(CONCAT(d.driver_fname, ' ', d.driver_lname)) AS driver_name
           FROM geotab_driver_event e
           LEFT JOIN drivers d ON d.driver_id = e.driver_id
          WHERE e.dismissed = FALSE AND e.violation_id IS NULL
          ORDER BY e.occurred_at DESC NULLS LAST, e.created_at DESC
          LIMIT 500"
    )->fetchAll();

    $out = array_map(function ($r) {
        return [
            'event_id'    => $r['event_id'],
            'unit_name'   => $r['unit_name'],
            'driver_id'   => $r['driver_id'] !== null ? (int)$r['driver_id'] : null,
            'driver_name' => trim((string)$r['driver_name']) !== '' ? $r['driver_name'] : null,
            'rule_name'   => $r['rule_name'] !== '' ? $r['rule_name'] : 'Exception',
            'occurred_at' => $r['occurred_at'],
            'duration_s'  => $r['duration_s'] !== null ? (int)$r['duration_s'] : null,
            'distance_km' => $r['distance_km'] !== null ? (float)$r['distance_km'] : null,
        ];
    }, $rows);

    echo json_encode(['status' => 'success', 'events' => $out, 'count' => count($out)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
