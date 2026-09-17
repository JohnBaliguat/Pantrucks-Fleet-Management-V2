<?php
include __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid method.');
}

$user_Id1     = (int)($_POST['user_Id1'] ?? 0);
$id           = (int)($_POST['f_id1']     ?? 0);
$equipmentNo  = $_POST['equipmentNo1']    ?? '';
$driver       = $_POST['driver1']         ?? '';
$meterReading = $_POST['meterReading1']   ?? '0';
$lastmeter    = $_POST['lastmeter1']      ?? '0';
$totalKm      = $_POST['totalKm1']        ?? '0';
$hourMeter    = $_POST['hourMeter1']      ?? '0';
$noLiter      = $_POST['noOfLtr1']        ?? '0';
$actualRatio  = $_POST['actualRatio1']    ?? '0';
$givenRatio   = $_POST['givenRatio1']     ?? '0';
$ideNoLt      = $_POST['ideNoLt1']        ?? '0';
$excessSave   = $_POST['excessSave2']     ?? '0';

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid record id.');
}

$equipmentNo = pt_pg_escape($conn, $equipmentNo);
$driver      = pt_pg_escape($conn, $driver);

$sql = "UPDATE fuel_report
        SET f_unit='$equipmentNo', f_driver='$driver', f_hubo='$meterReading',
            f_lastHubo='$lastmeter', f_kmRun='$totalKm', f_hourMeter='$hourMeter',
            f_noOfLit='$noLiter', f_actRatio='$actualRatio', f_STDRatio='$givenRatio',
            f_ideNoLt='$ideNoLt', f_excess_Saving='$excessSave'
        WHERE f_id=$id";

if ($conn->query($sql)) {
    if (pt_table_exists($conn, 'update_log')) {
        $conn->query("INSERT INTO update_log (f_id, user_id, upLog_datetime) VALUES ($id, $user_Id1, NOW())");
    }
    echo 'Fuel record updated successfully!';
} else {
    http_response_code(500);
    echo 'Error: ' . (($conn->errorInfo()[2]) ?? "");
}
