<?php
include "../../config/config.php";
require_once __DIR__ . '/../../lib/container_lifecycle.php';   // pt_customer_is_import()

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $booking_date     = $_POST['booking_date'];
    $booking_required = $_POST['booking_required'];
    $booking_sn       = trim($_POST['booking_sn'] ?? '');
    $booking_do       = trim($_POST['booking_do'] ?? '');
    $haulingStart     = trim($_POST['haulingStart'] ?? '');
    $lastDayStorage   = trim($_POST['lastDayStorage'] ?? '');
    $lastDayDemurrage = trim($_POST['lastDayDemurrage'] ?? '');
    $lastDayDetention = trim($_POST['lastDayDetention'] ?? '');
    $costumer         = $_POST['costumer'];
    $container_seal   = $_POST['container_seal'];
    $container        = $_POST['container'];
    $container_status = $_POST['container_status'];
    $hauling_segment  = $_POST['hauling_segment'];
    $trip_from        = $_POST['trip_from'];
    $trip_to          = $_POST['trip_to'];
    $return_location  = trim($_POST['return_location'] ?? '');
    $booking_activity = $_POST['booking_activity'];
    $quantity         = (int)$_POST['quantity'];

    // Phase 2 — booking type + port fields.
    $booking_type      = $_POST['booking_type']      ?? 'Local';
    $vessel_name       = $_POST['vessel_name']       ?? '';
    $voyage_no         = $_POST['voyage_no']         ?? '';
    $container_no_port = $_POST['container_no_port'] ?? '';
    $bill_of_lading    = $_POST['bill_of_lading']    ?? '';
    $port_location     = $_POST['port_location']     ?? '';
    $customs_cleared   = isset($_POST['customs_cleared']) ? 1 : 0;

    // Whitelist booking_type — defensive against tampered POSTs.
    if (!in_array($booking_type, ['Local', 'Import', 'Export'], true)) {
        $booking_type = 'Local';
    }
    // Local bookings have no port context; clear those fields rather
    // than store stale values from a previous selection.
    if ($booking_type === 'Local') {
        $vessel_name = $voyage_no = $container_no_port = $bill_of_lading = $port_location = '';
        $customs_cleared = FALSE;
    }
    if ($costumer !== 'CTH') {
        $booking_sn = $booking_do = $haulingStart = $lastDayStorage = $lastDayDemurrage = $lastDayDetention = '';
    } elseif ($booking_required === '' && $lastDayDetention !== '') {
        $booking_required = $lastDayDetention;
    }

    // Phase 11 — container_status lifecycle whitelist. Legacy values
    // ('EMPTY'/'LOADED'/'N/A') get normalised onto the new base states
    // so dashboard tile colours render uniformly.
    $allowedContainerStatus = [
        'Empty', 'Empty Container Pickup', 'Empty Container On Trip', 'Empty Container Delivered',
        'Loaded', 'Loaded Container Pickup', 'Loaded Container On Trip', 'Loaded Container Delivered',
    ];
    $legacyContainerStatusMap = [
        'EMPTY'  => 'Empty',
        'LOADED' => 'Loaded',
        'N/A'    => 'Empty',
    ];
    if (isset($legacyContainerStatusMap[$container_status])) {
        $container_status = $legacyContainerStatusMap[$container_status];
    }
    // Import-direction customers (CTH + any customer flagged trade_type='Import')
    // start LOADED and later spawn an Empty return; Export starts Empty.
    $isImport = pt_customer_is_import($conn, $costumer);
    if (!in_array($container_status, $allowedContainerStatus, true)) {
        $container_status = $isImport ? 'Loaded' : 'Empty';
    }
    if ($isImport && !str_starts_with($container_status, 'Loaded')) {
        $container_status = 'Loaded';
    }
    if ($isImport && $return_location === '') {
        echo json_encode(["status" => "error", "message" => "Return Location is required for import bookings."]);
        exit;
    }

    // Phase 11 — stamp customer_segment from the selected client so the
    // booking carries its own snapshot (segment may be reassigned later).
    // Client-side form sends this as a hidden field; dispatcher/admin
    // forms send it from a picker. Either way we re-derive from the
    // customer table to guarantee correctness.
    $customer_segment = '';
    $segStmt = $conn->prepare("SELECT customer_segment FROM customer WHERE customer_code = ? LIMIT 1");
    if ($segStmt) {
        $segStmt->execute([$costumer]);
        $segResult = $segStmt->fetchColumn();
        if ($segResult !== false) {
            $customer_segment = $segResult ?? '';
        }
    }
    // Allow dispatcher override only when the customer row has no segment yet.
    if ($customer_segment === '' && !empty($_POST['customer_segment'])) {
        $customer_segment = trim($_POST['customer_segment']);
    }

    // Get hauling_type based on hauling_segment
    $hauling_type = '';
    $stmt1 = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ? LIMIT 1");
    $stmt1->execute([$hauling_segment]);
    $hauling_type_result = $stmt1->fetchColumn();
    if ($hauling_type_result !== false) {
        $hauling_type = $hauling_type_result;
    }
// Phase 14.1 — booking_no format: Customer-BN-From-To (with -N
    // suffix when the same triple already has a booking).
    require_once __DIR__ . '/../../lib/booking_no.php';
    $booking_no = bn_generate($conn, $costumer, $trip_from, $trip_to);

    $stmt2 = $conn->prepare("INSERT INTO booking (
        booking_no, booking_type, booking_date, booking_daterequired, booking_sn, booking_do,
        booking_haulingstartdate, booking_lastdatestorage, booking_lastdatedemurrage, booking_lastdatedetention,
        costumer, customer_segment, container_seal, container, booking_activity, container_status,
        hauling_segment, hauling_type, trip_from, trip_to, return_location, quantity, quantity_use, status,
        vessel_name, voyage_no, container_no_port, bill_of_lading, port_location, customs_cleared
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Active', ?, ?, ?, ?, ?, ?)");

    

    // PostgreSQL rejects '' for DATE / BOOLEAN params, so coerce optional
    // dates to NULL and customs_cleared to a real bool, then bindValue
    // with explicit types instead of relying on execute([])'s default
    // PARAM_STR coercion.
    $nullIfBlank = fn($v) => ($v === '' || $v === null) ? null : $v;
    $haulingStart     = $nullIfBlank($haulingStart);
    $lastDayStorage   = $nullIfBlank($lastDayStorage);
    $lastDayDemurrage = $nullIfBlank($lastDayDemurrage);
    $lastDayDetention = $nullIfBlank($lastDayDetention);
    $booking_required = $nullIfBlank($booking_required);
    $customsCleared   = (bool)$customs_cleared;

    // Also make booking_daterequired nullable on the off-chance the form
    // sent it blank. booking_date itself stays required by the form.
    $stmt2->bindValue(1,  $booking_no,            PDO::PARAM_STR);
    $stmt2->bindValue(2,  $booking_type,          PDO::PARAM_STR);
    $stmt2->bindValue(3,  $booking_date,          PDO::PARAM_STR);
    $stmt2->bindValue(4,  $booking_required,      $booking_required === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt2->bindValue(5,  $booking_sn,            PDO::PARAM_STR);
    $stmt2->bindValue(6,  $booking_do,            PDO::PARAM_STR);
    $stmt2->bindValue(7,  $haulingStart,          $haulingStart     === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt2->bindValue(8,  $lastDayStorage,        $lastDayStorage   === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt2->bindValue(9,  $lastDayDemurrage,      $lastDayDemurrage === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt2->bindValue(10, $lastDayDetention,      $lastDayDetention === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt2->bindValue(11, $costumer,              PDO::PARAM_STR);
    $stmt2->bindValue(12, $customer_segment,      PDO::PARAM_STR);
    $stmt2->bindValue(13, $container_seal,        PDO::PARAM_STR);
    $stmt2->bindValue(14, $container,             PDO::PARAM_STR);
    $stmt2->bindValue(15, $booking_activity,      PDO::PARAM_STR);
    $stmt2->bindValue(16, $container_status,      PDO::PARAM_STR);
    $stmt2->bindValue(17, $hauling_segment,       PDO::PARAM_STR);
    $stmt2->bindValue(18, $hauling_type,          PDO::PARAM_STR);
    $stmt2->bindValue(19, $trip_from,             PDO::PARAM_STR);
    $stmt2->bindValue(20, $trip_to,               PDO::PARAM_STR);
    $stmt2->bindValue(21, $return_location,       PDO::PARAM_STR);
    $stmt2->bindValue(22, $quantity,              PDO::PARAM_INT);
    $stmt2->bindValue(23, $vessel_name,           PDO::PARAM_STR);
    $stmt2->bindValue(24, $voyage_no,             PDO::PARAM_STR);
    $stmt2->bindValue(25, $container_no_port,     PDO::PARAM_STR);
    $stmt2->bindValue(26, $bill_of_lading,        PDO::PARAM_STR);
    $stmt2->bindValue(27, $port_location,         PDO::PARAM_STR);
    $stmt2->bindValue(28, $customsCleared,        PDO::PARAM_BOOL);

    if ($stmt2->execute()) {
        // Phase 1 workflow audit — every new booking starts at order_created.
        $logged_no = $booking_no;
        $stage_log = $conn->prepare(
            "INSERT INTO workflow_event (booking_no, stage, actor_role, notes) VALUES (?, 'order_created', 'system', 'Booking created via addbooking form')"
        );
        if ($stage_log) {
            $stage_log->execute([$logged_no]);
}
        echo json_encode(["status" => "success", "message" => "Booking successfully saved.", "booking_no" => $booking_no]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save booking: " . ($stmt2->errorInfo()[2] ?? '')]);
    }

}
?>
