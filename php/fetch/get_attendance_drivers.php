<?php
include "../config/config.php";

$sql = "
    SELECT driver_id, driver_fname, driver_mname, driver_lname
    FROM drivers
    ORDER BY driver_lname ASC, driver_fname ASC
";
$result = $conn->query($sql);

$drivers = [];
while ($row = ($result)->fetch()) {
    $last = trim((string)($row['driver_lname'] ?? ''));
    $first = trim((string)($row['driver_fname'] ?? ''));
    $middle = trim((string)($row['driver_mname'] ?? ''));

    $name = $last !== ''
        ? strtoupper($last) . ', ' . strtoupper($first . ($middle !== '' ? ' ' . $middle : ''))
        : strtoupper(trim($first . ' ' . $middle));

    $drivers[] = [
        'id' => (int)$row['driver_id'],
        'name' => trim($name),
    ];
}

header('Content-Type: application/json');
echo json_encode($drivers);
