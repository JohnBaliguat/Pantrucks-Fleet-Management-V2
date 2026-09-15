<?php
// Read-only feed for the device-mapping page (admin/geotab-devices.php).
//
// Returns every Geotab device alongside the unit it's linked to (if any) and
// a suggested unit by plate match — units in this system have no VIN yet, so
// plate is the best hint for the one-time manual pairing. Also returns the
// unit list for the link dropdown.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/geotab_client.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}

// Normalise a plate to bare alphanumerics for fuzzy comparison
// ("ABC 123" / "abc-123" → "ABC123").
function pt_geotab_norm_plate(?string $p): string {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$p));
}

try {
    // Units (exclude gensets, same rule the truck page uses).
    $unitRows = $conn->query(
        "SELECT unit_id, unit_name, unit_plate, geotab_device_id, vin
           FROM units
          WHERE unit_name NOT LIKE 'GS%'
          ORDER BY unit_name"
    )->fetchAll();

    $units = [];
    $unitByDevice = [];        // device_id => unit summary (current links)
    $plateIndex = [];          // normalised plate => unit_id
    foreach ($unitRows as $u) {
        $summary = [
            'unit_id'          => (int)$u['unit_id'],
            'unit_name'        => $u['unit_name'],
            'unit_plate'       => $u['unit_plate'],
            'geotab_device_id' => $u['geotab_device_id'],
        ];
        $units[] = $summary;
        if (!empty($u['geotab_device_id'])) {
            $unitByDevice[(string)$u['geotab_device_id']] = $summary;
        }
        $np = pt_geotab_norm_plate($u['unit_plate']);
        if ($np !== '' && !isset($plateIndex[$np])) {
            $plateIndex[$np] = (int)$u['unit_id'];
        }
    }

    // Geotab devices. Feed may include non-vehicle hardware; we surface all and
    // let the admin decide.
    $devices = pt_geotab_get('Device');

    $out = [];
    foreach ($devices as $d) {
        $deviceId = (string)($d['id'] ?? '');
        if ($deviceId === '') {
            continue;
        }
        $plate = $d['licensePlate'] ?? '';
        $np = pt_geotab_norm_plate($plate);
        $suggestedUnitId = ($np !== '' && isset($plateIndex[$np])) ? $plateIndex[$np] : null;

        $out[] = [
            'device_id'    => $deviceId,
            'name'         => $d['name'] ?? '',
            'serial'       => $d['serialNumber'] ?? '',
            'vin'          => $d['vehicleIdentificationNumber'] ?? '',
            'plate'        => $plate,
            'linked_unit'  => $unitByDevice[$deviceId] ?? null,
            'suggested_unit_id' => $suggestedUnitId,
        ];
    }

    echo json_encode([
        'status'  => 'success',
        'devices' => $out,
        'units'   => $units,
        'count'   => count($out),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
