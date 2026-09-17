<?php
// List of fueling units (returns name + name as id, matching PTSI's fetch_unit.php)
include __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$units = [];
$sql = "SELECT unit_name FROM units WHERE unit_name IS NOT NULL AND unit_name <> '' ORDER BY unit_name ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch()) {
        $units[] = ['id' => $row['unit_name'], 'name' => $row['unit_name']];
    }
}

echo json_encode($units);
