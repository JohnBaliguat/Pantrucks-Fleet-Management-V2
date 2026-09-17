<?php
include __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$ticketCode = isset($_GET['code']) ? trim($_GET['code']) : '';

if ($ticketCode === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ticket code is required.']);
    exit;
}

if (!pt_table_exists($conn, 'ticket_code')) {
    echo json_encode(['success' => false, 'message' => 'Ticket code system not initialised.']);
    exit;
}

$escapedCode = pt_pg_escape($conn, strtoupper($ticketCode));

$ticketQuery = "SELECT tc_id, tc_code, f_driverId, unit_name, tc_date, tc_status
                FROM ticket_code WHERE tc_code = '$escapedCode' LIMIT 1";
$ticketResult = $conn->query($ticketQuery);

if (!$ticketResult || ($ticketResult)->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Ticket code not found.']);
    exit;
}

$ticket = ($ticketResult)->fetch();

$trips = [];
$totalKm = 0;
if (pt_table_exists($conn, 'trip_receipts')) {
    $tripQuery = "SELECT receipts_id, tc_id, control_no, tr_number, location_from, location_to, total_km, maptotal_kmRun
                  FROM trip_receipts WHERE tc_id = '{$ticket['tc_id']}' ORDER BY receipts_id ASC";
    $tripResult = $conn->query($tripQuery);
    if ($tripResult) {
        while ($row = ($tripResult)->fetch()) {
            $trips[] = $row;
            $totalKm += (float)$row['total_km'];
        }
    }
}

echo json_encode([
    'success'  => true,
    'ticket'   => $ticket,
    'trips'    => $trips,
    'total_km' => number_format($totalKm, 2, '.', '')
]);
