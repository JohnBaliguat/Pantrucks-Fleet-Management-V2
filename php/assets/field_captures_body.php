<?php
// Shared body for the Field Captures admin page.
// Included by admin/field-captures.php and dispatcher/field-captures.php.
// Required: $role ('admin' | 'dispatcher')
$pageTitle = 'Field Captures';
require_once __DIR__ . '/../config/config.php';

// Customer + driver pickers for the filter bar.
$customers = [];
$res = $conn->query("SELECT DISTINCT costumer FROM booking WHERE costumer <> '' ORDER BY costumer");
while ($r = $res->fetch()) $customers[] = $r['costumer'];

$drivers = [];
$res = $conn->query("SELECT driver_id, driver_fname, driver_lname FROM drivers ORDER BY driver_lname, driver_fname");
while ($r = $res->fetch()) {
    $drivers[] = [
        'id'   => (int)$r['driver_id'],
        'name' => trim(($r['driver_fname'] ?? '') . ' ' . ($r['driver_lname'] ?? '')),
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle); ?> &mdash; Pantrucks</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <style>
    .fc-thumb { width:64px; height:64px; object-fit:cover; border-radius:6px;
                border:1px solid #cbd5e1; cursor:zoom-in; background:#f1f5f9; }
    .fc-thumb-stack { display:flex; gap:4px; flex-wrap:wrap; }
    .fc-thumb-stack .fc-thumb { width:40px; height:40px; }
    .fc-thumb.is-missing { display:flex; align-items:center; justify-content:center;
                           color:#94a3b8; font-size:10px; cursor:default; }
    .fc-meta-line { font-size:11px; color:#64748b; }
    .fc-pill { font-size:10px; font-weight:700; padding:2px 8px; border-radius:999px;
               text-transform:uppercase; letter-spacing:.04em; }
    .fc-pill.active   { background:#fef3c7; color:#92400e; }
    .fc-pill.returned { background:#d1fae5; color:#065f46; }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6"
       data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Pantrucks Field Captures</h3>
    </div>
    <?php include $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
            <h3 class="mb-0">Field Captures</h3>
            <span class="text-muted small">Driver-submitted photos: container pickups, trailer jackups, and proof-of-delivery.</span>
            <div class="ms-auto small text-muted">Last refresh <span id="fetchedAt">—</span></div>
          </div>

          <!-- Filter bar -->
          <div class="card mb-3">
            <div class="card-body">
              <div class="row g-2 align-items-end">
                <div class="col-md-2">
                  <label class="form-label small mb-1">From</label>
                  <input type="date" class="form-control form-control-sm" id="fFrom">
                </div>
                <div class="col-md-2">
                  <label class="form-label small mb-1">To</label>
                  <input type="date" class="form-control form-control-sm" id="fTo">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-1">Driver</label>
                  <select class="form-select form-select-sm" id="fDriver">
                    <option value="">All</option>
                    <?php foreach ($drivers as $d): ?>
                      <option value="<?php echo (int)$d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-1">Customer</label>
                  <select class="form-select form-select-sm" id="fCustomer">
                    <option value="">All</option>
                    <?php foreach ($customers as $c): ?>
                      <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                  <button class="btn btn-sm btn-primary flex-fill" id="btnApply"><i class="ti ti-filter"></i> Apply</button>
                  <button class="btn btn-sm btn-outline-secondary" id="btnReset" title="Reset to last 7 days"><i class="ti ti-refresh"></i></button>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <ul class="nav nav-tabs mb-0" id="fcTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-tab="pickup" type="button"><i class="ti ti-camera"></i> Pickups <span class="badge bg-secondary ms-1" id="cntPickup">0</span></button></li>
            <li class="nav-item"><button class="nav-link" data-tab="jackup" type="button"><i class="ti ti-anchor"></i> Jackups <span class="badge bg-secondary ms-1" id="cntJackup">0</span></button></li>
            <li class="nav-item"><button class="nav-link" data-tab="pod" type="button"><i class="ti ti-clipboard-check"></i> PODs <span class="badge bg-secondary ms-1" id="cntPod">0</span></button></li>
          </ul>

          <div class="card" style="border-top-left-radius:0;">
            <div class="card-body p-0">
              <div class="table-responsive">
                <!-- Pickup table -->
                <table class="table table-hover align-middle mb-0 fc-table" id="tblPickup">
                  <thead class="table-light">
                    <tr>
                      <th style="width:80px;">Photo</th>
                      <th>Booking</th>
                      <th>Customer / Route</th>
                      <th>Container</th>
                      <th>Driver / Truck</th>
                      <th>GPS</th>
                      <th>Captured</th>
                    </tr>
                  </thead>
                  <tbody id="bodyPickup"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                </table>
                <!-- Jackup table -->
                <table class="table table-hover align-middle mb-0 fc-table d-none" id="tblJackup">
                  <thead class="table-light">
                    <tr>
                      <th style="width:80px;">Photo</th>
                      <th>Trailer</th>
                      <th>Booking</th>
                      <th>Customer / Route</th>
                      <th>Driver / Truck</th>
                      <th>Status</th>
                      <th>GPS</th>
                      <th>Detached</th>
                      <th>Returned</th>
                    </tr>
                  </thead>
                  <tbody id="bodyJackup"><tr><td colspan="9" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                </table>
                <!-- POD table -->
                <table class="table table-hover align-middle mb-0 fc-table d-none" id="tblPod">
                  <thead class="table-light">
                    <tr>
                      <th style="width:170px;">Photos</th>
                      <th>Booking</th>
                      <th>Customer / Route</th>
                      <th>Driver / Truck</th>
                      <th>Signed by</th>
                      <th>Signature</th>
                      <th>GPS</th>
                      <th>Captured</th>
                    </tr>
                  </thead>
                  <tbody id="bodyPod"><tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function isoDateNDaysAgo(n) {
      var d = new Date(); d.setDate(d.getDate() - n);
      return d.toISOString().slice(0, 10);
    }
    function gpsCell(r) {
      if (r.lat == null || r.lng == null) return '<span class="text-muted small">—</span>';
      return '<a class="small" href="https://www.google.com/maps?q=' + r.lat + ',' + r.lng +
             '" target="_blank" rel="noopener">' + r.lat.toFixed(4) + ', ' + r.lng.toFixed(4) + '</a>';
    }
    function thumb(url, alt) {
      if (!url) return '<div class="fc-thumb is-missing">no img</div>';
      return '<img class="fc-thumb" src="' + escapeHtml(url) + '" alt="' + escapeHtml(alt || '') +
             '" data-zoom="' + escapeHtml(url) + '" loading="lazy">';
    }

    // SweetAlert lightbox — clicking any thumbnail opens the full-size image.
    $(document).on('click', '[data-zoom]', function () {
      var url = $(this).attr('data-zoom');
      Swal.fire({
        imageUrl: url,
        imageAlt: 'Capture',
        showConfirmButton: false,
        showCloseButton: true,
        background: '#0f172a',
        width: 'auto'
      });
    });

    function filterParams() {
      return {
        from:     $('#fFrom').val()     || '',
        to:       $('#fTo').val()       || '',
        driver:   $('#fDriver').val()   || '',
        customer: $('#fCustomer').val() || ''
      };
    }

    function renderPickup(rows) {
      $('#cntPickup').text(rows.length);
      if (!rows.length) { $('#bodyPickup').html('<tr><td colspan="7" class="text-center text-muted py-4">No pickup captures.</td></tr>'); return; }
      $('#bodyPickup').html(rows.map(function (r) {
        return '<tr>' +
          '<td>' + thumb(r.photo_url, r.container_no) + '</td>' +
          '<td><span class="font-monospace small">' + escapeHtml(r.booking_no || '—') + '</span></td>' +
          '<td>' + escapeHtml(r.customer || '—') +
              '<div class="fc-meta-line">' + escapeHtml(r.trip_from || '—') + ' → ' + escapeHtml(r.trip_to || '—') + '</div></td>' +
          '<td><span class="font-monospace">' + escapeHtml(r.container_no || '—') + '</span>' +
              (r.container_seal ? '<div class="fc-meta-line">seal ' + escapeHtml(r.container_seal) + '</div>' : '') +
              (r.shipping_line  ? '<div class="fc-meta-line">line ' + escapeHtml(r.shipping_line)  + '</div>' : '') +
              (r.driver_id_number ? '<div class="fc-meta-line">ID ' + escapeHtml(r.driver_id_number) + '</div>' : '') + '</td>' +
          '<td>' + escapeHtml(r.driver_name || '—') +
              '<div class="fc-meta-line">' + escapeHtml(r.truck || '—') + '</div></td>' +
          '<td>' + gpsCell(r) + '</td>' +
          '<td><small>' + escapeHtml(r.captured_at || '—') + '</small></td>' +
        '</tr>';
      }).join(''));
    }

    function renderJackup(rows) {
      $('#cntJackup').text(rows.length);
      if (!rows.length) { $('#bodyJackup').html('<tr><td colspan="9" class="text-center text-muted py-4">No jackup captures.</td></tr>'); return; }
      $('#bodyJackup').html(rows.map(function (r) {
        var statusPill = r.billing_active
          ? '<span class="fc-pill active">Detached</span>'
          : '<span class="fc-pill returned">Returned</span>';
        return '<tr>' +
          '<td>' + thumb(r.photo_url, r.trailer_code) + '</td>' +
          '<td><span class="font-monospace">' + escapeHtml(r.trailer_code || '—') + '</span></td>' +
          '<td><span class="font-monospace small">' + escapeHtml(r.booking_no || '—') + '</span></td>' +
          '<td>' + escapeHtml(r.customer || '—') +
              '<div class="fc-meta-line">' + escapeHtml(r.trip_from || '—') + ' → ' + escapeHtml(r.trip_to || '—') + '</div></td>' +
          '<td>' + escapeHtml(r.driver_name || '—') +
              '<div class="fc-meta-line">' + escapeHtml(r.truck || '—') + '</div></td>' +
          '<td>' + statusPill + '</td>' +
          '<td>' + gpsCell(r) + '</td>' +
          '<td><small>' + escapeHtml(r.detached_at || '—') + '</small></td>' +
          '<td><small>' + escapeHtml(r.returned_at || '—') + '</small></td>' +
        '</tr>';
      }).join(''));
    }

    function renderPod(rows) {
      $('#cntPod').text(rows.length);
      if (!rows.length) { $('#bodyPod').html('<tr><td colspan="8" class="text-center text-muted py-4">No POD captures.</td></tr>'); return; }
      $('#bodyPod').html(rows.map(function (r) {
        var photos = (r.photos || []).map(function (p) { return thumb(p, 'POD'); }).join('');
        if (!photos) photos = '<div class="fc-thumb is-missing">no img</div>';
        return '<tr>' +
          '<td><div class="fc-thumb-stack">' + photos + '</div></td>' +
          '<td><span class="font-monospace small">' + escapeHtml(r.booking_no || '—') + '</span></td>' +
          '<td>' + escapeHtml(r.customer || '—') +
              '<div class="fc-meta-line">' + escapeHtml(r.trip_from || '—') + ' → ' + escapeHtml(r.trip_to || '—') + '</div></td>' +
          '<td>' + escapeHtml(r.driver_name || '—') +
              '<div class="fc-meta-line">' + escapeHtml(r.truck || '—') + '</div></td>' +
          '<td>' + escapeHtml(r.signed_by || '—') + '</td>' +
          '<td>' + (r.signature_url ? thumb(r.signature_url, 'Signature') : '<span class="text-muted small">—</span>') + '</td>' +
          '<td>' + gpsCell(r) + '</td>' +
          '<td><small>' + escapeHtml(r.captured_at || '—') + '</small></td>' +
        '</tr>';
      }).join(''));
    }

    var TABS = {
      pickup: { url: 'php/fetch/pickup_captures.php', body: '#bodyPickup', table: '#tblPickup', render: renderPickup, count: 7 },
      jackup: { url: 'php/fetch/jackup_captures.php', body: '#bodyJackup', table: '#tblJackup', render: renderJackup, count: 9 },
      pod:    { url: 'php/fetch/pod_captures.php',    body: '#bodyPod',    table: '#tblPod',    render: renderPod,    count: 8 }
    };
    var currentTab = 'pickup';

    function loadTab(tab) {
      var t = TABS[tab];
      if (!t) return;
      $(t.body).html('<tr><td colspan="' + t.count + '" class="text-center text-muted py-4">Loading…</td></tr>');
      $.getJSON(t.url, filterParams())
        .done(function (res) {
          if (res.status !== 'success') {
            $(t.body).html('<tr><td colspan="' + t.count + '" class="text-center text-danger py-4">' +
              escapeHtml(res.message || 'Failed to load.') + '</td></tr>');
            return;
          }
          t.render(res.rows || []);
          $('#fetchedAt').text(new Date().toLocaleTimeString());
        })
        .fail(function (xhr) {
          var msg = (xhr.responseJSON && xhr.responseJSON.message) || xhr.responseText || 'Network error';
          $(t.body).html('<tr><td colspan="' + t.count + '" class="text-center text-danger py-4">' + escapeHtml(msg) + '</td></tr>');
        });
    }

    // Tabs
    $(document).on('click', '#fcTabs button[data-tab]', function () {
      var $btn = $(this);
      if ($btn.hasClass('active')) return;
      $('#fcTabs button[data-tab]').removeClass('active');
      $btn.addClass('active');
      currentTab = $btn.data('tab');
      Object.keys(TABS).forEach(function (k) {
        $(TABS[k].table).toggleClass('d-none', k !== currentTab);
      });
      loadTab(currentTab);
    });

    // Filter bar
    $('#btnApply').on('click', function () { loadTab(currentTab); });
    $('#btnReset').on('click', function () {
      $('#fFrom').val(isoDateNDaysAgo(7));
      $('#fTo').val(isoDateNDaysAgo(0));
      $('#fDriver').val('');
      $('#fCustomer').val('');
      loadTab(currentTab);
    });

    // Default to last 7 days on load.
    $('#fFrom').val(isoDateNDaysAgo(7));
    $('#fTo').val(isoDateNDaysAgo(0));
    loadTab('pickup');
  </script>

  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
</body>
</html>
