<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'booking_id required']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT booking_id, booking_no, booking_type, booking_date, booking_daterequired,
            booking_sn, booking_do, booking_haulingstartdate, booking_lastdatestorage,
            booking_lastdatedemurrage, booking_lastdatedetention,
            costumer, customer_segment, container_seal, container, booking_activity, container_status,
            hauling_segment, hauling_type, trip_from, trip_to, return_location, quantity, quantity_use, status,
            vessel_name, voyage_no, container_no_port, bill_of_lading, port_location,
            customs_cleared, customs_cleared_at
     FROM booking WHERE booking_id = ? LIMIT 1"
);
$stmt->execute([$bookingId]);
$result = $stmt;
$row = $result->fetch();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
    exit;
}

echo json_encode(['status' => 'success', 'booking' => $row]);
?>
