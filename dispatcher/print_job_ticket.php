<?php
include 'php/config/config.php';

$id = intval($_GET['id']);

$query = $conn->query("
    SELECT da.*, 
           d.driver_fname, d.driver_lname, da_controlNo
    FROM drivers_attendance da
    JOIN drivers d ON da.driver_id = d.driver_id
    WHERE da.da_id = '$id'
");

$data = $query->fetch();

/* =====================================================
   GENERATE CUSTOM TIME FORMAT
   0700H - 0730H
   0731H - 0800H
   0801H - 0830H
===================================================== */

$timeSlots = [];

$hour = 7;
$minutePattern = 0; // 0 = 00, 1 = 31, 2 = 01

for ($i = 0; $i < 48; $i++) {

    if ($minutePattern == 0) {
        $startMin = 0;
        $endMin   = 30;
        $nextPattern = 1;
    } elseif ($minutePattern == 1) {
        $startMin = 31;
        $endMin   = 0;
        $nextPattern = 2;
    } else {
        $startMin = 1;
        $endMin   = 30;
        $nextPattern = 1;
    }

    $startTime = sprintf("%02d%02dH", $hour, $startMin);

    if ($endMin == 0) {
        $endHour = $hour + 1;
    } else {
        $endHour = $hour;
    }

    if ($endHour == 24) {
        $endHour = 0;
    }

    $endTime = sprintf("%02d%02dH", $endHour, $endMin);

    $timeSlots[] = [
        'start' => $startTime,
        'end'   => $endTime
    ];

    if ($minutePattern == 1) {
        $hour++;
        if ($hour == 24) $hour = 0;
    }

    $minutePattern = $nextPattern;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Job Ticket</title>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
}

/* LONG BONDPAPER PORTRAIT */
@page {
    size: 8.5in 13in;
    margin: 0.2in;
}

.ticket {
    width: 100%;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    border: 0.5px solid #000;
    padding: 2px;
    font-size: 10px; /* smaller to fit portrait */
    text-align: center;
}

.left { text-align: left; }
.center { text-align: center; }
.right { text-align: right; }

.header-logo {
    font-size: 22px;
    font-weight: bold;
    color: red;
}

.job-title {
    font-size: 30px;
    font-weight: bold;
    border:none;
}

.rotate {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    font-size: 9px;
    padding: 3;
    width: 30px;
}

.header-info td {
    font-size: 10px;
    padding: 4px;
}

.total-row td {
    font-weight: bold;
}

@media print {
    body {
        margin: 0;
    }
}
</style>
</head>

<body onload="window.print()" onafterprint="window.close();">

<div class="ticket">

<!-- HEADER -->
<table class="header-info">
<tr>
    <td colspan="15" class="center" style="border:none;">
        <img src="assets/images/logos/pantrucks.png" style="width: 300px;" alt="">
        <div style="font-size:10px; margin-top: -10px;">B.O. A.O Florendo, Panabo, Davao del Norte</div>
    </td>
</tr>
<tr>
    <td colspan="15" class="right" style="border:none;">
        <strong>NO.:</strong> <?= $data['da_controlno']; ?>
    </td>
</tr>

<tr>
    <td colspan="15" class="job-title">JOB TICKET</td>
</tr>

<tr>
    <td colspan="3" class="left"><strong>DRIVER</strong></td>
    <td colspan="4" class="left">
        <?= $data['driver_lname'] . ', ' . $data['driver_fname']; ?>
    </td>

    <td colspan="2" class="left"><strong>ATTENDANCE DATE & TIME</strong></td>
    <td colspan="6" class="left">
        <?= date('F d, Y', strtotime($data['da_date'])); ?>
        <?= date('h:i A', strtotime($data['da_timein'])); ?>
    </td>
</tr>

<tr>
    <td colspan="3" class="left"><strong>HELPER</strong></td>
    <td colspan="4"></td>

    <td colspan="2" class="left"><strong>TRUCK NUMBER</strong></td>
    <td colspan="6"></td>
</tr>
</table>

<br>

<!-- TIME GRID -->
<table>
<tr>
    <th colspan="2">TIME</th>
    <th colspan="2">VERIFIED</th>
    <th>CARGO</th>

    <th class="rotate">Pretrip</th>
    <th class="rotate">Transit</th>
    <th class="rotate">Unloading</th>
    <th class="rotate">Loading</th>
    <th class="rotate">Advice</th>
    <th class="rotate">Wait</th>
    <th class="rotate">Break</th>
    <th class="rotate">Meal</th>
    <th class="rotate">Refuel</th>

    <th>Remarks</th>
</tr>

<tr>
    <th>Time Start</th>
    <th>Time End</th>
    <th>Start</th>
    <th>Eend</th>
    <th></th>
    <th colspan="9"></th>
    <th></th>
</tr>

<?php foreach ($timeSlots as $slot): ?>
<tr>
    <td><?= $slot['start']; ?></td>
    <td><?= $slot['end']; ?></td>
    <td></td>
    <td></td>
    <td></td>

    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>

    <td></td>
</tr>
<?php endforeach; ?>

<tr class="total-row">
    <td colspan="3" class="right">TOTAL TIME CONSUMED</td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
</tr>

</table>

</div>
</body>
</html>
