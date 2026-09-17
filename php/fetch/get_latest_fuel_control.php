<?php
include __DIR__ . '/../config/config.php';

if (!pt_table_exists($conn, 'fuel_report')) { echo 'PTSI-00001'; exit; }

$result = $conn->query("SELECT MAX(f_controlno) AS last_control FROM fuel_report WHERE f_controlno LIKE 'PTSI-%'");

if ($result && $row = ($result)->fetch()) {
    $last = $row['last_control'];
    if ($last) {
        $n = (int)str_replace('PTSI-', '', $last);
        echo 'PTSI-' . str_pad($n + 1, 5, '0', STR_PAD_LEFT);
    } else {
        echo 'PTSI-00001';
    }
} else {
    echo 'PTSI-00001';
}
