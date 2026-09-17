<?php
include __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!pt_table_exists($conn, 'ticket_code')) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$limit = 200;
$rows  = [];
$result = $conn->query("SELECT tc_id, tc_code, f_driverId, unit_name, tc_date, tc_status
                                FROM ticket_code ORDER BY tc_date DESC LIMIT $limit");
if ($result) {
    while ($r = ($result)->fetch()) {
        $rows[] = $r;
    }
}
echo json_encode(['success' => true, 'data' => $rows]);
