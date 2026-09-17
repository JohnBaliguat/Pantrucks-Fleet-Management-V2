<?php
include "../config/config.php";

// Today’s date
$today = date("Y-m-d");

// Daily (today’s completed trips based on dispatch date)
$sqlDaily = "
    SELECT COUNT(*) as daily_count
    FROM trips t
    INNER JOIN dispatch d ON t.d_id = d.d_id
    WHERE t.trip_status = 'Done' 
      AND DATE(d.d_datetime) = '$today'
";
$daily = $conn->query($sqlDaily)->fetch()['daily_count'] ?? 0;

// Total (all completed trips based on dispatch date)
$sqlTotal = "
    SELECT COUNT(*) as total_count
    FROM trips t
    INNER JOIN dispatch d ON t.d_id = d.d_id
    WHERE t.trip_status = 'Done'
";
$total = $conn->query($sqlTotal)->fetch()['total_count'] ?? 0;

// Average (total / number of days that have trips based on dispatch date)
$sqlAvg = "
    SELECT ROUND(AVG(cnt), 2) AS avg_count
    FROM (
        SELECT DATE(d.d_datetime) AS trip_date,
               COUNT(*) AS cnt
        FROM trips t
        INNER JOIN dispatch d ON t.d_id = d.d_id
        WHERE t.trip_status = 'Done'
        GROUP BY DATE(d.d_datetime)
    ) AS daily_counts
";

$result = $conn->query($sqlAvg);
$avg = $result ? $result->fetch()['avg_count'] : 0;

// Return JSON
echo json_encode([
    "daily" => (int)$daily,
    "total" => (int)$total,
    "average" => (int)$avg
]);
?>
