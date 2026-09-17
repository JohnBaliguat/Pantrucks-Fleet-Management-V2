<?php
include '../config/config.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromDate = $_POST['fromDate'] ?? '';
    $toDate = $_POST['toDate'] ?? '';
    $driverId = $_POST['driverId'] ?? '';

    // ==========================
    // FETCH ATTENDANCE DATA
    // ==========================
    $query = "
        SELECT 
            d.driver_id, 
            d.driver_idnumber,
            CONCAT(d.driver_fname, ' ', d.driver_mname, ' ', d.driver_lname) AS driver_name,
            da.da_date,
            da.da_timein,
            da.da_timeout,
            da.da_status,
            da.da_remarks,
            da.vl_sl_dateprepared,
            da.vl_sl_datestart,
            da.vl_sl_date
        FROM drivers d
        INNER JOIN drivers_attendance da ON d.driver_id = da.driver_id
        WHERE 1
    ";

    if (!empty($fromDate) && !empty($toDate)) {
        $from = pt_pg_escape($conn, $fromDate);
        $to = pt_pg_escape($conn, $toDate);
        $query .= " AND da.da_date BETWEEN '$from' AND '$to'";
    }

    if (!empty($driverId)) {
        $id = pt_pg_escape($conn, $driverId);
        $query .= " AND d.driver_id = '$id'";
    }

    $query .= " ORDER BY da.da_date ASC, driver_name ASC";
    $result = $conn->query($query);

    if (($result)->rowCount() > 0) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ==========================
        // REPORT TITLE
        // ==========================
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'Driver Attendance Report');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:J2');
        $dateRange = (!empty($fromDate) && !empty($toDate)) ? "$fromDate to $toDate" : 'All Dates';
        $sheet->setCellValue('A2', 'Date Range: ' . $dateRange);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:J3');
        $sheet->setCellValue('A3', 'Date Prepared: ' . date('Y-m-d'));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ==========================
        // TABLE HEADERS
        // ==========================
        $headers = [
            'Driver ID',
            'Driver Name',
            'date',
            'Time In',
            'Time Out',
            'Status',
            'Remarks',
            'VL/SL Date Prepared',
            'VL/SL Start Date',
            'VL/SL End Date'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '5', $header);
            $col++;
        }

        $sheet->getStyle('A5:J5')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // ==========================
        // TABLE DATA
        // ==========================
        $row = 6;
        while ($data = ($result)->fetch()) {

            $excelDate = (!empty($data['da_date']) && $data['da_date'] !== '0000-00-00')
                ? ExcelDate::PHPToExcel(strtotime($data['da_date'])) : '';

            $excelTimeIn = (!empty($data['da_timein']) && $data['da_timein'] !== '0000-00-00 00:00:00')
                ? ExcelDate::PHPToExcel(strtotime($data['da_timein'])) : '';

            $excelTimeOut = (!empty($data['da_timeout']) && $data['da_timeout'] !== '0000-00-00 00:00:00')
                ? ExcelDate::PHPToExcel(strtotime($data['da_timeout'])) : '';

            $excelVLPrepared = (!empty($data['vl_sl_dateprepared']) && $data['vl_sl_dateprepared'] !== '0000-00-00')
                ? ExcelDate::PHPToExcel(strtotime($data['vl_sl_dateprepared'])) : '';

            $excelVLStart = (!empty($data['vl_sl_datestart']) && $data['vl_sl_datestart'] !== '0000-00-00')
                ? ExcelDate::PHPToExcel(strtotime($data['vl_sl_datestart'])) : '';

            $excelVLDate = (!empty($data['vl_sl_date']) && $data['vl_sl_date'] !== '0000-00-00')
                ? ExcelDate::PHPToExcel(strtotime($data['vl_sl_date'])) : '';

            $sheet->setCellValue('A' . $row, $data['driver_idnumber']);
            $sheet->setCellValue('B' . $row, $data['driver_name']);

            if ($excelDate !== '') {
                $sheet->setCellValue('C' . $row, $excelDate);
                $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }

            if ($excelTimeIn !== '') {
                $sheet->setCellValue('D' . $row, $excelTimeIn);
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('hh:mm AM/PM');
            }

            if ($excelTimeOut !== '') {
                $sheet->setCellValue('E' . $row, $excelTimeOut);
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('hh:mm AM/PM');
            }

            $sheet->setCellValue('F' . $row, $data['da_status']);
            $sheet->setCellValue('G' . $row, $data['da_remarks']);

            if ($excelVLPrepared !== '') {
                $sheet->setCellValue('H' . $row, $excelVLPrepared);
                $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }

            if ($excelVLStart !== '') {
                $sheet->setCellValue('I' . $row, $excelVLStart);
                $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }

            if ($excelVLDate !== '') {
                $sheet->setCellValue('J' . $row, $excelVLDate);
                $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }

            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            $row++;
        }

        // ==========================
        // AUTO-SIZE COLUMNS
        // ==========================
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ==========================
        // OUTPUT FILE
        // ==========================
        $filename = "Attendance_Report_" . date('Ymd_His');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename.xlsx\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } else {
        echo "<script>alert('No attendance records found for the selected filters!');window.close();</script>";
    }
}
?>
