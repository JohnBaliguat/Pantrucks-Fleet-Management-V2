<?php
// POST handler for the Gastender refueling ticket form.
// Inserts a fuel_report row, links/marks ticket_code if a code was used,
// and FIFO-deducts liters from fuel_inventory while logging consume_fuel.
include __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

date_default_timezone_set("Asia/Manila");

$controlNo         = $_POST['controlNo'] ?? '';
$selectedEquipment = $_POST['selectedEquipment'] ?? '';
$selectedDriver    = $_POST['selectedDriver'] ?? '';
$meterRead         = $_POST['meterRead'] ?? '0';
$meterRead1        = $_POST['meterRead1'] ?? '0';
$hourMeter         = $_POST['hourMeter'] ?? '0';
$lastHourMeter     = $_POST['lastHourMeter'] ?? '0';
$manualDate        = $_POST['manualDate'] ?? '';
$lastHubo          = $_POST['lastHubo'] ?? '0';
$totalKmRun        = $_POST['totalKmRun'] ?? '0';
$totalKmRun1       = $_POST['totalKmRun1'] ?? '0';
$noLiter           = $_POST['noLiter'] ?? '0';
$actualRatio       = $_POST['actualRatio'] ?? '0';
$givenRatio        = $_POST['givenRatio'] ?? '0';
$ideNoLt           = $_POST['ideNoLt'] ?? '0';
$excessSave        = $_POST['excessSave'] ?? '0';
$userName          = $_POST['userName'] ?? '';
$driverID          = $_POST['searchId'] ?? '';
$ticketCodeInput   = isset($_POST['ticketCode']) ? strtoupper(trim($_POST['ticketCode'])) : '';
$legacyTicketTrip  = isset($_POST['ticketTrip']) ? trim($_POST['ticketTrip']) : '';

// Guard: hour meter must not regress.
if (!empty($hourMeter) && !empty($lastHourMeter) && floatval($hourMeter) < floatval($lastHourMeter)) {
    http_response_code(400);
    exit("Hour meter reading cannot be less than the previous reading. Current: $hourMeter, Previous: $lastHourMeter");
}

$date = !empty($manualDate) ? date('Y-m-d H:i:s', strtotime($manualDate)) : date('Y-m-d H:i:s');

// Build trip segment string from checkboxes.
$segmentMap = [
    'bb_dm'      => 'BB/DM',
    'dm_rv'      => 'DM/RV',
    'dole_rv'    => 'DOLE/RV',
    'sumi'       => 'SUMI',
    'abc'        => 'ABC(PANTUKAN)',
    'cat_donmar' => 'CAT-DONMAR',
    'other'      => 'OTHER',
];
$segments = [];
foreach ($segmentMap as $key => $label) {
    if (!empty($_POST[$key])) $segments[] = $label;
}
$f_tripsegment = implode(', ', $segments);

$conn->beginTransaction();

try {
    $ticketCodeRow = null;

    // Validate ticket code (if provided)
    if ($ticketCodeInput !== '' && pt_table_exists($conn, 'ticket_code')) {
        $escaped = pt_pg_escape($conn, $ticketCodeInput);
        $tcResult = $conn->query("SELECT tc_id, tc_code, f_driverId, unit_name, tc_status
                                          FROM ticket_code WHERE tc_code = '$escaped' LIMIT 1");
        if (!$tcResult || ($tcResult)->rowCount() === 0) {
            throw new Exception('Invalid ticket code.');
        }
        $ticketCodeRow = ($tcResult)->fetch();

        if (strcasecmp($ticketCodeRow['tc_status'], 'Used') === 0) {
            throw new Exception('This ticket code was already used.');
        }
        if (!empty($ticketCodeRow['f_driverid']) && !empty($driverID) && $ticketCodeRow['f_driverid'] !== $driverID) {
            throw new Exception('The ticket code does not match the selected driver.');
        }
        if (!empty($ticketCodeRow['unit_name']) && !empty($selectedEquipment)
            && strcasecmp(trim($ticketCodeRow['unit_name']), trim($selectedEquipment)) !== 0) {
            throw new Exception('The ticket code does not match the selected unit.');
        }
    }

    // Insert into fuel_report.
    $escSel    = pt_pg_escape($conn, $selectedEquipment);
    $escDrv    = pt_pg_escape($conn, $selectedDriver);
    $escUser   = pt_pg_escape($conn, $userName);
    $escDrvID  = pt_pg_escape($conn, $driverID);
    $escCtl    = pt_pg_escape($conn, $controlNo);
    $escSeg    = pt_pg_escape($conn, $f_tripsegment);

    $sql = "INSERT INTO fuel_report (
                f_date, f_unit, f_lastHubo, f_hubo, calculated_hubo, f_kmRun, f_noOfLit,
                f_actRatio, f_driver, f_STDRatio, f_excess_Saving, f_hourMeter, f_controlNo,
                f_ideNoLt, f_tripsegment, f_trasactionBy, f_driverId
            ) VALUES (
                '$date', '$escSel', '$lastHubo', '$meterRead', '$meterRead1', '$totalKmRun', '$noLiter',
                '$actualRatio', '$escDrv', '$givenRatio', '$excessSave', '$hourMeter', '$escCtl',
                '$ideNoLt', '$escSeg', '$escUser', '$escDrvID'
            )";

    if (!$conn->query($sql)) {
        throw new Exception('Error saving report: ' . (($conn->errorInfo()[2]) ?? ""));
    }

    // Link / mark ticket code.
    $tripData = isset($_POST['tripData']) ? json_decode($_POST['tripData'], true) : [];
    if (!is_array($tripData)) $tripData = [];

    if ($ticketCodeRow) {
        $tcId = (int)$ticketCodeRow['tc_id'];
        if (pt_table_exists($conn, 'trip_receipts')) {
            if (!$conn->query("UPDATE trip_receipts SET control_no = '$escCtl' WHERE tc_id = $tcId")) {
                throw new Exception((($conn->errorInfo()[2]) ?? ""));
            }
        }
        if (!$conn->query("UPDATE ticket_code SET tc_status = 'Used' WHERE tc_id = $tcId")) {
            throw new Exception((($conn->errorInfo()[2]) ?? ""));
        }
    } elseif (!empty($tripData) && $legacyTicketTrip === '' && pt_table_exists($conn, 'trip_receipts')) {
        // Legacy fallback: caller submitted trips directly without a ticket code.
        foreach ($tripData as $trip) {
            $trNumber = pt_pg_escape($conn, $trip['tr'] ?? '');
            $from     = pt_pg_escape($conn, $trip['from'] ?? '');
            $to       = pt_pg_escape($conn, $trip['to'] ?? '');
            $km       = pt_pg_escape($conn, $trip['km'] ?? '0');
            $km1      = pt_pg_escape($conn, $trip['km1'] ?? '0');
            $insert = "INSERT INTO trip_receipts (control_no, tr_number, location_from, location_to, total_km, maptotal_kmRun)
                       VALUES ('$escCtl', '$trNumber', '$from', '$to', '$km', '$km1')";
            if (!$conn->query($insert)) {
                throw new Exception((($conn->errorInfo()[2]) ?? ""));
            }
        }
    }

    // FIFO-deduct from fuel_inventory.
    if (pt_table_exists($conn, 'fuel_inventory')) {
        $remaining = floatval($noLiter);
        $invResult = $conn->query("SELECT * FROM fuel_inventory WHERE fi_consumableltr > 0 ORDER BY fi_date ASC");
        if ($invResult && ($invResult)->rowCount() > 0) {
            while ($remaining > 0 && $row = ($invResult)->fetch()) {
                $id = (int)$row['fi_id'];
                $available = floatval($row['fi_consumableltr']);
                $consumed  = floatval($row['fi_consumeltr']);

                if ($available >= $remaining) {
                    $toConsume   = $remaining;
                    $newAvail    = $available - $remaining;
                    $newConsumed = $consumed + $remaining;
                    $conn->query("UPDATE fuel_inventory SET fi_consumableLtr=$newAvail, fi_consumeLtr=$newConsumed WHERE fi_id=$id");
                    if (pt_table_exists($conn, 'consume_fuel')) {
                        $conn->query("INSERT INTO consume_fuel (unit, driver, hubo, consumeLtr, date)
                                              VALUES ('$escSel', '$escDrv', '$meterRead', '$toConsume', NOW())");
                    }
                    $remaining = 0;
                } else {
                    $toConsume   = $available;
                    $newConsumed = $consumed + $available;
                    $conn->query("UPDATE fuel_inventory SET fi_consumableLtr=0, fi_consumeLtr=$newConsumed WHERE fi_id=$id");
                    if (pt_table_exists($conn, 'consume_fuel')) {
                        $conn->query("INSERT INTO consume_fuel (unit, driver, hubo, consumeLtr, date)
                                              VALUES ('$escSel', '$escDrv', '$meterRead', '$toConsume', NOW())");
                    }
                    $remaining -= $available;
                }
            }
            $conn->commit();
            echo "Record added and fuel inventory updated successfully!";
            exit;
        }
    }

    $conn->commit();
    echo "Record added, but no available fuel inventory to deduct from.";
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(400);
    echo $e->getMessage();
}
