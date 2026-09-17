<?php
include '../php/config/config.php';

$where = [];
$params = [];
$types = '';

// 🔹 Date filter (based on dispatch date)
if (!empty($_GET['fromDate'])) {
    $where[] = "DATE(d.d_datetime) >= ?";
    $params[] = $_GET['fromDate'];
    $types .= 's';
}

if (!empty($_GET['toDate'])) {
    $where[] = "DATE(d.d_datetime) <= ?";
    $params[] = $_GET['toDate'];
    $types .= 's';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* 🔹 Total available trucks (exclude GS & FL) */
$sqlTotal = "SELECT COUNT(*) AS total_trailer FROM trailer";

$totalRes = $conn->query($sqlTotal);
$totalRow = $totalRes->fetch();
$totalTrailer = (int)$totalRow['total_trailer'];

/* 🔹 Used trucks (filtered by date) */
$sqlUsed = "
    SELECT COUNT(DISTINCT d.d_trailer) AS used_trailers
    FROM dispatch d
    INNER JOIN trips t ON t.d_id = d.d_id
    $whereSQL
";

$stmt = $conn->prepare($sqlUsed);
$stmt->execute($params);
$usedRes = $stmt;
$usedRow = $usedRes->fetch();
$usedTrailers = (int)$usedRow['used_trailers'];

/* 🔹 Utilization % */
$utilization = 0;
if ($totalTrailer > 0) {
    $utilization = round(($usedTrailers / $totalTrailer) * 100, 1);
}

echo json_encode([
    'utilization' => $utilization,
    'used'        => $usedTrailers,
    'total'       => $totalTrailer
]);
