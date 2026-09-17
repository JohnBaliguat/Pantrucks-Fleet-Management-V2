<?php
include __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_POST['control_no']) || !pt_table_exists($conn, 'trip_receipts')) {
    echo json_encode([]);
    exit;
}

$control_no = pt_pg_escape($conn, $_POST['control_no']);
$query = "SELECT receipts_id, control_no, tr_number, location_from, location_to, total_km, maptotal_kmRun
          FROM trip_receipts WHERE control_no = '$control_no' ORDER BY receipts_id ASC";
$result = $conn->query($query);

$data = [];
if ($result) {
    while ($row = ($result)->fetch()) {
        $data[] = $row;
    }
}

echo json_encode($data);
