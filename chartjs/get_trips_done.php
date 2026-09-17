<?php
include '../php/config/config.php';

$costumer = $_GET['costumer'] ?? '';
$fromDate = $_GET['fromDate'] ?? '';
$toDate   = $_GET['toDate'] ?? '';

$sql = "
    SELECT 
        DATE(d.d_datetime) as trip_date, 
        t.costumer, 
        COUNT(t.trip_id) AS total_done
    FROM trips t
    INNER JOIN dispatch d ON t.d_id = d.d_id
    WHERE t.trip_status = 'Done' 
";
if (!empty($costumer)){
    $sql .= " AND t.costumer = '$costumer'";
}
// Add date filter if provided
if (!empty($fromDate) && !empty($toDate)) {
    $sql .= " AND DATE(d.d_datetime) BETWEEN '$fromDate' AND '$toDate'";
}

$sql .= " GROUP BY DATE(d.d_datetime), d.costumer
          ORDER BY trip_date ASC";

$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch()) {
    $customer = $row["costumer"];
    $date = $row["trip_date"];
    $count = (int) $row["total_done"];

    if (!isset($data[$customer])) {
        $data[$customer] = [];
    }
    $data[$customer][] = [
        strtotime($date) * 1000,
        $count
    ];
}

echo json_encode($data);
?>
