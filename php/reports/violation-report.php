<?php
include '../config/config.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fromDate  = $_POST['fromDate'] ?? '';
    $toDate    = $_POST['toDate'] ?? '';
    $violation = $_POST['violation'] ?? '';

    /* ===============================
       BASE QUERY
    ================================ */
    $query = "
        SELECT
            d.driver_id,
            CONCAT(d.driver_fname, ' ', d.driver_lname) AS driver_name,
            d.driver_assignunit,
            v.vr_type,
            v.vr_description,
            v.vr_status,
            v.vr_date,
            v.vr_done_date
        FROM drivers d
        INNER JOIN violation_record v ON d.driver_id = v.driver_id
        WHERE 1=1
    ";

    if (!empty($violation)) {
        $query .= " AND v.vr_type = '" . pt_pg_escape($conn, $violation) . "'";
    }

    if (!empty($fromDate) && !empty($toDate)) {
        $query .= " AND v.vr_date BETWEEN '$fromDate' AND '$toDate'";
    }

    $query .= " ORDER BY v.vr_date DESC";

    $result = $conn->query($query);

    if (($result)->rowCount() > 0) {

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        /* ===============================
           REPORT HEADER
        ================================ */
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'Driver Violation Report');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Violation Type: ' . ($violation ?: 'All'));

        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue(
            'A3',
            'Date Range: ' . ($fromDate && $toDate ? "$fromDate to $toDate" : 'All Dates')
        );

        $sheet->mergeCells('A4:F4');
        $sheet->setCellValue('A4', 'Date Prepared: ' . date('Y-m-d'));

        /* ===============================
           TABLE HEADERS
        ================================ */
        $headers = [
            'A6' => 'Driver Name',
            'B6' => 'Violation Type',
            'C6' => 'Description',
            'D6' => 'Status',
            'E6' => 'Violation Date',
            'F6' => 'Done Date'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A6:F6')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);

        /* ===============================
           DATA ROWS
        ================================ */
        $row = 7;

        while ($data = ($result)->fetch()) {

            $sheet->setCellValue("A$row", $data['driver_name']);
            $sheet->setCellValue("B$row", $data['vr_type']);
            $sheet->setCellValue("C$row", $data['vr_description']);
            $sheet->setCellValue("D$row", $data['vr_status']);

            // Violation Date
            if (!empty($data['vr_date'])) {
                $sheet->setCellValue(
                    "E$row",
                    ExcelDate::PHPToExcel(strtotime($data['vr_date']))
                );
                $sheet->getStyle("E$row")
                    ->getNumberFormat()
                    ->setFormatCode('yyyy-mm-dd');
            }

            // Done Date
            if (!empty($data['vr_done_date']) && $data['vr_done_date'] !== '0000-00-00') {
                $sheet->setCellValue(
                    "F$row",
                    ExcelDate::PHPToExcel(strtotime($data['vr_done_date']))
                );
                $sheet->getStyle("F$row")
                    ->getNumberFormat()
                    ->setFormatCode('yyyy-mm-dd');
            }

            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]);

            $row++;
        }

        /* ===============================
           AUTO SIZE
        ================================ */
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        /* ===============================
           OUTPUT
        ================================ */
        $filename = "Violation_Report_" . date('Ymd_His');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename.xlsx\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    } else {
        echo "<script>alert('No records found!');window.close();</script>";
    }
}
?>
