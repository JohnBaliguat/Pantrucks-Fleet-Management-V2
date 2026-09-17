<?php
include __DIR__ . '/../config/config.php';

if (!isset($_GET['unit'])) { echo '0'; exit; }
$unit = pt_pg_escape($conn, $_GET['unit']);

if (!pt_table_exists($conn, 'fuel_report')) { echo '0'; exit; }

$result = $conn->query("SELECT f_hourMeter FROM fuel_report WHERE f_unit = '$unit' AND f_hourMeter IS NOT NULL AND f_hourMeter <> '' ORDER BY f_date DESC LIMIT 1");
if ($result && $row = ($result)->fetch()) {
    echo $row['f_hourmeter'];
} else {
    echo '0';
}
