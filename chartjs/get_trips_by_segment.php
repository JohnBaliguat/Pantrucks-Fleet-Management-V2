<?php
include '../php/config/config.php';
header('Content-Type: application/json');

$fromDate = trim((string)($_GET['fromDate'] ?? ''));
$toDate = trim((string)($_GET['toDate'] ?? ''));
$customer = trim((string)($_GET['costumer'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($fromDate !== '') {
    $where[] = "DATE(d.d_datetime) >= ?";
    $params[] = $fromDate;
    $types .= 's';
}

if ($toDate !== '') {
    $where[] = "DATE(d.d_datetime) <= ?";
    $params[] = $toDate;
    $types .= 's';
}

if ($customer !== '') {
    $where[] = "t.costumer = ?";
    $params[] = $customer;
    $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        COALESCE(NULLIF(TRIM(t.trip_haulingSegment), ''), 'Unassigned') AS segment,
        COUNT(t.trip_id) AS trip_count
    FROM trips t
    INNER JOIN dispatch d ON d.d_id = t.d_id
    $whereSql
    GROUP BY COALESCE(NULLIF(TRIM(t.trip_haulingSegment), ''), 'Unassigned')
    ORDER BY trip_count DESC, segment ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$result = $stmt;

$data = [];
while ($row = $result->fetch()) {
    $data[] = [
        'segment' => $row['segment'],
        'trip_count' => (int)$row['trip_count'],
    ];
}
echo json_encode($data);
