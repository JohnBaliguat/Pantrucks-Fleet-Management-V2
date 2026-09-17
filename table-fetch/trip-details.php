<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

$driverId = intval($_POST['driverId'] ?? 0);
$fromDate = $_POST['fromDate'] ?? '';
$toDate   = $_POST['toDate'] ?? '';

if ($driverId <= 0) {
    echo json_encode([]);
    exit;
}

$dateCondition = '';
if (!empty($fromDate) && !empty($toDate)) {
    $from = pt_pg_escape($conn, $fromDate);
    $to = pt_pg_escape($conn, $toDate);
    $dateCondition = " AND DATE(d.d_datetime) BETWEEN '$from' AND '$to'";
}

$sql = "
SELECT
    t.trip_id,
    u.unit_name AS unit,
    t.costumer AS customer,
    t.trip_haulingSegment AS segment,
    t.trip_status AS status,
    TO_CHAR(d.d_datetime, 'FMMonth DD, YYYY HH12:MI AM') AS date
FROM trips t
INNER JOIN dispatch d ON t.d_id = d.d_id
LEFT JOIN units u ON d.d_truck = u.unit_name
WHERE d.driver_id = $driverId
$dateCondition
ORDER BY d.d_datetime DESC
";

$result = $conn->query($sql);

$data = [];
while ($row = ($result)->fetch()) {
    $data[] = [
        'trip_id' => $row['trip_id'],
        'unit' => $row['unit'] ?: '-',
        'customer' => $row['customer'] ?: '-',
        'segment' => $row['segment'] ?: '-',
        'status' => $row['status'],
        'date' => $row['date']
    ];
}

// Get trips by segment summary
$segmentSql = "
SELECT
    t.trip_haulingSegment AS segment,
    COUNT(t.trip_id) AS trip_count
FROM trips t
INNER JOIN dispatch d ON t.d_id = d.d_id
WHERE d.driver_id = $driverId
$dateCondition
GROUP BY t.trip_haulingSegment
ORDER BY trip_count DESC
";

$segmentResult = $conn->query($segmentSql);

$segmentSummary = [];
while ($row = ($segmentResult)->fetch()) {
    $segmentSummary[] = [
        'segment' => $row['segment'] ?: 'Unknown',
        'count' => (int) $row['trip_count']
    ];
}

echo json_encode([
    'trips' => $data,
    'segment_summary' => $segmentSummary
]);
?>
