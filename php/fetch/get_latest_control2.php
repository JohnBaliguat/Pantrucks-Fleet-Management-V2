<?php
include "../config/config.php";

$query = "SELECT MAX(da_controlno) AS last_control 
          FROM drivers_attendance 
          WHERE da_controlno LIKE 'PTSIJOB-%'";

$result = $conn->query($query);

if ($result && $row = ($result)->fetch()) {

    $last_control = $row['last_control'];

    if (!empty($last_control)) {
        $number = (int) str_replace('PTSIJOB-', '', $last_control);
        $next_number = $number + 1;
        $new_control = 'PTSIJOB-' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
    } else {
        $new_control = 'PTSIJOB-00001';
    }

    echo $new_control;

} else {
    echo 'PTSIJOB-00001';
}
?>
