<?php
include "../config/config.php";
require_once __DIR__ . '/../helpers/settings_helper.php';

// When the admin allows dispatching in-use equipment, include gensets currently
// on a trip ('Dispatch') too; otherwise only free ('Good') ones.
$allowInUse = pt_setting_bool($conn, 'allow_inuse_equipment', false);

$selectedDriverId = (int)($_GET['driver_id'] ?? 0);
$assignedGenset = '';

if ($selectedDriverId > 0) {
    $stmt = $conn->prepare(
        "SELECT tu.unit_assigngenset
         FROM drivers d
         LEFT JOIN units tu ON tu.unit_name = d.shift_truck
         WHERE d.driver_id = ?
         LIMIT 1"
    );
    $stmt->execute([$selectedDriverId]);
    $driverRow = $stmt->fetch();
$assignedGenset = trim((string)($driverRow['unit_assigngenset'] ?? ''));
}

$statusList = $allowInUse ? "unit_status IN ('Good', 'Dispatch')" : "unit_status = 'Good'";
$sql = "SELECT unit_name
        FROM units
        WHERE unit_name LIKE 'GS%'
          AND maintenance_blocked = FALSE
          AND (
                $statusList" . ($assignedGenset !== '' ? " OR unit_name = ?" : "") . "
              )
        ORDER BY unit_name ASC";
$stmt = $conn->prepare($sql);

// Only pass the bind parameter when the SQL has a placeholder for it.
$params = $assignedGenset !== '' ? [$assignedGenset] : [];
$stmt->execute($params);

$gensets = [];
while ($row = $stmt->fetch()) {
    $gensets[] = $row['unit_name'];
}
header('Content-Type: application/json');
echo json_encode($gensets);
