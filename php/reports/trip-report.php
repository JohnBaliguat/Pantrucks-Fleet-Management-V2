<?php
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
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
    $customer = $_POST['customer'] ?? '';

    // ===== Fetch trips data =====
    $query = "
        SELECT 
        d.driver_id, drv.driver_idnumber, d.d_drivername, d.d_truck, d.d_trailer, d.d_genset,
        d.d_tripreceipt, d.d_ecs, d.d_dispatchhub, d.booking_no,
        d.d_datetime, d.d_dispatcher, d.costumer,
        -- trip1 fields
        t1.trip_from AS trip1_from, t1.trip_to AS trip1_to, t1.trip_container AS trip1_container,
        t1.trip_containerstat AS \"trip1_containerStat\", t1.trip_haulingsegment AS trip1_segment,
        t1.trip_haulingtype AS trip1_type, t1.container_activity AS trip1_activity, t1.km_run AS trip1_km,
        t1.trip_departuredatetime AS trip1_depart, t1.trip_arrivaldatetime AS trip1_arrival,
        t1.trip_pharrivaldatetime AS trip1_pharrival, t1.deliver_location AS \"trip1_deliverLoc\",
        t1.deliver_datetime AS \"trip1_deliverDate\", t1.withdraw_location AS \"trip1_withdrawLoc\",
        t1.withdraw_datetime AS \"trip1_withdrawDate\", t1.required_date AS trip1_required,
        t1.costumer AS trip1_costumer, t1.trip_status AS trip1_status,
        t1.trip_trailer AS trip1_trailer, t1.trip_genset AS trip1_genset,
        -- trip2 fields
        t2.trip_from AS trip2_from, t2.trip_to AS trip2_to, t2.trip_container AS trip2_container,
        t2.trip_containerstat AS \"trip2_containerStat\", t2.trip_haulingsegment AS trip2_segment,
        t2.trip_haulingtype AS trip2_type, t2.container_activity AS trip2_activity, t2.km_run AS trip2_km,
        t2.trip_departuredatetime AS trip2_depart, t2.trip_arrivaldatetime AS trip2_arrival,
        t2.trip_pharrivaldatetime AS trip2_pharrival, t2.deliver_location AS \"trip2_deliverLoc\",
        t2.deliver_datetime AS \"trip2_deliverDate\", t2.withdraw_location AS \"trip2_withdrawLoc\",
        t2.withdraw_datetime AS \"trip2_withdrawDate\", t2.required_date AS trip2_required,
        t2.costumer AS trip2_costumer, t2.trip_status AS trip2_status,
        t2.trip_trailer AS trip2_trailer, t2.trip_genset AS trip2_genset,
        -- trip3 fields
        t3.trip_from AS trip3_from, t3.trip_to AS trip3_to, t3.trip_container AS trip3_container,
        t3.trip_containerstat AS \"trip3_containerStat\", t3.trip_haulingsegment AS trip3_segment,
        t3.trip_haulingtype AS trip3_type, t3.container_activity AS trip3_activity, t3.km_run AS trip3_km,
        t3.trip_departuredatetime AS trip3_depart, t3.trip_arrivaldatetime AS trip3_arrival,
        t3.trip_pharrivaldatetime AS trip3_pharrival, t3.deliver_location AS \"trip3_deliverLoc\",
        t3.deliver_datetime AS \"trip3_deliverDate\", t3.withdraw_location AS \"trip3_withdrawLoc\",
        t3.withdraw_datetime AS \"trip3_withdrawDate\", t3.required_date AS trip3_required,
        t3.costumer AS trip3_costumer, t3.trip_status AS trip3_status,
        t3.trip_trailer AS trip3_trailer, t3.trip_genset AS trip3_genset,
        -- trip4 fields
        t4.trip_from AS trip4_from, t4.trip_to AS trip4_to, t4.trip_container AS trip4_container,
        t4.trip_containerstat AS \"trip4_containerStat\", t4.trip_haulingsegment AS trip4_segment,
        t4.trip_haulingtype AS trip4_type, t4.container_activity AS trip4_activity, t4.km_run AS trip4_km,
        t4.trip_departuredatetime AS trip4_depart, t4.trip_arrivaldatetime AS trip4_arrival,
        t4.trip_pharrivaldatetime AS trip4_pharrival, t4.deliver_location AS \"trip4_deliverLoc\",
        t4.deliver_datetime AS \"trip4_deliverDate\", t4.withdraw_location AS \"trip4_withdrawLoc\",
        t4.withdraw_datetime AS \"trip4_withdrawDate\", t4.required_date AS trip4_required,
        t4.costumer AS trip4_costumer, t4.trip_status AS trip4_status,
        t4.trip_trailer AS trip4_trailer, t4.trip_genset AS trip4_genset
    FROM dispatch d
    LEFT JOIN drivers drv ON d.driver_id = drv.driver_id
    LEFT JOIN trips t1 ON d.d_id = t1.d_id AND t1.trip_type = 'Trip 1'
    LEFT JOIN trips t2 ON d.d_id = t2.d_id AND t2.trip_type = 'Trip 2'
    LEFT JOIN trips t3 ON d.d_id = t3.d_id AND t3.trip_type = 'Trip 3'
    LEFT JOIN trips t4 ON d.d_id = t4.d_id AND t4.trip_type = 'Trip 4'
    WHERE 1=1
    ";
// WHERE (t1.trip_status = 'Done' OR t2.trip_status = 'Done')
    if (!empty($customer)) {
        $query .= " AND d.costumer = '" . pt_pg_escape($conn, $customer) . "'";
    }
    if (!empty($fromDate) && !empty($toDate)) {
        $query .= " AND d.d_datetime BETWEEN '$fromDate' AND '$toDate'";
    }

    $query .= " ORDER BY d.d_datetime ASC";
    $result = $conn->query($query);

    if (($result)->rowCount() > 0) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ===== Report Title =====
        $sheet->mergeCells('A1:AF1');
        $sheet->setCellValue('A1', 'Trips Report');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:AF2');
        $sheet->setCellValue('A2', 'Customer: ' . ($customer ?: 'All'));

        $sheet->mergeCells('A3:AF3');
        $sheet->setCellValue('A3', 'Date Range: ' . ($fromDate && $toDate ? "$fromDate to $toDate" : 'All Dates'));

        $sheet->mergeCells('A4:AF4');
        $sheet->setCellValue('A4', 'Date Prepared: ' . date('Y-m-d'));

        // ===== Headers =====
        $headers = [
            'A6' => 'Trip Type', 'B6' => 'Booking No', 'C6' => 'Dispatch Date', 'D6' => 'Dispatch Time',
            'E6' => 'Dispatch Hub', 'F6' => 'Dispatcher', 'G6' => 'Driver ID',
            'H6' => 'Driver Name', 'I6' => 'Truck', 'J6' => 'Trailer (trip override)',
            'K6' => 'Genset (trip override)', 'L6' => 'Trip Receipt', 'M6' => 'ECS',
            'N6' => 'Trip From', 'O6' => 'Trip To', 'P6' => 'Container',
            'Q6' => 'Container Status', 'R6' => 'Segment', 'S6' => 'Type',
            'T6' => 'Activity', 'U6' => 'KM Run', 'V6' => 'Depart',
            'W6' => 'Arrival', 'X6' => 'PH Arrival', 'Y6' => 'Deliver Loc',
            'Z6' => 'Deliver Date', 'AA6' => 'Withdraw Loc', 'AB6' => 'Withdraw Date',
            'AC6' => 'Required Date', 'AD6' => 'Customer'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A6:AD6')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // ===== Insert Data =====
        $row = 7;

        while ($data = ($result)->fetch()) {

            // helper for dispatch details; applies trip-specific trailer/genset if provided
            $fillDispatchInfo = function ($r, $tripType, $tripNum) use ($sheet, $data) {
                $timestamp = strtotime($data['d_datetime']);

                $sheet->setCellValue("A$r", $tripType);
                $sheet->setCellValue("B$r", $data['booking_no']);

                // Dispatch Date
                $sheet->setCellValue("C$r", date('Y-m-d', $timestamp));
                $sheet->getStyle("C$r")->getNumberFormat()->setFormatCode('yyyy-mm-dd');

                // Dispatch Time
                $sheet->setCellValue("D$r", date('H:i:s', $timestamp));
                $sheet->getStyle("D$r")->getNumberFormat()->setFormatCode('hh:mm:ss');

                $sheet->setCellValue("E$r", $data['d_dispatchhub']);
                $sheet->setCellValue("F$r", $data['d_dispatcher']);
                $sheet->setCellValue("G$r", $data['driver_idnumber']);
                $sheet->setCellValue("H$r", $data['d_drivername']);
                $sheet->setCellValue("I$r", $data['d_truck']);

                // use trip-specific trailer/genset when available
                $trailer = $data['d_trailer'];
                $genset  = $data['d_genset'];
                if (!empty($data["trip{$tripNum}_trailer"])) {
                    $trailer = $data["trip{$tripNum}_trailer"];
                }
                if (!empty($data["trip{$tripNum}_genset"])) {
                    $genset = $data["trip{$tripNum}_genset"];
                }
                $sheet->setCellValue("J$r", $trailer);
                $sheet->setCellValue("K$r", $genset);

                $sheet->setCellValue("L$r", $data['d_tripreceipt']);
                $sheet->setCellValue("M$r", $data['d_ecs']);
            };

            // === Trip 1 ===
            if (!empty($data['trip1_from'])) {
                $fillDispatchInfo($row, 'Trip 1', 1);
                $sheet->setCellValue("N$row", $data['trip1_from']);
                $sheet->setCellValue("O$row", $data['trip1_to']);
                $sheet->setCellValue("P$row", $data['trip1_container']);
                $sheet->setCellValue("Q$row", $data['trip1_containerStat']);
                $sheet->setCellValue("R$row", $data['trip1_segment']);
                $sheet->setCellValue("S$row", $data['trip1_type']);
                $sheet->setCellValue("T$row", $data['trip1_activity']);
                $sheet->setCellValue("U$row", $data['trip1_km']);

                $dateCols = [
                    'V' => $data['trip1_depart'], 'W' => $data['trip1_arrival'],
                    'X' => $data['trip1_pharrival'], 'Y' => $data['trip1_deliverLoc'],
                    'Z' => $data['trip1_deliverDate'], 'AA' => $data['trip1_withdrawLoc'],
                    'AB' => $data['trip1_withdrawDate'], 'AC' => '0000-00-00 00:00:00', 'AD' => $data['trip1_costumer']
                ];

                foreach ($dateCols as $col => $val) {
                    if (!empty($val) && $val !== '0000-00-00 00:00:00') {
                        if (in_array($col, ['Z', 'AB', 'AC', 'V', 'W', 'X'])) {
                            $excelVal = ExcelDate::PHPToExcel(strtotime($val));
                            $sheet->setCellValue("$col$row", $excelVal);
                            $sheet->getStyle("$col$row")->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
                        } else {
                            $sheet->setCellValue("$col$row", $val);
                        }
                    }
                }

                $row++;
            }

            // === Trip 2 ===
            if (!empty($data['trip2_from'])) {
                $fillDispatchInfo($row, 'Trip 2', 2);
                $sheet->setCellValue("N$row", $data['trip2_from']);
                $sheet->setCellValue("O$row", $data['trip2_to']);
                $sheet->setCellValue("P$row", $data['trip2_container']);
                $sheet->setCellValue("Q$row", $data['trip2_containerStat']);
                $sheet->setCellValue("R$row", $data['trip2_segment']);
                $sheet->setCellValue("S$row", $data['trip2_type']);
                $sheet->setCellValue("T$row", $data['trip2_activity']);
                $sheet->setCellValue("U$row", $data['trip2_km']);

                $dateCols = [
                    'V' => $data['trip2_depart'], 'W' => $data['trip2_arrival'],
                    'X' => $data['trip2_pharrival'], 'Y' => $data['trip2_deliverLoc'],
                    'Z' => $data['trip2_deliverDate'], 'AA' => $data['trip2_withdrawLoc'],
                    'AB' => $data['trip2_withdrawDate'], 'AC' => '0000-00-00 00:00:00', 'AD' => $data['trip2_costumer']
                ];

                foreach ($dateCols as $col => $val) {
                    if (!empty($val) && $val !== '0000-00-00 00:00:00') {
                        if (in_array($col, ['Z', 'AB', 'AC', 'V', 'W', 'X'])) {
                            $excelVal = ExcelDate::PHPToExcel(strtotime($val));
                            $sheet->setCellValue("$col$row", $excelVal);
                            $sheet->getStyle("$col$row")->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
                        } else {
                            $sheet->setCellValue("$col$row", $val);
                        }
                    }
                }

                $row++;
            }

            // === Trip 3 ===
            if (!empty($data['trip3_from'])) {
                $fillDispatchInfo($row, 'Trip 3', 3);
                $sheet->setCellValue("N$row", $data['trip3_from']);
                $sheet->setCellValue("O$row", $data['trip3_to']);
                $sheet->setCellValue("P$row", $data['trip3_container']);
                $sheet->setCellValue("Q$row", $data['trip3_containerStat']);
                $sheet->setCellValue("R$row", $data['trip3_segment']);
                $sheet->setCellValue("S$row", $data['trip3_type']);
                $sheet->setCellValue("T$row", $data['trip3_activity']);
                $sheet->setCellValue("U$row", $data['trip3_km']);

                $dateCols = [
                    'V' => $data['trip3_depart'], 'W' => $data['trip3_arrival'],
                    'X' => $data['trip3_pharrival'], 'Y' => $data['trip3_deliverLoc'],
                    'Z' => $data['trip3_deliverDate'], 'AA' => $data['trip3_withdrawLoc'],
                    'AB' => $data['trip3_withdrawDate'], 'AC' => '0000-00-00 00:00:00', 'AD' => $data['trip3_costumer']
                ];

                foreach ($dateCols as $col => $val) {
                    if (!empty($val) && $val !== '0000-00-00 00:00:00') {
                        if (in_array($col, ['Z', 'AB', 'AC', 'V', 'W', 'X'])) {
                            $excelVal = ExcelDate::PHPToExcel(strtotime($val));
                            $sheet->setCellValue("$col$row", $excelVal);
                            $sheet->getStyle("$col$row")->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
                        } else {
                            $sheet->setCellValue("$col$row", $val);
                        }
                    }
                }

                $row++;
            }

            // === Trip 4 ===
            if (!empty($data['trip4_from'])) {
                $fillDispatchInfo($row, 'Trip 4', 4);
                $sheet->setCellValue("N$row", $data['trip4_from']);
                $sheet->setCellValue("O$row", $data['trip4_to']);
                $sheet->setCellValue("P$row", $data['trip4_container']);
                $sheet->setCellValue("Q$row", $data['trip4_containerStat']);
                $sheet->setCellValue("R$row", $data['trip4_segment']);
                $sheet->setCellValue("S$row", $data['trip4_type']);
                $sheet->setCellValue("T$row", $data['trip4_activity']);
                $sheet->setCellValue("U$row", $data['trip4_km']);

                $dateCols = [
                    'V' => $data['trip4_depart'], 'W' => $data['trip4_arrival'],
                    'X' => $data['trip4_pharrival'], 'Y' => $data['trip4_deliverLoc'],
                    'Z' => $data['trip4_deliverDate'], 'AA' => $data['trip4_withdrawLoc'],
                    'AB' => $data['trip4_withdrawDate'], 'AC' => '0000-00-00 00:00:00', 'AD' => $data['trip4_costumer']
                ];

                foreach ($dateCols as $col => $val) {
                    if (!empty($val) && $val !== '0000-00-00 00:00:00') {
                        if (in_array($col, ['Z', 'AB', 'AC', 'V', 'W', 'X'])) {
                            $excelVal = ExcelDate::PHPToExcel(strtotime($val));
                            $sheet->setCellValue("$col$row", $excelVal);
                            $sheet->getStyle("$col$row")->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
                        } else {
                            $sheet->setCellValue("$col$row", $val);
                        }
                    }
                }

                $row++;
            }
        }

        $lastRow = $row - 1;

        $sheet->getStyle("A6:AD{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);

        // ===== Auto-size columns =====
        foreach ($sheet->getColumnIterator() as $col) {
            $sheet->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
        }

        // ===== Output file =====
        $filename = "Trips_Report_" . date('Ymd_His');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename.xlsx\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } else {
        echo "<script>alert('No records found for the selected filters!');window.close();</script>";
    }
}
?>
