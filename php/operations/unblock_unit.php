<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Maintenance', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$kind          = strtolower(trim($_POST['unit_kind'] ?? ''));
$code          = strtoupper(trim($_POST['unit_code'] ?? ''));
$costLabor     = (float)($_POST['cost_labor'] ?? 0);
$costParts     = (float)($_POST['cost_parts'] ?? 0);
$releaseNotes  = trim($_POST['release_notes'] ?? '');
$actor         = (int)($_SESSION['user_id'] ?? 0);

if (!in_array($kind, ['truck', 'genset', 'trailer'], true) || $code === '') {
    echo json_encode(['status' => 'error', 'message' => 'unit_kind + unit_code required']); exit;
}

$conn->beginTransaction();
try {
    // Close the open unit_maintenance row.
    $stmt = $conn->prepare(
        "UPDATE unit_maintenance
         SET status = 'released', released_by = ?, released_at = NOW(),
             release_notes = ?, cost_labor = ?, cost_parts = ?
         WHERE um_id = (
             SELECT um_id FROM unit_maintenance
             WHERE unit_kind = ? AND unit_code = ? AND status IN ('active', 'scheduled')
             ORDER BY um_id DESC LIMIT 1
         )"
    );
    $stmt->execute([$actor, $releaseNotes, $costLabor, $costParts, $kind, $code]);
    $affected = $stmt->rowCount();
    if ($affected === 0) {
        throw new Exception("No active block found for $kind $code.");
    }

    // Reset the master row.
    if ($kind === 'trailer') {
        $stmt = $conn->prepare(
            "UPDATE trailer SET maintenance_blocked = FALSE, maintenance_reason = '', maintenance_expected_return = NULL,
                                maintenance_blocked_at = NULL, trailer_status = 'Good'
             WHERE trailer_name = ?"
        );
        $stmt->execute([$code]);
    } else {
        $stmt = $conn->prepare(
            "UPDATE units SET maintenance_blocked = FALSE, maintenance_reason = '', maintenance_expected_return = NULL,
                              maintenance_blocked_at = NULL, unit_status = 'Good'
             WHERE unit_name = ? AND unit_type = ?"
        );
        $stmt->execute([$code, $kind]);
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Release failed: ' . $e->getMessage()]); exit;
}

echo json_encode(['status' => 'success', 'message' => "$code released back to dispatch."]);
