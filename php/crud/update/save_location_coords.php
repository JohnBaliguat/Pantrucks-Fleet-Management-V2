<?php
// Set coordinates for a location by name (upsert into `location`). Admin-only.
// Handles one row (name/lat/lng) or a bulk batch (rows = JSON array).
//
//   POST name, lat, lng            (single)
//   POST rows = [{name,lat,lng}..] (bulk)

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
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

// Normalise input to a list of {name, lat, lng}.
$items = [];
if (isset($_POST['rows'])) {
    $decoded = is_array($_POST['rows']) ? $_POST['rows'] : json_decode((string)$_POST['rows'], true);
    if (is_array($decoded)) { $items = $decoded; }
} else {
    $items = [['name' => $_POST['name'] ?? '', 'lat' => $_POST['lat'] ?? '', 'lng' => $_POST['lng'] ?? '']];
}
if (!$items) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nothing to save']);
    exit;
}

try {
    $find   = $conn->prepare("SELECT location_id FROM location WHERE location_name = ? LIMIT 1");
    $update = $conn->prepare("UPDATE location SET latitude = ?, longitude = ? WHERE location_name = ?");
    $insert = $conn->prepare("INSERT INTO location (location_name, latitude, longitude) VALUES (?, ?, ?)");

    $saved = 0;
    $skipped = [];

    $conn->beginTransaction();
    foreach ($items as $it) {
        $name = trim((string)($it['name'] ?? ''));
        $lat  = trim((string)($it['lat'] ?? ''));
        $lng  = trim((string)($it['lng'] ?? ''));
        if ($name === '') { continue; }
        if (!is_numeric($lat) || !is_numeric($lng) ||
            $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $skipped[] = "$name: invalid coordinates";
            continue;
        }
        $find->execute([$name]);
        if ($find->fetchColumn() !== false) {
            $update->execute([$lat, $lng, $name]);
        } else {
            $insert->execute([$name, $lat, $lng]);
        }
        $saved++;
    }
    $conn->commit();

    pt_log_activity($conn, (int)($_SESSION['user_id'] ?? 0), 'Admin',
        (string)($_SESSION['user_name'] ?? 'Admin'),
        'Set location coordinates', "saved=$saved skipped=" . count($skipped));

    echo json_encode(['status' => 'success', 'saved' => $saved, 'skipped' => $skipped]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
