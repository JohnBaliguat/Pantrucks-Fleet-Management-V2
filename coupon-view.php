<?php
// Public, no-login transaction view reached by scanning a coupon QR code.
// Looks the dispatch up by its unguessable qr_token and shows the trip
// details — deliberately WITHOUT any price / piece-rate.
include __DIR__ . '/php/config/config.php';
require_once __DIR__ . '/php/helpers/trip_rate_lookup.php';

$token = trim((string)($_GET['t'] ?? ''));
$d = null; $t = [];
if ($token !== '' && preg_match('/^[a-f0-9]{8,64}$/i', $token)) {
    $stmt = $conn->prepare(
        "SELECT d.d_id, d.control_no, d.booking_no, d.d_drivername, d.d_tripreceipt,
                d.d_datetime, d.approved_at, d.verified_at, d.payroll_status, d.driver_id,
                d.is_hustling,
                u.user_fname AS ap_fname, u.user_lname AS ap_lname
           FROM dispatch d
           LEFT JOIN \"user\" u ON u.user_id = d.approved_by
          WHERE d.qr_token = ? LIMIT 1"
    );
    $stmt->execute([$token]);
    $d = $stmt->fetch() ?: null;
    if ($d) {
        $ts = $conn->prepare(
            "SELECT trip_from, trip_to, trip_haulingsegment, trip_haulingtype,
                    container_activity, trip_containerstat, trip_sku
               FROM trips
              WHERE d_id = ?
              ORDER BY CASE WHEN trip_type = 'Trip 1' THEN 0 ELSE 1 END, trip_id ASC
              LIMIT 1"
        );
        $ts->execute([(int)$d['d_id']]);
        $t = $ts->fetch() ?: [];
    }
}

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$dash = function ($v) { $v = trim((string)$v); return $v === '' ? '—' : $v; };

if ($d) {
    // SKU from the stored trips.trip_sku column; compute on legacy rows.
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
    $approver = trim(trim((string)($d['ap_fname'] ?? '')) . ' ' . trim((string)($d['ap_lname'] ?? '')));
    $dateSrc  = $d['verified_at'] ?: ($d['approved_at'] ?: ($d['d_datetime'] ?? null));
    $dateStr  = $dateSrc ? date('M d, Y H:i', strtotime((string)$dateSrc)) : '—';
    $payroll  = ($d['payroll_status'] ?? '') === 'rated' ? 'Rated' : 'Pending';

    // DICT Hustling day roll-up (price still hidden on the public page).
    if (!empty($d['is_hustling'])) {
        $hc = $conn->prepare("SELECT COUNT(*) FROM trips WHERE d_id = ?");
        $hc->execute([(int)$d['d_id']]);
        $hCount = (int)$hc->fetchColumn();
        $sku      = 'DICT HUSTLING';
        $segment  = 'DICT HUSTLING';
        $location = $hCount . ' container' . ($hCount === 1 ? '' : 's');
    }

    // Job ticket = driver's daily attendance control number, matched on
    // driver + verification date (closest time-in).
    $jobTicket = '';
    if (!empty($d['driver_id']) && $dateSrc) {
        try {
            $jt = $conn->prepare(
                "SELECT da_controlno FROM drivers_attendance
                  WHERE driver_id = ? AND da_date = DATE(?)
                  ORDER BY ABS(EXTRACT(EPOCH FROM (da_timein - ?::timestamp))) ASC LIMIT 1"
            );
            $jt->execute([(int)$d['driver_id'], $dateSrc, $dateSrc]);
            $jobTicket = (string)($jt->fetchColumn() ?: '');
        } catch (Throwable $e) { $jobTicket = ''; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= $d ? 'Coupon ' . $h($d['control_no']) : 'Coupon not found' ?></title>
  <style>
    :root { --maroon:#7a1220; --ink:#1b1b1b; }
    * { box-sizing: border-box; }
    body { font-family:-apple-system, Segoe UI, Roboto, Arial, sans-serif; margin:0;
           background:#eef0f3; color:var(--ink); display:flex; justify-content:center; padding:16px; }
    .ticket { background:var(--maroon); width:100%; max-width:460px; border-radius:14px; overflow:hidden;
              box-shadow:0 10px 30px rgba(0,0,0,.18); }
    .t-head { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; color:#fff; gap:10px; }
    .t-head .title { font-size:17px; font-weight:800; letter-spacing:.02em; line-height:1.15; }
    .no-stub { background:#fff; border-radius:8px; padding:6px 12px; text-align:right; flex:none; }
    .no-stub .lbl { font-size:10px; color:#555; font-weight:700; text-transform:uppercase; letter-spacing:.08em; }
    .no-stub .val { font-size:16px; font-weight:800; color:var(--maroon); font-family:'Courier New', monospace; }
    .t-body { background:#fff; margin:0 8px; padding:6px 18px 14px; }
    .field { display:flex; padding:11px 0; border-bottom:1px dotted #d8c2c6; font-size:15px; }
    .field:last-child { border-bottom:0; }
    .k { width:104px; flex:none; font-weight:800; }
    .k::after { content:":"; float:right; }
    .v { flex:1; padding-left:10px; font-weight:600; word-break:break-word; }
    .v.mono { font-family:'Courier New', monospace; }
    .pill { display:inline-block; font-size:12px; font-weight:800; padding:3px 10px; border-radius:999px; }
    .pill.pending { background:#fff3cd; color:#8a6d00; }
    .pill.rated   { background:#d1f7dd; color:#0a6b2e; }
    .t-foot { color:#fff; text-align:center; font-size:11px; letter-spacing:.05em; text-transform:uppercase; padding:12px 14px; }
    .empty { background:#fff; margin:0 8px 8px; padding:48px 24px; text-align:center; border-radius:8px; }
    .empty h2 { margin:0 0 8px; } .empty p { color:#6b7280; margin:0; }
  </style>
</head>
<body>
<?php if (!$d): ?>
  <div class="ticket">
    <div class="t-head"><div class="title">TRIP VERIFICATION COUPON</div></div>
    <div class="empty">
      <h2>Coupon not found</h2>
      <p>This link is invalid or the transaction no longer exists.</p>
    </div>
    <div class="t-foot">Pantrucks Fleet Management</div>
  </div>
<?php else: ?>
  <div class="ticket">
    <div class="t-head">
      <div class="title">TRIP VERIFICATION<br>COUPON</div>
      <div class="no-stub">
        <div class="lbl">No.</div>
        <div class="val"><?= $h($d['control_no']) ?></div>
      </div>
    </div>
    <div class="t-body">
      <div class="field"><div class="k">Driver</div><div class="v"><?= $h($dash($d['d_drivername'])) ?></div></div>
      <div class="field"><div class="k">Job Ticket</div><div class="v mono"><?= $h($dash($jobTicket)) ?></div></div>
      <div class="field"><div class="k">Doc. Ref.</div><div class="v mono"><?= $h($docRef) ?></div></div>
      <div class="field"><div class="k">Segment</div><div class="v"><?= $h($segment) ?></div></div>
      <div class="field"><div class="k">SKU</div><div class="v mono"><?= $h($sku) ?></div></div>
      <div class="field"><div class="k">Location</div><div class="v"><?= $h($location) ?></div></div>
      <div class="field"><div class="k">Booking</div><div class="v"><?= $h($dash($d['booking_no'])) ?></div></div>
      <div class="field"><div class="k">Verifier</div><div class="v"><?= $h($approver !== '' ? $approver : '—') ?></div></div>
      <div class="field"><div class="k">Date</div><div class="v"><?= $h($dateStr) ?></div></div>
      <div class="field"><div class="k">Status</div><div class="v">
        <span class="pill <?= $payroll === 'Rated' ? 'rated' : 'pending' ?>">Payroll: <?= $h($payroll) ?></span>
      </div></div>
    </div>
    <div class="t-foot">★ System generated · valid only with authorized verification ★</div>
  </div>
<?php endif; ?>
</body>
</html>
