<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vehicle Health</title>
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
              <div class="col-lg-7">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center mb-3">
                      <div>
                        <h4 class="card-title">Active Engine Faults</h4>
                        <p class="card-subtitle">Fault codes reported by Geotab. Dismiss to acknowledge, or block the unit for maintenance.</p>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0">
                        <a href="blocked-units" class="btn btn-outline-danger btn-sm"><i class="ti ti-tool"></i> Maintenance</a>
                      </div>
                    </div>
                    <div id="statusBox"></div>
                    <div class="table-responsive mt-2">
                      <table class="table text-nowrap align-middle fs-3 mb-0">
                        <thead>
                          <tr>
                            <th class="text-muted">Truck</th>
                            <th class="text-muted">Code</th>
                            <th class="text-muted">Description</th>
                            <th class="text-muted">Count</th>
                            <th class="text-muted">Since</th>
                            <th class="text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody id="faultRows">
                          <tr><td colspan="6" class="text-center text-muted py-5">Loading…</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-5">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title mb-3">Odometer &amp; Engine Hours</h4>
                    <div class="table-responsive">
                      <table class="table text-nowrap align-middle fs-3 mb-0">
                        <thead>
                          <tr>
                            <th class="text-muted">Truck</th>
                            <th class="text-muted text-end">Odometer (km)</th>
                            <th class="text-muted text-end">Engine hrs</th>
                            <th class="text-muted">Updated</th>
                          </tr>
                        </thead>
                        <tbody id="diagRows">
                          <tr><td colspan="4" class="text-center text-muted py-5">Loading…</td></tr>
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
      function num(n) { return n == null ? '—' : Number(n).toLocaleString(undefined, { maximumFractionDigits: 1 }); }

      function renderFaults(faults) {
        const $tb = $('#faultRows').empty();
        if (!faults.length) {
          $tb.append('<tr><td colspan="6" class="text-center text-success py-5"><i class="ti ti-circle-check"></i> No active faults.</td></tr>');
          return;
        }
        faults.forEach(f => {
          const $tr = $(`
            <tr>
              <td class="fw-bolder">${esc(f.unit_name) || '(unlinked)'}</td>
              <td><span class="badge bg-danger">${esc(f.code) || '—'}</span></td>
              <td style="white-space:normal">${esc(f.description) || esc(f.fault_state) || '—'}</td>
              <td>${f.occurrences}</td>
              <td class="text-muted">${f.occurred_at ? esc(f.occurred_at) : '—'}</td>
              <td class="text-end"><button class="btn btn-outline-secondary btn-sm dismiss">Dismiss</button></td>
            </tr>`);
          $tr.find('.dismiss').on('click', () => dismiss(f.fault_id, $tr));
          $tb.append($tr);
        });
      }

      function renderDiag(diag) {
        const $tb = $('#diagRows').empty();
        if (!diag.length) {
          $tb.append('<tr><td colspan="4" class="text-center text-muted py-5">No linked trucks.</td></tr>');
          return;
        }
        diag.forEach(d => {
          $tb.append(`
            <tr>
              <td class="fw-bolder">${esc(d.unit_name)}</td>
              <td class="text-end">${num(d.odometer_km)}</td>
              <td class="text-end">${num(d.engine_hours)}</td>
              <td class="text-muted">${d.diagnostics_at ? esc(d.diagnostics_at) : '—'}</td>
            </tr>`);
        });
      }

      function dismiss(faultId, $tr) {
        $tr.find('.dismiss').prop('disabled', true);
        $.post('php/crud/update/dismiss_geotab_fault.php', { fault_id: faultId })
          .done(res => {
            if (res.status === 'success') { $tr.fadeOut(200, () => $tr.remove()); }
            else { Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unknown error' }); $tr.find('.dismiss').prop('disabled', false); }
          })
          .fail(() => { Swal.fire({ icon: 'error', title: 'Failed' }); $tr.find('.dismiss').prop('disabled', false); });
      }

      function load() {
        $.getJSON('php/fetch/geotab_faults.php')
          .done(res => {
            if (res.status !== 'success') {
              $('#statusBox').html('<div class="alert alert-danger">' + esc(res.message || 'Failed') + '</div>');
              return;
            }
            renderFaults(res.faults || []);
            renderDiag(res.diagnostics || []);
          })
          .fail(() => $('#statusBox').html('<div class="alert alert-danger">Could not load vehicle health.</div>'));
      }
      load();
      setInterval(() => { if (!document.hidden) load(); }, 60000);
    </script>
  </body>
  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
}
