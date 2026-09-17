<?php
include __DIR__ . '/../config/config.php';

$ticketId = isset($_GET['tc_id']) ? (int)$_GET['tc_id'] : 0;
if ($ticketId <= 0) { die('Invalid ticket code ID.'); }

if (!pt_table_exists($conn, 'ticket_code')) {
    die('Ticket code system not initialised.');
}

$ticketResult = $conn->query("SELECT tc_id, tc_code, f_driverId, tc_date, tc_status FROM ticket_code WHERE tc_id = $ticketId LIMIT 1");
$ticket = $ticketResult ? ($ticketResult)->fetch() : null;
if (!$ticket) { die('Ticket code not found.'); }

$trips = [];
$totalKm = 0;
if (pt_table_exists($conn, 'trip_receipts')) {
    $tripResult = $conn->query("SELECT tr_number, location_from, location_to, total_km, maptotal_kmRun
                                        FROM trip_receipts WHERE tc_id = $ticketId ORDER BY receipts_id ASC");
    if ($tripResult) {
        while ($row = ($tripResult)->fetch()) {
            $trips[] = $row;
            $totalKm += (float)$row['total_km'];
        }
    }
}

$unitName = isset($_GET['unit']) ? trim($_GET['unit']) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Trip Ticket Code — <?php echo htmlspecialchars($ticket['tc_code']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
        .sheet { border: 2px dashed #222; padding: 20px; max-width: 760px; margin: 0 auto; }
        .code { font-size: 34px; font-weight: 700; letter-spacing: 6px; text-align: center; margin: 16px 0 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #222; padding: 8px; font-size: 12px; }
        th { background: #f0f0f0; }
        tfoot td { font-weight: 700; background: #f8f8f8; }
        .meta { margin-bottom: 6px; font-size: 13px; }
        @media print { body { margin: 0; } .sheet { border: none; } }
    </style>
</head>
<body onload="window.print();" onafterprint="window.close();">
    <div class="sheet">
        <h2 style="margin: 0; text-align: center;">Trip Ticket Code</h2>
        <div class="code"><?php echo htmlspecialchars($ticket['tc_code']); ?></div>
        <div class="meta"><strong>Date:</strong> <?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($ticket['tc_date']))); ?></div>
        <div class="meta"><strong>Driver ID:</strong> <?php echo htmlspecialchars($ticket['f_driverid']); ?></div>
        <?php if (!empty($unitName)) { ?>
            <div class="meta"><strong>Unit:</strong> <?php echo htmlspecialchars($unitName); ?></div>
        <?php } ?>
        <div class="meta"><strong>Total KM:</strong> <?php echo number_format($totalKm, 2); ?></div>

        <table>
            <thead><tr><th>TR No</th><th>From</th><th>To</th><th>Total KM</th><th>Map KM</th></tr></thead>
            <tbody>
                <?php foreach ($trips as $trip) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($trip['tr_number']); ?></td>
                        <td><?php echo htmlspecialchars($trip['location_from']); ?></td>
                        <td><?php echo htmlspecialchars($trip['location_to']); ?></td>
                        <td><?php echo htmlspecialchars($trip['total_km']); ?></td>
                        <td><?php echo htmlspecialchars($trip['maptotal_kmrun']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3" style="text-align:right;">Total</td><td><?php echo number_format($totalKm, 2); ?></td><td></td></tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
