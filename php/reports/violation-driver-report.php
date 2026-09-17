<?php
include '../config/config.php';
require_once '../../reports/TCPDF-main/tcpdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request.');
}

$driverId = (int)($_POST['driver_id'] ?? 0);
$fromDate = trim((string)($_POST['fromDate'] ?? ''));
$toDate = trim((string)($_POST['toDate'] ?? ''));
$violationType = trim((string)($_POST['violation'] ?? ''));

if ($driverId <= 0) {
    exit('driver_id required');
}

$stmt = $conn->prepare(
    "SELECT driver_id,
            driver_idnumber,
            CONCAT(driver_lname, ', ', driver_fname) AS driver_name,
            COALESCE(NULLIF(TRIM(driver_assignunit), ''), '-') AS driver_unit,
            COALESCE(NULLIF(TRIM(driver_assignsegment), ''), '-') AS driver_segment
     FROM drivers
     WHERE driver_id = ?
     LIMIT 1"
);
$stmt->execute([$driverId]);
$driver = $stmt->fetch();
if (!$driver) {
    exit('Driver not found.');
}

$where = ["v.driver_id = ?"];
$bindTypes = "i";
$bindValues = [$driverId];

if ($fromDate !== '' && $toDate !== '') {
    $where[] = "DATE(v.vr_date) BETWEEN ? AND ?";
    $bindTypes .= "ss";
    $bindValues[] = $fromDate;
    $bindValues[] = $toDate;
}

if ($violationType !== '') {
    $where[] = "v.vr_type = ?";
    $bindTypes .= "s";
    $bindValues[] = $violationType;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_violations,
        SUM(CASE WHEN v.vr_status = 'Active' THEN 1 ELSE 0 END) AS active_violations,
        SUM(CASE WHEN v.vr_status = 'Done' THEN 1 ELSE 0 END) AS resolved_violations,
        MAX(v.vr_date) AS last_violation_date
     FROM violation_record v
     $whereSql"
);
$stmt->execute($bindValues);
$summary = $stmt->fetch();
$types = [];
$stmt = $conn->prepare(
    "SELECT v.vr_type, COUNT(*) AS violation_count
     FROM violation_record v
     $whereSql
     GROUP BY v.vr_type
     ORDER BY violation_count DESC, v.vr_type ASC"
);
$stmt->execute($bindValues);
$res = $stmt;
while ($row = $res->fetch()) {
    $types[] = $row;
}
$records = [];
$stmt = $conn->prepare(
    'SELECT
        v.vr_type,
        v.vr_description,
        v.vr_status,
        v.vr_date,
        v.vr_done_date,
        COALESCE(
            NULLIF(CONCAT_WS(\', \', NULLIF(TRIM(u.user_lname), \'\'), NULLIF(TRIM(u.user_fname), \'\')), \'\'),
            NULLIF(TRIM(u.user_name), \'\'),
            CAST(v.vr_recordedby AS TEXT)
        ) AS recorded_by
     FROM violation_record v
     LEFT JOIN "user" u ON u.user_id = v.vr_recordedby
     ' . $whereSql . '
     ORDER BY v.vr_date DESC'
);
$stmt->execute($bindValues);
$res = $stmt;
while ($row = $res->fetch()) {
    $records[] = $row;
}
$dateLabel = ($fromDate !== '' && $toDate !== '') ? ($fromDate . ' to ' . $toDate) : 'All Dates';
$filterLabel = $violationType !== '' ? $violationType : 'All Types';
$topType = $types[0]['vr_type'] ?? '-';

$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Pantrucks Fleet Management System');
$pdf->SetAuthor('Pantrucks Fleet Management System');
$pdf->SetTitle('Driver Violation Breakdown');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

$headerHtml = '
    <h2 style="text-align:center;">Driver Violation Breakdown</h2>
    <table cellpadding="4">
        <tr>
            <td width="32%"><strong>Driver:</strong> ' . htmlspecialchars($driver['driver_name']) . ' (' . htmlspecialchars($driver['driver_idnumber']) . ')</td>
            <td width="18%"><strong>Unit:</strong> ' . htmlspecialchars($driver['driver_unit']) . '</td>
            <td width="18%"><strong>Segment:</strong> ' . htmlspecialchars($driver['driver_segment']) . '</td>
            <td width="16%"><strong>Type Filter:</strong> ' . htmlspecialchars($filterLabel) . '</td>
            <td width="16%"><strong>Date Range:</strong> ' . htmlspecialchars($dateLabel) . '</td>
        </tr>
        <tr>
            <td width="20%"><strong>Total Violations:</strong> ' . (int)($summary['total_violations'] ?? 0) . '</td>
            <td width="20%"><strong>Active:</strong> ' . (int)($summary['active_violations'] ?? 0) . '</td>
            <td width="20%"><strong>Resolved:</strong> ' . (int)($summary['resolved_violations'] ?? 0) . '</td>
            <td width="20%"><strong>Top Type:</strong> ' . htmlspecialchars($topType) . '</td>
            <td width="20%"><strong>Last Violation:</strong> ' . htmlspecialchars(!empty($summary['last_violation_date']) ? date('Y-m-d', strtotime($summary['last_violation_date'])) : '-') . '</td>
        </tr>
    </table>
    <br>
';
$pdf->SetFont('helvetica', '', 10);
$pdf->writeHTML($headerHtml, true, false, true, false, '');

$typesHtml = '<h4>Violation Type Summary</h4><table border="1" cellpadding="4"><thead><tr style="background-color:#e9eef8;font-weight:bold;"><th width="70%">Violation Type</th><th width="30%">Count</th></tr></thead><tbody>';
if (count($types) === 0) {
    $typesHtml .= '<tr><td colspan="2" align="center">No violation type summary available.</td></tr>';
} else {
    foreach ($types as $typeRow) {
        $typesHtml .= '<tr><td>' . htmlspecialchars($typeRow['vr_type']) . '</td><td align="center">' . (int)$typeRow['violation_count'] . '</td></tr>';
    }
}
$typesHtml .= '</tbody></table><br>';
$pdf->SetFont('helvetica', '', 8.5);
$pdf->writeHTML($typesHtml, true, false, true, false, '');

$recordsHtml = '
<h4>Violation Records</h4>
<table border="1" cellpadding="4">
    <thead>
        <tr style="background-color:#e9eef8;font-weight:bold;">
            <th>Violation Type</th>
            <th>Description</th>
            <th>Status</th>
            <th>Recorded</th>
            <th>Resolved</th>
            <th>Recorded By</th>
        </tr>
    </thead>
    <tbody>
';

if (count($records) === 0) {
    $recordsHtml .= '<tr><td colspan="6" align="center">No violation records found for the selected period.</td></tr>';
} else {
    foreach ($records as $record) {
        $recordsHtml .= '
            <tr>
                <td>' . htmlspecialchars($record['vr_type']) . '</td>
                <td>' . htmlspecialchars($record['vr_description']) . '</td>
                <td>' . htmlspecialchars($record['vr_status']) . '</td>
                <td>' . htmlspecialchars(date('Y-m-d H:i', strtotime($record['vr_date']))) . '</td>
                <td>' . htmlspecialchars((!empty($record['vr_done_date']) && $record['vr_done_date'] !== '0000-00-00 00:00:00') ? date('Y-m-d H:i', strtotime($record['vr_done_date'])) : '-') . '</td>
                <td>' . htmlspecialchars($record['recorded_by'] ?: '-') . '</td>
            </tr>
        ';
    }
}

$recordsHtml .= '</tbody></table>';
$pdf->writeHTML($recordsHtml, true, false, true, false, '');
$pdf->Output('Driver_Violation_Breakdown_' . $driverId . '_' . date('Ymd_His') . '.pdf', 'I');
