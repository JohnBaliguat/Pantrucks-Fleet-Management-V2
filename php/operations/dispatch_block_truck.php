<?php
// Dispatch-Admin truck block.
// Marks a truck as dispatch-blocked so it can no longer be assigned to a
// booking. Independent of the Maintenance block (does not touch unit_status
// or the unit_maintenance tickets). Reversible via dispatch_unblock_truck.php.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$code   = strtoupper(trim($_POST['unit_name'] ?? ''));
$reason = trim($_POST['reason'] ?? '');
$actor  = (int)($_SESSION['user_id'] ?? 0);

if ($code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Truck is required.']);
    exit;
}
if ($reason === '') {
    echo json_encode(['status' => 'error', 'message' => 'Reason is required.']);
    exit;
}

// Defensive: make sure the columns exist (idempotent, cheap after first run).
pt_ensure_column($conn, 'units', 'dispatch_blocked',      "BOOLEAN NOT NULL DEFAULT FALSE");
pt_ensure_column($conn, 'units', 'dispatch_block_reason', "VARCHAR(255) NOT NULL DEFAULT ''");
pt_ensure_column($conn, 'units', 'dispatch_blocked_by',   "INTEGER NOT NULL DEFAULT 0");
pt_ensure_column($conn, 'units', 'dispatch_blocked_at',   "TIMESTAMP NULL");

// A truck is any unit that isn't a genset (GS%).
$stmt = $conn->prepare(
    "SELECT unit_name, dispatch_blocked FROM units
     WHERE unit_name = ? AND unit_name NOT LIKE 'GS%' LIMIT 1"
);
$stmt->execute([$code]);
$unit = $stmt->fetch();
if (!$unit) {
    echo json_encode(['status' => 'error', 'message' => "Truck '$code' not found."]);
    exit;
}
if ((int)$unit['dispatch_blocked'] === 1) {
    echo json_encode(['status' => 'error', 'message' => "$code is already blocked."]);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE units
        SET dispatch_blocked = TRUE,
            dispatch_block_reason = ?,
            dispatch_blocked_by = ?,
            dispatch_blocked_at = NOW()
      WHERE unit_name = ? AND unit_name NOT LIKE 'GS%'"
);
$stmt->execute([$reason, $actor, $code]);

echo json_encode([
    'status'  => 'success',
    'message' => "$code blocked — it can no longer be assigned until unblocked.",
]);
