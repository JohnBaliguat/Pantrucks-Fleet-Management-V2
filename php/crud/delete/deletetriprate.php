<?php
include __DIR__ . "/../../config/config.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing id.']); exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM trip_rates WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Trip rate deleted.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not found.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
