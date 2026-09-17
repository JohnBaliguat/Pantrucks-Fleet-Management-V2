<?php
include "../config/config.php";

$costumer = $_GET['costumer'] ?? '';
$fromDate = $_GET['fromDate'] ?? '';
$toDate = $_GET['toDate'] ?? '';

// Base WHERE clause
$where = "WHERE t.trip_status = 'Done'";

if (!empty($costumer)) {
    $where .= " AND t.costumer = '" . pt_pg_escape($conn, $costumer) . "'";
}

if (!empty($fromDate) && !empty($toDate)) {
    $where .= " AND DATE(d.d_datetime) BETWEEN '" . pt_pg_escape($conn, $fromDate) . "' AND '" . pt_pg_escape($conn, $toDate) . "'";
}

// Daily count
$today = date("Y-m-d");
$sqlDaily = "
    SELECT COUNT(*) AS daily_count
    FROM trips t
    INNER JOIN dispatch d ON t.d_id = d.d_id
    $where
";

// Only filter by today's date if no custom date range
if (empty($fromDate) && empty($toDate)) {
    $sqlDaily .= " AND DATE(d.d_datetime) = '$today'";
}

$dailyResult = $conn->query($sqlDaily);
$daily = $dailyResult ? ($dailyResult->fetch()['daily_count'] ?? 0) : 0;

// Total count
$sqlTotal = "
    SELECT COUNT(*) AS total_count
    FROM trips t
    INNER JOIN dispatch d ON t.d_id = d.d_id
    $where
";
$totalResult = $conn->query($sqlTotal);
$total = $totalResult ? ($totalResult->fetch()['total_count'] ?? 0) : 0;

// Average per day
$sqlAvg = "
    SELECT AVG(cnt) AS avg_count
    FROM (
        SELECT DATE(d.d_datetime) AS trip_date, COUNT(*) AS cnt
        FROM trips t
        INNER JOIN dispatch d ON t.d_id = d.d_id
        $where
        GROUP BY DATE(d.d_datetime)
    ) AS sub
";
$avgResult = $conn->query($sqlAvg);
$avg = $avgResult ? round($avgResult->fetch()['avg_count'] ?? 0, 0) : 0;

// Output JSON
echo json_encode([
    "daily" => (int)$daily,
    "total" => (int)$total,
    "average" => (float)$avg
]);
?>
