<?php
// Bulk update zone classification/active flags in one save. Admin-only, POST.
//
//   POST zones = JSON array of { zone_id, kind, active }
//        kind one of: base | gate | customer | other ; active 1|0

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

$raw = $_POST['zones'] ?? '';
$zones = is_array($raw) ? $raw : json_decode((string)$raw, true);
if (!is_array($zones) || !$zones) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No changes to save']);
    exit;
}

$valid = ['base', 'gate', 'customer', 'other'];

try {
    $upd = $conn->prepare("UPDATE geotab_zone SET kind = ?, active = ? WHERE zone_id = ?");

    $saved = 0;
    $skipped = [];

    $conn->beginTransaction();
    foreach ($zones as $z) {
        $zoneId = trim((string)($z['zone_id'] ?? ''));
        $kind   = strtolower(trim((string)($z['kind'] ?? 'other')));
        $active = !empty($z['active']) && $z['active'] !== '0' && $z['active'] !== false;

        if ($zoneId === '') { continue; }
        if (!in_array($kind, $valid, true)) {
            $skipped[] = "$zoneId: invalid kind";
            continue;
        }
        $upd->execute([$kind, $active, $zoneId]);
        if ($upd->rowCount() > 0) {
            $saved++;
        } else {
            // rowCount 0 may just mean the values were unchanged — not an error.
            $saved++;
        }
    }
    $conn->commit();

    pt_log_activity($conn, (int)($_SESSION['user_id'] ?? 0), 'Admin',
        (string)($_SESSION['user_name'] ?? 'Admin'),
        'Bulk Geotab zone update', "updated=$saved skipped=" . count($skipped));

    echo json_encode(['status' => 'success', 'saved' => $saved, 'skipped' => $skipped]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
