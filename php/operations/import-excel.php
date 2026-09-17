<?php
include '../config/config.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {

    $file = $_FILES['import_file'];

    if ($file['error'] != UPLOAD_ERR_OK) {
        echo "File upload error.";
        exit;
    }

    $allowed_ext = ['xls', 'xlsx', 'csv'];
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (!in_array($file_ext, $allowed_ext)) {
        echo "Invalid file type.";
        exit;
    }

    try {
        $spreadsheet = IOFactory::load($file['tmp_name']);

        // ✅ ONLY TEMPLATE SHEET
        $sheet = $spreadsheet->getSheetByName('TEMPLATE');
        if (!$sheet) {
            echo "Sheet 'TEMPLATE' not found.";
            exit;
        }

        $highestRow = $sheet->getHighestRow();

        // Phase 14.1 — booking_no per row uses Customer-BN-From-To.
        require_once __DIR__ . '/../lib/booking_no.php';

        $inserted = 0;

        for ($r = 2; $r <= $highestRow; $r++) {

            $customer = trim($sheet->getCell("B$r")->getValue());
            if ($customer == '') continue;

            // Dates
            $booking_date = $sheet->getCell("A$r")->getValue();
            if (Date::isDateTime($sheet->getCell("A$r"))) {
                $booking_date = Date::excelToDateTimeObject($booking_date)->format('Y-m-d');
            }

            $dateRequired = $sheet->getCell("D$r")->getValue();
            if (Date::isDateTime($sheet->getCell("D$r"))) {
                $dateRequired = Date::excelToDateTimeObject($dateRequired)->format('Y-m-d');
            }

            // Other fields
            $container        = pt_pg_escape($conn, $sheet->getCell("E$r")->getValue());
            $seal             = pt_pg_escape($conn, $sheet->getCell("F$r")->getValue());
            $activity         = pt_pg_escape($conn, $sheet->getCell("G$r")->getValue());
            $status           = pt_pg_escape($conn, $sheet->getCell("H$r")->getValue());
            $hauling_segment  = pt_pg_escape($conn, $sheet->getCell("I$r")->getValue());
            $trip_from        = pt_pg_escape($conn, $sheet->getCell("J$r")->getValue());
            $trip_to          = pt_pg_escape($conn, $sheet->getCell("K$r")->getValue());
            $quantity         = intval($sheet->getCell("L$r")->getValue());

            // Generate booking_no from the row's customer + from + to.
            $currentBookingNo = bn_generate($conn, $customer, $trip_from, $trip_to);

            $sql = "
                INSERT INTO booking (
                    booking_no,
                    booking_date,
                    booking_dateRequired,
                    costumer,
                    container,
                    container_seal,
                    booking_activity,
                    container_status,
                    hauling_segment,
                    trip_from,
                    trip_to,
                    quantity,
                    status
                ) VALUES (
                    '$currentBookingNo',
                    '$booking_date',
                    '$dateRequired',
                    '$customer',
                    '$container',
                    '$seal',
                    '$activity',
                    '$status',
                    '$hauling_segment',
                    '$trip_from',
                    '$trip_to',
                    '$quantity',
                    'Active'
                )
            ";

            if (!$conn->query($sql)) {
                echo "Insert error on row $r: " . (($conn->errorInfo()[2]) ?? "");
                exit;
            }

            $inserted++;
        }

        echo "$inserted records inserted successfully.";
        

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "No file uploaded.";
}
?>
