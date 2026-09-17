<?php
// Activity Log — Excel export. Admin only. Mirrors the other php/reports/*
// scripts: POST the filters, stream an .xlsx built with PhpSpreadsheet.
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
session_start();
include '../config/config.php';
require_once '../helpers/activity_log_helper.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(401);
    echo 'Not authorised';
    exit;
}

pt_ensure_activity_log_table($conn);

$from   = trim($_POST['from'] ?? $_GET['from'] ?? '');
$to     = trim($_POST['to'] ?? $_GET['to'] ?? '');
$role   = trim($_POST['role'] ?? $_GET['role'] ?? '');
$search = trim($_POST['search'] ?? $_GET['search'] ?? '');

$base = "
    SELECT * FROM (
        SELECT al.created_at AS ts, al.user_role AS role, al.user_name AS name,
               al.action AS action, al.details AS details, '' AS booking_no,
               al.ip_address AS ip, 'activity' AS source
        FROM activity_log al
        UNION ALL
        SELECT we.event_at AS ts, we.actor_role AS role,
               COALESCE(
                   CASE WHEN LOWER(we.actor_role) = 'driver'
                        THEN (SELECT CONCAT(d.driver_lname, ', ', d.driver_fname) FROM drivers d WHERE d.driver_id = we.actor_id)
                        ELSE (SELECT CONCAT_WS(' ', u.user_fname, u.user_lname) FROM \"user\" u WHERE u.user_id = we.actor_id)
                   END, '') AS name,
               we.stage AS action, we.notes AS details, we.booking_no AS booking_no,
               '' AS ip, 'workflow' AS source
        FROM workflow_event we
    ) t
    WHERE 1=1
";

$params = [];
if ($from !== '') { $base .= " AND t.ts >= ? ";  $params[] = $from . ' 00:00:00'; }
if ($to   !== '') { $base .= " AND t.ts <= ? ";  $params[] = $to   . ' 23:59:59'; }
if ($role !== '') { $base .= " AND LOWER(t.role) = LOWER(?) "; $params[] = $role; }
if ($search !== '') {
    $base .= " AND (t.name ILIKE ? OR t.action ILIKE ? OR t.details ILIKE ? OR t.booking_no ILIKE ?) ";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$base .= " ORDER BY t.ts DESC";

$stmt = $conn->prepare($base);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Activity Log');

$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'User Activity Log');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:G2');
$sheet->setCellValue('A2', 'Date Range: ' . ($from && $to ? "$from to $to" : 'All Dates') .
    ($role !== '' ? '   |   Role: ' . $role : '') .
    ($search !== '' ? '   |   Search: "' . $search . '"' : ''));

$sheet->mergeCells('A3:G3');
$sheet->setCellValue('A3', 'Generated: ' . date('Y-m-d H:i:s'));

$headers = [
    'A5' => 'Date/Time', 'B5' => 'User', 'C5' => 'Role', 'D5' => 'Action',
    'E5' => 'Details', 'F5' => 'Booking No', 'G5' => 'IP Address',
];
foreach ($headers as $cell => $text) { $sheet->setCellValue($cell, $text); }
$sheet->getStyle('A5:G5')->applyFromArray([
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'font'      => ['bold' => true],
    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$r = 6;
foreach ($rows as $row) {
    $ts = $row['ts'];
    if (!empty($ts)) {
        $sheet->setCellValue("A$r", ExcelDate::PHPToExcel(strtotime($ts)));
        $sheet->getStyle("A$r")->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');
    }
    $sheet->setCellValue("B$r", $row['name']);
    $sheet->setCellValue("C$r", $row['role']);
    $sheet->setCellValue("D$r", $row['action']);
    $sheet->setCellValue("E$r", $row['details']);
    $sheet->setCellValue("F$r", $row['booking_no']);
    $sheet->setCellValue("G$r", $row['ip']);
    $r++;
}

$lastRow = max(6, $r - 1);
$sheet->getStyle("A5:G{$lastRow}")->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'Activity_Log_' . date('Ymd_His');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename.xlsx\"");
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
