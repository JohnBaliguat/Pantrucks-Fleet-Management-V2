<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
$id   = (int)($_SESSION['user_id'] ?? 0);
if (!in_array($role, ['Driver', 'Dispatcher', 'Admin'], true) || $id <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required']); exit;
}

$dId = (int)($_GET['d_id'] ?? 0);
if ($dId <= 0) { echo json_encode(['status' => 'error', 'message' => 'd_id required']); exit; }

// Drivers can only see receipts for their own dispatches.
if ($role === 'Driver') {
    $stmt = $conn->prepare("SELECT driver_id FROM dispatch WHERE d_id = ? LIMIT 1");
    $stmt->execute([$dId]);
    $r = $stmt->fetch();
if (!$r || (int)$r['driver_id'] !== $id) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Not your dispatch']); exit;
    }
}

$sql = "SELECT r.dr_id, r.d_id, r.receipt_type, r.title, r.file_path, r.mime_type,
               r.requires_ack, r.attached_at,
               (SELECT a.acknowledged_at FROM receipt_acknowledge a WHERE a.dr_id = r.dr_id AND a.driver_id = ? LIMIT 1) AS acknowledged_at
        FROM dispatch_receipt r
        WHERE r.d_id = ? ORDER BY r.dr_id ASC";
$stmt = $conn->prepare($sql);
$ackDriver = ($role === 'Driver') ? $id : 0;
$stmt->execute([$ackDriver, $dId]);
$res = $stmt;
$rows = [];
while ($r = $res->fetch()) { $rows[] = $r; }
echo json_encode(['status' => 'success', 'rows' => $rows]);
