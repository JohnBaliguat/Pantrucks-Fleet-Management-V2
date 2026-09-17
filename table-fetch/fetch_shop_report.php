<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

// Prevent stray PHP warnings from corrupting the JSON DataTables expects.
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

$sql = "
SELECT
    s.s_id,
    s.s_unit,
    s.s_startdatetime,
    s.s_enddatetime,
    s.s_status,
    d.driver_fname,
    d.driver_lname,
    STRING_AGG(
        CONCAT(sm.smp_fname, ' ', sm.smp_lname),
        ', '
    ) AS manpower
FROM shop_unit s
LEFT JOIN drivers d ON s.driver_id = d.driver_id
LEFT JOIN shop_record sr ON s.s_id = sr.s_id
LEFT JOIN assign_manpower am ON sr.sr_id = am.sr_id
LEFT JOIN shop_manpower sm ON am.smp_id = sm.smp_id
WHERE s.s_status = 'Good'
GROUP BY s.s_id
";

$result = $conn->query($sql);

$data = [];

while ($row = $result->fetch()) {

    $start = $row['s_startdatetime'];
    $end   = $row['s_enddatetime'];

    $start_display = date("F j, Y h:i A", strtotime($start));

    if ($end == "0000-00-00 00:00:00" || empty($end)) {
        $end_display = "<span class='live-time' data-start='{$start}'></span>";
        $age_display = "<span class='live-age' data-start='{$start}'></span>";
    } else {
        $end_display = date("F j, Y h:i A", strtotime($end));

        $diff = strtotime($end) - strtotime($start);
        $days = floor($diff / 86400);
        $hms  = gmdate("H:i:s", $diff % 86400);
        $age_display = $days . " Days " . $hms;
    }

    $data[] = [
        "driver"   => $row['driver_fname'] . " " . $row['driver_lname'],
        "unit"     => $row['s_unit'],
        "start"    => $start_display,
        "end"      => $end_display,
        "age"      => $age_display,
        "manpower" => $row['manpower'] ?? '—',
        "status"   => "<span class='badge bg-info'>Good</span>"
    ];
}

echo json_encode(["data" => $data]);
?>
