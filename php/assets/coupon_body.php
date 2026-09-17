<?php
// Shared printable "Trip Verification Coupon" generated when a dispatcher
// approves a trip. Compact ticket/stamp styling; auto-prints on load. The QR
// encodes a public coupon-view URL anyone can open by scanning.
//
// Required before include: $conn (config.php), and the caller has already
// checked the role. Reads ?d_id=<dispatch id>.
require_once __DIR__ . '/../helpers/trip_rate_lookup.php';

$dId = (int)($_GET['d_id'] ?? 0);
if ($dId <= 0) { die('Missing dispatch id'); }

$stmt = $conn->prepare(
    "SELECT d.*, u.user_fname AS ap_fname, u.user_lname AS ap_lname
       FROM dispatch d
       LEFT JOIN \"user\" u ON u.user_id = d.approved_by
      WHERE d.d_id = ? LIMIT 1"
);
$stmt->execute([$dId]);
$d = $stmt->fetch();
if (!$d) { die('No dispatch record found.'); }
if (trim((string)($d['control_no'] ?? '')) === '') {
    die('This trip has not been approved yet — no coupon to print.');
}

// Primary billable leg (Trip 1 preferred) drives the SKU + route + rate.
$tripStmt = $conn->prepare(
    "SELECT trip_from, trip_to, trip_haulingsegment, trip_haulingtype,
            container_activity, trip_containerstat, trip_container, piece_rate, trip_sku
       FROM trips
      WHERE d_id = ?
      ORDER BY CASE WHEN trip_type = 'Trip 1' THEN 0 ELSE 1 END, trip_id ASC
      LIMIT 1"
);
$tripStmt->execute([$dId]);
$t = $tripStmt->fetch() ?: [];

// Verified/approved timestamp drives the coupon date.
$dateSrc  = $d['verified_at'] ?: ($d['approved_at'] ?: ($d['d_datetime'] ?? null));

// Job ticket = the driver's daily attendance control number
// (drivers_attendance.da_controlno). No hard FK, so match on driver + the
// verification date, preferring the attendance whose time-in is closest.
$jobTicket = '';
if (!empty($d['driver_id']) && $dateSrc) {
    try {
        $jt = $conn->prepare(
            "SELECT da_controlno
               FROM drivers_attendance
              WHERE driver_id = ? AND da_date = DATE(?)
              ORDER BY ABS(EXTRACT(EPOCH FROM (da_timein - ?::timestamp))) ASC
              LIMIT 1"
        );
        $jt->execute([(int)$d['driver_id'], $dateSrc, $dateSrc]);
        $jobTicket = (string)($jt->fetchColumn() ?: '');
    } catch (Throwable $e) { $jobTicket = ''; }
}

$dash = function ($v) { $v = trim((string)$v); return $v === '' ? '—' : $v; };
// SKU comes from the stored trips.trip_sku column
// (haulingSegment.haulingType.<Loaded|Empty>); fall back to computing it on
// legacy rows that predate the column.
$sku = trim((string)($t['trip_sku'] ?? ''));
if ($sku === '') {
    $sku = fleet_trip_rate_key(
        (string)($t['trip_haulingsegment'] ?? ''),
        (string)($t['trip_haulingtype'] ?? ''),
        (string)($t['trip_containerstat'] ?? '')
    );
}
if (trim(str_replace('.', '', $sku)) === '') { $sku = '—'; }
$segment  = $dash($t['trip_haulingsegment'] ?? '');
$location = $dash($t['trip_from'] ?? '') . ' → ' . $dash($t['trip_to'] ?? '');
$docRef   = $dash($d['d_tripreceipt'] ?? '');
// Rate: prefer the stamped piece_rate (payroll-assigned / trigger); if none,
// fall back to a direct lookup by the trip's Segment.Activity (trip_key).
$rate     = (float)($t['piece_rate'] ?? 0);
if ($rate <= 0) {
    $lk = fleet_lookup_rate_by_trip_key(
        $conn,
        (string)($t['trip_haulingsegment'] ?? ''),
        (string)($t['trip_haulingtype'] ?? ''),
        (string)($t['trip_containerstat'] ?? '')
    );
    if ($lk['matched']) { $rate = $lk['total_rates']; }
}
$rateStr  = $rate > 0 ? '₱' . number_format($rate, 2) : 'Awaiting payroll';
$approver = trim(trim((string)($d['ap_fname'] ?? '')) . ' ' . trim((string)($d['ap_lname'] ?? '')));
if ($approver === '') { $approver = trim((string)($d['d_dispatcher'] ?? '')); }
$dateStr  = $dateSrc ? date('m/d/Y H:i', strtotime((string)$dateSrc)) : '—';

// DICT Hustling day — one coupon covers every container the driver logged.
// Override the single-leg SKU/route/rate with the day roll-up.
$isHustling = !empty($d['is_hustling']);
if ($isHustling) {
    $hs = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(piece_rate), 0) AS tot FROM trips WHERE d_id = ?");
    $hs->execute([$dId]);
    $hrow   = $hs->fetch() ?: ['c' => 0, 'tot' => 0];
    $hCount = (int)$hrow['c'];
    $hTotal = (float)$hrow['tot'];
    $sku      = 'DICT HUSTLING';
    $segment  = 'DICT HUSTLING';
    $location = $hCount . ' container' . ($hCount === 1 ? '' : 's');
    $rateStr  = $hTotal > 0 ? '₱' . number_format($hTotal, 2) : 'Awaiting payroll';
}

// Absolute public URL for the QR — points at the real coupon-view.php file
// so scanning works regardless of pretty-URL rewriting.
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$appBase = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
$publicUrl = $scheme . '://' . $host . $appBase . '/coupon-view.php?t=' . rawurlencode((string)$d['qr_token']);

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Coupon <?= $h($d['control_no']) ?></title>
  <style>
    :root { --maroon:#7a1220; --ink:#1b1b1b; }
    * { box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    body { font-family:Arial, Helvetica, sans-serif; margin:0; padding:12px; color:var(--ink); background:#fff; }

    .ticket { max-width:420px; margin:0 auto; background:var(--maroon); border-radius:10px; overflow:hidden; }
    .t-head { display:flex; align-items:center; justify-content:space-between; padding:9px 12px; color:#fff; gap:8px; }
    .t-head .title { font-size:15px; font-weight:800; line-height:1.1; display:flex; align-items:center; gap:8px; }
    .t-head .title .bars { display:inline-flex; flex-direction:column; gap:2px; }
    .t-head .title .bars i { display:block; height:2px; width:16px; background:#fff; border-radius:2px; }
    .t-head .title .bars i:nth-child(2){ width:11px; } .t-head .title .bars i:nth-child(3){ width:14px; }
    .no-stub { background:#fff; border-radius:6px; padding:4px 10px; text-align:right; flex:none; }
    .no-stub .lbl { font-size:9px; color:#555; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
    .no-stub .val { font-size:15px; font-weight:800; color:var(--maroon); font-family:'Courier New', monospace; }

    .t-body { background:#fff; margin:0 6px; padding:10px 14px; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; }
    .field { display:flex; align-items:baseline; font-size:12px; padding:5px 0; }
    .field .k { width:74px; flex:none; font-weight:800; }
    .field .k::after { content:":"; float:right; }
    .field .v { flex:1; padding-left:6px; word-break:break-word; }
    .field .v.mono { font-family:'Courier New', monospace; font-weight:700; }
    .field .v.rate { font-weight:800; color:var(--maroon); }
    .sep { grid-column:1 / -1; border-top:1.5px dotted #c9a3a9; margin:4px 0; }

    .qrbox { grid-column:1 / -1; display:flex; align-items:center; gap:12px; padding-top:8px; }
    #qr { display:inline-block; }
    .qrbox .hint { font-size:10px; color:#555; line-height:1.3; }

    .t-foot { text-align:center; color:#fff; font-size:9px; letter-spacing:.05em; text-transform:uppercase; font-weight:600; padding:8px 12px; }

    @media print { body { padding:0; } }
  </style>
</head>
<body onload="renderQR(); setTimeout(function(){ window.print(); }, 400);" onafterprint="window.close();">
  <div class="ticket">
    <div class="t-head">
      <div class="title"><span class="bars"><i></i><i></i><i></i></span> TRIP VERIFICATION COUPON</div>
      <div class="no-stub"><div class="lbl">No.</div><div class="val"><?= $h($d['control_no']) ?></div></div>
    </div>

    <div class="t-body">
      <div class="grid">
        <div class="field"><div class="k">Driver</div><div class="v"><?= $h($dash($d['d_drivername'] ?? '')) ?></div></div>
        <div class="field"><div class="k">Job Ticket</div><div class="v mono"><?= $h($dash($jobTicket)) ?></div></div>

        <div class="field"><div class="k">Doc. Ref.</div><div class="v mono"><?= $h($docRef) ?></div></div>
        <div class="field"><div class="k">SKU</div><div class="v mono"><?= $h($sku) ?></div></div>

        <div class="field"><div class="k">Segment</div><div class="v"><?= $h($segment) ?></div></div>
        <div class="field"><div class="k">Rate</div><div class="v rate"><?= $h($rateStr) ?></div></div>

        <div class="field"><div class="k">Location</div><div class="v"><?= $h($location) ?></div></div>
        <div class="field"><div class="k">Booking</div><div class="v"><?= $h($dash($d['booking_no'] ?? '')) ?></div></div>

        <div class="sep"></div>

        <div class="field"><div class="k">Verifier</div><div class="v"><?= $h($approver !== '' ? $approver : '—') ?></div></div>
        <div class="field"><div class="k">Date</div><div class="v"><?= $h($dateStr) ?></div></div>

        <div class="qrbox">
          <div id="qr"></div>
          <div class="hint">Scan to view this transaction.</div>
        </div>
      </div>
    </div>

    <div class="t-foot">★ System generated · valid only with authorized verification ★</div>
  </div>

  <script src="assets/libs/qrcodejs/qrcode.min.js"></script>
  <script>
    var PUBLIC_URL = <?= json_encode($publicUrl) ?>;
    function renderQR() {
      try {
        new QRCode(document.getElementById('qr'), {
          text: PUBLIC_URL, width: 82, height: 82, correctLevel: QRCode.CorrectLevel.M
        });
      } catch (e) { document.getElementById('qr').textContent = PUBLIC_URL; }
    }
  </script>
</body>
</html>
