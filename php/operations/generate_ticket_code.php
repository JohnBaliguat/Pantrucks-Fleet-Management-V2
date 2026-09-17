<?php
// Generates a unique 6-character ticket code + its trip rows.
// Used by the gas/ticket-codes.php (sub-admin) workflow.
include __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!pt_table_exists($conn, 'ticket_code') || !pt_table_exists($conn, 'trip_receipts')) {
    echo json_encode(['success' => false, 'message' => 'Ticket code system not initialised. Run migration 009.']);
    exit;
}

date_default_timezone_set("Asia/Manila");

$driverId  = isset($_POST['driverId']) ? trim($_POST['driverId']) : '';
$tripData  = isset($_POST['tripData']) ? json_decode($_POST['tripData'], true) : [];
$unitRaw   = isset($_POST['unit']) ? trim($_POST['unit']) : '';

if (!is_array($tripData) || empty($tripData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Trip details are required.']);
    exit;
}

function generateUniqueTicketCode($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max   = strlen($chars) - 1;
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, $max)];
        $esc = pt_pg_escape($conn, $code);
        $check = $conn->query("SELECT tc_id FROM ticket_code WHERE tc_code = '$esc' LIMIT 1");
        if ($check && ($check)->rowCount() === 0) return $code;
    }
    return false;
}

$conn->beginTransaction();

try {
    $code = generateUniqueTicketCode($conn);
    if ($code === false) throw new Exception('Unable to generate a unique ticket code.');

    $escCode = pt_pg_escape($conn, $code);
    $escDrv  = pt_pg_escape($conn, $driverId);
    $escUnit = pt_pg_escape($conn, $unitRaw);
    $now     = date('Y-m-d H:i:s');

    if (!$conn->query("INSERT INTO ticket_code (tc_code, f_driverId, unit_name, tc_date, tc_status)
                              VALUES ('$escCode', '$escDrv', '$escUnit', '$now', 'Unused')")) {
        throw new Exception((($conn->errorInfo()[2]) ?? ""));
    }
    $tcId = $conn->lastInsertId();

    foreach ($tripData as $trip) {
        $tr   = pt_pg_escape($conn, trim($trip['tr']   ?? ''));
        $from = pt_pg_escape($conn, trim($trip['from'] ?? ''));
        $to   = pt_pg_escape($conn, trim($trip['to']   ?? ''));
        $km   = pt_pg_escape($conn, trim($trip['km']   ?? '0'));
        $km1  = pt_pg_escape($conn, trim($trip['km1']  ?? '0'));
        if ($tr === '' || $from === '' || $to === '') {
            throw new Exception('Each trip must have TR No, From, and To.');
        }
        $sql = "INSERT INTO trip_receipts (tc_id, control_no, tr_number, location_from, location_to, total_km, maptotal_kmRun)
                VALUES ($tcId, '', '$tr', '$from', '$to', '$km', '$km1')";
        if (!$conn->query($sql)) throw new Exception((($conn->errorInfo()[2]) ?? ""));
    }

    $conn->commit();

    $printUrl = 'php/fetch/print_ticket_code.php?tc_id=' . $tcId;
    if ($unitRaw !== '') $printUrl .= '&unit=' . rawurlencode($unitRaw);

    echo json_encode([
        'success'   => true,
        'tc_id'     => $tcId,
        'tc_code'   => $code,
        'print_url' => $printUrl
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
