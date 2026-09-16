<?php
session_start();

if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['Admin', 'HR-Admin', 'User'], true)) {
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Driver Behavior</title>
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
                        <h4 class="card-title">Driver Behavior (Geotab)</h4>
                        <p class="card-subtitle">Harsh-driving / speeding / idling events. Convert to a violation (blocks the driver + WhatsApp) or dismiss.</p>
                      </div>
                    </div>
                    <div id="statusBox"></div>
                    <div class="table-responsive mt-2">
                      <table class="table text-nowrap align-middle fs-3 mb-0">
                        <thead>
                          <tr>
                            <th class="text-muted">When</th>
                            <th class="text-muted">Driver</th>
                            <th class="text-muted">Truck</th>
                            <th class="text-muted">Event</th>
                            <th class="text-muted text-end">Duration</th>
                            <th class="text-muted text-end">Distance</th>
                            <th class="text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody id="evRows">
                          <tr><td colspan="7" class="text-center text-muted py-5">Loading…</td></tr>
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
      function dur(s) { if (s == null) return '—'; const m = Math.floor(s / 60), sec = s % 60; return m > 0 ? (m + 'm ' + sec + 's') : (sec + 's'); }

      function render(events) {
        const $tb = $('#evRows').empty();
        if (!events.length) {
          $tb.append('<tr><td colspan="7" class="text-center text-success py-5"><i class="ti ti-circle-check"></i> No open behavior events.</td></tr>');
          return;
        }
        events.forEach(e => {
          const driver = e.driver_name ? esc(e.driver_name) : '<span class="text-danger">Unattributed</span>';
          const $tr = $(`
            <tr>
              <td class="text-muted">${e.occurred_at ? esc(e.occurred_at) : '—'}</td>
              <td class="fw-bolder">${driver}</td>
              <td>${esc(e.unit_name)}</td>
              <td><span class="badge bg-warning text-dark">${esc(e.rule_name)}</span></td>
              <td class="text-end">${dur(e.duration_s)}</td>
              <td class="text-end">${e.distance_km == null ? '—' : (Number(e.distance_km).toFixed(1) + ' km')}</td>
              <td class="text-end">
                <button class="btn btn-danger btn-sm convert" ${e.driver_id == null ? 'disabled title="No driver attributed"' : ''}>Violation</button>
                <button class="btn btn-outline-secondary btn-sm dismiss">Dismiss</button>
              </td>
            </tr>`);
          $tr.find('.convert').on('click', () => convert(e, $tr));
          $tr.find('.dismiss').on('click', () => dismiss(e.event_id, $tr));
          $tb.append($tr);
        });
      }

      function convert(e, $tr) {
        Swal.fire({
          title: 'Create violation?',
          html: 'This will block <b>' + esc(e.driver_name || '') + '</b> from dispatch and notify them.<br>Event: <b>' + esc(e.rule_name) + '</b>',
          icon: 'warning', showCancelButton: true, confirmButtonText: 'Create violation', confirmButtonColor: '#d33'
        }).then(r => {
          if (!r.isConfirmed) return;
          $tr.find('button').prop('disabled', true);
          $.post('php/operations/geotab_behavior_to_violation.php', { event_id: e.event_id })
            .done(res => {
              if (res.status === 'success') { Swal.fire({ icon: 'success', title: 'Done', text: res.message, timer: 2200, showConfirmButton: false }); $tr.fadeOut(200, () => $tr.remove()); }
              else { Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unknown error' }); $tr.find('button').prop('disabled', false); }
            })
            .fail(xhr => { let m = 'Request failed'; try { m = JSON.parse(xhr.responseText).message || m; } catch (x) {} Swal.fire({ icon: 'error', title: 'Failed', text: m }); $tr.find('button').prop('disabled', false); });
        });
      }

      function dismiss(eventId, $tr) {
        $tr.find('button').prop('disabled', true);
        $.post('php/crud/update/dismiss_geotab_event.php', { event_id: eventId })
          .done(res => { if (res.status === 'success') { $tr.fadeOut(200, () => $tr.remove()); } else { Swal.fire({ icon: 'error', title: 'Failed', text: res.message }); $tr.find('button').prop('disabled', false); } })
          .fail(() => { Swal.fire({ icon: 'error', title: 'Failed' }); $tr.find('button').prop('disabled', false); });
      }

      function load() {
        $.getJSON('php/fetch/geotab_driver_events.php')
          .done(res => {
            if (res.status !== 'success') { $('#statusBox').html('<div class="alert alert-danger">' + esc(res.message || 'Failed') + '</div>'); return; }
            render(res.events || []);
          })
          .fail(() => $('#statusBox').html('<div class="alert alert-danger">Could not load behavior events.</div>'));
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
