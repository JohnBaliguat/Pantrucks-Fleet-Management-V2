<?php
// Set a zone's classification (kind) and active flag. Admin-only, POST.
//
//   POST zone_id  (required)
//   POST kind     one of: base | gate | customer | other
//   POST active   '1' | '0'

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

$zoneId = trim((string)($_POST['zone_id'] ?? ''));
$kind   = strtolower(trim((string)($_POST['kind'] ?? 'other')));
$active = !empty($_POST['active']) && $_POST['active'] !== '0';

if ($zoneId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'zone_id required']);
    exit;
}
if (!in_array($kind, ['base', 'gate', 'customer', 'other'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid kind']);
    exit;
}

try {
    $st = $conn->prepare(
        "UPDATE geotab_zone SET kind = ?, active = ? WHERE zone_id = ?"
    );
    $st->execute([$kind, $active, $zoneId]);
    if ($st->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        exit;
    }

    pt_log_activity(
        $conn, (int)($_SESSION['user_id'] ?? 0), 'Admin',
        (string)($_SESSION['user_name'] ?? 'Admin'),
        'Updated Geotab zone', "zone=$zoneId kind=$kind active=" . ($active ? '1' : '0')
    );

    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
