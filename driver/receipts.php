<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Driver') { header("Location: driver-index.php?route=login"); exit(); }
$pageTitle = 'Trip Receipts';
$activeNav = 'home';
include 'driver/_layout_top.php';

include 'php/config/config.php';
$driverId = (int)$_SESSION['user_id'];
$dId = (int)($_GET['d_id'] ?? 0);

$dispatch = null;
if ($dId > 0) {
    $stmt = $conn->prepare("SELECT d_id, booking_no, costumer, driver_id FROM dispatch WHERE d_id = ? LIMIT 1");
    $stmt->execute([$dId]);
    $dispatch = $stmt->fetch();
if (!$dispatch || (int)$dispatch['driver_id'] !== $driverId) { $dispatch = null; }
}
?>
<?php if (!$dispatch): ?>
  <div class="alert alert-warning mt-3">No dispatch found.</div>
<?php else: ?>
<div class="card mt-3"><div class="card-body">
  <h5 class="card-title mb-1"><?php echo htmlspecialchars($dispatch['booking_no']); ?></h5>
  <p class="card-subtitle text-muted">Receipts attached by your dispatcher. Tap to open. Required ones must be acknowledged before going en-route.</p>

  <input type="hidden" id="d_id" value="<?php echo (int)$dispatch['d_id']; ?>">

  <div id="receiptsList" class="d-grid gap-2 mt-3">
    <div class="text-muted small">Loading…</div>
  </div>

  <a href="driver-dashboard" class="btn-modern btn-outline-modern w-100 mt-3"><i class="ti ti-arrow-left"></i> Back</a>
</div></div>
<?php endif; ?>

<script>
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function typeBadge(t) {
  const map = { manifest: 'Manifest', gate_pass: 'Gate Pass', customs: 'Customs', delivery_note: 'Delivery Note', other: 'Other' };
  return '<span class="badge bg-info">' + escapeHtml(map[t] || t) + '</span>';
}
function loadReceipts() {
  $.getJSON('php/fetch/dispatch_receipts.php', { d_id: $('#d_id').val() }, function (res) {
    if (res.status !== 'success') {
      $('#receiptsList').html('<div class="alert alert-danger">' + escapeHtml(res.message) + '</div>');
      return;
    }
    if (!res.rows.length) {
      $('#receiptsList').html('<div class="alert alert-secondary">No receipts attached yet. Dispatcher will add them.</div>');
      return;
    }
    const html = res.rows.map(function (r) {
      const acked = !!r.acknowledged_at;
      const requiresAck = parseInt(r.requires_ack, 10) === 1;
      const ackBlock = !requiresAck
        ? '<span class="badge bg-secondary">Optional</span>'
        : (acked
            ? '<span class="badge bg-success">Acknowledged ' + escapeHtml(r.acknowledged_at) + '</span>'
            : '<button class="btn-modern btn-primary-modern btn-ack" data-id="' + r.dr_id + '" style="width:auto;padding:4px 10px;font-size:12px;">Acknowledge</button>');
      return '<div class="card border" style="padding:10px;">'
        + '<div class="d-flex align-items-center gap-2 mb-2">'
        +   '<i class="ti ti-file"></i>'
        +   '<strong>' + escapeHtml(r.title || r.receipt_type) + '</strong>'
        +   typeBadge(r.receipt_type)
        + '</div>'
        + '<a href="' + escapeHtml(r.file_path) + '" target="_blank" class="btn-modern btn-outline-modern" style="text-align:center;"><i class="ti ti-external-link"></i> Open</a>'
        + '<div class="mt-2">' + ackBlock + '</div>'
        + '</div>';
    }).join('');
    $('#receiptsList').html(html);
  });
}
$(loadReceipts);
$(document).on('click', '.btn-ack', function () {
  const id = $(this).data('id');
  $.post('php/operations/acknowledge_receipt.php', { dr_id: id }, function (res) {
    Swal.fire({ icon: res.status === 'success' ? 'success' : (res.status === 'queued' ? 'info' : 'error'),
                text: res.message, timer: 1200, showConfirmButton: false });
    if (res.status === 'success' || res.status === 'queued') loadReceipts();
  }, 'json');
});
</script>
<?php include 'driver/_layout_bottom.php'; ?>
