<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Payroll') {
    header("Location: index.php?route=login");
    exit();
}
$pageTitle = 'Payroll — Rate Assignment';
include 'payroll/_layout_top.php';
?>
<div class="card mt-3"><div class="card-body">
  <div class="d-md-flex align-items-center mb-3">
    <div>
      <h4 class="card-title mb-0">Piece-Rate Assignment</h4>
      <p class="card-subtitle">Enter a coupon <b>control number</b> to auto-fill the trip details, then assign the piece-rate for each leg.</p>
    </div>
    <div class="ms-auto">
      <button class="btn btn-sm btn-outline-secondary" id="pendingRefresh"><i class="ti ti-refresh"></i> Refresh queue</button>
    </div>
  </div>

  <div class="row g-2 align-items-end mb-3">
    <div class="col-md-5">
      <label class="form-label small mb-1">Control Number</label>
      <input type="text" class="form-control" id="controlNo" placeholder="e.g. 2026-000125" autocomplete="off">
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary" id="lookupBtn"><i class="ti ti-search"></i> Load</button>
    </div>
  </div>

  <div id="lookupResult"></div>
</div></div>

<div class="card"><div class="card-body">
  <h4 class="card-title mb-0">Awaiting payroll rate</h4>
  <p class="card-subtitle">Approved trips whose piece-rate hasn't been assigned yet. Click one to load it.</p>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Control No</th><th>Date</th><th>Hauling Segment</th><th>Route</th>
        <th>SKU / Container</th><th>Driver / Truck</th><th></th>
      </tr></thead>
      <tbody id="pendingBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
    </table>
  </div>
</div></div>

<script>
// This inline block is emitted before _layout_bottom.php loads jQuery, so
// defer execution until jQuery (and SweetAlert) are actually on the page.
(function boot(){
  if (!window.jQuery || typeof window.Swal === 'undefined') { return setTimeout(boot, 30); }
  var $ = window.jQuery;
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

var activityCache = null;
function loadActivities() {
  if (activityCache) return $.Deferred().resolve(activityCache).promise();
  return $.getJSON('php/fetch/trip_rates_activities.php').then(function (res) {
    activityCache = (res && res.status === 'success') ? (res.rows || []) : [];
    return activityCache;
  });
}
function activityOptions(current) {
  var cur = String(current || '').trim().toLowerCase();
  var opts = '<option value="">— pick activity —</option>';
  (activityCache || []).forEach(function (a) {
    var sel = (cur && cur === String(a.activity).trim().toLowerCase()) ? ' selected' : '';
    opts += '<option value="' + escapeHtml(a.activity) + '"' + sel + '>' +
            escapeHtml(a.activity) + '  (₱' + a.total_rates.toFixed(2) + ')</option>';
  });
  return opts;
}
function rateLookup(activity) {
  var a = (activityCache || []).find(function (x) {
    return String(x.activity).trim().toLowerCase() === String(activity).trim().toLowerCase();
  });
  return a ? a.total_rates : 0;
}

function loadCoupon(controlNo) {
  controlNo = (controlNo || $('#controlNo').val() || '').trim();
  if (!controlNo) { Swal.fire({icon:'info', text:'Enter a control number first.'}); return; }
  $('#controlNo').val(controlNo);
  $('#lookupResult').html('<div class="text-muted py-3">Loading…</div>');
  $.when(loadActivities(), $.getJSON('php/fetch/coupon_lookup.php', { control_no: controlNo }))
    .then(function (acts, rawRes) {
      var res = rawRes[0];
      if (res.status !== 'success') {
        $('#lookupResult').html('<div class="alert alert-danger mb-0">' + escapeHtml(res.message || 'Not found') + '</div>');
        return;
      }
      renderCoupon(res.dispatch, res.trips);
    }, function () {
      $('#lookupResult').html('<div class="alert alert-danger mb-0">Failed to load coupon.</div>');
    });
}

function renderCoupon(d, trips) {
  var rated = (d.payroll_status === 'rated');
  var head =
    '<div class="alert ' + (rated ? 'alert-success' : 'alert-info') + ' d-flex flex-wrap gap-4 mb-3">' +
      '<div><small class="d-block text-muted">Control No</small><b class="font-monospace">' + escapeHtml(d.control_no) + '</b></div>' +
      '<div><small class="d-block text-muted">Date</small><b>' + escapeHtml(d.date || '—') + '</b></div>' +
      '<div><small class="d-block text-muted">Driver</small><b>' + escapeHtml(d.driver || '—') + '</b></div>' +
      '<div><small class="d-block text-muted">Truck</small><b>' + escapeHtml(d.truck || '—') + '</b></div>' +
      '<div><small class="d-block text-muted">Booking</small><b>' + escapeHtml(d.booking_no || '—') + '</b></div>' +
      '<div><small class="d-block text-muted">Approved By</small><b>' + escapeHtml(d.approved_by || '—') + '</b></div>' +
      '<div><small class="d-block text-muted">Payroll</small><b>' + (rated ? 'Rated' : 'Pending') + '</b></div>' +
    '</div>';

  if (!trips || !trips.length) {
    $('#lookupResult').html(head + '<div class="alert alert-warning mb-0">No billable trips on this coupon.</div>');
    return;
  }
  if (!activityCache || !activityCache.length) {
    $('#lookupResult').html(head + '<div class="alert alert-warning mb-0">No trip-rates configured yet. Add them under Trip Rates first.</div>');
    return;
  }

  var body = trips.map(function (t) {
    var seg = escapeHtml(t.trip_haulingsegment || '—');
    var route = escapeHtml(t.trip_from || '—') + ' → ' + escapeHtml(t.trip_to || '—');
    var actStat = escapeHtml(t.trip_sku || t.container_activity || '—') +
      (t.trip_containerstat ? ' <small class="text-muted">/ ' + escapeHtml(t.trip_containerstat) + '</small>' : '');
    return '<tr class="pr-row" data-trip-id="' + t.trip_id + '">' +
      '<td><b>' + escapeHtml(t.trip_type || ('Trip ' + t.trip_id)) + '</b></td>' +
      '<td>' + seg + '</td>' +
      '<td>' + route + '</td>' +
      '<td>' + actStat + '</td>' +
      '<td style="min-width:240px;"><select class="form-select form-select-sm pr-activity">' +
        activityOptions(t.container_activity) + '</select></td>' +
      '<td class="text-end fw-bold pr-rate">' + (t.piece_rate > 0 ? '₱' + t.piece_rate.toFixed(2) : '—') + '</td>' +
    '</tr>';
  }).join('');

  var html = head +
    '<div class="table-responsive"><table class="table table-sm align-middle mb-2">' +
      '<thead class="table-light"><tr>' +
        '<th>Trip</th><th>Hauling Segment</th><th>Route</th><th>SKU / Container</th>' +
        '<th style="min-width:240px;">Assign Activity (rate)</th><th class="text-end">Rate</th>' +
      '</tr></thead><tbody>' + body + '</tbody></table></div>' +
    '<div class="d-flex justify-content-between align-items-center">' +
      '<span class="text-muted" id="prTotal">—</span>' +
      '<button class="btn btn-success" id="prSave" data-d-id="' + d.d_id + '"><i class="ti ti-cash"></i> Assign Piece-Rate</button>' +
    '</div>';
  $('#lookupResult').html(html);
  recomputeTotal();
}

function recomputeTotal() {
  var total = 0, picked = 0;
  $('#lookupResult .pr-row').each(function () {
    var act = $(this).find('.pr-activity').val();
    if (act) { picked++; var r = rateLookup(act); total += r; $(this).find('.pr-rate').text('₱' + r.toFixed(2)); }
  });
  $('#prTotal').text(picked ? ('Total to assign: ₱' + total.toFixed(2)) : 'Pick an activity to compute the rate.');
  $('#prSave').prop('disabled', picked === 0);
}
$(document).on('change', '.pr-activity', recomputeTotal);

$(document).on('click', '#prSave', function () {
  var dId = $(this).data('d-id');
  var picks = {};
  $('#lookupResult .pr-row').each(function () {
    var tid = $(this).data('trip-id');
    var act = $(this).find('.pr-activity').val();
    if (tid && act) picks[tid] = act;
  });
  if (!Object.keys(picks).length) { Swal.fire({icon:'info', text:'Pick at least one activity.'}); return; }
  var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving…');
  $.post('php/operations/payroll_assign_rate.php', { d_id: dId, picks: picks }, function (res) {
    $btn.prop('disabled', false).html('<i class="ti ti-cash"></i> Assign Piece-Rate');
    if (res.status === 'success') {
      Swal.fire({ icon:'success', text: res.message, timer: 1800, showConfirmButton: false });
      loadCoupon($('#controlNo').val());
      loadPending();
    } else {
      Swal.fire({ icon:'error', text: res.message || 'Failed' });
    }
  }, 'json').fail(function (xhr) {
    $btn.prop('disabled', false).html('<i class="ti ti-cash"></i> Assign Piece-Rate');
    Swal.fire({ icon:'error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Network error.' });
  });
});

$('#lookupBtn').on('click', function () { loadCoupon(); });
$('#controlNo').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); loadCoupon(); } });

function loadPending() {
  $('#pendingBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>');
  $.getJSON('php/fetch/payroll_pending.php', function (res) {
    if (res.status !== 'success') {
      $('#pendingBody').html('<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHtml(res.message || 'Failed') + '</td></tr>');
      return;
    }
    if (!res.rows.length) {
      $('#pendingBody').html('<tr><td colspan="7" class="text-center text-success py-4">Nothing pending — all rated ✓</td></tr>');
      return;
    }
    $('#pendingBody').html(res.rows.map(function (r) {
      var actStat = escapeHtml(r.trip_sku || r.container_activity || '—') +
        (r.trip_containerstat ? ' <small class="text-muted">/ ' + escapeHtml(r.trip_containerstat) + '</small>' : '');
      return '<tr>' +
        '<td><span class="font-monospace fw-bold">' + escapeHtml(r.control_no) + '</span>' +
          '<div class="small text-muted">' + escapeHtml(r.booking_no || '') + '</div></td>' +
        '<td><small>' + escapeHtml(r.date) + '</small></td>' +
        '<td><small>' + escapeHtml(r.trip_haulingsegment || '—') + '</small></td>' +
        '<td><small>' + escapeHtml(r.trip_from || '—') + ' → ' + escapeHtml(r.trip_to || '—') + '</small></td>' +
        '<td><small>' + actStat + '</small></td>' +
        '<td><small>' + escapeHtml(r.driver || '—') + '<br>' + escapeHtml(r.truck || '—') + '</small></td>' +
        '<td class="text-end"><button class="btn btn-sm btn-outline-primary pending-load" data-ctl="' + escapeHtml(r.control_no) + '">Load</button></td>' +
      '</tr>';
    }).join(''));
  }).fail(function (xhr) {
    var msg = (xhr.responseJSON && xhr.responseJSON.message) || ('Request failed (' + xhr.status + ')');
    $('#pendingBody').html('<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHtml(msg) + '</td></tr>');
  });
}
$(document).on('click', '.pending-load', function () { loadCoupon($(this).data('ctl')); $('html,body').animate({scrollTop:0}, 200); });
$('#pendingRefresh').on('click', loadPending);

// Deep-link support: ?control_no=2026-000125
(function () {
  var ctl = new URLSearchParams(window.location.search).get('control_no');
  if (ctl) loadCoupon(ctl);
})();
loadPending();
})();
</script>
<?php include 'payroll/_layout_bottom.php'; ?>
