<?php
// Jollibee-style dispatch receipt. Included by:
//   dispatcher/print_dispatch_receipt.php
//   admin/print_dispatch_receipt.php
//
// Expects: $d_id (int) — the dispatch row to print.

require_once __DIR__ . '/../config/config.php';

$d_id = (int)($d_id ?? 0);
if ($d_id <= 0) {
    http_response_code(400);
    echo "Invalid dispatch id.";
    return;
}

$stmt = $conn->prepare(
    "SELECT d.*, b.container, b.trip_from AS bk_from, b.trip_to AS bk_to,
            b.hauling_segment, b.container_status AS bk_container_status, b.booking_activity,
            t.trip_from AS t_from, t.trip_to AS t_to, t.trip_container, t.trip_containerstat
     FROM dispatch d
     LEFT JOIN booking b ON b.booking_no = d.booking_no
     LEFT JOIN trips t ON t.d_id = d.d_id AND t.trip_type = 'Trip 1'
     WHERE d.d_id = ? LIMIT 1"
);
$stmt->execute([$d_id]);
$d = $stmt->fetch();
if (!$d) {
    http_response_code(404);
    echo "Dispatch not found.";
    return;
}

$ref       = trim((string)($d['dispatch_ref'] ?? '')) !== '' ? $d['dispatch_ref'] : ($d['booking_no'] ?: '—');
$bookingNo = $d['booking_no'] ?: '—';
$customer  = $d['costumer'] ?: '—';
$driver    = $d['d_drivername'] ?: '—';
$truck     = $d['d_truck'] ?: '—';
$trailer   = $d['d_trailer'] ?: '—';
$genset    = $d['d_genset'] ?: '—';
$tripFrom  = $d['t_from'] ?: ($d['bk_from'] ?: '—');
$tripTo    = $d['t_to']   ?: ($d['bk_to']   ?: '—');
$container = $d['trip_container'] ?: ($d['container'] ?: '');
$contState = $d['trip_containerstat'] ?: ($d['bk_container_status'] ?: '');
$tripRct   = $d['d_tripreceipt'] ?: '';
$dispatcher = trim((string)($d['d_dispatcher'] ?? '')) ?: '—';

// Resolve to the full "First Middle Last" name. Handles three cases:
//   1. New rows from the dnd / service-trip flow already store the full name.
//   2. Legacy rows that stored just the numeric user_id → look it up.
//   3. Legacy rows that stored "LASTNAME, F.M." → try a username match in
//      the user table; if that finds a single row, swap to the full name.
if ($dispatcher !== '—') {
    $uRow = null;
    if (ctype_digit($dispatcher)) {
        $stmtUser = $conn->prepare("SELECT user_fname, user_mname, user_lname FROM \"user\" WHERE user_id = ? LIMIT 1");
        $stmtUser->execute([$dispatcher]);
        $uRow = $stmtUser->fetch();
} elseif (strpos($dispatcher, ',') !== false) {
        // Pattern "LASTNAME, F.M." — match by user_lname (case-insensitive).
        $lname = trim(strtok($dispatcher, ','));
        if ($lname !== '') {
            $stmtUser = $conn->prepare("SELECT user_fname, user_mname, user_lname FROM \"user\" WHERE UPPER(user_lname) = UPPER(?) LIMIT 2");
            $stmtUser->execute([$lname]);
            $res = $stmtUser;
            $matches = [];
            while ($r = $res->fetch()) $matches[] = $r;
if (count($matches) === 1) $uRow = $matches[0];
        }
    }
    if ($uRow) {
        $parts = array_filter([
            trim((string)$uRow['user_fname']),
            trim((string)$uRow['user_mname']),
            trim((string)$uRow['user_lname']),
        ], 'strlen');
        if (!empty($parts)) $dispatcher = implode(' ', $parts);
    }
}
$hub       = $d['d_dispatchhub'] ?: '—';
$datetime  = $d['d_datetime'] ?: '';
$cthEirOut = trim((string)($d['cth_eirout'] ?? ''));
$cthEirIn  = trim((string)($d['cth_eirin'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dispatch Receipt — <?= htmlspecialchars($ref) ?></title>
<style>
  /* Thermal-receipt-style ticket: 80mm wide, print-friendly. */
  @page { size: 80mm auto; margin: 4mm; }
  html, body { margin:0; padding:0; background:#f1f5f9; }
  body { font-family: 'Courier New', monospace; font-size: 12px; color:#000; }
  .receipt {
    width: 72mm; margin: 12px auto; padding: 8mm 5mm; background:#fff;
    box-shadow: 0 2px 10px rgba(0,0,0,.1);
  }
  .center { text-align:center; }
  .right  { text-align:right; }
  .bold   { font-weight: 700; }
  .hr     { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
  .row    { display:flex; justify-content:space-between; gap:6px; margin: 1px 0; }
  .row .lbl { color:#000; }
  .row .val { font-weight: 700; text-align:right; word-break: break-all; }
  .ref-box {
    border: 2px dashed #000; padding: 6px; margin: 6px 0; text-align:center;
    font-size: 14px; font-weight: 700; letter-spacing: 0.5px; word-break: break-all;
  }
  .small  { font-size: 10px; }
  .total-box {
    border: 2px solid #000; padding: 6px 8px; margin: 6px 0;
    display:flex; justify-content:space-between; align-items:center;
    font-weight:700;
  }
  .total-box .lbl { font-size: 12px; }
  .total-box .val { font-size: 16px; }
  .actions { width:72mm; margin: 8px auto; text-align:center; }
  .actions button {
    padding: 8px 16px; margin: 0 4px; border: 0; border-radius: 6px; cursor: pointer;
    font-weight: 600; font-family: inherit;
  }
  .btn-print { background: #0d6efd; color:#fff; }
  .btn-close { background: #e5e7eb; color:#222; }
  @media print {
    body { background:#fff; }
    .receipt { box-shadow:none; margin:0; width:auto; padding:0; }
    .actions { display:none; }
  }
</style>
</head>
<body>

<div class="actions">
  <button class="btn-print" onclick="window.print()"><i></i> Print</button>
  <button class="btn-close" onclick="window.close()">Close</button>
</div>

<div class="receipt">
  <div class="center bold" style="font-size:14px;">PANTRUCKS</div>
  <div class="center small">B.O. A.O. Florendo, Panabo, Davao del Norte</div>
  <div class="hr"></div>
  <div class="center bold">DISPATCH RECEIPT</div>
  <div class="hr"></div>

  <div class="ref-box"><?= htmlspecialchars($ref) ?></div>

  <div class="row"><span class="lbl">Booking #</span><span class="val"><?= htmlspecialchars($bookingNo) ?></span></div>
  <div class="row"><span class="lbl">Customer</span><span class="val"><?= htmlspecialchars($customer) ?></span></div>
  <div class="row"><span class="lbl">Date/Time</span><span class="val"><?= htmlspecialchars($datetime) ?></span></div>
  <div class="row"><span class="lbl">Hub</span><span class="val"><?= htmlspecialchars($hub) ?></span></div>

  <div class="hr"></div>
  <div class="bold">DRIVER &amp; UNIT</div>
  <div class="row"><span class="lbl">Driver</span><span class="val"><?= htmlspecialchars($driver) ?></span></div>
  <div class="row"><span class="lbl">Truck</span><span class="val"><?= htmlspecialchars($truck) ?></span></div>
  <div class="row"><span class="lbl">Trailer</span><span class="val"><?= htmlspecialchars($trailer ?: '—') ?></span></div>
  <div class="row"><span class="lbl">Genset</span><span class="val"><?= htmlspecialchars($genset ?: '—') ?></span></div>

  <div class="hr"></div>
  <div class="bold">ROUTE</div>
  <div class="row"><span class="lbl">From</span><span class="val"><?= htmlspecialchars($tripFrom) ?></span></div>
  <div class="row"><span class="lbl">To</span><span class="val"><?= htmlspecialchars($tripTo) ?></span></div>

  <?php if ($container !== '' || $contState !== ''): ?>
  <div class="hr"></div>
  <div class="bold">CONTAINER</div>
  <?php if ($container !== ''): ?>
    <div class="row"><span class="lbl">No.</span><span class="val"><?= htmlspecialchars($container) ?></span></div>
  <?php endif; ?>
  <?php if ($contState !== ''): ?>
    <div class="row"><span class="lbl">State</span><span class="val"><?= htmlspecialchars($contState) ?></span></div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($tripRct !== '' || $cthEirOut !== '' || $cthEirIn !== ''): ?>
  <div class="hr"></div>
  <div class="bold">DOCUMENTS</div>
  <?php if ($tripRct !== ''): ?>
    <div class="row"><span class="lbl">Trip Rcpt</span><span class="val"><?= htmlspecialchars($tripRct) ?></span></div>
  <?php endif; ?>
  <?php if ($cthEirOut !== ''): ?>
    <div class="row"><span class="lbl">EIR Out</span><span class="val"><?= htmlspecialchars($cthEirOut) ?></span></div>
  <?php endif; ?>
  <?php if ($cthEirIn !== ''): ?>
    <div class="row"><span class="lbl">EIR In</span><span class="val"><?= htmlspecialchars($cthEirIn) ?></span></div>
  <?php endif; ?>
  <?php endif; ?>

  <div class="hr"></div>
  <div class="total-box">
    <span class="lbl">TRIP PRICE</span>
    <span class="val">&#8369;1.00</span>
  </div>
  <div class="center small" style="margin-top:-4px;">Placeholder rate &mdash; pricing module pending.</div>

  <div class="hr"></div>
  <div class="row"><span class="lbl">Dispatched by</span><span class="val"><?= htmlspecialchars($dispatcher) ?></span></div>

  <div class="hr"></div>
  <div class="center small">Please present this receipt at the gate.</div>
  <div class="center small">Pantrucks Fleet Management — <?= date('Y') ?></div>
</div>

<script>
  // Auto-open the print dialog so the dispatcher just hits Enter.
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });
</script>
</body>
</html>
