<?php
include "php/config/config.php";

if (!isset($_GET['id'])) {
    die("Missing dispatch ID");
}

$id = intval($_GET['id']);

// Fetch dispatch
$dispatchStmt = $conn->prepare("SELECT * FROM dispatch WHERE d_id = ?");
$dispatchStmt->execute([$id]);
$dispatchResult = $dispatchStmt;

if ($dispatchResult->rowCount() === 0) {
    die("No dispatch record found.");
}
$dispatch = $dispatchResult->fetch();

require_once "php/lib/trip_ticket_gate.php";
require_driver_accepted_for_ticket($dispatch);

$truck = $dispatch['d_truck'];

// Fetch dispatch
$plateNo = $conn->prepare("SELECT * FROM units WHERE unit_name = ?");
$plateNo->execute([$truck]);
$plateNoResult = $plateNo;

if ($plateNoResult->rowCount() === 0) {
    die("No dispatch record found.");
}
$plateNo1 = $plateNoResult->fetch();



// Fetch trips
$tripStmt = $conn->prepare("SELECT * FROM trips WHERE d_id = ? ORDER BY trip_type ASC");
$tripStmt->execute([$id]);
$tripResult = $tripStmt;

$trips = [];
while ($trip = $tripResult->fetch()) {
    $trips[$trip['trip_type']] = $trip;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dispatch Ticketing System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 10px; }
        .header, .section { border: 1px solid #000; padding: 10px; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        td, th { border: 1px solid black; padding: 4px; font-size: 12px; }
        .title { font-size: 18px; font-weight: bold; text-align: center; }
        .logo { float: left; }
        .transaction { float: right; text-align: right; font-size: 12px; }
        .clear { clear: both; }
        .section-title { font-weight: bold; text-align: left; margin-top: 15px; }
        .small { font-size: 10px; }
    </style>
</head>
<body onload="window.print()" onafterprint="window.close();">

<div class="header">
    <div class="logo">
        <img src="assets/images/logos/pantrucks.png" height="40">
    </div>
    <div class="transaction">
        <b>Transaction Code</b><br>
        TRANSACTION NO.: <?= $dispatch['d_id'] ?><br>
        Date: <?= date("m/d/Y H:i", strtotime($dispatch['d_datetime'])) ?>
    </div>
    <div class="clear"></div>
    <div class="title">Trip Ticket</div>
</div>

<div class="section">
    <table>
        <tr>
            <td><b>Driver name:</b> <?= $dispatch['d_drivername'] ?></td>
            <td><b>Truck no:</b> <?= $dispatch['d_truck'] ?></td>
            <td><b>Plate no:</b> <?= $plateNo1['unit_plate'] ?></td>
        </tr>
        <tr>
            <td><b>TripReceipt(ECS):</b> <?= $dispatch['d_tripreceipt'] . ' (' . $dispatch['d_ecs'] .')'?></td>
            <td>
                <b>TRIP1:</b> <?= $dispatch['d_tripreceipt'] ?> |
                <b>TRIP2:</b> <?= isset($trips['Trip 2']) ? $dispatch['d_tripreceipt'] : '-' ?>
            </td>
            <td><b>Est. Departure Time:</b> <?= date("H:i", strtotime($dispatch['d_datetime'])) ?></td>
        </tr>
        <tr>
            <td colspan="3"><b>Dispatcher:</b> <?= $dispatch['d_dispatcher'] ?></td>
        </tr>
    </table>

    <div class="section-title">Destination / Segment / Equipment</div>
    <table>
        <tr>
            <th>From</th><th>To</th><th>Hauling Seg</th><th>Hauling Job</th><th>Trailer</th><th>Genset</th>
            <th>Van no</th><th>ATD</th><th>ATA</th><th>Empty/Loaded</th>
        </tr>

        <?php
            // Pre-leg row — origin (PTSI / CONSOL / OUTSIDE last location) → Trip 1's pickup.
            $tripOrigin = trim((string)($dispatch['d_origin'] ?? ''));
            $trip1From  = $trips['Trip 1']['trip_from'] ?? '';
        ?>
        <?php if ($tripOrigin !== '' && strcasecmp($tripOrigin, trim((string)$trip1From)) !== 0): ?>
        <!-- Origin pre-leg — only when the origin differs from Trip 1's pickup -->
        <tr>
            <td><?= htmlspecialchars($tripOrigin) ?></td>
            <td><?= htmlspecialchars((string)$trip1From) ?></td>
            <td><?= htmlspecialchars((string)($trips['Trip 1']['trip_haulingsegment'] ?? '-')) ?></td>
            <td>Repositioning</td>
            <td><?= $dispatch['d_trailer'] ?></td>
            <td><?= $dispatch['d_genset'] ?></td>
            <td>-</td>
            <td></td>
            <td></td>
            <td>-</td>
        </tr>
        <?php endif; ?>

        <?php include __DIR__ . '/../php/lib/trip_ticket_rows.php'; ?>
    </table>
</div>

<div class="section small">
    <b>Booking no:</b>  <?php if (strpos($dispatch['booking_no'], 'PTSIBN') === 0): ?>
    <?= htmlspecialchars($dispatch['booking_no']) ?>
<?php else: ?>
    N/A
<?php endif; ?> <b>Remarks:</b> _______
    <div style="float:right;"><b>Approved by:</b> RD. Felicano</div>
</div>

<!-- Security Pass Section -->
<div class="section" style="width: 48%; float: left; box-sizing: border-box; min-width: 300px; margin-top: 20px;">
    <div class="title">SECURITY PASS</div>
    <table>
        <tr>
            <td><b>Date:</b> <?= date("m/d/Y", strtotime($dispatch['d_datetime'])) ?></td>
            <td><b>Time:</b> <?= date("H:i", strtotime($dispatch['d_datetime'])) ?></td>
        </tr>
        <tr>
            <td><b>Destination:</b> <?= $trips['Trip 1']['trip_to'] ?? '-' ?></td>
            <td><b>From:</b> <?= $trips['Trip 1']['trip_from'] ?? '-' ?> <b>To:</b> <?= $trips['Trip 1']['trip_to'] ?? '-' ?></td>
        </tr>
    </table>

    <div class="section-title">EQUIPMENT / DRIVER DETAILS</div>
    <table>
        <tr><td colspan="2"><b>Name:</b> <?= $dispatch['d_drivername'] ?></td></tr>
        <tr><td colspan="2"><b>Vehicle:</b> <?= $dispatch['d_truck'] ?></td></tr>
        <tr><td colspan="2"><b>Trailer:</b> <?= htmlspecialchars($firstLegTrailer ?? $dispatch['d_trailer']) ?></td></tr>
        <tr><td colspan="2"><b>Genset:</b> <?= htmlspecialchars($firstLegGenset ?? $dispatch['d_genset']) ?></td></tr>
        <tr><td colspan="2">Dispatch to indicate "N/A" if equipment is not applicable</td></tr>
        <tr><td><b>Authorized Personnel:</b></td><td><b>Name and Signature</b></td></tr>
        <tr><td><b>Dispatcher:</b></td><td> <?= $dispatch['d_dispatcher'] ?></td></tr>
        <tr><td><b>Driver's Acknowledgement:</b></td><td></td></tr>
        <tr><td><b>Inspected/Verified by:</b></td><td> GATE-OUT</td></tr>
        <tr><td><b>Remarks:</b></td><td></td></tr>
        <tr><td colspan="2">This Security Pass is Valid for 1 hour from dispatch time only</td></tr>
    </table>
</div>

<!-- PAGE BREAK -->
<div style="page-break-before: always;"></div>

<!-- INCOMING T-CARD -->
<div class="section" style="width: 70%; box-sizing: border-box; margin-top: -750px; font-size: 14px; transform: rotate(-90deg); transform-origin: left top; page-break-before: always;">

    <style>
        .section table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* ensures equal column widths */
        }
        .section colgroup col:first-child {
            width: 50%; /* label column */
        }
        .section colgroup col:last-child {
            width: 50%; /* value column */
        }
        .section td {
            height: 30px;
            padding: 2px 5px;
            vertical-align: top;
        }
        .section b {
            font-weight: bold;
        }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center;">
            <img src='assets/images/logos/pantrucks.png' height='45' style='margin-right: 10px;'>
        </div>
        <div style="text-align: right;">
            <b>Series No.: <?= $dispatch['d_id'] ?></b><br>
        </div>
    </div>

    <div class="title" style="font-size: 15px; text-align: center; margin-bottom: 10px;">
        INCOMING T-CARD<br>
        <span style="font-size: 14px;">(Incoming Container Traveler’s Card)</span>
    </div>

    <!-- 1ST TABLE -->
    <table>
        <colgroup>
            <col><col>
        </colgroup>
        <tr><td><b>Date:</b> <?= date("M d, Y", strtotime($dispatch['d_datetime'])) ?></td><td><b>DO#:</b> <?= $dispatch['booking_do'] ?></td></tr>
        <tr><td><b>Broker:</b> <?= $dispatch['cth_broker'] ?></td><td><b>Destination:</b> <?= $trips['Trip 1']['trip_to'] ?? '-' ?></td></tr>
        <tr><td><b>Shipment #:</b> <?= $dispatch['booking_sn'] ?></td><td><b>Driver:</b> <?= $dispatch['d_drivername'] ?></td></tr>
        <tr><td><b>Trucker:</b> PanTrucks</td><td><b>Shipline:</b></td></tr>
        <tr><td colspan="2"><b>Van #:</b> <?= $trips['Trip 1']['trip_container'] ?? '-' ?></td></tr>
    </table>

    <br>

    <!-- 1. PORT WITHDRAWAL DETAILS -->
    <div style="font-weight: bold; font-size: 13px; margin-top: 10px;">1. PORT WITHDRAWAL DETAILS</div>
    <table>
        <colgroup>
            <col><col>
        </colgroup>
        <tr><td colspan="2"><b>Port Withdrawal Date:</b> <?= date("M d, Y", strtotime($dispatch['d_datetime'])) ?></td></tr>
        <tr><td><b>Port:</b> <?= $trips['Trip 1']['trip_from'] ?? '-' ?></td><td><b>EIR(Out):</b></td></tr>
        <tr><td><b>Guard/Checker:</b></td><td><b>Time:</b></td></tr>
    </table>

    <br>

    <!-- 2. GATE IN DETAILS -->
    <div style="font-weight: bold; font-size: 13px; margin-top: 10px;">2. GATE IN DETAILS</div>
    <table>
        <colgroup>
            <col><col>
        </colgroup>
        <tr><td colspan="2"><b>Gate In Date:</b></td></tr>
        <tr><td><b>Guard:</b></td><td><b>Time:</b></td></tr>
        <tr><td><b>Logistics:</b></td><td><b>Waybill:</b> <?= $dispatch['d_tripreceipt']?></td></tr>
    </table>

    <br>

    <!-- 3. GATE OUT DETAILS -->
    <div style="font-weight: bold; font-size: 13px; margin-top: 10px;">3. GATE OUT DETAILS</div>
    <table>
        <colgroup>
            <col><col>
        </colgroup>
        <tr><td><b>Gate Out Date:</b></td><td><b>Guard:</b></td></tr>
        <tr><td><b>Receiving In Charge:</b></td><td><b>Time:</b></td></tr>
        <tr><td><b>Logistics:</b></td><td><b>Driver:</b></td></tr>
    </table>

    <br>

    <!-- 4. VAN YARD RETURN DETAILS -->
    <div style="font-weight: bold; font-size: 13px; margin-top: 10px;">4. VAN YARD RETURN DETAILS</div>
    <table>
        <colgroup>
            <col><col>
        </colgroup>
        <tr><td colspan="2"><b>Van Yard Return Date:</b></td></tr>
        <tr><td><b>Port:</b> <?= $trips['Trip 1']['return_location'] ?? '-' ?></td><td><b>EIR(IN):</b></td></tr>
        <tr><td><b>Guard:</b></td><td><b>Time:</b></td></tr>
        <tr><td colspan="2"><b>Driver:</b></td></tr>
    </table>

    <br><br>

    <div style="text-align: center; font-size: 13px;">
        <b>Note:</b> This T-Card must accompany the container until final yard return.<br>
    </div>
</div>

</body>
</html>
