<?php
include "../config/config.php";
require_once __DIR__ . '/../helpers/settings_helper.php';

// When the admin allows dispatching in-use equipment, include trailers that are
// currently on a trip ('Dispatch'); otherwise only free ('Good') ones.
$allowInUse = pt_setting_bool($conn, 'allow_inuse_equipment', false);
$statusFilter = $allowInUse ? "trailer_status IN ('Good', 'Dispatch')" : "trailer_status = 'Good'";

$sql = "SELECT trailer_name
        FROM trailer
        WHERE $statusFilter
          AND maintenance_blocked = FALSE
        ORDER BY trailer_name ASC";
$result = $conn->query($sql);

$trailers = [];
while ($row = ($result)->fetch()) {
    $trailers[] = $row['trailer_name'];
}

header('Content-Type: application/json');
echo json_encode($trailers);
