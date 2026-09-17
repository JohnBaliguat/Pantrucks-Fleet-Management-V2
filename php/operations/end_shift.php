<?php
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';

$truckCode = '';
$assignedTrailer = '';
$assignedGenset = '';

$stmt = $conn->prepare("SELECT shift_truck FROM drivers WHERE driver_id = ? LIMIT 1");
$stmt->execute([$driverId]);
$driverRow = $stmt->fetch();
$truckCode = trim((string)($driverRow['shift_truck'] ?? ''));

if ($truckCode !== '') {
    $stmt = $conn->prepare(
        "SELECT unit_assigntrailer, unit_assigngenset
         FROM units
         WHERE unit_name = ?
         LIMIT 1"
    );
    $stmt->execute([$truckCode]);
    $unitRow = $stmt->fetch();
$assignedTrailer = trim((string)($unitRow['unit_assigntrailer'] ?? ''));
    $assignedGenset = trim((string)($unitRow['unit_assigngenset'] ?? ''));
}

// Refuse if the driver has an in-flight job (anything past
// driver_accepted that isn't fully finished). Lets dispatchers see a
// reason and avoids a driver clocking out mid-trip.
$stmt = $conn->prepare(
    "SELECT d_id, booking_no, workflow_stage FROM dispatch
     WHERE driver_id = ?
       AND workflow_stage IN ('driver_accepted', 'gate_cleared', 'en_route', 'pending_verification')
     ORDER BY d_id DESC LIMIT 1"
);
$stmt->execute([$driverId]);
$blocking = $stmt->fetch();
if ($blocking) {
    json_out([
        'status'  => 'error',
        'message' => 'You still have an in-flight job (' . $blocking['booking_no'] . ' at stage ' . $blocking['workflow_stage'] . '). Finish or hand it off before ending your shift.',
    ], 409);
}

$conn->beginTransaction();
try {
    // Close the open driver_shift row (and capture machine hours).
    $stmt = $conn->prepare(
        "UPDATE driver_shift
         SET ended_at = NOW(),
             machine_hours = ROUND(EXTRACT(EPOCH FROM (NOW() - started_at)) / 3600.0, 2)
         WHERE driver_id = ? AND ended_at IS NULL"
    );
    $stmt->execute([$driverId]);
    $rowsClosed = $stmt->rowCount();
if ($rowsClosed === 0) {
        $conn->rollBack();
        json_out(['status' => 'error', 'message' => 'No active shift to end.'], 409);
    }

    // Read back the just-closed shift so we can show the driver their hours.
    $stmt = $conn->prepare(
        "SELECT ds_id, truck_code, started_at, ended_at, machine_hours
         FROM driver_shift WHERE driver_id = ? ORDER BY ds_id DESC LIMIT 1"
    );
    $stmt->execute([$driverId]);
    $shift = $stmt->fetch();
if ($assignedTrailer !== '') {
        $stmt = $conn->prepare(
            "UPDATE trailer
             SET trailer_assignto = '',
                 driver_id = 0,
                 trailer_status = 'Good'
             WHERE trailer_name = ?"
        );
        $stmt->execute([$assignedTrailer]);
}

    if ($assignedGenset !== '') {
        $stmt = $conn->prepare(
            "UPDATE units
             SET unit_assign = '',
                 driver_id = 0,
                 unit_status = 'Good'
             WHERE unit_name = ?"
        );
        $stmt->execute([$assignedGenset]);
}

    if ($truckCode !== '') {
        $stmt = $conn->prepare(
            "UPDATE units
             SET unit_assign = '',
                 driver_id = 0,
                 unit_assigntrailer = '',
                 unit_assigngenset = '',
                 unit_status = 'Good'
             WHERE unit_name = ?"
        );
        $stmt->execute([$truckCode]);
}

    // Mirror onto drivers row for fast filter checks.
    $stmt = $conn->prepare("UPDATE drivers SET shift_ended_at = NOW(), shift_truck = '', driver_status = 'Active' WHERE driver_id = ?");
    $stmt->execute([$driverId]);
$conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    json_out(['status' => 'error', 'message' => 'End shift failed: ' . $e->getMessage()], 500);
}

json_out([
    'status'        => 'success',
    'message'       => 'Shift ended. Machine hours recorded.',
    'shift'         => $shift,
    'machine_hours' => $shift['machine_hours'] ?? 0,
]);
