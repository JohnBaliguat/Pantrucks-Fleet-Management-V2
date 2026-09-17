<?php
header('Content-Type: application/json');
session_start();

if (($_SESSION['user_type'] ?? '') !== 'Client') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated as Client.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/booking_no.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare(
    "SELECT u.customer_code, c.customer_name, c.customer_segment
     FROM \"user\" u
     LEFT JOIN customer c ON c.customer_code = u.customer_code
     WHERE u.user_id = ? LIMIT 1"
);
$stmt->execute([$userId]);
$ctx = $stmt->fetch();
$customerCode = trim((string)($ctx['customer_code'] ?? ''));
$customerSegment = trim((string)($ctx['customer_segment'] ?? ''));
if ($customerCode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Your client account is not linked to a customer.']);
    exit;
}
if (strcasecmp($customerCode, 'CTH') !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'This booking form is only for CTH clients.']);
    exit;
}

$bookingDate = trim($_POST['booking_date'] ?? '');
$bookingSn = trim($_POST['booking_sn'] ?? '');
$bookingDo = trim($_POST['booking_do'] ?? '');
$haulingStart = trim($_POST['haulingStart'] ?? '');
$lastDayStorage = trim($_POST['lastDayStorage'] ?? '');
$lastDayDemurrage = trim($_POST['lastDayDemurrage'] ?? '');
$lastDayDetention = trim($_POST['lastDayDetention'] ?? '');
$tripFrom = trim($_POST['trip_from'] ?? '');
$tripTo = trim($_POST['trip_to'] ?? '');
$returnLocation = trim($_POST['return_location'] ?? '');
$containers = $_POST['container'] ?? [];
$containerSeals = $_POST['container_seal'] ?? [];
$quantities = $_POST['quantity'] ?? [];

$errors = [];
foreach ([
    'Date Requested' => $bookingDate,
    'Shipment Number' => $bookingSn,
    'DO' => $bookingDo,
    'Hauling Start' => $haulingStart,
    'Last Day of Storage' => $lastDayStorage,
    'Last Day of Demurrage' => $lastDayDemurrage,
    'Last Day of Detention' => $lastDayDetention,
    'Pickup Location' => $tripFrom,
    'Delivery Location' => $tripTo,
    'Return Location' => $returnLocation,
 ] as $label => $value) {
    if (trim((string)$value) === '') {
        $errors[] = $label . ' is required.';
    }
}

if ($tripFrom !== '' && strcasecmp($tripFrom, $tripTo) === 0) {
    $errors[] = 'Pickup and delivery cannot be the same location.';
}
if ($tripTo !== '' && strcasecmp($tripTo, $returnLocation) === 0) {
    $errors[] = 'Delivery and return location cannot be the same.';
}
foreach ([$bookingDate, $haulingStart, $lastDayStorage, $lastDayDemurrage, $lastDayDetention] as $dateValue) {
    if ($dateValue !== '' && strtotime($dateValue) === false) {
        $errors[] = 'One or more dates are invalid.';
        break;
    }
}

if (!is_array($containers) || count($containers) === 0) {
    $errors[] = 'At least one container row is required.';
}
if (count($containers) !== count($containerSeals) || count($containers) !== count($quantities)) {
    $errors[] = 'Container row counts do not match.';
}

$normalizedContainers = [];
$normalizedSeals = [];
for ($i = 0; $i < count($containers); $i++) {
    $container = strtoupper(trim((string)$containers[$i]));
    $seal = trim((string)($containerSeals[$i] ?? ''));
    $qty = (int)($quantities[$i] ?? 0);
    if (!preg_match('/^[A-Z]{4}[0-9]{7}$/', $container)) {
        $errors[] = 'Container #' . ($i + 1) . ' must be exactly 4 capital letters followed by 7 numbers.';
    }
    if ($seal === '') {
        $errors[] = 'Container seal is required for row #' . ($i + 1) . '.';
    }
    if ($qty < 1) {
        $errors[] = 'Quantity must be at least 1 for row #' . ($i + 1) . '.';
    }
    $normalizedContainers[] = $container;
    $normalizedSeals[] = $seal;
}

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode(' ', array_unique($errors))]);
    exit;
}

$bookingRequired = $lastDayDetention;
$bookingType = 'Local';
$bookingActivity = 'DELIVER';
$containerStatus = 'Loaded';
$haulingSegment = '';
$haulingType = '';
$status = 'Active';
$createdBookings = [];

$conn->beginTransaction();
try {
    $stmtInsert = $conn->prepare(
        "INSERT INTO booking (
            booking_no, booking_type, booking_date, booking_daterequired, booking_sn, booking_do,
            booking_haulingstartdate, booking_lastdatestorage, booking_lastdatedemurrage, booking_lastdatedetention,
            costumer, customer_segment, container_seal, container, booking_activity, container_status,
            hauling_segment, hauling_type, trip_from, trip_to, return_location, quantity, quantity_use, status,
            vessel_name, voyage_no, container_no_port, bill_of_lading, port_location, customs_cleared
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, '', '', '', '', '', 0)"
    );
    $stmtLog = $conn->prepare(
        "INSERT INTO workflow_event (booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, 'order_created', 'client', ?, 'CTH multi-container booking submitted via client portal')"
    );

    foreach ($normalizedContainers as $index => $containerValue) {
        $bookingNo = bn_generate($conn, $customerCode, $tripFrom, $tripTo);
        $sealValue = $normalizedSeals[$index];
        $quantityValue = 1;

        $stmtInsert->execute([
            $bookingNo,
            $bookingType,
            $bookingDate,
            $bookingRequired,
            $bookingSn,
            $bookingDo,
            $haulingStart,
            $lastDayStorage,
            $lastDayDemurrage,
            $lastDayDetention,
            $customerCode,
            $customerSegment,
            $sealValue,
            $containerValue,
            $bookingActivity,
            $containerStatus,
            $haulingSegment,
            $haulingType,
            $tripFrom,
            $tripTo,
            $returnLocation,
            $quantityValue,
            $status
        ]);

        $stmtLog->execute([$bookingNo, $userId]);
        $createdBookings[] = $bookingNo;
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'CTH booking submitted.',
        'booking_nos' => $createdBookings,
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
