<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$dId          = (int)($_POST['d_id'] ?? 0);
$type         = trim($_POST['receipt_type'] ?? 'other');
$title        = trim($_POST['title'] ?? '');
$requiresAck  = !empty($_POST['requires_ack']) ? 1 : 0;
$attachedBy   = (int)($_SESSION['user_id'] ?? 0);

if ($dId <= 0) { echo json_encode(['status' => 'error', 'message' => 'd_id required']); exit; }
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'File required']); exit;
}

$validTypes = ['manifest', 'gate_pass', 'customs', 'delivery_note', 'other'];
if (!in_array($type, $validTypes, true)) $type = 'other';

$dir = __DIR__ . '/../assets/uploads/receipts';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','webp','heic','pdf'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Allowed: jpg/png/webp/heic/pdf']); exit;
}
$mime = $_FILES['file']['type'] ?: '';
$name = 'rcpt_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
$abs  = $dir . '/' . $name;
if (!move_uploaded_file($_FILES['file']['tmp_name'], $abs)) {
    echo json_encode(['status' => 'error', 'message' => 'Move failed']); exit;
}
$relPath = 'php/assets/uploads/receipts/' . $name;

$stmt = $conn->prepare(
    "INSERT INTO dispatch_receipt (d_id, receipt_type, title, file_path, mime_type, requires_ack, attached_by)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     RETURNING dr_id"
);
$stmt->execute([$dId, $type, $title, $relPath, $mime, $requiresAck, $attachedBy]);
$drId = (int)$stmt->fetchColumn();
echo json_encode(['status' => 'success', 'dr_id' => $drId, 'file_path' => $relPath, 'message' => 'Receipt attached.']);
