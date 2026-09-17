<?php
include "../../config/config.php";
header('Content-Type: application/json');

if (!isset($_POST['unit_id'])) {
    echo json_encode(["success" => false, "message" => "Missing unit_id"]);
    exit;
}

$unitId = (int)$_POST['unit_id'];
$engineOk = (int)($_POST['engine_ok'] ?? $_POST['fuel_ok'] ?? 0);
$tiresOk = (int)($_POST['tires_ok'] ?? $_POST['tyres_ok'] ?? 0);
$lightsOk = (int)($_POST['lights_ok'] ?? 0);
$bodyOk = (int)($_POST['body_ok'] ?? $_POST['cargo_area_ok'] ?? 0);
$clearedOk = (int)($_POST['cleared_ok'] ?? $_POST['genset_ok'] ?? 0);
$remarks = trim((string)($_POST['remarks'] ?? ''));

if (!$engineOk || !$tiresOk || !$lightsOk || !$bodyOk || !$clearedOk) {
    echo json_encode(["success" => false, "message" => "All checklist items must be completed before marking the unit available."]);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE units
     SET unit_status = 'Available',
         unit_assign = '',
         driver_id = 0,
         unit_assigngenset = '',
         unit_assigntrailer = ''
     WHERE unit_id = ?"
);

$stmt->execute([$unitId]);

echo json_encode([
    "success" => true,
    "message" => "Checklist passed. Unit marked as available.",
    "remarks" => $remarks
]);
