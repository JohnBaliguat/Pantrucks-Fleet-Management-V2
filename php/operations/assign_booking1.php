<?php
session_start();
include "../config/config.php";

header('Content-Type: application/json');
date_default_timezone_set("Asia/Manila");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

$dispatcher  = trim((string)($_POST['userName'] ?? ''));
$dispatchHub = trim((string)($_POST['assignLocation'] ?? ''));
$driver      = trim((string)($_POST['driver'] ?? ''));
$driverId    = (int)($_POST['driver_id'] ?? 0);
$genset      = trim((string)($_POST['genset'] ?? ''));
$unitName    = trim((string)($_POST['unitName'] ?? ''));
$booking1    = json_decode($_POST['booking1'] ?? '{}', true) ?: [];
$booking2    = json_decode($_POST['booking2'] ?? '{}', true) ?: [];
$now         = date("Y-m-d H:i:s");
$actorId     = (int)($_SESSION['user_id'] ?? 0);

if ($driver === '' || $driverId <= 0 || $unitName === '') {
    echo json_encode(["status" => "error", "message" => "Driver and unit are required."]);
    exit;
}

function computeKM($conn, $from, $to)
{
    if ($from === '' || $to === '') {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT location_name, latitude, longitude
         FROM location
         WHERE location_name IN (?, ?)"
    );
    $stmt->execute([$from, $to]);
    $res = $stmt;

    $locations = [];
    while ($row = $res->fetch()) {
        $locations[$row['location_name']] = $row;
    }
if (!isset($locations[$from], $locations[$to])) {
        return 0;
    }

    $lat1 = (float)$locations[$from]['latitude'];
    $lon1 = (float)$locations[$from]['longitude'];
    $lat2 = (float)$locations[$to]['latitude'];
    $lon2 = (float)$locations[$to]['longitude'];

    return round(sqrt(pow($lat2 - $lat1, 2) + pow($lon2 - $lon1, 2)) * 111, 2);
}

function validateTruck($conn, $unitName, $driver, $driverId)
{
    $stmt = $conn->prepare("SELECT unit_status, maintenance_blocked, unit_assign, driver_id FROM units WHERE unit_name = ? LIMIT 1");
    $stmt->execute([$unitName]);
    $unit = $stmt->fetch();
if (!$unit) {
        throw new Exception("Truck is not registered.");
    }

    if ((int)($unit['maintenance_blocked'] ?? 0) === 1) {
        throw new Exception("Truck is blocked for maintenance.");
    }

    if (in_array($unit['unit_status'], ['Rescue', 'Rescue Unit', 'Shop Unit'], true)) {
        throw new Exception("Truck is unavailable (" . $unit['unit_status'] . ").");
    }

    if (!empty($unit['unit_assign']) && (int)$unit['driver_id'] !== 0) {
        if ($unit['unit_assign'] !== $driver || (int)$unit['driver_id'] !== $driverId) {
            throw new Exception("Truck is already assigned to another driver.");
        }
    }
}

function validateOptionalUnit($conn, $unitName, $typeLabel)
{
    if ($unitName === '') {
        return;
    }

    if ($typeLabel === 'Trailer') {
        $stmt = $conn->prepare("SELECT trailer_status, maintenance_blocked FROM trailer WHERE trailer_name = ? LIMIT 1");
        $stmt->execute([$unitName]);
        $row = $stmt->fetch();
if (!$row) {
            throw new Exception("Trailer $unitName is not registered.");
        }
        if ((int)($row['maintenance_blocked'] ?? 0) === 1) {
            throw new Exception("Trailer $unitName is blocked for maintenance.");
        }
        if (in_array($row['trailer_status'], ['Rescue', 'Rescue Unit', 'Shop Unit'], true)) {
            throw new Exception("Trailer $unitName is unavailable (" . $row['trailer_status'] . ").");
        }
        return;
    }

    $stmt = $conn->prepare("SELECT unit_status, maintenance_blocked, dispatch_blocked FROM units WHERE unit_name = ? LIMIT 1");
    $stmt->execute([$unitName]);
    $row = $stmt->fetch();
if (!$row) {
        throw new Exception("$typeLabel $unitName is not registered.");
    }
    if ((int)($row['maintenance_blocked'] ?? 0) === 1) {
        throw new Exception("$typeLabel $unitName is blocked for maintenance.");
    }
    if ((int)($row['dispatch_blocked'] ?? 0) === 1) {
        throw new Exception("$typeLabel $unitName is blocked by Dispatch Admin and cannot be assigned.");
    }
    if (in_array($row['unit_status'], ['Rescue', 'Rescue Unit', 'Shop Unit'], true)) {
        throw new Exception("$typeLabel $unitName is unavailable (" . $row['unit_status'] . ").");
    }
}

function ensureDriverAvailable($conn, $driverId)
{
    if (pt_driver_has_active_violation($conn, (int)$driverId)) {
        throw new Exception(pt_driver_violation_block_message());
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS active_count
         FROM dispatch d
         WHERE d.driver_id = ?
           AND d.workflow_stage IN (
             'dispatcher_assigned',
             'reassigned',
             'driver_accepted',
             'gate_cleared',
             'en_route',
             'pending_verification'
           )"
    );
    $stmt->execute([$driverId]);
    $row = $stmt->fetch();
if ((int)($row['active_count'] ?? 0) > 0) {
        throw new Exception("Driver still has an active dispatch workflow.");
    }
}

function ensureOpenShift($conn, $driverId, $truckCode, $bookingNo)
{
    if ($driverId <= 0 || $truckCode === '') {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT ds_id
         FROM driver_shift
         WHERE driver_id = ? AND ended_at IS NULL
         ORDER BY ds_id DESC
         LIMIT 1"
    );
    $stmt->execute([$driverId]);
    $openShift = $stmt->fetch();
if ($openShift) {
        return;
    }

    $notes = 'Auto-started by dispatcher assignment for ' . $bookingNo;
    $stmt = $conn->prepare(
        "INSERT INTO driver_shift (driver_id, truck_code, started_at, notes)
         VALUES (?, ?, NOW(), ?)"
    );
    $stmt->execute([$driverId, $truckCode, $notes]);
$stmt = $conn->prepare(
        "UPDATE drivers
         SET shift_truck = ?, shift_started_at = NOW(), shift_ended_at = NULL
         WHERE driver_id = ?"
    );
    $stmt->execute([$truckCode, $driverId]);
}

function processBooking($conn, $data, $dispatcher, $dispatchHub, $driver, $driverId, $unitName, $genset, $now, $actorId)
{
    $bookingNo = trim((string)($data['booking_no'] ?? ''));
    if ($bookingNo === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM booking WHERE booking_no = ? LIMIT 1");
    $stmt->execute([$bookingNo]);
    $booking = $stmt->fetch();
if (!$booking) {
        throw new Exception("Booking not found: " . $bookingNo);
    }

    $trailer = trim((string)($data['trailer'] ?? ''));
    $receipt = trim((string)($data['receipt'] ?? ''));
    $ecs     = trim((string)($data['ecs'] ?? ''));

    $stmt = $conn->prepare(
        "INSERT INTO dispatch
         (booking_no, d_datetime, d_dispatcher, d_dispatchhub,
          d_drivername, driver_id, d_truck, d_trailer, d_genset,
          d_tripreceipt, d_ecs, costumer, workflow_stage, workflow_updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'dispatcher_assigned', NOW())
         RETURNING d_id"
    );
    $costumer = (string)($booking['costumer'] ?? '');

    $stmt->execute([
        $bookingNo,
        $now,
        $dispatcher,
        $dispatchHub,
        $driver,
        $driverId,
        $unitName,
        $trailer,
        $genset,
        $receipt,
        $ecs,
        $costumer
    ]);
    $dispatchId = (int)$stmt->fetchColumn();

    $tripDefs = [
        ['type' => 'Trip 1', 'from' => trim((string)($data['trip1_from'] ?? '')), 'to' => trim((string)($data['trip1_to'] ?? ''))],
        ['type' => 'Trip 2', 'from' => trim((string)($data['trip2_from'] ?? '')), 'to' => trim((string)($data['trip2_to'] ?? ''))],
    ];

    $lastDestination = '';
    foreach ($tripDefs as $tripDef) {
        if ($tripDef['from'] === '' || $tripDef['to'] === '') {
            continue;
        }

        $km = computeKM($conn, $tripDef['from'], $tripDef['to']);
        $lastDestination = $tripDef['to'];
        $segment = (string)($booking['hauling_segment'] ?? '');
        $haulingType = (string)($booking['hauling_type'] ?? '');
        $requiredDate = (string)($booking['booking_daterequired'] ?? '');
        $container = (string)($booking['container'] ?? '');
        $containerStatus = (string)($booking['container_status'] ?? '');
        $activity = (string)($booking['booking_activity'] ?? '');

        // Rate stamped at dispatch time via the SAME matcher the coupon uses,
        // so trips.piece_rate agrees with the Trip Verification Coupon.
        require_once __DIR__ . '/../helpers/trip_rate_lookup.php';
        $pieceRate = fleet_compute_trip_piece_rate($conn, $segment, $haulingType, $containerStatus);

        $stmt = $conn->prepare(
            "INSERT INTO trips
             (d_id, trip_type, costumer, trip_container, container_activity,
              trip_containerstat, trip_haulingsegment, trip_haulingtype,
              trip_from, trip_to, km_run, required_date, trip_status, piece_rate)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)"
        );

        $stmt->execute([
            $dispatchId,
            $tripDef['type'],
            $costumer,
            $container,
            $activity,
            $containerStatus,
            $segment,
            $haulingType,
            $tripDef['from'],
            $tripDef['to'],
            $km,
            $requiredDate,
            $pieceRate
        ]);
        // Auto-add a 0.00 placeholder Trip Rate for an un-priced SKU.
        require_once __DIR__ . '/../helpers/trip_rate_lookup.php';
        fleet_ensure_trip_rate_stub($conn, $segment, $haulingType, $containerStatus);
    }

    $stmt = $conn->prepare("UPDATE booking SET quantity_use = quantity_use + 1 WHERE booking_no = ?");
    $stmt->execute([$bookingNo]);

    $stmt = $conn->prepare("SELECT quantity, quantity_use FROM booking WHERE booking_no = ? LIMIT 1");
    $stmt->execute([$bookingNo]);
    $qtyRow = $stmt->fetch();
    if ($qtyRow && (int)$qtyRow['quantity_use'] >= (int)$qtyRow['quantity']) {
        $stmt = $conn->prepare("UPDATE booking SET status = 'Complete' WHERE booking_no = ?");
        $stmt->execute([$bookingNo]);
    }

    $stmt = $conn->prepare("UPDATE drivers SET driver_status = 'Dispatch' WHERE driver_id = ?");
    $stmt->execute([$driverId]);

    ensureOpenShift($conn, $driverId, $unitName, $bookingNo);

    $stmt = $conn->prepare(
        "UPDATE units
         SET unit_assign = ?, driver_id = ?, unit_assigngenset = ?, unit_assigntrailer = ?, unit_status = 'Dispatch'
         WHERE unit_name = ?"
    );
    $stmt->execute([$driver, $driverId, $genset, $trailer, $unitName]);
if ($trailer !== '') {
        $stmt = $conn->prepare("UPDATE trailer SET trailer_assignto = ?, driver_id = ? WHERE trailer_name = ?");
        $stmt->execute([$driver, $driverId, $trailer]);
if ($lastDestination !== '') {
            $recordedType = 'Dispatch';
            $stmt = $conn->prepare(
                "INSERT INTO trailer_movement
                 (tm_trailername, tm_driverassign, tm_location, tm_recordedtype, tm_recordedby, tm_date)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$trailer, $driver, $lastDestination, $recordedType, $dispatcher, $now]);
}
    }

    if ($genset !== '') {
        $stmt = $conn->prepare("UPDATE units SET unit_assign = ?, driver_id = ?, unit_status = 'Dispatch' WHERE unit_name = ?");
        $stmt->execute([$driver, $driverId, $genset]);
}

    $notes = 'Dispatch created via dispatch-test';
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, 'dispatcher_assigned', 'dispatcher', ?, ?)"
    );
    $stmt->execute([$dispatchId, $bookingNo, $actorId, $notes]);
return $dispatchId;
}

try {
    validateTruck($conn, $unitName, $driver, $driverId);
    validateOptionalUnit($conn, $genset, 'Genset');
    validateOptionalUnit($conn, trim((string)($booking1['trailer'] ?? '')), 'Trailer');
    validateOptionalUnit($conn, trim((string)($booking2['trailer'] ?? '')), 'Trailer');
    ensureDriverAvailable($conn, $driverId);

    $conn->beginTransaction();

    $dispatchId1 = processBooking($conn, $booking1, $dispatcher, $dispatchHub, $driver, $driverId, $unitName, $genset, $now, $actorId);
    $dispatchId2 = processBooking($conn, $booking2, $dispatcher, $dispatchHub, $driver, $driverId, $unitName, $genset, $now, $actorId);

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Dispatch assigned successfully",
        "dispatch_ids" => array_values(array_filter([$dispatchId1, $dispatchId2]))
    ]);
} catch (Throwable $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
