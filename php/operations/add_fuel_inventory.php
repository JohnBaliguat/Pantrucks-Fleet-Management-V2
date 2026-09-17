<?php
include __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid method.');
}

if (!pt_table_exists($conn, 'fuel_inventory')) {
    http_response_code(500);
    exit('Fuel inventory table missing. Run migration 009.');
}

$fi_date         = $_POST['fi_date'] ?? '';
$fi_noOfLiters   = floatval($_POST['fi_noofliters'] ?? 0);
$fi_poNo         = $_POST['fi_pono'] ?? '';
$fi_inVo         = $_POST['fi_invo'] ?? '';
$fi_plateNo      = $_POST['fi_plateno'] ?? '';
$fi_receiveBy    = $_POST['fi_receiveby'] ?? '';

if (!$fi_date || $fi_noOfLiters <= 0 || !$fi_poNo) {
    http_response_code(400);
    exit('Missing required fields.');
}

$date = date('Y-m-d H:i:s', strtotime($fi_date));

$stmt = $conn->prepare("INSERT INTO fuel_inventory
    (fi_pono, fi_noofliters, fi_receiveby, fi_date, fi_invo, fi_plateno, fi_consumableltr, fi_consumeltr)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
$consumable = $fi_noOfLiters;

if ($stmt->execute([$fi_poNo, $fi_noOfLiters, $fi_receiveBy, $date, $fi_inVo, $fi_plateNo, $consumable])) {
    echo 'Fuel record saved.';
} else {
    http_response_code(500);
    echo 'Save failed: ' . ($stmt->errorInfo()[2] ?? '');
}
