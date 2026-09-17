<?php
include '../php/config/config.php';

$where = [];
$params = [];

if (!empty($_GET['fromDate'])) {
    $where[] = "DATE(d.d_datetime) >= ?";
    $params[] = $_GET['fromDate'];
}

if (!empty($_GET['toDate'])) {
    $where[] = "DATE(d.d_datetime) <= ?";
    $params[] = $_GET['toDate'];
}

$whereSQL = '';
if ($where) {
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT 
        DATE(d.d_datetime) AS trip_date,
        COUNT(t.trip_id) AS total_trips,
        COUNT(DISTINCT d.d_truck) AS used_truck
    FROM trips t
    INNER JOIN dispatch d ON d.d_id = t.d_id
    $whereSQL
    GROUP BY DATE(d.d_datetime)
    ORDER BY trip_date
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$result = $stmt;

$dates = [];
$trips = [];
$trucks = [];

while ($row = $result->fetch()) {
    $dates[]  = $row['trip_date']; // keep raw date for JS
    $trips[]  = (int)$row['total_trips'];
    $trucks[] = (int)$row['used_truck'];
}

echo json_encode([
    'dates'  => $dates,
    'trips'  => $trips,
    'trucks' => $trucks
]);
