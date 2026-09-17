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
$sqlTotal = "
    SELECT COUNT(*) AS total_trucks
    FROM units
    WHERE unit_name NOT LIKE '%GS%'
      AND unit_name NOT LIKE '%FL%'
";

$totalRes = $conn->query($sqlTotal);
$totalRow = $totalRes->fetch();
$totalTrucks = (int)$totalRow['total_trucks'];

/* 🔹 Used trucks (filtered by date) */
$sqlUsed = "
    SELECT COUNT(DISTINCT d.d_truck) AS used_trucks
    FROM dispatch d
    INNER JOIN trips t ON t.d_id = d.d_id
    $whereSQL
";

$stmt = $conn->prepare($sqlUsed);
$stmt->execute($params);
$usedRes = $stmt;
$usedRow = $usedRes->fetch();
$usedTrucks = (int)$usedRow['used_trucks'];

/* 🔹 Utilization % */
$utilization = 0;
if ($totalTrucks > 0) {
    $utilization = round(($usedTrucks / $totalTrucks) * 100, 1);
}

echo json_encode([
    'utilization' => $utilization,
    'used'        => $usedTrucks,
    'total'       => $totalTrucks
]);
