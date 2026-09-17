<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';   // pt_customer_is_import()

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$parent_booking_no = trim($_POST['booking_no'] ?? '');
$parent_d_id = (int)($_POST['parent_d_id'] ?? 0);

if ($parent_booking_no === '') {
    echo json_encode(['status' => 'error', 'message' => 'booking_no required']);
    exit;
}
if ($parent_d_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'parent_d_id required']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT b.booking_no, b.costumer, b.customer_segment, b.container_seal, b.container,
            b.container_status, b.return_location, b.booking_activity, b.booking_daterequired,
            b.booking_sn, b.booking_do, b.booking_haulingstartdate, b.booking_lastdatestorage,
            b.booking_lastdatedemurrage, b.booking_lastdatedetention,
            d.d_id, d.workflow_stage, COALESCE(t.trip_to, b.trip_to) AS completed_drop
     FROM booking b
     INNER JOIN dispatch d ON d.booking_no = b.booking_no
     LEFT JOIN trips t ON t.trip_id = (
        SELECT MAX(t2.trip_id) FROM trips t2 WHERE t2.d_id = d.d_id
     )
     WHERE b.booking_no = ? AND d.d_id = ?
     LIMIT 1"
);
$stmt->execute([$parent_booking_no, $parent_d_id]);
$src = $stmt->fetch();
if (!$src) {
    echo json_encode(['status' => 'error', 'message' => 'Parent CTH loaded transaction not found.']);
    exit;
}
if (!pt_customer_is_import($conn, (string)$src['costumer'])) {
    echo json_encode(['status' => 'error', 'message' => 'Only import (CTH / trade_type=Import) bookings can create an empty return leg.']);
    exit;
}
if (stripos((string)$src['container_status'], 'Loaded') !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Only loaded import bookings can create an empty return leg.']);
    exit;
}
if (!in_array((string)$src['workflow_stage'], ['pod_captured', 'billing_closed', 'client_notified'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Only completed loaded import transactions can create an empty return leg.']);
    exit;
}

$emptyTripTo = trim((string)($src['return_location'] ?? ''));
if ($emptyTripTo === '') {
    echo json_encode(['status' => 'error', 'message' => 'Return Location is missing on the parent CTH booking.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT 1 FROM workflow_event
     WHERE actor_role = 'dispatcher' AND stage = 'cth_empty_return_ready'
       AND notes LIKE ?
     LIMIT 1"
);
$stmt->execute(['%parent_d_id=' . $parent_d_id . '%']);
$existing = $stmt->fetch();
if ($existing) {
    echo json_encode(['status' => 'error', 'message' => 'An empty return booking has already been created for this completed CTH transaction.']);
    exit;
}

$emptyTripFrom = trim((string)($src['completed_drop'] ?? ''));
if ($emptyTripFrom === '') {
    $emptyTripFrom = '';
}

$conn->beginTransaction();
try {
    require_once __DIR__ . '/../lib/booking_no.php';
    $new_booking_no = bn_generate($conn, $src['costumer'], $emptyTripFrom, $emptyTripTo);

    $new_booking_type = 'Local';
    $new_booking_date = date('Y-m-d');
    $new_required_date = $new_booking_date;
    $new_quantity = 1;
    $new_status = 'Active';
    $new_booking_activity = 'WITHDRAW';
    $new_container_status = 'Empty';
    $new_customs_cleared = false;
    $blank = '';

    $stmt = $conn->prepare(
        "INSERT INTO booking (
            booking_no, booking_type, booking_date, booking_daterequired, booking_sn, booking_do,
            booking_haulingstartdate, booking_lastdatestorage, booking_lastdatedemurrage, booking_lastdatedetention,
            costumer, customer_segment, container_seal, container, booking_activity, container_status,
            hauling_segment, hauling_type, trip_from, trip_to, return_location, quantity, quantity_use, status,
            vessel_name, voyage_no, container_no_port, bill_of_lading, port_location, customs_cleared
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, '', '', '', '', '', ?)"
    );

    $stmt->bindValue(1,  $new_booking_no,            PDO::PARAM_STR);
    $stmt->bindValue(2,  $new_booking_type,          PDO::PARAM_STR);
    $stmt->bindValue(3,  $new_booking_date,          PDO::PARAM_STR);
    $stmt->bindValue(4,  $new_required_date,         PDO::PARAM_STR);
    $stmt->bindValue(5,  $src['booking_sn'],         PDO::PARAM_STR);
    $stmt->bindValue(6,  $src['booking_do'],         PDO::PARAM_STR);
    $stmt->bindValue(7,  $src['booking_haulingstartdate'], PDO::PARAM_STR);
    $stmt->bindValue(8,  $src['booking_lastdatestorage'],  PDO::PARAM_STR);
    $stmt->bindValue(9,  $src['booking_lastdatedemurrage'], PDO::PARAM_STR);
    $stmt->bindValue(10, $src['booking_lastdatedetention'], PDO::PARAM_STR);
    $stmt->bindValue(11, $src['costumer'],           PDO::PARAM_STR);
    $stmt->bindValue(12, $src['customer_segment'],   PDO::PARAM_STR);
    $stmt->bindValue(13, $src['container_seal'],     PDO::PARAM_STR);
    $stmt->bindValue(14, $src['container'],          PDO::PARAM_STR);
    $stmt->bindValue(15, $new_booking_activity,      PDO::PARAM_STR);
    $stmt->bindValue(16, $new_container_status,      PDO::PARAM_STR);
    $stmt->bindValue(17, $blank,                     PDO::PARAM_STR);
    $stmt->bindValue(18, $blank,                     PDO::PARAM_STR);
    $stmt->bindValue(19, $emptyTripFrom,             PDO::PARAM_STR);
    $stmt->bindValue(20, $emptyTripTo,               PDO::PARAM_STR);
    $stmt->bindValue(21, $blank,                     PDO::PARAM_STR);
    $stmt->bindValue(22, $new_quantity,              PDO::PARAM_INT);
    $stmt->bindValue(23, $new_status,                PDO::PARAM_STR);
    $stmt->bindValue(24, $new_customs_cleared,       PDO::PARAM_BOOL);
    $stmt->execute();

    $userId = (int)($_SESSION['user_id'] ?? 0);

    $noteChild = "Spawned from loaded CTH leg parent=$parent_booking_no parent_d_id=$parent_d_id (route: $emptyTripFrom → $emptyTripTo)";
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, 'order_created', 'dispatcher', ?, ?)"
    );
    $stmt->execute([$new_booking_no, $userId, $noteChild]);

    $noteParent = "CTH empty return booking child=$new_booking_no created (parent=$parent_booking_no, parent_d_id=$parent_d_id, route: $emptyTripFrom → $emptyTripTo)";
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, 'cth_empty_return_ready', 'dispatcher', ?, ?)"
    );
    $stmt->execute([$parent_booking_no, $userId, $noteParent]);

    $conn->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'CTH empty return booking created.',
        'parent' => $parent_booking_no,
        'new_booking' => $new_booking_no,
        'trip_from' => $emptyTripFrom,
        'trip_to' => $emptyTripTo,
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Failed: ' . $e->getMessage()]);
}
