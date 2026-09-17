<?php
include "../config/config.php";

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);
$fromDate = $input['fromDate'] ?? null;
$toDate   = $input['toDate'] ?? null;
$customer = $input['customer'] ?? null;

// Build filter conditions
$conditions = "
    t.trip_status = 'Done'
    AND d.d_truck IS NOT NULL AND d.d_truck <> ''
";

if (!empty($fromDate) && !empty($toDate)) {
    $conditions .= " AND DATE(d.d_datetime) BETWEEN '$fromDate' AND '$toDate'";
}

if (!empty($customer)) {
    $customerEscaped = pt_pg_escape($conn, $customer);
    $conditions .= " AND d.costumer = '$customerEscaped'";
}

// TOTAL trips
$sqlTrips = "SELECT COUNT(*) as total_trips
    FROM trips t
    INNER JOIN dispatch d ON t.d_id = d.d_id
    WHERE $conditions";
$totalTrips = $conn->query($sqlTrips)->fetch()['total_trips'] ?? 0;

// TOTAL minutes (earliest -> latest d_datetime)
$sqlTotalMinutes = "SELECT EXTRACT(EPOCH FROM (MAX(d.d_datetime) - MIN(d.d_datetime))) / 60 AS total_minutes
    FROM trips t
    INNER JOIN dispatch d ON t.d_id = d.d_id
    WHERE $conditions";
$totalMinutes = $conn->query($sqlTotalMinutes)->fetch()['total_minutes'] ?? 0;

// AVERAGE minutes per day
$sqlAvgMinutes = "SELECT AVG(diff) as avg_minutes
    FROM (
        SELECT DATE(d.d_datetime) as trip_day,
               EXTRACT(EPOCH FROM (MAX(d.d_datetime) - MIN(d.d_datetime))) / 60 AS diff
        FROM trips t
        INNER JOIN dispatch d ON t.d_id = d.d_id
        WHERE $conditions
        GROUP BY DATE(d.d_datetime)
    ) t";
$avgMinutes = round($conn->query($sqlAvgMinutes)->fetch()['avg_minutes'] ?? 0);

// Helper: convert minutes → HH:MM
function formatHours($minutes) {
    $hours = floor($minutes / 60);
    $mins  = $minutes % 60;
    return sprintf("%02d:%02d", $hours, $mins);
}

// Format outputs
$totalHoursFormatted = formatHours($totalMinutes) . " Hrs";
$avgHoursFormatted   = formatHours($avgMinutes) . " Hrs";

// Return JSON
echo json_encode([
    "trips"   => (int)$totalTrips,
    "hours"   => $totalHoursFormatted,
    "average" => $avgHoursFormatted
]);
?>
