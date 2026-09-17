<?php
// Phase 14 — Empty Delivered → create new Loaded booking.
//
// The Loaded leg is a SEPARATE booking, not a flip of the empty row.
// Reasoning (per ops spec):
//   • The original empty-leg booking stays at 'Empty Container Delivered'
//     so the audit + billing record is preserved.
//   • The new Loaded booking starts a fresh container_status lifecycle.
//   • trip_from for the new booking is the empty leg's trip_to (i.e.
//     where the container currently sits after the empty drop).
//   • trip_to for the new booking is chosen by the dispatcher at this
//     step — the loaded destination is only known once the customer
//     confirms it.
//
// Customer / segment / seal / container / hauling fields are carried
// forward from the parent; the dispatcher can edit them on the booking
// page if needed.
//
// We stamp the parent booking_no into the workflow_event notes so the
// chain is traceable without a schema change.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';

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
$parent_d_id       = (int)($_POST['parent_d_id'] ?? 0);
$loaded_trip_to    = trim($_POST['trip_to']    ?? '');

if ($parent_booking_no === '') {
    echo json_encode(['status' => 'error', 'message' => 'booking_no required']);
    exit;
}
if ($parent_d_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'parent_d_id required']);
    exit;
}
if ($loaded_trip_to === '') {
    echo json_encode(['status' => 'error', 'message' => 'Loaded delivery location is required.']);
    exit;
}

// Source booking/transaction — must be a completed empty transaction.
$stmt = $conn->prepare(
    "SELECT b.booking_id, b.booking_no, b.costumer, b.customer_segment, b.container_seal, b.container,
            b.container_status, b.hauling_segment, b.hauling_type, b.trip_from, b.trip_to,
            b.booking_daterequired, b.booking_activity, b.booking_sn, b.booking_do,
            b.booking_haulingstartdate, b.booking_lastdatestorage, b.booking_lastdatedemurrage,
            b.booking_lastdatedetention, d.d_id, d.workflow_stage,
            d.d_trailer, d.d_genset,
            t.trip_container, t.trip_haulingsegment, t.trip_haulingtype
     FROM booking b
     INNER JOIN dispatch d ON d.booking_no = b.booking_no
     LEFT JOIN trips t ON t.trip_id = (SELECT MAX(t2.trip_id) FROM trips t2 WHERE t2.d_id = d.d_id)
     WHERE b.booking_no = ? AND d.d_id = ?
     LIMIT 1"
);
$stmt->execute([$parent_booking_no, $parent_d_id]);
$src = $stmt->fetch();
if (!$src) {
    echo json_encode(['status' => 'error', 'message' => 'Parent empty transaction not found.']);
    exit;
}

// Container / hauling can live on the trip, not the booking row — fall back to
// the parent trip's values so the new Loaded booking doesn't end up blank.
$srcContainer    = trim((string)($src['trip_container']     ?? '')) !== '' ? $src['trip_container']     : (string)$src['container'];
$srcHaulingSeg   = trim((string)($src['trip_haulingsegment']?? '')) !== '' ? $src['trip_haulingsegment']: (string)$src['hauling_segment'];
$srcHaulingType  = trim((string)($src['trip_haulingtype']   ?? '')) !== '' ? $src['trip_haulingtype']   : (string)$src['hauling_type'];
if (cl_normalise_lane((string)$src['container_status']) !== 'empty') {
    echo json_encode(['status' => 'error', 'message' => 'Only completed empty legs can spawn a Loaded booking (current: ' . $src['container_status'] . ').']);
    exit;
}
if (!in_array((string)$src['workflow_stage'], ['pod_captured', 'billing_closed', 'client_notified'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Only completed empty transactions can create a Loaded booking.']);
    exit;
}

// Align with the tracking page: completed empty transactions should be at
// *Delivered before we spawn the loaded leg.
if (cl_normalise_stage((string)$src['container_status']) !== 'delivered') {
    cl_advance_booking($conn, $parent_booking_no, 'delivered');
}

// Don't spawn twice for the same completed empty transaction.
$stmt = $conn->prepare(
    "SELECT 1 FROM workflow_event
     WHERE actor_role = 'dispatcher' AND stage = 'loaded_ready'
       AND notes LIKE ? LIMIT 1"
);
$stmt->execute(['%parent_d_id=' . $parent_d_id . '%']);
$existing = $stmt->fetch();
if ($existing) {
    echo json_encode(['status' => 'error', 'message' => 'A Loaded booking has already been created for this completed empty transaction.']);
    exit;
}

$loaded_trip_from = $src['trip_to'];                 // empty leg's drop-off
$today            = date('Y-m-d');
$new_required     = $today;                          // surface on dispatch board today

$conn->beginTransaction();
try {
    // Phase 14.1 — booking_no format: Customer-BN-From-To.
    require_once __DIR__ . '/../lib/booking_no.php';

    $new_booking_type     = 'Local';
    $new_container_status = 'Loaded';
    $new_quantity         = 1;
    $new_customs_cleared  = false;
    $blank                = '';

    $stmt = $conn->prepare(
        "INSERT INTO booking (
            booking_no, booking_type, booking_date, booking_daterequired, booking_sn, booking_do,
            booking_haulingstartdate, booking_lastdatestorage, booking_lastdatedemurrage, booking_lastdatedetention,
            costumer, customer_segment, container_seal, container, booking_activity, container_status,
            hauling_segment, hauling_type, trip_from, trip_to, return_location, quantity, quantity_use, status,
            vessel_name, voyage_no, container_no_port, bill_of_lading, port_location, customs_cleared
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Active', '', '', '', '', '', ?)"
    );

    // Null-safe binders: NOT-NULL text columns must never get a PHP null
    // (becomes '' instead), and DATE columns must never get '' (becomes NULL).
    $asStr  = function ($v) { return (string)($v ?? ''); };
    $asDate = function ($v) { $v = trim((string)($v ?? '')); return $v === '' ? null : $v; };

    // Static binds (everything except booking_no, which is set per attempt).
    $stmt->bindValue(2,  $new_booking_type,                  PDO::PARAM_STR);
    $stmt->bindValue(3,  $today,                             PDO::PARAM_STR);
    $stmt->bindValue(4,  $new_required,                      PDO::PARAM_STR);
    $stmt->bindValue(5,  $asStr($src['booking_sn']),         PDO::PARAM_STR);
    $stmt->bindValue(6,  $asStr($src['booking_do']),         PDO::PARAM_STR);
    $stmt->bindValue(7,  $asDate($src['booking_haulingstartdate']),  PDO::PARAM_STR);
    $stmt->bindValue(8,  $asDate($src['booking_lastdatestorage']),   PDO::PARAM_STR);
    $stmt->bindValue(9,  $asDate($src['booking_lastdatedemurrage']), PDO::PARAM_STR);
    $stmt->bindValue(10, $asDate($src['booking_lastdatedetention']), PDO::PARAM_STR);
    $stmt->bindValue(11, $asStr($src['costumer']),           PDO::PARAM_STR);
    $stmt->bindValue(12, $asStr($src['customer_segment']),   PDO::PARAM_STR);
    $stmt->bindValue(13, $asStr($src['container_seal']),     PDO::PARAM_STR);
    $stmt->bindValue(14, $asStr($srcContainer),              PDO::PARAM_STR);
    $stmt->bindValue(15, $asStr($src['booking_activity']),   PDO::PARAM_STR);
    $stmt->bindValue(16, $new_container_status,              PDO::PARAM_STR);
    $stmt->bindValue(17, $asStr($srcHaulingSeg),             PDO::PARAM_STR);
    $stmt->bindValue(18, $asStr($srcHaulingType),            PDO::PARAM_STR);
    $stmt->bindValue(19, $loaded_trip_from,                  PDO::PARAM_STR);
    $stmt->bindValue(20, $loaded_trip_to,                    PDO::PARAM_STR);
    $stmt->bindValue(21, $blank,                             PDO::PARAM_STR);
    $stmt->bindValue(22, $new_quantity,                     PDO::PARAM_INT);
    $stmt->bindValue(23, $new_customs_cleared,               PDO::PARAM_BOOL);

    // Generate a unique booking_no, retrying on a rare collision (two
    // concurrent Create-Loaded clicks computing the same MAX()+1 sequence).
    $new_booking_no = '';
    $inserted = false;
    for ($attempt = 0; $attempt < 4 && !$inserted; $attempt++) {
        $new_booking_no = bn_generate($conn, $src['costumer'], $loaded_trip_from, $loaded_trip_to);
        $stmt->bindValue(1, $new_booking_no, PDO::PARAM_STR);
        $conn->exec('SAVEPOINT sp_mark_loaded');
        try {
            $stmt->execute();
            $inserted = true;
        } catch (PDOException $e) {
            $conn->exec('ROLLBACK TO SAVEPOINT sp_mark_loaded');
            if ($e->getCode() !== '23505') { throw $e; }   // not a duplicate-key → real error
        }
    }
    if (!$inserted) {
        throw new Exception('Could not generate a unique booking number — please try again.');
    }

    // Workflow audit — both sides of the link.
    $userId = (int)($_SESSION['user_id'] ?? 0);

    $noteChild  = "Spawned from empty leg parent=$parent_booking_no parent_d_id=$parent_d_id (route: $loaded_trip_from → $loaded_trip_to)";
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, 'order_created', 'dispatcher', ?, ?)"
    );
    $stmt->execute([$new_booking_no, $userId, $noteChild]);

    $noteParent = "Loaded booking child=$new_booking_no created (parent=$parent_booking_no, parent_d_id=$parent_d_id, route: $loaded_trip_from → $loaded_trip_to)";
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, 'loaded_ready', 'dispatcher', ?, ?)"
    );
    $stmt->execute([$parent_booking_no, $userId, $noteParent]);

    $conn->commit();
    echo json_encode([
        'status'        => 'success',
        'message'       => 'Loaded booking created.',
        'parent'        => $parent_booking_no,
        'new_booking'   => $new_booking_no,
        'customer'      => $src['costumer'],
        'trip_from'     => $loaded_trip_from,
        'trip_to'       => $loaded_trip_to,
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Failed: ' . $e->getMessage()]);
}
