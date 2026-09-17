<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

// Prevent stray PHP warnings from corrupting the JSON DataTables expects.
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

$sql = "SELECT s.s_id, s.s_unit, s.driver_id, s.s_startdatetime, s.s_enddatetime, s.s_status, 
               d.driver_fname, d.driver_lname
        FROM shop_unit s
        LEFT JOIN drivers d ON s.driver_id = d.driver_id";
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch()) {
    $start  = $row['s_startdatetime'];
    $end    = $row['s_enddatetime'];
    $status = strtolower($row['s_status']);

    // Handle End Time + Age
    if ($end == "0000-00-00 00:00:00") {
        $end_display = "<span class='live-time' data-start='{$start}'></span>";
        $age_display = "<span class='live-age' data-start='{$start}'></span>";
    } else {
        $end_display = date("F j, Y h:i A", strtotime($end));
        
        $diff = strtotime($end) - strtotime($start);
        $days = floor($diff / 86400);
        $hms  = gmdate("H:i:s", $diff % 86400);
        $age_display = $days . " Days " . $hms;

        $start_display = date("F j, Y h:i A", strtotime($start));
    }

    // Action buttons based on status
    $buttons = "<div class='d-grid gap-2 d-md-block text-start'>";
    if ($status === "active") {
     $buttons .= "
      <button class='btn btn-primary btn-sm assign-btn' data-id='{$row['s_id']}'>
        Assign
      </button>";
    } else if($status === "assigned"){
     $buttons .= "<button class='btn btn-success btn-sm good-btn' data-id='{$row['s_id']}'>Complete</button>";
    } else if($status === "good"){
     $buttons .= "<button class='btn btn-success btn-sm'>Confirm</button>";
    } else if($status === "to be confirmed by dispatch"){
     $buttons .= "<button class='btn btn-info btn-sm'>To Be Confirmed by Dispatch</button>";
    }
    $buttons .= "</div>";

    $data[] = [
        "driver" => $row['driver_fname'] . " " . $row['driver_lname'],
        "unit"   => $row['s_unit'],
        "start"  => $start,
        "end"    => $end_display,
        "age"    => $age_display,
        "action" => $buttons
    ];
}

echo json_encode(["data" => $data]);
?>
