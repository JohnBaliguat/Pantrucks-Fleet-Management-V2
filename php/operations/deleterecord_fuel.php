<?php
include __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$id = (int)($_POST['f_Id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['valid' => false, 'msg' => 'Invalid id.']);
    exit;
}

if ($conn->query("DELETE FROM fuel_report WHERE f_id = $id")) {
    echo json_encode(['valid' => true, 'msg' => 'Deleted successfully.']);
} else {
    echo json_encode(['valid' => false, 'msg' => 'Delete failed.']);
}
