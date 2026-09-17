<?php
// Dispatch-Admin truck unblock — reverses dispatch_block_truck.php.
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

$code = strtoupper(trim($_POST['unit_name'] ?? ''));
if ($code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Truck is required.']);
    exit;
}

pt_ensure_column($conn, 'units', 'dispatch_blocked',      "BOOLEAN NOT NULL DEFAULT FALSE");
pt_ensure_column($conn, 'units', 'dispatch_block_reason', "VARCHAR(255) NOT NULL DEFAULT ''");
pt_ensure_column($conn, 'units', 'dispatch_blocked_by',   "INTEGER NOT NULL DEFAULT 0");
pt_ensure_column($conn, 'units', 'dispatch_blocked_at',   "TIMESTAMP NULL");

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
if ((int)$unit['dispatch_blocked'] === 0) {
    echo json_encode(['status' => 'error', 'message' => "$code is not blocked."]);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE units
        SET dispatch_blocked = FALSE,
            dispatch_block_reason = '',
            dispatch_blocked_by = 0,
            dispatch_blocked_at = NULL
      WHERE unit_name = ? AND unit_name NOT LIKE 'GS%'"
);
$stmt->execute([$code]);

echo json_encode([
    'status'  => 'success',
    'message' => "$code unblocked — it can be assigned again.",
]);
