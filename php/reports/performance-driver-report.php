<?php
include '../config/config.php';
require_once '../../reports/TCPDF-main/tcpdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request.');
}

$driverId = (int)($_POST['driver_id'] ?? 0);
$fromDate = trim((string)($_POST['fromDate'] ?? ''));
$toDate = trim((string)($_POST['toDate'] ?? ''));

if ($driverId <= 0) {
    exit('driver_id required');
}

$stmt = $conn->prepare(
    "SELECT driver_id,
            driver_idnumber,
            CONCAT(driver_lname, ', ', driver_fname) AS driver_name,
            COALESCE(NULLIF(TRIM(driver_assignunit), ''), '-') AS driver_unit,
            COALESCE(NULLIF(TRIM(driver_assignsegment), ''), '-') AS driver_segment,
            COALESCE(NULLIF(TRIM(driver_status), ''), 'Good') AS driver_status
     FROM drivers
     WHERE driver_id = ?
     LIMIT 1"
);
$stmt->execute([$driverId]);
$driver = $stmt->fetch();
if (!$driver) {
    exit('Driver not found.');
}

$tripDateSql = '';
$tripDateBind = '';
$tripDateParams = [];
$attDateSql = '';
$attDateBind = '';
$attDateParams = [];

if ($fromDate !== '' && $toDate !== '') {
    $tripDateSql = " AND DATE(d.d_datetime) BETWEEN ? AND ?";
    $tripDateBind = "ss";
    $tripDateParams = [$fromDate, $toDate];
    $attDateSql = " AND DATE(da.da_date) BETWEEN ? AND ?";
    $attDateBind = "ss";
    $attDateParams = [$fromDate, $toDate];
}

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS active_violations,
            MAX(vr_date) AS last_violation_date
     FROM violation_record
     WHERE driver_id = ?
       AND vr_status = 'Active'"
);
$stmt->execute([$driverId]);
$violation = $stmt->fetch();
$sql = "SELECT
            COUNT(t.trip_id) AS total_trips,
            SUM(t.trip_status = 'Done') AS done_trips,
            SUM(t.trip_status IS NOT NULL AND LOWER(t.trip_status) <> 'done') AS pending_trips
        FROM dispatch d
        INNER JOIN trips t ON t.d_id = d.d_id
        WHERE d.driver_id = ?" . $tripDateSql;
$stmt = $conn->prepare($sql);
$bindTypes = "i" . $tripDateBind;
$params = array_merge([$driverId], $tripDateParams);
$stmt->execute($params);
$tripAgg = $stmt->fetch();
$sql = "SELECT
            SUM(da.da_status = 'Present') AS days_present,
            SUM(da.da_status = 'Absent') AS days_absent,
            SUM(da.da_status IN ('VL','SL')) AS days_leave
        FROM drivers_attendance da
        WHERE da.driver_id = ?" . $attDateSql;
$stmt = $conn->prepare($sql);
$bindTypes = "i" . $attDateBind;
$params = array_merge([$driverId], $attDateParams);
$stmt->execute($params);
$attendance = $stmt->fetch();
$totalTrips = (int)($tripAgg['total_trips'] ?? 0);
$doneTrips = (int)($tripAgg['done_trips'] ?? 0);
$pendingTrips = (int)($tripAgg['pending_trips'] ?? 0);
$daysPresent = (int)($attendance['days_present'] ?? 0);
$daysAbsent = (int)($attendance['days_absent'] ?? 0);
$daysLeave = (int)($attendance['days_leave'] ?? 0);
$activeViolations = (int)($violation['active_violations'] ?? 0);

$efficiency = $totalTrips > 0 ? round(($doneTrips / $totalTrips) * 100, 1) : 0.0;
$attendanceBase = $daysPresent + $daysAbsent + $daysLeave;
$presentPct = $attendanceBase > 0 ? round(($daysPresent / $attendanceBase) * 100, 1) : 0.0;

if ($totalTrips === 0 && $attendanceBase === 0) {
    $recommendation = 'No Activity';
} elseif ($activeViolations >= 2 || $efficiency < 70 || $presentPct < 70) {
    $recommendation = 'Critical Review';
} elseif ($activeViolations >= 1 || $efficiency < 90 || $presentPct < 90) {
    $recommendation = 'Needs Coaching';
} else {
    $recommendation = 'Top Performer';
}

$recentTrips = [];
$sql = "SELECT
            t.trip_id,
            COALESCE(d.d_truck, '-') AS truck,
            COALESCE(t.costumer, '-') AS customer,
            COALESCE(NULLIF(TRIM(t.trip_haulingsegment), ''), '-') AS segment,
            COALESCE(t.trip_status, '-') AS status,
            TO_CHAR(d.d_datetime, 'YYYY-MM-DD HH12:MI AM') AS dispatched_at
        FROM dispatch d
        INNER JOIN trips t ON t.d_id = d.d_id
        WHERE d.driver_id = ?" . $tripDateSql . "
        ORDER BY d.d_datetime DESC, t.trip_id DESC
        LIMIT 20";
$stmt = $conn->prepare($sql);
$bindTypes = "i" . $tripDateBind;
$params = array_merge([$driverId], $tripDateParams);
$stmt->execute($params);
$res = $stmt;
while ($row = $res->fetch()) {
    $recentTrips[] = $row;
}
$dateLabel = ($fromDate !== '' && $toDate !== '') ? ($fromDate . ' to ' . $toDate) : 'All Dates';

$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Pantrucks Fleet Management System');
$pdf->SetAuthor('Pantrucks Fleet Management System');
$pdf->SetTitle('Driver Performance Breakdown');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

$headerHtml = '
    <h2 style="text-align:center;">Driver Performance Breakdown</h2>
    <table cellpadding="4">
        <tr>
            <td width="35%"><strong>Driver:</strong> ' . htmlspecialchars($driver['driver_name']) . ' (' . htmlspecialchars($driver['driver_idnumber']) . ')</td>
            <td width="25%"><strong>Unit:</strong> ' . htmlspecialchars($driver['driver_unit']) . '</td>
            <td width="20%"><strong>Segment:</strong> ' . htmlspecialchars($driver['driver_segment']) . '</td>
            <td width="20%"><strong>Date Range:</strong> ' . htmlspecialchars($dateLabel) . '</td>
        </tr>
        <tr>
            <td width="25%"><strong>Recommendation:</strong> ' . htmlspecialchars($recommendation) . '</td>
            <td width="25%"><strong>Efficiency:</strong> ' . number_format($efficiency, 1) . '%</td>
            <td width="25%"><strong>Presence Rate:</strong> ' . number_format($presentPct, 1) . '%</td>
            <td width="25%"><strong>Active Violations:</strong> ' . $activeViolations . '</td>
        </tr>
        <tr>
            <td width="20%"><strong>Total Trips:</strong> ' . $totalTrips . '</td>
            <td width="20%"><strong>Done:</strong> ' . $doneTrips . '</td>
            <td width="20%"><strong>Pending:</strong> ' . $pendingTrips . '</td>
            <td width="20%"><strong>Present / Absent / Leave:</strong> ' . $daysPresent . ' / ' . $daysAbsent . ' / ' . $daysLeave . '</td>
            <td width="20%"><strong>Status:</strong> ' . htmlspecialchars($driver['driver_status']) . '</td>
        </tr>
    </table>
    <br>
';
$pdf->SetFont('helvetica', '', 10);
$pdf->writeHTML($headerHtml, true, false, true, false, '');

$tableHtml = '
<h4>Recent Trips</h4>
<table border="1" cellpadding="4">
    <thead>
        <tr style="background-color:#e9eef8;font-weight:bold;">
            <th>Trip ID</th>
            <th>Truck</th>
            <th>Customer</th>
            <th>Segment</th>
            <th>Status</th>
            <th>Dispatched At</th>
        </tr>
    </thead>
    <tbody>
';

if (count($recentTrips) === 0) {
    $tableHtml .= '<tr><td colspan="6" align="center">No trips found for the selected period.</td></tr>';
} else {
    foreach ($recentTrips as $trip) {
        $tableHtml .= '
            <tr>
                <td>' . htmlspecialchars((string)$trip['trip_id']) . '</td>
                <td>' . htmlspecialchars($trip['truck']) . '</td>
                <td>' . htmlspecialchars($trip['customer']) . '</td>
                <td>' . htmlspecialchars($trip['segment']) . '</td>
                <td>' . htmlspecialchars($trip['status']) . '</td>
                <td>' . htmlspecialchars($trip['dispatched_at']) . '</td>
            </tr>
        ';
    }
}

$tableHtml .= '</tbody></table>';
$pdf->SetFont('helvetica', '', 8.5);
$pdf->writeHTML($tableHtml, true, false, true, false, '');
$pdf->Output('Driver_Performance_Breakdown_' . $driverId . '_' . date('Ymd_His') . '.pdf', 'I');
