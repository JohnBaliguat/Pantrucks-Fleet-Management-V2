<?php
include "php/config/config.php";

if (!isset($_GET['ids'])) {
    die("Missing dispatch ID(s)");
}

$ids = explode(',', $_GET['ids']);
$ids = array_map('intval', $ids);

// Fetch dispatch
foreach ($ids as $id) {

    $dispatchStmt = $conn->prepare("SELECT * FROM dispatch WHERE d_id = ?");
    $dispatchStmt->execute([$id]);
    $dispatchResult = $dispatchStmt;

    if ($dispatchResult->rowCount() === 0) {
        continue;
    }

    $dispatch = $dispatchResult->fetch();

    // Skip dispatches the driver hasn't accepted — no trip ticket until then.
    $wfStage = strtolower(trim((string)($dispatch['workflow_stage'] ?? '')));
    if (in_array($wfStage, ['dispatcher_assigned', 'reassigned'], true)) {
        continue;
    }

    $truck = $dispatch['d_truck'];

    // Fetch plate number
    $plateNo = $conn->prepare("SELECT * FROM units WHERE unit_name = ?");
    $plateNo->execute([$truck]);
    $plateNoResult = $plateNo;
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

        <!-- FIRST BOOKING -->
        <!-- Trip 1 -->
        <tr>
            <td><?= $trips['Trip 1']['trip_from'] ?? '-' ?></td>
            <td><?= $trips['Trip 1']['trip_to'] ?? '-' ?></td>
            <td><?= $trips['Trip 1']['trip_haulingsegment'] ?? '-' ?></td>
            <td><?= $trips['Trip 1']['trip_haulingtype'] ?? '-' ?></td>
            <td><?= $dispatch['d_trailer'] ?></td>
            <td><?= $dispatch['d_genset'] ?></td>
            <td><?= $trips['Trip 1']['trip_container'] ?? '-' ?></td>
            <td></td>
            <td></td>
            <td><?= $trips['Trip 1']['trip_containerstat'] ?? '-' ?></td>
        </tr>

        <!-- Trip 2 -->
        <tr>
            <td><?= $trips['Trip 2']['trip_from'] ?? '-' ?></td>
            <td><?= $trips['Trip 2']['trip_to'] ?? '-' ?></td>
            <td><?= $trips['Trip 2']['trip_haulingsegment'] ?? '-' ?></td>
            <td><?= $trips['Trip 2']['trip_haulingtype'] ?? '-' ?></td>
            <td></td>
            <td></td>
            <td><?= $trips['Trip 2']['trip_container'] ?? '-' ?></td>
            <td></td>
            <td></td>
            <td><?= $trips['Trip 2']['trip_containerstat'] ?? '-' ?></td>
        </tr>
        <!-- SECOND BOOKING -->
        <!-- Trip 1 -->
        <tr>
            <td><?= $trips['Trip 1']['trip_from'] ?? '-' ?></td>
            <td><?= $trips['Trip 1']['trip_to'] ?? '-' ?></td>
            <td><?= $trips['Trip 1']['trip_haulingsegment'] ?? '-' ?></td>
            <td><?= $trips['Trip 1']['trip_haulingtype'] ?? '-' ?></td>
            <td><?= $dispatch['d_trailer'] ?></td>
            <td><?= $dispatch['d_genset'] ?></td>
            <td><?= $trips['Trip 1']['trip_container'] ?? '-' ?></td>
            <td></td>
            <td></td>
            <td><?= $trips['Trip 1']['trip_containerstat'] ?? '-' ?></td>
        </tr>

        <!-- Trip 2 -->
        <tr>
            <td><?= $trips['Trip 2']['trip_from'] ?? '-' ?></td>
            <td><?= $trips['Trip 2']['trip_to'] ?? '-' ?></td>
            <td><?= $trips['Trip 2']['trip_haulingsegment'] ?? '-' ?></td>
            <td><?= $trips['Trip 2']['trip_haulingtype'] ?? '-' ?></td>
            <td></td>
            <td></td>
            <td><?= $trips['Trip 2']['trip_container'] ?? '-' ?></td>
            <td></td>
            <td></td>
            <td><?= $trips['Trip 2']['trip_containerstat'] ?? '-' ?></td>
        </tr>
        <tr>
            <td> - </td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td> - </td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td> - </td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td> - </td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td> - </td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td> - </td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>
</div>

<div class="section small">
    <b>Booking no:</b>  <?php if (strpos($dispatch['booking_no'], 'PTSIBN') === 0): ?>
        <!-- ADD / if Two booking example Booking A/Booking B -->
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
        <tr><td colspan="2"><b>Trailer:</b> <?= $dispatch['d_trailer'] ?></td></tr>
        <tr><td colspan="2"><b>Genset:</b> <?= $dispatch['d_genset'] ?></td></tr>
        <tr><td colspan="2">Dispatch to indicate "N/A" if equipment is not applicable</td></tr>
        <tr><td><b>Authorized Personnel:</b></td><td><b>Name and Signature</b></td></tr>
        <tr><td><b>Dispatcher:</b></td><td> <?= $dispatch['d_dispatcher'] ?></td></tr>
        <tr><td><b>Driver's Acknowledgement:</b></td><td></td></tr>
        <tr><td><b>Inspected/Verified by:</b></td><td> GATE-OUT</td></tr>
        <tr><td><b>Remarks:</b></td><td></td></tr>
        <tr><td colspan="2">This Security Pass is Valid for 1 hour from dispatch time only</td></tr>
    </table>
</div>

</body>
</html>
<?php
    echo '<div style="page-break-after: always;"></div>';
}
?>
