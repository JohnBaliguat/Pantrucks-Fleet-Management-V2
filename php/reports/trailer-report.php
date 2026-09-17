<?php
include '../config/config.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromDate = $_POST['fromDate'] ?? '';
    $toDate   = $_POST['toDate'] ?? '';
    $customer = $_POST['customer'] ?? '';

    // -------------------------------
    // Query with Trailer filter
    // -------------------------------
    $query = "
    SELECT 
        d.d_id,
        d.d_drivername,
        d.d_trailer,
        d.d_datetime,
        d.d_dispatcher,
        d.d_dispatchhub,
        t1.trip_from AS start_location,
        t1.trip_haulingsegment,
        t1.trip_haulingtype,
        t_last.trip_to AS last_location
    FROM dispatch d
    LEFT JOIN trips t1 
        ON d.d_id = t1.d_id AND t1.trip_type = 'Trip 1'
    LEFT JOIN trips t_last 
        ON d.d_id = t_last.d_id 
        AND t_last.trip_id = (
            SELECT MAX(t3.trip_id) 
            FROM trips t3 
            WHERE t3.d_id = d.d_id
        )
    WHERE d.d_trailer IS NOT NULL AND d.d_trailer <> ''
    ";

    if (!empty($customer)) {
        $query .= " AND d.costumer = '" . pt_pg_escape($conn, $customer) . "'";
    }
    if (!empty($fromDate) && !empty($toDate)) {
        $query .= " AND d.d_datetime BETWEEN '$fromDate' AND '$toDate'";
    }

    $query .= " ORDER BY d.d_id DESC";
    $result = $conn->query($query);

    // -------------------------------
    // Spreadsheet setup
    // -------------------------------
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Trailer Report');

    // Header row
    $headers = [
        'Trailer', 'Driver', 'date', 'Start Location', 'Last Location',
        'Hauling Segment', 'Hauling Type', 'Time', 'Dispatcher', 'Dispatch Hub'
    ];

    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($col . '1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $col++;
    }

    // -------------------------------
    // Data rows
    // -------------------------------
    $rowNum = 2;
    if ($result && ($result)->rowCount() > 0) {
        while ($row = ($result)->fetch()) {
            $sheet->setCellValue('A' . $rowNum, $row['d_trailer']);
            $sheet->setCellValue('B' . $rowNum, $row['d_drivername']);
            $sheet->setCellValue('C' . $rowNum, date('Y-m-d', strtotime($row['d_datetime'])));
            $sheet->setCellValue('D' . $rowNum, !empty($row['start_location']) ? $row['start_location'] : 'N/A');
            $sheet->setCellValue('E' . $rowNum, !empty($row['last_location']) ? $row['last_location'] : 'N/A');
            $sheet->setCellValue('F' . $rowNum, $row['trip_haulingsegment']);
            $sheet->setCellValue('G' . $rowNum, $row['trip_haulingtype']);
            $sheet->setCellValue('H' . $rowNum, date('H:i', strtotime($row['d_datetime'])));
            $sheet->setCellValue('I' . $rowNum, $row['d_dispatcher']);
            $sheet->setCellValue('J' . $rowNum, $row['d_dispatchhub']);

            // Add borders for neatness
            foreach (range('A', 'J') as $col) {
                $sheet->getStyle($col . $rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }

            $rowNum++;
        }
    }

    // Auto size columns
    foreach (range('A', 'J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // -------------------------------
    // Export file
    // -------------------------------
    $fileName = 'Trailer_Report_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
