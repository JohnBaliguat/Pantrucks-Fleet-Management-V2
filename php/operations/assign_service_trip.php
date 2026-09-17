<?php
// Service Trip assignment — non-booked route assigned by the dispatcher.
//
// Used when a driver needs to go somewhere that isn't a customer booking:
// repositioning, fuel run, shop visit, trailer pickup, etc.
//
// Mirrors the booking DnD flow (assign_booking_dnd.php) but:
//   * No booking lookup / no quantity_use bump
//   * No CTH / EIR logic
//   * Trip Receipt is OPTIONAL
//   * trips.trip_purpose = 'Service'
//   * dispatch.costumer = 'INTERNAL' (sentinel, excluded from billing reports)
//   * dispatch.booking_no = '' (empty)

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/settings_helper.php';

function ensureTripsColumn(PDO $conn, string $column, string $definition): void {
    pt_ensure_column($conn, 'trips', $column, $definition);
}

// Inline safety net — the migration file is the authoritative source, but
// these guards keep this endpoint self-bootstrapping on fresh installs.
ensureTripsColumn($conn, 'trip_purpose',    "VARCHAR(20) NOT NULL DEFAULT 'Booking'");
ensureTripsColumn($conn, 'service_reason',  "VARCHAR(60) NOT NULL DEFAULT ''");
ensureTripsColumn($conn, 'service_remarks', "VARCHAR(255) NOT NULL DEFAULT ''");

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

$driver_id       = (int)($_POST['driver_id'] ?? 0);
$trip_from       = trim($_POST['trip_from'] ?? '');
$trip_to         = trim($_POST['trip_to'] ?? '');
$service_reason  = trim($_POST['service_reason'] ?? '');
$service_remarks = trim($_POST['service_remarks'] ?? '');
$trip_receipt    = preg_replace('/\D+/', '', trim($_POST['trip_receipt'] ?? ''));
$trailer         = trim($_POST['trailer'] ?? '');
$genset          = trim($_POST['genset'] ?? '');
$container       = strtoupper(trim($_POST['container'] ?? ''));
// Optional truck override — the dispatcher can re-assign a different truck than
// the driver's shift truck for this service trip.
$truckInput      = strtoupper(trim($_POST['truck'] ?? ''));
// Admin toggle — allow assigning a truck/trailer/genset already in use on
// another driver/trip. When off, the "already assigned" guards apply.
$allowInUseEquip = pt_setting_bool($conn, 'allow_inuse_equipment', false);

if ($driver_id <= 0)         { echo json_encode(['status' => 'error', 'message' => 'Driver is required.']); exit; }
if ($trip_from === '')       { echo json_encode(['status' => 'error', 'message' => 'Trip From is required.']); exit; }
if ($trip_to === '')         { echo json_encode(['status' => 'error', 'message' => 'Trip To is required.']); exit; }
if ($service_reason === '')  { echo json_encode(['status' => 'error', 'message' => 'Service Reason is required.']); exit; }
if ($trip_receipt !== '' && !preg_match('/^\d{6}$/', $trip_receipt)) {
    echo json_encode(['status' => 'error', 'message' => 'Trip Receipt must be exactly 6 numbers.']);
    exit;
}

$dispatcherId   = (int)($_SESSION['user_id'] ?? 0);
$dispatcherName = 'system';
if ($dispatcherId > 0) {
    $stmtName = $conn->prepare("SELECT user_fname, user_mname, user_lname FROM \"user\" WHERE user_id = ? LIMIT 1");
    $stmtName->execute([$dispatcherId]);
    $uRow = $stmtName->fetch();
if ($uRow) {
        $parts = array_filter([
            trim((string)$uRow['user_fname']),
            trim((string)$uRow['user_mname']),
            trim((string)$uRow['user_lname']),
        ], 'strlen');
        if (!empty($parts)) $dispatcherName = implode(' ', $parts);
    }
}
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');

$conn->beginTransaction();
try {
    // ---- Driver guard -------------------------------------------------
    $stmt = $conn->prepare(
        "SELECT d.driver_id,
                CONCAT(d.driver_lname, ', ', d.driver_fname) AS driver_name,
                d.shift_truck,
                d.driver_assignbase,
                EXISTS(SELECT 1 FROM driver_shift s WHERE s.driver_id = d.driver_id AND s.ended_at IS NULL) AS on_shift,
                (SELECT COUNT(*)
                   FROM dispatch dd
                   WHERE dd.driver_id = d.driver_id
                     AND dd.workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')
                ) AS active_trips
         FROM drivers d WHERE d.driver_id = ? LIMIT 1"
    );
    $stmt->execute([$driver_id]);
    $drv = $stmt->fetch();
if (!$drv)                                              throw new Exception("Driver not found.");
    if (function_exists('pt_driver_has_active_violation') && pt_driver_has_active_violation($conn, $driver_id)) {
        throw new Exception(pt_driver_violation_block_message());
    }

    $onShift       = (int)$drv['on_shift'] === 1;
    $hasShiftTruck = trim((string)$drv['shift_truck']) !== '';
    // The driver needs a usable shift (open shift + truck). When they're off
    // shift, OR on an open shift with no truck (an orphan shift), the dispatcher
    // enters a truck and we start / repair the shift inline.
    if (!$onShift || !$hasShiftTruck) {
        if ($truckInput === '') {
            throw new Exception("This driver has no active truck — enter a truck to start their shift.");
        }
        // The entered truck must be a free, good truck unit.
        $tchk = $conn->prepare("SELECT unit_status, maintenance_blocked, dispatch_blocked, unit_type, driver_id FROM units WHERE unit_name = ? LIMIT 1");
        $tchk->execute([$truckInput]);
        $tr2 = $tchk->fetch();
        if (!$tr2)                                                 throw new Exception("Truck '{$truckInput}' not found.");
        if (trim((string)($tr2['unit_type'] ?? '')) !== 'truck')   throw new Exception("'{$truckInput}' is not a truck unit.");
        if ((int)$tr2['maintenance_blocked'] === 1)                throw new Exception("Truck '{$truckInput}' is maintenance-blocked.");
        if ((int)($tr2['dispatch_blocked'] ?? 0) === 1)            throw new Exception("Truck '{$truckInput}' is blocked by Dispatch Admin and cannot be assigned.");
        if (in_array($tr2['unit_status'], ['Rescue', 'Shop Unit'], true)) throw new Exception("Truck '{$truckInput}' is unavailable: " . $tr2['unit_status']);
        if (
            !$allowInUseEquip &&
            (int)($tr2['driver_id'] ?? 0) !== 0 &&
            (int)$tr2['driver_id'] !== $driver_id
        ) {
            throw new Exception("Truck '{$truckInput}' is already assigned to another driver.");
        }
        if (!$onShift) {
            // No open shift → start one.
            $conn->prepare("INSERT INTO driver_shift (driver_id, truck_code, started_at, notes) VALUES (?, ?, NOW(), ?)")
                 ->execute([$driver_id, $truckInput, 'Auto-started by dispatcher service trip.']);
        } else {
            // Open shift with no truck → set its truck.
            $conn->prepare("UPDATE driver_shift SET truck_code = ? WHERE driver_id = ? AND ended_at IS NULL")
                 ->execute([$truckInput, $driver_id]);
        }
        $conn->prepare("UPDATE drivers SET shift_truck = ?, shift_started_at = NOW(), shift_ended_at = NULL WHERE driver_id = ?")
             ->execute([$truckInput, $driver_id]);
        $drv['shift_truck'] = $truckInput;
        $onShift = true;
    }

    if (!$onShift)                                          throw new Exception("Driver is not on an active shift.");
    if (trim($drv['shift_truck']) === '' && $truckInput === '') throw new Exception("Driver has no truck selected for this shift.");
    if ((int)$drv['active_trips'] >= 2)                     throw new Exception("Driver already has 2 active trips.");

    $shiftTruck  = trim((string)$drv['shift_truck']);
    // Use the dispatcher's truck override when provided, else the shift truck.
    $truck       = $truckInput !== '' ? $truckInput : $shiftTruck;
    $dispatchHub = $drv['driver_assignbase'] ?: '';
    $driverName  = $drv['driver_name'];

    // ---- Truck guard --------------------------------------------------
    $stmt = $conn->prepare("SELECT unit_status, maintenance_blocked, dispatch_blocked, unit_type, driver_id FROM units WHERE unit_name = ? LIMIT 1");
    $stmt->execute([$truck]);
    $tr = $stmt->fetch();
if (!$tr)                                               throw new Exception("Truck '{$truck}' not found in units.");
    if ((int)$tr['maintenance_blocked'] === 1)              throw new Exception("Truck '{$truck}' is maintenance-blocked.");
    if ((int)($tr['dispatch_blocked'] ?? 0) === 1)          throw new Exception("Truck '{$truck}' is blocked by Dispatch Admin and cannot be assigned.");
    if (in_array($tr['unit_status'], ['Rescue', 'Shop Unit'], true)) {
        throw new Exception("Truck '{$truck}' is unavailable: " . $tr['unit_status']);
    }
    // When overriding to a truck that isn't the driver's own, it must be a
    // truck unit that's free (not held by another driver).
    if (strcasecmp($truck, $shiftTruck) !== 0) {
        if (trim((string)($tr['unit_type'] ?? '')) !== 'truck') throw new Exception("'{$truck}' is not a truck unit.");
        if (
            !$allowInUseEquip &&
            (int)($tr['driver_id'] ?? 0) !== 0 &&
            (int)$tr['driver_id'] !== $driver_id
        ) {
            throw new Exception("Truck '{$truck}' is already assigned to another driver.");
        }
    }

    // ---- Trailer guard (optional) ------------------------------------
    if ($trailer !== '') {
        $stmt = $conn->prepare("SELECT trailer_status, maintenance_blocked, trailer_assignto, driver_id FROM trailer WHERE trailer_name = ? LIMIT 1");
        $stmt->execute([$trailer]);
        $trlRow = $stmt->fetch();
if (!$trlRow)                                       throw new Exception("Trailer not found.");
        if ((int)$trlRow['maintenance_blocked'] === 1)      throw new Exception("Trailer is maintenance-blocked.");
        if (in_array($trlRow['trailer_status'], ['Rescue', 'Shop Unit'], true)) {
            throw new Exception("Trailer is unavailable: " . $trlRow['trailer_status']);
        }
        if (
            !$allowInUseEquip &&
            $trlRow['trailer_assignto'] !== '' &&
            (int)$trlRow['driver_id'] !== 0 &&
            (int)$trlRow['driver_id'] !== $driver_id
        ) {
            throw new Exception("Trailer is already assigned to another driver.");
        }
    }

    // ---- Genset guard (optional) -------------------------------------
    if ($genset !== '') {
        $stmt = $conn->prepare("SELECT unit_status, maintenance_blocked, unit_assign, driver_id FROM units WHERE unit_name = ? LIMIT 1");
        $stmt->execute([$genset]);
        $gsRow = $stmt->fetch();
if (!$gsRow)                                        throw new Exception("Genset not found.");
        if ((int)$gsRow['maintenance_blocked'] === 1)       throw new Exception("Genset is maintenance-blocked.");
        if (in_array($gsRow['unit_status'], ['Rescue', 'Shop Unit'], true)) {
            throw new Exception("Genset is unavailable: " . $gsRow['unit_status']);
        }
        if (
            !$allowInUseEquip &&
            $gsRow['unit_assign'] !== '' &&
            (int)$gsRow['driver_id'] !== 0 &&
            (int)$gsRow['driver_id'] !== $driver_id
        ) {
            throw new Exception("Genset is already assigned to another driver.");
        }
    }

    // ---- Trip Receipt uniqueness (only when provided) -----------------
    if ($trip_receipt !== '') {
        $stmt = $conn->prepare(
            "SELECT 1 FROM dispatch d
             INNER JOIN trips t ON t.d_id = d.d_id
             WHERE d.d_tripreceipt = ? AND t.trip_status != 'Done'
             LIMIT 1"
        );
        $stmt->execute([$trip_receipt]);
        $dup = $stmt->fetch();
if ($dup) throw new Exception("Trip Receipt already in use on an active trip.");
    }

    // ---- Dispatch row -------------------------------------------------
    // Service trips carry a generated reference: Service-<mmddyy>-<n>, where <n>
    // increments per day (e.g. Service-070826-1). costumer='INTERNAL' +
    // trip_purpose='Service' remain the authoritative service-trip markers.
    $serviceCustomer = 'INTERNAL';
    $svcPrefix = 'Service-' . date('mdy') . '-';
    $svcSeqStmt = $conn->prepare("SELECT booking_no FROM dispatch WHERE booking_no LIKE ?");
    $svcSeqStmt->execute([$svcPrefix . '%']);
    $svcMax = 0;
    while ($svcRow = $svcSeqStmt->fetch()) {
        if (preg_match('/-(\d+)$/', (string)$svcRow['booking_no'], $m)) {
            $svcMax = max($svcMax, (int)$m[1]);
        }
    }
    $serviceBookingNo = $svcPrefix . ($svcMax + 1);
    $stmt = $conn->prepare(
        "INSERT INTO dispatch
            (booking_no, d_datetime, d_dispatcher, d_dispatchhub, d_drivername, driver_id,
             d_truck, d_trailer, d_genset, d_tripreceipt, costumer,
             workflow_stage, workflow_updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'dispatcher_assigned', ?)
         RETURNING d_id"
    );

    $stmt->execute([$serviceBookingNo, $now, $dispatcherName, $dispatchHub, $driverName, $driver_id,
        $truck, $trailer, $genset, $trip_receipt, $serviceCustomer, $now]);
    $d_id = (int)$stmt->fetchColumn();
// ---- Trip 1 (service) --------------------------------------------
    // Hauling segment is fixed to 'SERVICE' so it reads clearly on the trip
    // ticket, reports, and monitoring (service trips aren't tied to a booking).
    $stmt = $conn->prepare(
        "INSERT INTO trips
            (d_id, trip_type, trip_purpose, service_reason, service_remarks,
             costumer, trip_haulingsegment, trip_from, trip_to, trip_container, trip_status, segment_status)
         VALUES (?, 'Trip 1', 'Service', ?, ?, ?, 'SERVICE', ?, ?, ?, 'Active', 'Assigned')"
    );

    $stmt->execute([$d_id, $service_reason, $service_remarks, $serviceCustomer, $trip_from, $trip_to, $container]);
// ---- Driver / truck status flips ----------------------------------
    $stmt = $conn->prepare("UPDATE drivers SET driver_status = 'Dispatch' WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
$stmt = $conn->prepare(
        "UPDATE units SET unit_assign = ?, driver_id = ?, unit_assigngenset = ?, unit_assigntrailer = ?, unit_status = 'Dispatch' WHERE unit_name = ?"
    );
    $stmt->execute([$driverName, $driver_id, $genset, $trailer, $truck]);
// ---- Trailer assignment + movement log ---------------------------
    if ($trailer !== '') {
        $stmt = $conn->prepare("UPDATE trailer SET trailer_assignto = ?, driver_id = ? WHERE trailer_name = ?");
        $stmt->execute([$driverName, $driver_id, $trailer]);
$stmt = $conn->prepare(
            "INSERT INTO trailer_movement
                (tm_trailername, tm_driverassign, tm_location, tm_recordedtype, tm_recordedby, tm_date)
             VALUES (?, ?, ?, 'Dispatch', ?, ?)"
        );
        $stmt->execute([$trailer, $driverName, $trip_to, $dispatcherName, $now]);
}

    // ---- Genset assignment -------------------------------------------
    if ($genset !== '') {
        $stmt = $conn->prepare("UPDATE units SET unit_assign = ?, driver_id = ?, unit_status = 'Dispatch' WHERE unit_name = ?");
        $stmt->execute([$driverName, $driver_id, $genset]);
}

    // ---- Workflow audit ----------------------------------------------
    $stage = 'dispatcher_assigned';
    $note  = 'Service trip assigned via tile dashboard (' . $service_reason . ').';
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, ?, 'dispatcher', ?, ?)"
    );
    $stmt->execute([$d_id, $serviceBookingNo, $stage, $dispatcherId, $note]);
$conn->commit();
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Service trip assigned.',
        'd_id'      => $d_id,
        'driver_id' => $driver_id,
        'truck'     => $truck,
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
