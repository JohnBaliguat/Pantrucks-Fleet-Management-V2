<?php
include __DIR__ . "/../../config/config.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$id         = (int)($_POST['id'] ?? 0);
$segment    = trim((string)($_POST['segment']    ?? ''));
$activity   = trim((string)($_POST['activity']   ?? ''));
$tripKey    = trim((string)($_POST['trip_key']   ?? ''));
$baseRate   = (float)($_POST['base_rate']        ?? 0);
$additional = (float)($_POST['additional']       ?? 0);

if ($id <= 0 || $activity === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing id or activity.']); exit;
}

try {
    $stmt = $conn->prepare(
        "UPDATE trip_rates
         SET segment = ?, activity = ?, trip_key = ?, base_rate = ?, additional = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$segment, $activity, $tripKey, $baseRate, $additional, $id]);
    echo json_encode(['status' => 'success', 'message' => 'Trip rate updated.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        echo json_encode(['status' => 'error', 'message' => 'Another rate row already covers this segment+activity.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
