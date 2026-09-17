<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$bookingNo = isset($_GET['booking_no']) ? trim($_GET['booking_no']) : '';
if ($bookingNo === '') {
    echo json_encode(['status' => 'error', 'message' => 'booking_no required']);
    exit;
}

// Booking-level summary.
$bk = null;
$stmt = $conn->prepare(
    "SELECT booking_id, booking_no, booking_type, costumer, container, container_status,
            hauling_segment, hauling_type, trip_from, trip_to, quantity, quantity_use, status,
            vessel_name, voyage_no, container_no_port, bill_of_lading, port_location,
            customs_cleared, customs_cleared_at, booking_date, booking_daterequired
     FROM booking WHERE booking_no = ? LIMIT 1"
);
$stmt->execute([$bookingNo]);
$bk = $stmt->fetch();
if (!$bk) {
    echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
    exit;
}

// Segments are trips rows joined with their parent dispatch row.
// One booking → many dispatches → each dispatch has trips.
$segments = [];
$sql = "SELECT t.trip_id, t.d_id, t.trip_type, t.segment_costumer, t.segment_status,
               t.foul_trip, t.cancelled_at, t.cancelled_reason, t.scheduled_at,
               t.trip_from, t.trip_to, t.trip_status, t.deliver_datetime, t.withdraw_datetime,
               d.booking_no, d.costumer AS dispatch_costumer, d.driver_id, d.d_drivername,
               d.d_truck, d.d_trailer, d.d_genset, d.d_datetime, d.workflow_stage
        FROM trips t
        INNER JOIN dispatch d ON d.d_id = t.d_id
        WHERE d.booking_no = ?
        ORDER BY t.trip_id ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$bookingNo]);
$res = $stmt;
while ($r = $res->fetch()) { $segments[] = $r; }

echo json_encode([
    'status'   => 'success',
    'booking'  => $bk,
    'segments' => $segments,
]);
