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

$kind            = strtolower(trim($_POST['unit_kind'] ?? ''));
$code            = strtoupper(trim($_POST['unit_code'] ?? ''));
$category        = trim($_POST['category']        ?? 'other');
$severity        = trim($_POST['severity']        ?? 'med');
$reason          = trim($_POST['reason']          ?? '');
$expectedReturn  = trim($_POST['expected_return'] ?? '');
$scheduledStart  = trim($_POST['scheduled_start'] ?? '');
$actor           = (int)($_SESSION['user_id'] ?? 0);

if (!in_array($kind, ['truck', 'genset', 'trailer'], true) || $code === '') {
    echo json_encode(['status' => 'error', 'message' => 'unit_kind (truck|genset|trailer) and unit_code required']); exit;
}
if ($reason === '') {
    echo json_encode(['status' => 'error', 'message' => 'Reason is required.']); exit;
}
// expected_return is now a full datetime (datetime-local sends "YYYY-MM-DDTHH:MM").
$expectedReturnSql = null;
if ($expectedReturn !== '') {
    $expectedReturnSql = str_replace('T', ' ', $expectedReturn);
    if (strlen($expectedReturnSql) === 16) {   // no seconds → append :00
        $expectedReturnSql .= ':00';
    }
}
$scheduledStartSql = null;
if ($scheduledStart !== '') {
    $scheduledStartSql = str_replace('T', ' ', $scheduledStart);
    if (strlen($scheduledStartSql) === 16) {
        $scheduledStartSql .= ':00';
    }
}

// Self-heal: make sure the "expected return" columns are TIMESTAMP (they were
// DATE originally). Only alters when still DATE, so it's a cheap no-op after
// the first run / once migration 013 has been applied.
foreach ([['unit_maintenance', 'expected_return'], ['units', 'maintenance_expected_return'], ['trailer', 'maintenance_expected_return']] as [$tbl, $col]) {
    try {
        $chk = $conn->prepare("SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1");
        $chk->execute([$tbl, $col]);
        $dt = $chk->fetchColumn();
        if ($dt !== false && stripos((string)$dt, 'timestamp') === false) {
            $conn->exec("ALTER TABLE \"$tbl\" ALTER COLUMN \"$col\" TYPE TIMESTAMP USING \"$col\"::timestamp");
        }
    } catch (Throwable $e) { /* best-effort */ }
}
if ($category === 'scheduled' && $scheduledStartSql === null) {
    echo json_encode(['status' => 'error', 'message' => 'Scheduled start is required for scheduled maintenance.']); exit;
}

// Optional photo upload.
$photoPath = '';
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $dir = __DIR__ . '/../assets/uploads/maintenance';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','heic'], true)) $ext = 'jpg';
    $name = 'um_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $name)) {
        $photoPath = 'php/assets/uploads/maintenance/' . $name;
    }
}

// Confirm the unit exists.
if ($kind === 'trailer') {
    $stmt = $conn->prepare("SELECT trailer_name AS code, maintenance_blocked AS blocked FROM trailer WHERE trailer_name = ? LIMIT 1");
    $stmt->execute([$code]);
} else {
    $stmt = $conn->prepare("SELECT unit_name AS code, maintenance_blocked AS blocked FROM units WHERE unit_name = ? AND unit_type = ? LIMIT 1");
    $stmt->execute([$code, $kind]);
}
$found = $stmt->fetch();
if (!$found)               { echo json_encode(['status' => 'error', 'message' => "$kind $code not found."]);   exit; }
if ((int)$found['blocked'] === 1) {
    echo json_encode(['status' => 'error', 'message' => "$code is already blocked."]); exit;
}

$stmt = $conn->prepare(
    "SELECT um_id
     FROM unit_maintenance
     WHERE unit_kind = ? AND unit_code = ? AND status IN ('active', 'scheduled')
     ORDER BY um_id DESC
     LIMIT 1"
);
$stmt->execute([$kind, $code]);
$existingOpen = $stmt->fetch();
if ($existingOpen) {
    echo json_encode(['status' => 'error', 'message' => "$code already has an open maintenance block or schedule."]); exit;
}

$startsImmediately = !($category === 'scheduled' && $scheduledStartSql !== null && strtotime($scheduledStartSql) > time());
$ticketStatus = $startsImmediately ? 'active' : 'scheduled';

$conn->beginTransaction();
try {
    // Insert the history row.
    $stmt = $conn->prepare(
        "INSERT INTO unit_maintenance (unit_kind, unit_code, category, severity, reason, photo_path, expected_return, scheduled_start_at, blocked_by, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$kind, $code, $category, $severity, $reason, $photoPath, $expectedReturnSql, $scheduledStartSql, $actor, $ticketStatus]);
    $umId = $conn->lastInsertId();

    if ($startsImmediately) {
        // Update master row's denormalised mirror.
        if ($kind === 'trailer') {
            $stmt = $conn->prepare(
                "UPDATE trailer SET maintenance_blocked = TRUE, maintenance_reason = ?, maintenance_expected_return = ?,
                                    maintenance_blocked_at = NOW(), trailer_status = 'Under Maintenance'
                 WHERE trailer_name = ?"
            );
            $stmt->execute([$reason, $expectedReturnSql, $code]);
        } else {
            $stmt = $conn->prepare(
                "UPDATE units SET maintenance_blocked = TRUE, maintenance_reason = ?, maintenance_expected_return = ?,
                                  maintenance_blocked_at = NOW(), unit_status = 'Under Maintenance'
                 WHERE unit_name = ? AND unit_type = ?"
            );
            $stmt->execute([$reason, $expectedReturnSql, $code, $kind]);
        }
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Block failed: ' . $e->getMessage()]); exit;
}

echo json_encode([
    'status' => 'success',
    'message' => $startsImmediately
        ? "$code blocked from dispatch."
        : "$code scheduled for maintenance blocking.",
    'um_id' => $umId
]);
