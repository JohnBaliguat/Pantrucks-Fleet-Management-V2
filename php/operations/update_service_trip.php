<?php
// Update a service trip's details. Service trips = dispatch rows with
// costumer = 'INTERNAL'. For Admin / Dispatch Admin / Dispatcher.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$dId       = (int)($_POST['d_id'] ?? 0);
$tripFrom  = trim($_POST['trip_from'] ?? '');
$tripTo    = trim($_POST['trip_to'] ?? '');
$reason    = trim($_POST['service_reason'] ?? '');
$remarks   = trim($_POST['service_remarks'] ?? '');
$container = strtoupper(trim($_POST['container'] ?? ''));
$trailer   = trim($_POST['trailer'] ?? '');
$genset    = trim($_POST['genset'] ?? '');

if ($dId <= 0)      { echo json_encode(['status' => 'error', 'message' => 'd_id required']); exit; }
if ($tripFrom === ''){ echo json_encode(['status' => 'error', 'message' => 'Trip From is required.']); exit; }
if ($tripTo === '') { echo json_encode(['status' => 'error', 'message' => 'Trip To is required.']); exit; }
if ($reason === '') { echo json_encode(['status' => 'error', 'message' => 'Service Reason is required.']); exit; }

$conn->beginTransaction();
try {
    $stmt = $conn->prepare(
        "SELECT d.d_id, t.trip_id, t.trip_status
         FROM dispatch d
         JOIN trips t ON t.d_id = d.d_id AND t.trip_purpose = 'Service'
         WHERE d.d_id = ? AND d.costumer = 'INTERNAL'
         ORDER BY t.trip_id DESC
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$dId]);
    $row = $stmt->fetch();
    if (!$row) { throw new Exception('Service trip not found.'); }
    if (strcasecmp((string)$row['trip_status'], 'Done') === 0) {
        throw new Exception('A completed service trip can no longer be edited.');
    }
    $tripId = (int)$row['trip_id'];

    if ($trailer !== '') {
        $c = $conn->prepare("SELECT 1 FROM trailer WHERE trailer_name = ? LIMIT 1");
        $c->execute([$trailer]);
        if (!$c->fetch()) throw new Exception('Trailer not found.');
    }
    if ($genset !== '') {
        $c = $conn->prepare("SELECT 1 FROM units WHERE unit_name = ? AND unit_type = 'genset' LIMIT 1");
        $c->execute([$genset]);
        if (!$c->fetch()) throw new Exception('Genset not found.');
    }

    $conn->prepare(
        "UPDATE trips
         SET trip_from = ?, trip_to = ?, service_reason = ?, service_remarks = ?, trip_container = ?
         WHERE trip_id = ?"
    )->execute([$tripFrom, $tripTo, $reason, $remarks, $container, $tripId]);

    $conn->prepare("UPDATE dispatch SET d_trailer = ?, d_genset = ? WHERE d_id = ?")
         ->execute([$trailer, $genset, $dId]);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Service trip updated.']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
