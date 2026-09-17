<?php
include "../config/config.php";

// Only consider control numbers starting with 'PTSI-'
$query = "SELECT MAX(booking_no) AS last_control FROM dispatch WHERE booking_no LIKE 'PTSI-%'";
$result = $conn->query($query);

if ($result && $row = ($result)->fetch()) {
    $last_control = $row['last_control'];

    if ($last_control) {
        // Extract number part after 'PTSI-'
        $number = (int) str_replace('PTSI-', '', $last_control);
        $next_number = $number + 1;
        // Format with leading zeros (adjust to desired digit count)
        $new_control = 'PTSI-' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
    } else {
        // Start from PTSI-00001 if no matching record
        $new_control = 'PTSI-00001';
    }

    echo $new_control;
} else {
    echo 'PTSI-00001';
}
?>
