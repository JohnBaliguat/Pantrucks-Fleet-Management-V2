<?php
include __DIR__ . "/../../config/config.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$segment    = trim((string)($_POST['segment']    ?? ''));
$activity   = trim((string)($_POST['activity']   ?? ''));
$tripKey    = trim((string)($_POST['trip_key']   ?? ''));
$baseRate   = (float)($_POST['base_rate']        ?? 0);
$additional = (float)($_POST['additional']       ?? 0);

if ($activity === '') {
    echo json_encode(['status' => 'error', 'message' => 'Activity is required.']); exit;
}

try {
    $stmt = $conn->prepare(
        "INSERT INTO trip_rates (segment, activity, trip_key, base_rate, additional)
         VALUES (?, ?, ?, ?, ?)
         RETURNING id"
    );
    $stmt->execute([$segment, $activity, $tripKey, $baseRate, $additional]);
    $id = $stmt->fetchColumn();
    echo json_encode(['status' => 'success', 'message' => 'Trip rate saved.', 'id' => (int)$id]);
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        echo json_encode(['status' => 'error', 'message' => 'A rate for this segment+activity already exists. Edit it instead.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
