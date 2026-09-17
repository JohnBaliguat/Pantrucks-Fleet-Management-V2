<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$truck = trim((string)($_GET['truck'] ?? ''));
if ($truck === '') {
    echo json_encode(['total_km' => 0]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(
        CASE
            WHEN NULLIF(TRIM(t.km_run), '') IS NULL THEN 0
            WHEN TRIM(t.km_run) ~ '^[0-9]+(\.[0-9]+)?$' THEN TRIM(t.km_run)::numeric
            ELSE 0
        END
    ), 0) AS total_km
     FROM trips t
     INNER JOIN dispatch d ON t.d_id = d.d_id
     WHERE d.d_truck = ?"
);
$stmt->execute([$truck]);
$row = $stmt->fetch();

echo json_encode([
    'total_km' => isset($row['total_km']) ? (float)$row['total_km'] : 0,
]);
