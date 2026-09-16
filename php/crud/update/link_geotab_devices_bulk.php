<?php
// Bulk link/unlink Geotab devices to units in one save. Admin-only, POST.
//
//   POST links = JSON array of { unit_id, device_id }
//        device_id '' (or missing) means unlink that unit.
//
// Devices are fetched from Geotab once (not per row) to capture VINs. Unlinks
// are applied first so a device can be MOVED from one unit to another in the
// same batch. Conflicts (a device still linked elsewhere) are skipped and
// reported rather than failing the whole save.

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

$raw = $_POST['links'] ?? '';
$links = is_array($raw) ? $raw : json_decode((string)$raw, true);
if (!is_array($links) || !$links) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No changes to save']);
    exit;
}

// Normalise + de-dupe by unit_id (last wins).
$byUnit = [];
foreach ($links as $l) {
    $uid = isset($l['unit_id']) ? (int)$l['unit_id'] : 0;
    if ($uid <= 0) { continue; }
    $byUnit[$uid] = trim((string)($l['device_id'] ?? ''));
}
if (!$byUnit) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No valid rows']);
    exit;
}

try {
    // One Geotab call → device_id => VIN.
    $vinByDevice = [];
    foreach (pt_geotab_get('Device') as $d) {
        $id = (string)($d['id'] ?? '');
        if ($id !== '') {
            $vin = $d['vehicleIdentificationNumber'] ?? '';
            $vinByDevice[$id] = ($vin === '' ? null : $vin);
        }
    }

    // Guard against the same device chosen for two units in this batch.
    $chosenCounts = [];
    foreach ($byUnit as $dev) { if ($dev !== '') { $chosenCounts[$dev] = ($chosenCounts[$dev] ?? 0) + 1; } }

    $saved = 0;
    $unlinked = 0;
    $skipped = [];   // human-readable messages

    $conn->beginTransaction();

    $doUnlink = $conn->prepare(
        "UPDATE units SET geotab_device_id = NULL, is_communicating = FALSE WHERE unit_id = ?"
    );
    $doLink = $conn->prepare(
        "UPDATE units SET geotab_device_id = ?, vin = COALESCE(?, vin) WHERE unit_id = ?"
    );
    $nameOf = $conn->prepare("SELECT unit_name FROM units WHERE unit_id = ?");
    $linkedElsewhere = $conn->prepare(
        "SELECT unit_name FROM units WHERE geotab_device_id = ? AND unit_id <> ?"
    );

    // Pass 1 — unlinks (frees devices being moved off a unit).
    foreach ($byUnit as $uid => $dev) {
        if ($dev === '') {
            $doUnlink->execute([$uid]);
            $unlinked += $doUnlink->rowCount();
        }
    }

    // Pass 2 — links.
    foreach ($byUnit as $uid => $dev) {
        if ($dev === '') { continue; }

        $nameOf->execute([$uid]);
        $unitName = $nameOf->fetchColumn();
        if ($unitName === false) { $skipped[] = "Unit #$uid not found"; continue; }

        if (!array_key_exists($dev, $vinByDevice)) {
            $skipped[] = "$unitName: device not found in Geotab";
            continue;
        }
        if (($chosenCounts[$dev] ?? 0) > 1) {
            $skipped[] = "$unitName: device $dev picked for more than one unit";
            continue;
        }
        $linkedElsewhere->execute([$dev, $uid]);
        $other = $linkedElsewhere->fetchColumn();
        if ($other !== false) {
            $skipped[] = "$unitName: device already linked to \"$other\"";
            continue;
        }

        $doLink->execute([$dev, $vinByDevice[$dev], $uid]);
        $saved++;
    }

    $conn->commit();

    pt_log_activity($conn, (int)($_SESSION['user_id'] ?? 0), 'Admin',
        (string)($_SESSION['user_name'] ?? 'Admin'),
        'Bulk Geotab device linking', "linked=$saved unlinked=$unlinked skipped=" . count($skipped));

    echo json_encode([
        'status'   => 'success',
        'saved'    => $saved,
        'unlinked' => $unlinked,
        'skipped'  => $skipped,
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
