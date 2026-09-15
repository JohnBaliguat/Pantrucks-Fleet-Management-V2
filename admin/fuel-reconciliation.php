<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fuel Reconciliation</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  </head>

  <body>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
      data-sidebar-position="fixed" data-header-position="fixed">

      <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
          <a class="d-flex justify-content-center" href="#">
            <img src="assets/images/logos/pantrucks.png" alt="" width="122">
          </a>
        </div>
        <div class="d-lg-flex align-items-center gap-2">
          <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3>
        </div>
      </div>

      <?php include 'sidebar.php'; ?>

      <div class="body-wrapper">
        <?php include 'navbar.php'; ?>
        <div class="body-wrapper-inner">
          <div class="container-fluid">
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center mb-3">
                      <div>
                        <h4 class="card-title">Fuel Reconciliation</h4>
                        <p class="card-subtitle">Litres dispensed on the fuel ticket vs. what Geotab measured the engine burn between tickets.</p>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0 d-flex gap-2">
                        <select id="flagFilter" class="form-select form-select-sm" style="width:auto">
                          <option value="flagged">Flagged only</option>
                          <option value="">All</option>
                          <option value="liters_flag">Litres flag</option>
                          <option value="km_flag">Distance flag</option>
                          <option value="ok">OK</option>
                          <option value="no_data">No Geotab data</option>
                        </select>
                        <button id="runBtn" class="btn btn-outline-secondary btn-sm"><i class="ti ti-refresh"></i> Run reconciliation</button>
                      </div>
                    </div>
                    <div id="statusBox"></div>
                    <div class="table-responsive mt-2">
                      <table class="table text-nowrap align-middle fs-3 mb-0">
                        <thead>
                          <tr>
                            <th class="text-muted">Truck</th>
                            <th class="text-muted">Period end</th>
                            <th class="text-muted text-end">Ticket L</th>
                            <th class="text-muted text-end">Geotab L</th>
                            <th class="text-muted text-end">Δ L</th>
                            <th class="text-muted text-end">Ticket km</th>
                            <th class="text-muted text-end">Geotab km</th>
                            <th class="text-muted text-end">Δ km</th>
                            <th class="text-muted">Flag</th>
                          </tr>
                        </thead>
                        <tbody id="recRows">
                          <tr><td colspan="9" class="text-center text-muted py-5">Loading…</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="py-6 px-6 text-center">
              <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
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
      function num(n, d) { return n == null ? '—' : Number(n).toLocaleString(undefined, { maximumFractionDigits: d == null ? 1 : d }); }

      function flagBadge(f) {
        if (f === 'liters_flag') return '<span class="badge bg-danger">Litres</span>';
        if (f === 'km_flag')     return '<span class="badge bg-warning text-dark">Distance</span>';
        if (f === 'ok')          return '<span class="badge bg-success">OK</span>';
        return '<span class="badge bg-secondary">No data</span>';
      }
      function deltaCell(v, flagged) {
        if (v == null) return '<td class="text-end text-muted">—</td>';
        const cls = flagged ? 'text-danger fw-bolder' : (Math.abs(v) > 0 ? 'text-dark' : 'text-muted');
        return '<td class="text-end ' + cls + '">' + (v > 0 ? '+' : '') + num(v) + '</td>';
      }

      function render(rows) {
        const $tb = $('#recRows').empty();
        if (!rows.length) { $tb.append('<tr><td colspan="9" class="text-center text-muted py-5">Nothing to show.</td></tr>'); return; }
        rows.forEach(r => {
          const lFlag = r.flag === 'liters_flag', kFlag = r.flag === 'km_flag';
          $tb.append(
            '<tr>' +
            '<td class="fw-bolder">' + esc(r.unit_name) + '</td>' +
            '<td class="text-muted">' + (r.period_end ? esc(r.period_end) : '—') + '</td>' +
            '<td class="text-end">' + num(r.ticket_liters) + '</td>' +
            '<td class="text-end">' + num(r.geotab_liters) + '</td>' +
            deltaCell(r.liters_variance, lFlag) +
            '<td class="text-end">' + num(r.ticket_km) + '</td>' +
            '<td class="text-end">' + num(r.geotab_km) + '</td>' +
            deltaCell(r.km_variance, kFlag) +
            '<td>' + flagBadge(r.flag) + '</td>' +
            '</tr>'
          );
        });
      }

      function load() {
        const flag = $('#flagFilter').val();
        $('#recRows').html('<tr><td colspan="9" class="text-center text-muted py-5">Loading…</td></tr>');
        $.getJSON('php/fetch/fuel_reconciliation.php', { flag })
          .done(res => {
            if (res.status !== 'success') { $('#statusBox').html('<div class="alert alert-danger">' + esc(res.message || 'Failed') + '</div>'); $('#recRows').empty(); return; }
            render(res.rows || []);
          })
          .fail(() => { $('#statusBox').html('<div class="alert alert-danger">Could not load reconciliation.</div>'); $('#recRows').empty(); });
      }

      function run() {
        $('#runBtn').prop('disabled', true);
        $('#statusBox').html('<div class="alert alert-info">Running reconciliation against Geotab… this can take a moment.</div>');
        $.post('php/operations/geotab_reconcile_fuel.php')
          .done(res => {
            if (res.status === 'success') {
              $('#statusBox').html('<div class="alert alert-success">Processed ' + res.processed + ' tickets, ' + res.flagged + ' flagged.</div>');
              load();
            } else {
              $('#statusBox').html('<div class="alert alert-danger">' + esc(res.message || 'Failed') + '</div>');
            }
          })
          .fail(xhr => {
            let msg = 'Reconciliation failed. Check php/config/geotab.php credentials.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            $('#statusBox').html('<div class="alert alert-danger">' + esc(msg) + '</div>');
          })
          .always(() => $('#runBtn').prop('disabled', false));
      }

      $('#flagFilter').on('change', load);
      $('#runBtn').on('click', run);
      load();
    </script>
  </body>
  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
}
