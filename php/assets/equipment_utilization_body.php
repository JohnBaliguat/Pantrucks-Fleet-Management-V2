<?php
// Equipment Utilization view. Required vars: $role ('admin'|'dispatcher').
// Shows every truck / trailer / genset coloured by trip activity in a date
// range, with a click-through modal listing that equipment's trips.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Equipment Utilization</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <style>
    :root {
      --eu-blue: #2563eb; --eu-blue-bg: #eff6ff; --eu-blue-bd: #bfdbfe;
      --eu-orange: #ea580c; --eu-orange-bg: #fff7ed; --eu-orange-bd: #fed7aa;
      --eu-gray: #94a3b8; --eu-gray-bg: #f8fafc; --eu-gray-bd: #e2e8f0;
    }
    .eu-page { }
    /* ---- Filter bar ---- */
    .eu-filter .card-body { padding: 14px 18px; }
    .eu-legend { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
    .eu-legend .item { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#475467; font-weight:500; }
    .legend-chip { display:inline-block; width:14px; height:14px; border-radius:4px;
                   vertical-align:middle; border:1px solid #cbd5e1; }

    /* ---- Summary cards ---- */
    .eu-sum { border:1px solid #eaecf0; border-radius:14px; overflow:hidden; height:100%;
              box-shadow:0 1px 2px rgba(16,24,40,.05); transition:box-shadow .15s, transform .15s; background:#fff; }
    .eu-sum:hover { box-shadow:0 8px 24px rgba(16,24,40,.10); transform:translateY(-2px); }
    .eu-sum-top { height:5px; }
    .eu-sum-body { padding:18px 20px; }
    .eu-sum-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center;
                   justify-content:center; font-size:24px; }
    .eu-sum-total { font-size:34px; font-weight:800; line-height:1; color:#101828; }
    .eu-sum-label { font-size:13px; font-weight:600; color:#475467; text-transform:uppercase; letter-spacing:.04em; }
    .eu-bar { height:8px; border-radius:999px; overflow:hidden; display:flex; background:var(--eu-gray-bg); margin:14px 0 12px; }
    .eu-bar > span { display:block; height:100%; }
    .eu-stats { display:flex; text-align:center; }
    .eu-stats > div { flex:1; }
    .eu-stats .n { font-size:20px; font-weight:700; line-height:1.1; }
    .eu-stats .l { font-size:11px; color:#667085; display:flex; align-items:center; justify-content:center; gap:4px; margin-top:2px; }

    /* ---- Section + grid ---- */
    .eu-section { border:1px solid #eaecf0; border-radius:14px; box-shadow:0 1px 2px rgba(16,24,40,.05); }
    .eu-section-head { display:flex; align-items:center; gap:12px; padding:16px 20px; border-bottom:1px solid #f1f3f5; }
    .eu-section-head .ic { width:38px; height:38px; border-radius:10px; display:flex; align-items:center;
                           justify-content:center; font-size:20px; }
    .eu-section-head h5 { margin:0; font-weight:700; }
    .eu-pill { font-size:11px; font-weight:600; padding:2px 9px; border-radius:999px; }
    .eu-pill.b { background:var(--eu-blue-bg); color:var(--eu-blue); }
    .eu-pill.o { background:var(--eu-orange-bg); color:var(--eu-orange); }
    .eu-pill.g { background:var(--eu-gray-bg); color:#475467; border:1px solid var(--eu-gray-bd); }

    .eu-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(118px, 1fr)); gap:12px; padding:18px 20px; }
    .eu-cell { border:1px solid var(--eu-gray-bd); border-radius:12px; padding:14px 10px 12px; text-align:center;
               cursor:pointer; transition:box-shadow .15s, transform .08s, border-color .15s; background:#fff;
               position:relative; overflow:hidden; }
    .eu-cell::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:transparent; }
    .eu-cell:hover { box-shadow:0 6px 18px rgba(16,24,40,.12); transform:translateY(-2px); }
    .eu-cell:active { transform:scale(.98); }
    .eu-dot { width:9px; height:9px; border-radius:999px; display:inline-block; margin-bottom:6px; }
    .eu-cell .eu-name { font-weight:700; font-family:ui-monospace, monospace; font-size:14px; color:#0f172a; }
    .eu-cell .eu-sub  { font-size:11px; color:#667085; margin-top:3px; min-height:14px; }
    /* States */
    .eu-unused { background:var(--eu-gray-bg); }
    .eu-unused::before { background:var(--eu-gray-bd); }
    .eu-unused .eu-dot { background:var(--eu-gray); }
    .eu-active { background:var(--eu-blue-bg); border-color:var(--eu-blue-bd); }
    .eu-active::before { background:var(--eu-blue); }
    .eu-active .eu-dot { background:var(--eu-blue); box-shadow:0 0 0 3px rgba(37,99,235,.18); }
    .eu-idle { background:var(--eu-orange-bg); border-color:var(--eu-orange-bd); }
    .eu-idle::before { background:var(--eu-orange); }
    .eu-idle .eu-dot { background:var(--eu-orange); }
    .eu-badge { position:absolute; top:8px; right:8px; background:var(--eu-blue); color:#fff;
                border-radius:999px; min-width:20px; height:20px; line-height:20px; font-size:11px;
                font-weight:700; padding:0 6px; box-shadow:0 1px 3px rgba(37,99,235,.4); }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Equipment Utilization</h3>
    </div>
    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">

          <!-- Filter -->
          <div class="card mt-3 eu-filter" style="border-radius:14px;"><div class="card-body">
            <div class="row g-2 align-items-end">
              <div class="col-6 col-md-3">
                <label class="form-label small mb-1 fw-semibold">From</label>
                <input type="date" class="form-control form-control-sm" id="euFrom">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small mb-1 fw-semibold">To</label>
                <input type="date" class="form-control form-control-sm" id="euTo">
              </div>
              <div class="col-12 col-md-6 d-flex align-items-end gap-2">
                <button class="btn btn-primary btn-sm" id="euApply"><i class="ti ti-filter"></i> Apply</button>
                <button class="btn btn-outline-secondary btn-sm" id="euToday"><i class="ti ti-calendar"></i> Today</button>
                <span class="eu-legend ms-auto">
                  <span class="item"><span class="legend-chip" style="background:var(--eu-blue-bg);border-color:var(--eu-blue-bd);"></span>Active</span>
                  <span class="item"><span class="legend-chip" style="background:var(--eu-orange-bg);border-color:var(--eu-orange-bd);"></span>Used, idle</span>
                  <span class="item"><span class="legend-chip" style="background:var(--eu-gray-bg);"></span>Not used</span>
                </span>
              </div>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-12 col-md-5 position-relative">
                <label class="form-label small mb-1 fw-semibold">Search equipment</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="ti ti-search"></i></span>
                  <input type="text" class="form-control" id="euSearch" placeholder="Type a truck / trailer / genset name…" autocomplete="off">
                  <button class="btn btn-outline-secondary" type="button" id="euSearchClear" title="Clear"><i class="ti ti-x"></i></button>
                </div>
              </div>
            </div>
            <div class="small text-muted mt-2" id="euRangeLabel"></div>
          </div></div>

          <!-- Summary cards -->
          <div class="row g-3 mt-1" id="euSummaryCards"></div>

          <div id="euSections">
            <div class="text-center text-muted py-5">Loading…</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Equipment trips modal -->
  <div class="modal fade" id="euModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="euModalTitle">Equipment trips</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
              <thead class="table-light"><tr>
                <th>Dispatched</th><th>Booking</th><th>Trip Receipt</th><th>Customer</th>
                <th>Trip</th><th>Route</th><th>Container</th><th>Status</th><th>Driver</th>
              </tr></thead>
              <tbody id="euModalBody"><tr><td colspan="9" class="text-center text-muted py-4">Loading…</td></tr></tbody>
            </table>
          </div>
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
  <script>
    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function filters() {
      return { from: $('#euFrom').val() || '', to: $('#euTo').val() || '' };
    }

    function cell(type, e) {
      var cls = e.status === 'active' ? 'eu-active' : (e.status === 'idle' ? 'eu-idle' : 'eu-unused');
      var badge = e.active > 0 ? '<span class="eu-badge" title="Active trips">' + e.active + '</span>' : '';
      var sub = e.status === 'active' ? (e.active + ' active' + (e.total > e.active ? ' · ' + e.total + ' total' : ''))
              : (e.status === 'idle' ? (e.total + ' trip' + (e.total === 1 ? '' : 's')) : 'No trips');
      return '<div class="eu-cell ' + cls + '" data-type="' + type + '" data-name="' + esc(e.name) + '" title="Click to view trips">' +
               badge +
               '<span class="eu-dot"></span>' +
               '<div class="eu-name">' + esc(e.name) + '</div>' +
               '<div class="eu-sub">' + esc(sub) + '</div>' +
             '</div>';
    }

    function summaryCard(title, icon, accent, sum) {
      var t = sum.total || 1;
      var pa = (sum.active / t * 100), pi = (sum.idle / t * 100), pu = (sum.unused / t * 100);
      var used = sum.active + sum.idle;
      var pct = sum.total ? Math.round(used / sum.total * 100) : 0;
      return '<div class="col-12 col-md-4">' +
        '<div class="eu-sum">' +
          '<div class="eu-sum-top" style="background:' + accent + ';"></div>' +
          '<div class="eu-sum-body">' +
            '<div class="d-flex align-items-center">' +
              '<div class="eu-sum-icon" style="background:' + accent + '22;color:' + accent + ';"><i class="ti ' + icon + '"></i></div>' +
              '<div class="ms-3">' +
                '<div class="eu-sum-total">' + sum.total + '</div>' +
                '<div class="eu-sum-label">' + esc(title) + '</div>' +
              '</div>' +
              '<div class="ms-auto text-end">' +
                '<div class="fw-bold fs-5" style="color:' + accent + ';">' + pct + '%</div>' +
                '<div class="small text-muted">utilized</div>' +
              '</div>' +
            '</div>' +
            '<div class="eu-bar" title="' + sum.active + ' active, ' + sum.idle + ' idle, ' + sum.unused + ' unused">' +
              '<span style="width:' + pa + '%;background:var(--eu-blue);"></span>' +
              '<span style="width:' + pi + '%;background:var(--eu-orange);"></span>' +
              '<span style="width:' + pu + '%;background:var(--eu-gray-bd);"></span>' +
            '</div>' +
            '<div class="eu-stats">' +
              '<div><div class="n" style="color:var(--eu-blue);">' + sum.active + '</div><div class="l"><span class="legend-chip" style="width:10px;height:10px;background:var(--eu-blue-bg);border-color:var(--eu-blue-bd);"></span>Active</div></div>' +
              '<div><div class="n" style="color:var(--eu-orange);">' + sum.idle + '</div><div class="l"><span class="legend-chip" style="width:10px;height:10px;background:var(--eu-orange-bg);border-color:var(--eu-orange-bd);"></span>Idle</div></div>' +
              '<div><div class="n text-secondary">' + sum.unused + '</div><div class="l"><span class="legend-chip" style="width:10px;height:10px;background:var(--eu-gray-bg);"></span>Unused</div></div>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    }

    var EU_ACCENT = { trucks: '#2563eb', trailers: '#7c3aed', gensets: '#059669' };

    function renderSummary(summary) {
      $('#euSummaryCards').html(
        summaryCard('Trucks', 'ti-truck', EU_ACCENT.trucks, summary.trucks) +
        summaryCard('Trailers', 'ti-box', EU_ACCENT.trailers, summary.trailers) +
        summaryCard('Gensets', 'ti-bolt', EU_ACCENT.gensets, summary.gensets)
      );
    }

    function section(title, icon, accent, type, list, sum) {
      var cells = list.length
        ? '<div class="eu-grid">' + list.map(function (e) { return cell(type, e); }).join('') + '</div>'
        : '<div class="text-muted small p-4">No equipment.</div>';
      return '<div class="eu-section mt-3">' +
               '<div class="eu-section-head">' +
                 '<div class="ic" style="background:' + accent + '22;color:' + accent + ';"><i class="ti ' + icon + '"></i></div>' +
                 '<h5>' + esc(title) + '</h5>' +
                 '<span class="text-muted small">' + sum.total + ' units</span>' +
                 '<span class="ms-auto d-flex gap-2">' +
                   '<span class="eu-pill b">' + sum.active + ' active</span>' +
                   '<span class="eu-pill o">' + sum.idle + ' idle</span>' +
                   '<span class="eu-pill g">' + sum.unused + ' unused</span>' +
                 '</span>' +
               '</div>' + cells +
             '</div>';
    }

    // Live client-side filter — hide equipment cells whose name doesn't match
    // the search box, and show a per-section note when a section has no match.
    function applyEquipSearch() {
      var q = String($('#euSearch').val() || '').trim().toLowerCase();
      $('.eu-section').each(function () {
        var $sec = $(this), visible = 0;
        $sec.find('.eu-cell').each(function () {
          var name = String($(this).data('name') || '').toLowerCase();
          var show = q === '' || name.indexOf(q) !== -1;
          $(this).toggle(show);
          if (show) visible++;
        });
        var $grid = $sec.find('.eu-grid');
        if (!$grid.length) return;                 // section with no equipment
        var $note = $sec.find('.eu-nomatch');
        if (q !== '' && visible === 0) {
          if (!$note.length) { $note = $('<div class="eu-nomatch text-muted small p-4"></div>'); $grid.after($note); }
          $note.text('No equipment matches “' + q + '”.').show();
          $grid.hide();
        } else {
          $note.hide();
          $grid.show();
        }
      });
    }
    $('#euSearch').on('input', applyEquipSearch);
    $('#euSearchClear').on('click', function () { $('#euSearch').val('').trigger('input'); });

    function load() {
      $('#euSummaryCards').empty();
      $('#euSections').html('<div class="text-center text-muted py-5">Loading…</div>');
      $.getJSON('php/fetch/equipment_utilization.php', filters(), function (res) {
        if (!res || res.status !== 'success') {
          $('#euSections').html('<div class="text-center text-danger py-5">Failed to load.</div>');
          return;
        }
        $('#euFrom').val(res.from); $('#euTo').val(res.to);
        $('#euRangeLabel').text(res.from === res.to ? ('Showing: ' + res.from) : ('Showing: ' + res.from + ' to ' + res.to));
        renderSummary(res.summary);
        $('#euSections').html(
          section('Trucks', 'ti-truck', EU_ACCENT.trucks, 'truck', res.trucks, res.summary.trucks) +
          section('Trailers', 'ti-box', EU_ACCENT.trailers, 'trailer', res.trailers, res.summary.trailers) +
          section('Gensets', 'ti-bolt', EU_ACCENT.gensets, 'genset', res.gensets, res.summary.gensets)
        );
        // Re-apply any active search to the freshly-rendered cells.
        applyEquipSearch();
      }).fail(function () {
        $('#euSections').html('<div class="text-center text-danger py-5">Network error.</div>');
      });
    }

    $('#euApply').on('click', load);
    $('#euToday').on('click', function () {
      var t = new Date(); var s = t.getFullYear() + '-' + String(t.getMonth()+1).padStart(2,'0') + '-' + String(t.getDate()).padStart(2,'0');
      $('#euFrom').val(s); $('#euTo').val(s); load();
    });

    // Click an equipment cell → load its trips into the modal.
    $(document).on('click', '.eu-cell', function () {
      var type = $(this).data('type');
      var name = String($(this).data('name'));
      var f = filters();
      $('#euModalTitle').text(type.charAt(0).toUpperCase() + type.slice(1) + ': ' + name);
      $('#euModalBody').html('<tr><td colspan="9" class="text-center text-muted py-4">Loading…</td></tr>');
      $('#euModal').modal('show');
      $.getJSON('php/fetch/equipment_trips.php', $.extend({ type: type, name: name }, f), function (res) {
        if (!res || res.status !== 'success') {
          $('#euModalBody').html('<tr><td colspan="9" class="text-center text-danger py-4">Failed to load.</td></tr>');
          return;
        }
        if (!res.rows.length) {
          $('#euModalBody').html('<tr><td colspan="9" class="text-center text-muted py-4">No trips in this date range.</td></tr>');
          return;
        }
        $('#euModalBody').html(res.rows.map(function (r) {
          var badge = String(r.status).toLowerCase() === 'active'
            ? '<span class="badge bg-primary">Active</span>'
            : '<span class="badge bg-success">' + esc(r.status || 'Done') + '</span>';
          return '<tr>' +
            '<td><small>' + esc(r.dispatched) + '</small></td>' +
            '<td><span class="font-monospace">' + esc(r.booking_no) + '</span></td>' +
            '<td><span class="font-monospace">' + esc(r.trip_receipt || '—') + '</span></td>' +
            '<td>' + esc(r.customer || '—') + '</td>' +
            '<td>' + esc(r.trip_type || '—') + '</td>' +
            '<td>' + esc(r.route || '—') + '</td>' +
            '<td>' + esc(r.container || '—') + '</td>' +
            '<td>' + badge + '</td>' +
            '<td>' + esc(r.driver || '—') + '</td>' +
          '</tr>';
        }).join(''));
      }).fail(function () {
        $('#euModalBody').html('<tr><td colspan="9" class="text-center text-danger py-4">Network error.</td></tr>');
      });
    });

    load();
  </script>
</body>
</html>
