<?php
// Shared body for the dispatcher/admin Trip Verification page.
// Required vars: $role ('dispatcher' | 'admin').
//
// Verification is now a pure APPROVE step — the dispatcher no longer picks
// piece-rates here (that moved to the Payroll role). Approving a POD calls
// approve_trip.php, which stamps a coupon control_no + QR token and returns
// the coupon print URL; the page auto-opens it to print. Rejecting sends the
// driver back to re-capture.
$couponRoute = ($role === 'admin') ? 'coupon' : 'dispatch-coupon';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trip Verification</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <style>
    .pod-photo { width:100%; height:200px; object-fit:cover; border-radius:8px; border:1px solid #ddd; cursor:zoom-in; background:#f8f9fa; }
    .sig-img   { width:100%; max-height:120px; object-fit:contain; border:1px solid #ddd; border-radius:8px; background:#fff; }
    .verification-focus { border: 2px solid #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18); }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Trip Verification</h3>
    </div>

    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3"><div class="card-body">
            <div class="d-md-flex align-items-center mb-3">
              <div>
                <h4 class="card-title mb-0">Trip Verification</h4>
                <p class="card-subtitle">Drivers' POD submissions awaiting your confirmation. Review the location, photos, and signature, then <b>Approve</b> (generates a coupon ticket and prints it) or <b>Reject</b> (sends them back to re-capture). Piece-rates are assigned afterwards by Payroll using the coupon's control number.</p>
              </div>
              <div class="ms-auto">
                <button class="btn btn-sm btn-outline-secondary" id="refreshBtn"><i class="ti ti-refresh"></i></button>
              </div>
            </div>

            <ul class="nav nav-tabs mb-3" id="verifTabs" role="tablist">
              <li class="nav-item"><button class="nav-link active" data-tab="pending" type="button"><i class="ti ti-clock"></i> Pending Verification</button></li>
              <li class="nav-item"><button class="nav-link" data-tab="verified" type="button"><i class="ti ti-check"></i> Approved Trips</button></li>
            </ul>

            <div id="paneVerified" class="d-none">
              <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                  <label class="form-label small mb-1">From</label>
                  <input type="date" class="form-control form-control-sm" id="vtFrom">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-1">To</label>
                  <input type="date" class="form-control form-control-sm" id="vtTo">
                </div>
                <div class="col-md-6 text-end">
                  <button class="btn btn-sm btn-primary" id="vtApply"><i class="ti ti-filter"></i> Apply</button>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Control No</th>
                      <th>Booking / Trip</th>
                      <th>Customer / Route</th>
                      <th>Hauling Segment</th>
                      <th>Driver / Truck</th>
                      <th>Payroll</th>
                      <th class="text-end">Approved</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody id="vtBody"><tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                </table>
              </div>
            </div>

            <div id="panePending"><div id="cardsBox"><div class="text-muted">Loading…</div></div></div>
          </div></div>
          <div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
  const COUPON_ROUTE = <?= json_encode($couponRoute) ?>;
  const targetVerificationId = new URLSearchParams(window.location.search).get('d_id') || '';
  function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function couponUrl(dId){ return COUPON_ROUTE + '?d_id=' + encodeURIComponent(dId); }
  function photoCell(path) {
    if (!path) return '<div class="pod-photo d-flex align-items-center justify-content-center text-muted">No photo</div>';
    return '<a href="' + escapeHtml(path) + '" target="_blank"><img class="pod-photo" src="' + escapeHtml(path) + '"></a>';
  }
  function gpsLink(lat, lng) {
    if (!lat || !lng) return '<span class="text-muted">No GPS captured</span>';
    return '<a href="https://www.google.com/maps?q=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lng) + '" target="_blank"><i class="ti ti-map-pin"></i> ' + escapeHtml(lat) + ', ' + escapeHtml(lng) + '</a>';
  }
  function tripDuration(min) {
    if (!min || min < 0) return '<span class="text-muted">—</span>';
    const h = Math.floor(min / 60), m = min % 60;
    return (h ? h + 'h ' : '') + m + 'm';
  }
  function focusTargetCard() {
    if (!targetVerificationId) return;
    const selector = '[data-d-id="' + String(targetVerificationId).replace(/"/g, '\\"') + '"]';
    const $card = $(selector).first();
    if (!$card.length) return;
    $('.verification-focus').removeClass('verification-focus');
    $card.addClass('verification-focus');
    $('html, body').stop(true).animate({ scrollTop: Math.max($card.offset().top - 120, 0) }, 250);
  }
  function loadPending() {
    $.getJSON('php/fetch/pending_verifications.php', function (res) {
      if (res.status !== 'success') { $('#cardsBox').html('<div class="alert alert-danger">' + escapeHtml(res.message) + '</div>'); return; }
      if (!res.rows.length) { $('#cardsBox').html('<div class="alert alert-success mb-0">Nothing pending. All approved ✓</div>'); return; }
      const html = res.rows.map(function (r) {
        const photos = '<div class="row g-2">'
          + '<div class="col-md-4">' + photoCell(r.photo1_path) + '</div>'
          + '<div class="col-md-4">' + photoCell(r.photo2_path) + '</div>'
          + '<div class="col-md-4">' + photoCell(r.photo3_path) + '</div>'
          + '</div>';
        const sig = r.signature_path
          ? '<img class="sig-img" src="' + escapeHtml(r.signature_path) + '">'
          : '<div class="text-muted">No signature</div>';
        const custSeg = (r.customer_segment || '').toString().trim();
        const haulSeg = (r.trip_haulingsegment || '').toString().trim();
        const segParts = [];
        if (custSeg) segParts.push(escapeHtml(custSeg));
        if (haulSeg && haulSeg.toUpperCase() !== custSeg.toUpperCase()) {
          segParts.push(escapeHtml(haulSeg));
        }
        const segChip = segParts.length
          ? ' &middot; Segment: <span class="badge bg-light text-dark border">' + segParts.join(' &middot; ') + '</span>'
          : '';
        return '<div class="card mb-3" data-d-id="' + r.d_id + '"><div class="card-body">'
          + '<div class="d-flex align-items-center mb-2 flex-wrap gap-2">'
          +   '<h5 class="mb-0">' + escapeHtml(r.booking_no || '?') + '</h5>'
          +   '<span class="badge bg-warning text-dark">Pending Verification</span>'
          +   '<span class="text-muted small ms-auto">'
          +     'Driver: ' + escapeHtml(r.d_driverName || '-') + ' &middot; Truck: ' + escapeHtml(r.d_truck || '-') + ' &middot; Customer: ' + escapeHtml(r.costumer || '-')
          +     segChip
          +   '</span>'
          + '</div>'
          + '<div class="row g-3">'
          +   '<div class="col-md-8">' + photos + '</div>'
          +   '<div class="col-md-4">'
          +     '<div class="mb-2"><strong>Signature</strong>' + sig + '</div>'
          +     '<div><strong>Hauling segment:</strong> ' + escapeHtml(r.trip_haulingsegment || '—') + '</div>'
          +     '<div><strong>Container status:</strong> ' + escapeHtml(r.trip_containerstat || '—') + '</div>'
          +     '<div><strong>Signed by:</strong> ' + escapeHtml(r.signed_by || '—') + '</div>'
          +     '<div><strong>POD location:</strong> ' + gpsLink(r.lat, r.lng) + '</div>'
          +     '<div><strong>POD captured:</strong> ' + escapeHtml(r.captured_at || '—') + '</div>'
          +     '<div><strong>Trip duration:</strong> ' + tripDuration(parseInt(r.trip_minutes, 10)) + '</div>'
          +   '</div>'
          + '</div>'
          + '<div class="d-flex gap-2 mt-3">'
          +   '<button class="btn btn-success btn-approve"><i class="ti ti-check"></i> Approve &amp; Print Coupon</button>'
          +   '<button class="btn btn-outline-danger btn-reject"><i class="ti ti-x"></i> Reject</button>'
          + '</div>'
          + '</div></div>';
      }).join('');
      $('#cardsBox').html(html);
      focusTargetCard();
    });
  }
  $('#refreshBtn').on('click', loadPending);

  // Reject path — driver re-captures. No rates involved.
  function reject(dId) {
    Swal.fire({
      title: 'Reject this POD?',
      input: 'textarea',
      inputPlaceholder: 'Reason (driver will see this)',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Reject',
      confirmButtonColor: '#dc3545',
    }).then(r => {
      if (!r.isConfirmed) return;
      $.post('php/operations/approve_trip.php',
        { d_id: dId, decision: 'rejected', notes: r.value || '' },
        function (res) {
          if (res.status === 'success') {
            Swal.fire({ icon: 'success', text: res.message, timer: 1500, showConfirmButton: false });
            loadPending();
          } else {
            Swal.fire({ icon: 'error', text: res.message || 'Failed' });
          }
        }, 'json');
    });
  }

  // Approve path — generates the coupon and auto-opens it to print.
  function approve(dId) {
    Swal.fire({
      title: 'Approve this trip?',
      text: 'A coupon ticket will be generated and printed. Payroll assigns the rate afterwards.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Approve & Print',
      confirmButtonColor: '#198754',
      input: 'textarea',
      inputPlaceholder: 'Approval notes (optional)',
    }).then(r => {
      if (!r.isConfirmed) return;
      // Pop the coupon window synchronously so it isn't blocked, then aim it
      // at the print URL once approval succeeds.
      var couponWin = window.open('', '_blank');
      $.post('php/operations/approve_trip.php',
        { d_id: dId, decision: 'approved', notes: r.value || '' },
        function (res) {
          if (res.status === 'success') {
            if (couponWin) { couponWin.location = couponUrl(dId); }
            else { window.open(couponUrl(dId), '_blank'); }
            Swal.fire({ icon: 'success', text: res.message, timer: 1800, showConfirmButton: false });
            loadPending();
          } else {
            if (couponWin) couponWin.close();
            Swal.fire({ icon: 'error', text: res.message || 'Failed' });
          }
        }, 'json'
      ).fail(function (xhr) {
        if (couponWin) couponWin.close();
        Swal.fire({ icon: 'error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Network error.' });
      });
    });
  }

  $('#cardsBox').on('click', '.btn-approve', function () {
    approve($(this).closest('[data-d-id]').data('d-id'));
  });
  $('#cardsBox').on('click', '.btn-reject', function(){ reject($(this).closest('[data-d-id]').data('d-id')); });

  // ---------- Tabs ----------------------------------------------------
  var currentTab = 'pending';
  var pendingTimer = null;
  function startPendingPolling() {
    if (pendingTimer) return;
    pendingTimer = setInterval(loadPending, 20000);
  }
  function stopPendingPolling() {
    if (pendingTimer) { clearInterval(pendingTimer); pendingTimer = null; }
  }
  $(document).on('click', '#verifTabs button[data-tab]', function () {
    var $btn = $(this);
    if ($btn.hasClass('active')) return;
    $('#verifTabs button[data-tab]').removeClass('active');
    $btn.addClass('active');
    currentTab = $btn.data('tab');
    $('#panePending').toggleClass('d-none', currentTab !== 'pending');
    $('#paneVerified').toggleClass('d-none', currentTab !== 'verified');
    if (currentTab === 'pending') {
      loadPending();
      startPendingPolling();
    } else {
      stopPendingPolling();
      loadVerified();
    }
  });

  // ---------- Approved Trips tab --------------------------------------
  function isoDateNDaysAgo(n) {
    var d = new Date(); d.setDate(d.getDate() - n);
    return d.toISOString().slice(0, 10);
  }
  $('#vtFrom').val(isoDateNDaysAgo(7));
  $('#vtTo').val(isoDateNDaysAgo(0));

  function payrollBadge(status) {
    if (status === 'rated') return '<span class="badge bg-success">Rated</span>';
    return '<span class="badge bg-warning text-dark">Pending</span>';
  }

  function loadVerified() {
    var params = { from: $('#vtFrom').val() || '', to: $('#vtTo').val() || '' };
    $('#vtBody').html('<tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>');
    $.getJSON('php/fetch/verified_trips.php', params)
      .done(function (res) {
        if (res.status !== 'success') {
          $('#vtBody').html('<tr><td colspan="8" class="text-center text-danger py-4">' +
            escapeHtml(res.message || 'Failed.') + '</td></tr>');
          return;
        }
        var rows = res.rows || [];
        if (!rows.length) {
          $('#vtBody').html('<tr><td colspan="8" class="text-center text-muted py-4">No approved trips in this range.</td></tr>');
          return;
        }
        $('#vtBody').html(rows.map(function (r) {
          var ctl = (r.control_no || '').trim();
          return '<tr data-trip-id="' + r.trip_id + '">' +
            '<td><span class="font-monospace fw-bold">' + escapeHtml(ctl || '—') + '</span></td>' +
            '<td><span class="font-monospace small">' + escapeHtml(r.booking_no || '—') + '</span>' +
              '<br><small class="text-muted">' + escapeHtml(r.trip_type || '') + '</small></td>' +
            '<td>' + escapeHtml(r.customer || '—') +
              '<div class="small text-muted">' + escapeHtml(r.trip_from || '—') + ' → ' + escapeHtml(r.trip_to || '—') + '</div></td>' +
            '<td><small>' + escapeHtml(r.trip_haulingsegment || '—') + '</small></td>' +
            '<td>' + escapeHtml(r.driver_name || '—') +
              '<div class="small text-muted">' + escapeHtml(r.truck || '—') + '</div></td>' +
            '<td>' + payrollBadge(r.payroll_status) + '</td>' +
            '<td class="text-end"><small>' + escapeHtml(r.verified_at || '—') + '</small></td>' +
            '<td class="text-end">' +
              (ctl ? '<a class="btn btn-sm btn-outline-secondary" target="_blank" href="' + escapeHtml(couponUrl(r.d_id)) + '">' +
                '<i class="ti ti-printer"></i> Coupon</a>' : '') +
            '</td>' +
          '</tr>';
        }).join(''));
      })
      .fail(function () {
        $('#vtBody').html('<tr><td colspan="8" class="text-center text-danger py-4">Failed to load approved trips.</td></tr>');
      });
  }
  $('#vtApply').on('click', loadVerified);

  loadPending();
  startPendingPolling();
  </script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
