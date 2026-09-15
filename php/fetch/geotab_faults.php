<?php
// Read-only feed for the Vehicle Health page (admin/geotab-faults.php).
// Returns active, non-dismissed faults (newest first) plus each linked unit's
// latest odometer / engine hours.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}

try {
    $faults = $conn->query(
        "SELECT fault_id, unit_id, unit_name, code, description, fault_state,
                occurrences, occurred_at
           FROM geotab_fault
          WHERE active = TRUE AND dismissed = FALSE
          ORDER BY occurred_at DESC NULLS LAST, updated_at DESC
          LIMIT 500"
    )->fetchAll();

    $diagnostics = $conn->query(
        "SELECT unit_id, unit_name, odometer_km, engine_hours, diagnostics_at,
                is_communicating
           FROM units
          WHERE geotab_device_id IS NOT NULL
          ORDER BY unit_name"
    )->fetchAll();

    echo json_encode([
        'status'      => 'success',
        'faults'      => array_map(function ($f) {
            return [
                'fault_id'    => $f['fault_id'],
                'unit_id'     => (int)$f['unit_id'],
                'unit_name'   => $f['unit_name'],
                'code'        => $f['code'],
                'description' => $f['description'],
                'fault_state' => $f['fault_state'],
                'occurrences' => (int)$f['occurrences'],
                'occurred_at' => $f['occurred_at'],
            ];
        }, $faults),
        'diagnostics' => array_map(function ($d) {
            return [
                'unit_id'       => (int)$d['unit_id'],
                'unit_name'     => $d['unit_name'],
                'odometer_km'   => $d['odometer_km'] !== null ? (float)$d['odometer_km'] : null,
                'engine_hours'  => $d['engine_hours'] !== null ? (float)$d['engine_hours'] : null,
                'diagnostics_at'=> $d['diagnostics_at'],
                'communicating' => (bool)$d['is_communicating'],
            ];
        }, $diagnostics),
        'fault_count' => count($faults),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
