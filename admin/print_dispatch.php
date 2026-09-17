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
        .title { font-size: 25px; font-weight: bold; text-align: center; }
        .logo { float: left; }
        .transaction { float: right; text-align: right; font-size: 12px; }
        .clear { clear: both; }
        .section-title { font-weight: bold; text-align: left; margin-top: 15px; }
        .small { font-size: 10px; }

        td.atd, td.ata {
            width: 80px;   /* adjust width */
            height: 20px;  /* adjust height */
            text-align: center;
        }

        .copy-label {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            letter-spacing: 1px;
            margin: 4px 0;
            text-transform: uppercase;
        }
        .ticket-coupon-panel { width: 48%; float: right; box-sizing: border-box; min-width: 300px; margin-top: 20px; border: 3px solid #7a1220; }
        .ticket-coupon-heading { background: #7a1220; color: #fff; text-align: center; padding: 8px; font-size: 14px; font-weight: bold; }
        .ticket-coupon-number { color: #7a1220; font-size: 16px; font-weight: bold; padding: 8px 10px 2px; text-align: right; }
        .ticket-coupon-grid { padding: 4px 10px 8px; font-size: 11px; }
        .ticket-coupon-grid div { border-bottom: 1px dotted #aaa; padding: 4px 0; word-break: break-word; }
        .ticket-coupon-qr-row { border-top: 1px solid #7a1220; display: flex; align-items: center; gap: 8px; padding: 8px 10px; font-size: 10px; }
        .ticket-coupon-pending { min-height: 180px; display: flex; align-items: center; justify-content: center; padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body onload="setTimeout(function(){ window.print(); }, 500)" onafterprint="window.close();">

<?php $ticketCopy = 'driver'; ?>
<div class="ticket-copy">
<div class="copy-label">Driver's Copy</div>

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
                <b>TRIP1:</b> <?= $dispatch['d_tripreceipt'] ?>
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
            // Rendered only when the dispatcher picked an origin during assignment.
            $tripOrigin = trim((string)($dispatch['d_origin'] ?? ''));
            $trip1From  = $trips['Trip 1']['trip_from'] ?? '';
            // Trailer hauled on the origin leg — recorded separately at assignment.
            // Blank means the truck ran bobtail to the pickup.
            $originTrailer = trim((string)($dispatch['d_origin_trailer'] ?? ''));
        ?>
        <?php if ($tripOrigin !== '' && strcasecmp($tripOrigin, trim((string)$trip1From)) !== 0): ?>
        <!-- Origin pre-leg — only when the origin differs from Trip 1's pickup -->
        <tr>
            <td><?= htmlspecialchars($tripOrigin) ?></td>
            <td><?= htmlspecialchars((string)$trip1From) ?></td>
            <td><?= htmlspecialchars((string)($trips['Trip 1']['trip_haulingsegment'] ?? '-')) ?></td>
            <td>Repositioning</td>
            <td><?= $originTrailer !== '' ? htmlspecialchars($originTrailer) : '-' ?></td>
            <td><?= $dispatch['d_genset'] ?></td>
            <td>-</td>
            <td class="atd"></td>
            <td class="ata"></td>
            <td>-</td>
        </tr>
        <?php endif; ?>

        <?php include __DIR__ . '/../php/lib/trip_ticket_rows.php'; ?>
    </table>
</div>

<div class="section small">
    <b>Booking no:</b>  <?= $dispatch['booking_no'] ?> <b>Remarks:</b> _______
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
<?php include __DIR__ . '/../php/lib/trip_ticket_coupon_panel.php'; ?>
<div class="clear"></div>

</div><!-- /.ticket-copy -->

</body>
</html>
