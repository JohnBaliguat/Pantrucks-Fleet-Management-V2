<?php
// Link (or unlink) a Geotab device to a unit. Admin-only, POST.
//
// On link we re-fetch the device from Geotab so the VIN/plate we store are
// authoritative, and we backfill units.vin — the fleet has no VIN recorded,
// so this is where that data first arrives.
//
//   POST unit_id     (required) target unit
//   POST device_id   Geotab Device.id to link, or '' to unlink the unit

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/geotab_client.php';
require_once __DIR__ . '/../../helpers/activity_log_helper.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$unitId   = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
$deviceId = trim((string)($_POST['device_id'] ?? ''));

if (!$unitId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid unit_id required']);
    exit;
}

try {
    $st = $conn->prepare("SELECT unit_name FROM units WHERE unit_id = ?");
    $st->execute([$unitId]);
    $unitName = $st->fetchColumn();
    if ($unitName === false) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Unit not found']);
        exit;
    }

    $actorId   = (int)($_SESSION['user_id'] ?? 0);
    $actorName = (string)($_SESSION['user_name'] ?? 'Admin');

    // ---- Unlink ----
    if ($deviceId === '') {
        $conn->prepare(
            "UPDATE units
                SET geotab_device_id = NULL, is_communicating = FALSE
              WHERE unit_id = ?"
        )->execute([$unitId]);
        pt_log_activity($conn, $actorId, 'Admin', $actorName,
            'Unlinked Geotab device', "unit=$unitName");
        echo json_encode(['status' => 'success', 'linked' => false]);
        exit;
    }

    // ---- Link ----
    // Pull the device from Geotab to capture VIN/plate authoritatively.
    $devs = pt_geotab_get('Device', ['id' => $deviceId], 1);
    $dev  = $devs[0] ?? null;
    if (!$dev || (string)($dev['id'] ?? '') !== $deviceId) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Geotab device not found']);
        exit;
    }
    $vin = $dev['vehicleIdentificationNumber'] ?? null;
    $vin = ($vin === '' ? null : $vin);

    // Guard: is this device already linked to a DIFFERENT unit?
    $chk = $conn->prepare(
        "SELECT unit_name FROM units WHERE geotab_device_id = ? AND unit_id <> ?"
    );
    $chk->execute([$deviceId, $unitId]);
    $other = $chk->fetchColumn();
    if ($other !== false) {
        http_response_code(409);
        echo json_encode([
            'status'  => 'error',
            'message' => "Device already linked to unit \"$other\". Unlink it there first.",
        ]);
        exit;
    }

    $conn->prepare(
        "UPDATE units
            SET geotab_device_id = ?, vin = COALESCE(?, vin)
          WHERE unit_id = ?"
    )->execute([$deviceId, $vin, $unitId]);

    pt_log_activity($conn, $actorId, 'Admin', $actorName,
        'Linked Geotab device', "unit=$unitName device=$deviceId vin=" . ($vin ?? '—'));

    echo json_encode([
        'status' => 'success',
        'linked' => true,
        'vin'    => $vin,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
