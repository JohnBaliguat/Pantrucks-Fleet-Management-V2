<?php
require __DIR__ . '/_driver_auth.php';
$driverId = require_driver_session();
require_post();
include __DIR__ . '/../config/config.php';

$truck   = strtoupper(trim($_POST['truck_code']   ?? ''));
$trailer = strtoupper(trim($_POST['trailer_code'] ?? ''));
$genset  = strtoupper(trim($_POST['genset_code']  ?? ''));
$fuel    = !empty($_POST['fuel_ok'])       ? 1 : 0;
$tyres   = !empty($_POST['tyres_ok'])      ? 1 : 0;
$lights  = !empty($_POST['lights_ok'])     ? 1 : 0;
$cargo   = !empty($_POST['cargo_area_ok']) ? 1 : 0;
$gens    = !empty($_POST['genset_ok'])     ? 1 : 0;
$remarks = trim($_POST['remarks'] ?? '');

if ($truck === '') json_out(['status' => 'error', 'message' => 'Pick a truck first.']);
if (!($fuel && $tyres && $lights && $cargo && $gens)) {
    json_out(['status' => 'error', 'message' => 'All checklist items must pass.']);
}

$stmt = $conn->prepare(
    "SELECT unit_id, unit_status, maintenance_blocked
     FROM units
     WHERE unit_name = ? AND unit_type = 'truck'
     LIMIT 1"
);
$stmt->execute([$truck]);
$truckRow = $stmt->fetch();
if (!$truckRow) {
    json_out(['status' => 'error', 'message' => 'Selected truck was not found in the unit table.']);
}
if ((int)($truckRow['maintenance_blocked'] ?? 0) === 1) {
    json_out(['status' => 'error', 'message' => 'Selected truck is blocked for maintenance and cannot be used for shift start.']);
}
if (strcasecmp((string)($truckRow['unit_status'] ?? ''), 'Good') !== 0) {
    json_out(['status' => 'error', 'message' => 'Selected truck is not in Good status and cannot be used for shift start.']);
}
if (pt_driver_has_active_violation($conn, (int)$driverId)) {
    json_out(['status' => 'error', 'message' => pt_driver_violation_block_message()]);
}

// Postgres: use RETURNING to grab the new id atomically. PDOStatement
// has no insert_id; on PDO_PGSQL lastInsertId() requires the sequence
// name, so RETURNING is cleaner and survives sequence renames.
$stmt = $conn->prepare(
    "INSERT INTO pre_departure_checklist
        (driver_id, truck_code, trailer_code, genset_code, fuel_ok, tyres_ok, lights_ok, cargo_area_ok, genset_ok, remarks)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     RETURNING pdc_id"
);
$stmt->execute([$driverId, $truck, $trailer, $genset, $fuel, $tyres, $lights, $cargo, $gens, $remarks]);
$pdcId = (int)$stmt->fetchColumn();
// Phase 7 — close any unfinished shift first, then open a fresh one.
// Marking the previous shift as ended without machine_hours is a
// safety net; normally the driver taps End Shift cleanly.
$stmt = $conn->prepare(
    "UPDATE driver_shift SET ended_at = NOW(),
        machine_hours = EXTRACT(EPOCH FROM (NOW() - started_at)) / 3600.0
     WHERE driver_id = ? AND ended_at IS NULL"
);
$stmt->execute([$driverId]);
$stmt = $conn->prepare("INSERT INTO driver_shift (driver_id, truck_code, started_at, pdc_id) VALUES (?, ?, NOW(), ?)");
$stmt->execute([$driverId, $truck, $pdcId]);
// Drivers row keeps a denormalised pointer so the dispatchable filter
// stays a single index lookup.
$stmt = $conn->prepare("UPDATE drivers SET shift_truck = ?, shift_started_at = NOW(), shift_ended_at = NULL WHERE driver_id = ?");
$stmt->execute([$truck, $driverId]);
json_out(['status' => 'success', 'message' => 'Shift started. You are now Available.']);
