<?php
require_once('TCPDF-main/tcpdf.php');
include '../php/config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fromDate = $_POST['fromDate'] ?? '';
    $toDate   = $_POST['toDate'] ?? '';
    $user_id  = $_POST['user_Id1'] ?? '';

    if (empty($user_id)) {
        die('Driver not specified.');
    }

    // =========================
    // DATE FILTER LOGIC
    // =========================
    $dateCondition = "";
    $params = [$user_id];
    $types  = "i";

    if (!empty($fromDate) && !empty($toDate)) {
        $dateCondition = " AND DATE(d.d_datetime) BETWEEN ? AND ? ";
        $params[] = $fromDate;
        $params[] = $toDate;
        $types   .= "ss";
        $dateLabel = date('F j, Y', strtotime($fromDate)) . " - " . date('F j, Y', strtotime($toDate));
    } elseif (!empty($fromDate)) {
        $dateCondition = " AND DATE(d.d_datetime) >= ? ";
        $params[] = $fromDate;
        $types   .= "s";
        $dateLabel = "From " . date('F j, Y', strtotime($fromDate));
    } elseif (!empty($toDate)) {
        $dateCondition = " AND DATE(d.d_datetime) <= ? ";
        $params[] = $toDate;
        $types   .= "s";
        $dateLabel = "Up to " . date('F j, Y', strtotime($toDate));
    } else {
        $dateLabel = "ALL DATES";
    }

    // =========================
    // GET DRIVER NAME
    // =========================
    $driverStmt = $conn->prepare("
        SELECT CONCAT(driver_lname, ', ', driver_fname) AS driver_name
        FROM drivers
        WHERE driver_id = ?
    ");
    $driverStmt->execute([$user_id]);
    $driver = $driverStmt->fetch();
    $driverName = $driver['driver_name'] ?? 'Unknown Driver';

    // =========================
    // COUNT DONE TRIPS
    // =========================
    $countSQL = "
        SELECT COUNT(*) AS total_trips
        FROM dispatch d
        INNER JOIN trips t ON d.d_id = t.d_id
        WHERE d.driver_id = ?
          AND t.trip_status = 'DONE'
          $dateCondition
    ";

    $countStmt = $conn->prepare($countSQL);
    $countStmt->execute($params);
    $totalTrips = $countStmt->fetch()['total_trips'];

    // =========================
    // CREATE PDF
    // =========================
    $pdf = new TCPDF();
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    // Title
    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->Cell(0, 8, 'DRIVER ACCOMPLISHMENT', 0, 1, 'C');

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, "Driver: $driverName", 0, 1, 'L');
    $pdf->Cell(0, 6, "Date Range: $dateLabel", 0, 1, 'L');
    $pdf->Cell(0, 6, "Total Completed Trips: $totalTrips", 0, 1, 'L');

    $pdf->Ln(4);

    // =========================
    // TABLE HEADER
    // =========================
    $tbl = '
    <table border="1" cellpadding="4">
        <thead>
            <tr style="background-color:#f2f2f2; font-weight:bold;">
                <th width="10%">Booking No</th>
                <th width="10%">Dispatch date</th>
                <th width="10%">Truck</th>
                <th width="10%">Trailer</th>
                <th width="10%">Genset</th>
                <th width="10%">Customer</th>
                <th width="10%">Container</th>
                <th width="10%">Segment</th>
                <th width="10%">From</th>
                <th width="10%">To</th>
            </tr>
        </thead>
        <tbody>
    ';

    // =========================
    // MAIN QUERY
    // =========================
    $sql = "
        SELECT 
            d.booking_no,
            d.d_datetime,
            d.d_truck,
            d.d_trailer,
            d.d_genset,
            d.costumer,
            t.trip_container,
            t.trip_haulingSegment,
            t.trip_from,
            t.trip_to
        FROM dispatch d
        INNER JOIN trips t ON d.d_id = t.d_id
        WHERE d.driver_id = ?
          AND t.trip_status = 'Done'
          $dateCondition
        ORDER BY d.d_datetime DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt;

    if ($result->rowCount() > 0) {
        while ($row = $result->fetch()) {
            $tbl .= "
            <tr>
                <td>{$row['booking_no']}</td>
                <td>" . date('M d, Y', strtotime($row['d_datetime'])) . "</td>
                <td>{$row['d_truck']}</td>
                <td>{$row['d_trailer']}</td>
                <td>{$row['d_genset']}</td>
                <td>{$row['costumer']}</td>
                <td>{$row['trip_container']}</td>
                <td>{$row['trip_haulingsegment']}</td>
                <td>{$row['trip_from']}</td>
                <td>{$row['trip_to']}</td>
            </tr>";
        }
    } else {
        $tbl .= '<tr><td colspan="10" align="center">No completed trips found.</td></tr>';
    }

    $tbl .= '</tbody></table>';

    $pdf->SetFont('helvetica', '', 8);
    $pdf->writeHTML($tbl, true, false, false, false, '');

    $pdf->Output('Driver_Completed_Trips_Report.pdf', 'I');
}
?>
