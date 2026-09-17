<?php
// Compact coupon panel printed beside the Security Pass on a Trip Ticket.
// Expects $conn, $dispatch and $trips from the including ticket template.
require_once __DIR__ . '/../helpers/trip_rate_lookup.php';

$couponControlNo = trim((string)($dispatch['control_no'] ?? ''));
$couponTrip = $trips['Trip 1'] ?? (reset($trips) ?: []);
$couponSku = trim((string)($couponTrip['trip_sku'] ?? ''));
if ($couponSku === '') {
    $couponSku = fleet_trip_rate_key(
        (string)($couponTrip['trip_haulingsegment'] ?? ''),
        (string)($couponTrip['trip_haulingtype'] ?? ''),
        (string)($couponTrip['trip_containerstat'] ?? '')
    );
}
if (trim(str_replace('.', '', $couponSku)) === '') $couponSku = '—';

$couponRate = (float)($couponTrip['piece_rate'] ?? 0);
if ($couponRate <= 0) {
    $couponLookup = fleet_lookup_rate_by_trip_key(
        $conn,
        (string)($couponTrip['trip_haulingsegment'] ?? ''),
        (string)($couponTrip['trip_haulingtype'] ?? ''),
        (string)($couponTrip['trip_containerstat'] ?? '')
    );
    if (!empty($couponLookup['matched'])) $couponRate = (float)$couponLookup['total_rates'];
}

$couponDate = $dispatch['verified_at'] ?? $dispatch['approved_at'] ?? $dispatch['d_datetime'] ?? null;
$couponDateText = $couponDate ? date('m/d/Y H:i', strtotime((string)$couponDate)) : '—';
$couponLocation = trim((string)($couponTrip['trip_from'] ?? '')) . ' → ' . trim((string)($couponTrip['trip_to'] ?? ''));
$couponLocation = trim($couponLocation, " →") ?: '—';

$couponPublicUrl = '';
if ($couponControlNo !== '' && !empty($dispatch['qr_token'])) {
    $couponScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $couponHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $couponPublicUrl = $couponScheme . '://' . $couponHost . '/coupon-view.php?t=' . rawurlencode((string)$dispatch['qr_token']);
}
?>
<div class="ticket-coupon-panel">
  <div class="ticket-coupon-heading">TRIP VERIFICATION COUPON</div>
  <?php if ($couponControlNo !== ''): ?>
  <div class="ticket-coupon-number">
    <?= 'NO. ' . htmlspecialchars($couponControlNo) ?>
  </div>
  <?php endif; ?>
  <div class="ticket-coupon-grid">
    <div><b>Driver:</b> <?= htmlspecialchars((string)($dispatch['d_drivername'] ?? '—')) ?></div>
    <div><b>Doc. Ref.:</b> <?= htmlspecialchars((string)($dispatch['d_tripreceipt'] ?? '—')) ?></div>
    <div><b>SKU:</b> <?= htmlspecialchars($couponSku) ?></div>
    <div><b>Rate:</b> ₱<?= number_format($couponRate, 2) ?></div>
    <div><b>Location:</b> <?= htmlspecialchars($couponLocation) ?></div>
    <div><b>Date:</b> <?= htmlspecialchars($couponDateText) ?></div>
  </div>
  <?php if ($couponControlNo !== ''): ?>
    <div class="ticket-coupon-qr-row">
      <div id="ticket-coupon-qr"></div><span>Scan to view this transaction.</span>
    </div>
  <?php endif; ?>
</div>
<?php if ($couponPublicUrl !== ''): ?>
<script src="assets/libs/qrcodejs/qrcode.min.js"></script>
<script>
  window.addEventListener('load', function () {
    if (window.QRCode) new QRCode(document.getElementById('ticket-coupon-qr'), {
      text: <?= json_encode($couponPublicUrl) ?>, width: 70, height: 70, correctLevel: QRCode.CorrectLevel.M
    });
  });
</script>
<?php endif; ?>
