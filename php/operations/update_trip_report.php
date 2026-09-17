<?php
// Updates the dispatch + trip rows surfaced in the Trip Report edit modal.
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

$dId          = (int)($_POST['d_id'] ?? 0);
$dDatetime    = trim((string)($_POST['d_datetime'] ?? ''));
$dDispatchhub = trim((string)($_POST['d_dispatchhub'] ?? ''));

if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

// Normalize datetime-local format ("2026-05-26T14:30") to a PG-friendly value.
$dDatetimeNormalized = null;
if ($dDatetime !== '') {
    $ts = strtotime(str_replace('T', ' ', $dDatetime));
    if ($ts === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid date/time format.']);
        exit;
    }
    $dDatetimeNormalized = date('Y-m-d H:i:s', $ts);
}

function trip_payload(array $post, string $prefix): ?array {
    $tripId = (int)($post[$prefix . '_id'] ?? 0);
    if ($tripId <= 0) return null;
    return [
        'trip_id'              => $tripId,
        'trip_from'            => trim((string)($post[$prefix . '_from'] ?? '')),
        'trip_to'              => trim((string)($post[$prefix . '_to'] ?? '')),
        'trip_container'       => strtoupper(trim((string)($post[$prefix . '_container'] ?? ''))),
        'trip_containerstat'   => trim((string)($post[$prefix . '_containerstat'] ?? '')),
        'trip_haulingsegment'  => trim((string)($post[$prefix . '_segment'] ?? '')),
        'trip_haulingtype'     => trim((string)($post[$prefix . '_type'] ?? '')),
        'trip_status'          => trim((string)($post[$prefix . '_status'] ?? '')),
    ];
}

$trip1 = trip_payload($_POST, 'trip1');
$trip2 = trip_payload($_POST, 'trip2');

try {
    $conn->beginTransaction();

    if ($dDatetimeNormalized !== null) {
        $stmt = $conn->prepare("UPDATE dispatch SET d_datetime = ?, d_dispatchhub = ? WHERE d_id = ?");
        $stmt->execute([$dDatetimeNormalized, $dDispatchhub, $dId]);
    } else {
        $stmt = $conn->prepare("UPDATE dispatch SET d_dispatchhub = ? WHERE d_id = ?");
        $stmt->execute([$dDispatchhub, $dId]);
    }

    $updateTripSql = "UPDATE trips
                      SET trip_from = ?, trip_to = ?, trip_container = ?, trip_containerstat = ?,
                          trip_haulingsegment = ?, trip_haulingtype = ?, trip_status = ?
                      WHERE trip_id = ? AND d_id = ?";
    foreach ([$trip1, $trip2] as $t) {
        if ($t === null) continue;
        $stmt = $conn->prepare($updateTripSql);
        $stmt->execute([
            $t['trip_from'], $t['trip_to'], $t['trip_container'], $t['trip_containerstat'],
            $t['trip_haulingsegment'], $t['trip_haulingtype'], $t['trip_status'],
            $t['trip_id'], $dId,
        ]);
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Trip report updated.']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
}
