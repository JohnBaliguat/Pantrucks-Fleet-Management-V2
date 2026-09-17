<?php
include "../config/config.php";

// Initialize the categories
$data = [
    'FUEL' => 0,
    'GPS' => 0,
    'PERFORMANCE' => 0
];

// Query all active violations
$sql = "SELECT vr_type FROM violation_record WHERE vr_status = 'Active'";
$result = $conn->query($sql);

while ($row = ($result)->fetch()) {
    $type = $row['vr_type'];

    if ($type === 'High Gas') {
        $data['FUEL'] += 1;
    } elseif (in_array($type, ['Over Speeding', 'Illegal Parking', 'Excessive Idling', 'Reckless Driving'])) {
        $data['GPS'] += 1;
    } elseif ($type === 'Low Performer') {
        $data['PERFORMANCE'] += 1;
    }
}

echo json_encode($data);
?>
