<?php
// Phase 12 — client-side booking submit.
//
// The client never selects Customer, Customer Segment, hauling segment,
// or booking type. We derive Customer + Customer Segment from the
// session-linked customer row (never the POST), default Container
// Status to "Empty", and let the dispatcher pick the hauling segment
// later. The booking lands in the same booking table that dispatcher
// addbooking writes to, so the dispatch dashboard sees it immediately.

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

$userId = (int)($_SESSION['user_id'] ?? 0);

// Resolve customer for this login. The user.customer_code link is the
// only trusted source; ignore any costumer/customer_segment in POST.
$stmt = $conn->prepare(
    "SELECT u.customer_code, c.customer_name, c.customer_segment
     FROM \"user\" u
     LEFT JOIN customer c ON c.customer_code = u.customer_code
     WHERE u.user_id = ? LIMIT 1"
);
$stmt->execute([$userId]);
$ctx = $stmt->fetch();
$customer_code    = $ctx['customer_code']    ?? '';
$customer_segment = $ctx['customer_segment'] ?? '';

if ($customer_code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Your client account is not linked to a customer. Please contact dispatch.']);
    exit;
}
if (strcasecmp($customer_code, 'CTH') === 0) {
    echo json_encode(['status' => 'error', 'message' => 'CTH clients must use the multi-container booking page.']);
    exit;
}

// Client-supplied fields (whitelisted + trimmed).
$booking_required = trim($_POST['booking_required'] ?? '');
$container_seal   = trim($_POST['container_seal']   ?? '');
$quantity         = (int)($_POST['quantity']        ?? 0);
$trip_from        = trim($_POST['trip_from']        ?? '');
$trip_to          = trim($_POST['trip_to']          ?? '');

// Server-defaulted fields the client never picks.
$booking_date     = date('Y-m-d');
$booking_type     = 'Local';            // dispatcher can switch to Import/Export later
$container_status = 'Empty';            // every client booking starts Empty
$booking_activity = 'DELIVER';          // sane default; dispatcher can change
$container        = '';                 // container number assigned at gate
$hauling_segment  = '';                 // dispatcher will pick the route on assignment
$hauling_type     = '';
$vessel_name = $voyage_no = $container_no_port = $bill_of_lading = $port_location = '';
$customs_cleared = FALSE;

// Validation — anything missing or impossible bounces back as JSON.
$errors = [];
if ($booking_required === '')        $errors[] = 'Required date is required.';
if ($container_seal === '')          $errors[] = 'Container seal is required.';
if ($quantity < 1)                   $errors[] = 'Container quantity must be at least 1.';
if ($trip_from === '')               $errors[] = 'Pickup location is required.';
if ($trip_to === '')                 $errors[] = 'Delivery location is required.';
if ($trip_from !== '' && strcasecmp($trip_from, $trip_to) === 0) {
    $errors[] = 'Pickup and delivery cannot be the same location.';
}
if ($booking_required !== '') {
    $ts = strtotime($booking_required);
    if ($ts === false || $ts < strtotime('today')) {
        $errors[] = 'Required date must be today or later.';
    }
}
if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
    exit;
}

// Phase 14.1 — booking_no format: Customer-BN-From-To.
require_once __DIR__ . '/../lib/booking_no.php';
$booking_no = bn_generate($conn, $customer_code, $trip_from, $trip_to);

$stmt = $conn->prepare("INSERT INTO booking (
    booking_no, booking_type, booking_date, booking_daterequired, costumer, customer_segment,
    container_seal, container, booking_activity, container_status,
    hauling_segment, hauling_type, trip_from, trip_to, quantity, quantity_use, status,
    vessel_name, voyage_no, container_no_port, bill_of_lading, port_location, customs_cleared
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Active', ?, ?, ?, ?, ?, ?)");



$stmt->execute([
    $booking_no,
    $booking_type,
    $booking_date,
    $booking_required,
    $customer_code,
    $customer_segment,
    $container_seal,
    $container,
    $booking_activity,
    $container_status,
    $hauling_segment,
    $hauling_type,
    $trip_from,
    $trip_to,
    $quantity,
    $vessel_name,
    $voyage_no,
    $container_no_port,
    $bill_of_lading,
    $port_location,
    $customs_cleared
]);

// Workflow audit — matches dispatcher addbooking flow.
$stage_log = $conn->prepare(
    "INSERT INTO workflow_event (booking_no, stage, actor_role, actor_id, notes)
     VALUES (?, 'order_created', 'client', ?, 'Booking submitted via client portal')"
);
if ($stage_log) {
    $stage_log->execute([$booking_no, $userId]);
}

echo json_encode([
    'status'     => 'success',
    'message'    => 'Booking submitted.',
    'booking_no' => $booking_no,
]);
