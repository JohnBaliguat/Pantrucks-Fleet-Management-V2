<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
$driver_id = 0;

if ($role === 'Driver') {
    $driver_id = (int)($_SESSION['user_id'] ?? 0);
} else {
    $driver_id = (int)($_GET['driver_id'] ?? 0);
}

if ($driver_id <= 0) {
    echo json_encode(["error" => "invalid driver_id"]);
    exit;
}

$response = [
    "truck" => [],
    "trailer" => [],
];

$stmt = $conn->prepare(
    "SELECT
        d.shift_truck,
        u.unit_name,
        u.unit_plate,
        u.unit_status,
        u.unit_assigngenset,
        u.unit_assigntrailer,
        g.unit_name AS genset_name,
        g.unit_status AS genset_status,
        tr.trailer_id,
        tr.trailer_name,
        tr.trailer_plateno,
        tr.trailer_location,
        tr.trailer_status
     FROM drivers d
     LEFT JOIN driver_shift s
            ON s.driver_id = d.driver_id
           AND s.ended_at IS NULL
     LEFT JOIN units u
            ON u.unit_name = d.shift_truck
           AND u.unit_type = 'truck'
     LEFT JOIN units g
            ON g.unit_name = u.unit_assigngenset
           AND g.unit_type = 'genset'
     LEFT JOIN trailer tr
            ON tr.trailer_name = u.unit_assigntrailer
     WHERE d.driver_id = ?
     ORDER BY s.ds_id DESC NULLS LAST
     LIMIT 1"
);
$stmt->execute([$driver_id]);
$row = $stmt->fetch();

if (!$row || trim((string)($row['shift_truck'] ?? '')) === '' || empty($row['unit_name'])) {
    echo json_encode($response);
    exit;
}

$response['truck'][] = [
    'unit_name' => $row['unit_name'],
    'unit_plate' => $row['unit_plate'],
    'unit_status' => $row['unit_status'],
    'genset_name' => $row['genset_name'] ?: null,
    'genset_status' => $row['genset_status'] ?: null,
];

if (!empty($row['trailer_id'])) {
    $response['trailer'][] = [
        'trailer_id' => $row['trailer_id'],
        'trailer_name' => $row['trailer_name'],
        'trailer_plateNo' => $row['trailer_plateno'],
        'trailer_location' => $row['trailer_location'],
        'trailer_status' => $row['trailer_status'],
    ];
}

echo json_encode($response);
