<?php
// Pre-assignment check: which of the chosen truck / trailer / genset are
// currently in use by ANOTHER driver. Used by the assign modal to warn the
// dispatcher before committing when "Dispatch in-use equipment" is enabled.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$driverId = (int)($_REQUEST['driver_id'] ?? 0);
$truck    = trim($_REQUEST['truck'] ?? '');
$trailer  = trim($_REQUEST['trailer'] ?? '');
$genset   = trim($_REQUEST['genset'] ?? '');

$inUse = [];

// Truck / genset live in `units` (unit_assign holds the driver name).
$checkUnit = function (string $name, string $type) use ($conn, $driverId, &$inUse) {
    if ($name === '') return;
    $stmt = $conn->prepare("SELECT unit_assign, driver_id, unit_status FROM units WHERE unit_name = ? LIMIT 1");
    $stmt->execute([$name]);
    $r = $stmt->fetch();
    if (!$r) return;
    $holderId = (int)($r['driver_id'] ?? 0);
    if ($holderId !== 0 && $holderId !== $driverId) {
        $inUse[] = ['type' => $type, 'name' => $name, 'holder' => trim((string)($r['unit_assign'] ?? '')) ?: 'another driver'];
    }
};
$checkUnit($truck, 'Truck');
$checkUnit($genset, 'Genset');

// Trailer lives in `trailer` (trailer_assignto holds the driver name).
if ($trailer !== '') {
    $stmt = $conn->prepare("SELECT trailer_assignto, driver_id FROM trailer WHERE trailer_name = ? LIMIT 1");
    $stmt->execute([$trailer]);
    $r = $stmt->fetch();
    if ($r) {
        $holderId = (int)($r['driver_id'] ?? 0);
        if ($holderId !== 0 && $holderId !== $driverId) {
            $inUse[] = ['type' => 'Trailer', 'name' => $trailer, 'holder' => trim((string)($r['trailer_assignto'] ?? '')) ?: 'another driver'];
        }
    }
}

echo json_encode(['status' => 'success', 'inuse' => $inUse]);
