<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Geotab Zones</title>
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
                        <h4 class="card-title">Geotab Zones (Geofences)</h4>
                        <p class="card-subtitle">
                          Classify each zone so arrivals mean something: Base, Gate, Customer site, or Other.
                          Trucks entering an active zone are logged and their location is updated automatically.
                        </p>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0">
                        <button id="syncBtn" class="btn btn-outline-secondary btn-sm">
                          <i class="ti ti-cloud-download"></i> Sync from Geotab
                        </button>
                      </div>
                    </div>

                    <div id="statusBox"></div>

                    <div class="table-responsive mt-2">
                      <table class="table text-nowrap align-middle fs-3 mb-0">
                        <thead>
                          <tr>
                            <th class="text-muted">Zone</th>
                            <th class="text-muted">Vertices</th>
                            <th class="text-muted">Trucks inside</th>
                            <th class="text-muted">Classification</th>
                            <th class="text-muted">Active</th>
                            <th class="text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody id="zoneRows">
                          <tr><td colspan="6" class="text-center text-muted py-5">Loading…</td></tr>
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
      const KINDS = ['base', 'gate', 'customer', 'other'];
      function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

      function kindOptions(sel) {
        return KINDS.map(k => `<option value="${k}"${k === sel ? ' selected' : ''}>${k[0].toUpperCase() + k.slice(1)}</option>`).join('');
      }

      function render(zones) {
        const $tb = $('#zoneRows').empty();
        if (!zones.length) {
          $tb.append('<tr><td colspan="6" class="text-center text-muted py-5">No zones synced yet. Click “Sync from Geotab”.</td></tr>');
          return;
        }
        zones.forEach(z => {
          const $tr = $(`
            <tr data-zone="${esc(z.zone_id)}">
              <td><h6 class="mb-0 fw-bolder">${esc(z.name) || '(unnamed)'}</h6>
                  <span class="text-muted">synced ${z.synced_at ? esc(z.synced_at) : '—'}</span></td>
              <td>${z.vertices}</td>
              <td>${z.units_inside > 0 ? '<span class="badge bg-success">' + z.units_inside + '</span>' : '<span class="text-muted">0</span>'}</td>
              <td><select class="form-select form-select-sm kind-select" style="min-width:140px">${kindOptions(z.kind)}</select></td>
              <td><div class="form-check form-switch"><input class="form-check-input active-toggle" type="checkbox" ${z.active ? 'checked' : ''}></div></td>
              <td class="text-end"><button class="btn btn-primary btn-sm save-zone">Save</button></td>
            </tr>`);
          $tr.find('.save-zone').on('click', () => saveZone(z.zone_id, $tr));
          $tb.append($tr);
        });
      }

      function saveZone(zoneId, $tr) {
        const kind = $tr.find('.kind-select').val();
        const active = $tr.find('.active-toggle').is(':checked') ? '1' : '0';
        $tr.find('.save-zone').prop('disabled', true);
        $.post('php/crud/update/update_geotab_zone.php', { zone_id: zoneId, kind, active })
          .done(res => {
            if (res.status === 'success') {
              Swal.fire({ icon: 'success', title: 'Saved', timer: 1200, showConfirmButton: false });
            } else {
              Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unknown error' });
            }
          })
          .fail(xhr => {
            let msg = 'Request failed';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            Swal.fire({ icon: 'error', title: 'Failed', text: msg });
          })
          .always(() => $tr.find('.save-zone').prop('disabled', false));
      }

      function load() {
        $('#zoneRows').html('<tr><td colspan="6" class="text-center text-muted py-5">Loading…</td></tr>');
        $.getJSON('php/fetch/geotab_zones.php')
          .done(res => {
            if (res.status !== 'success') {
              $('#statusBox').html('<div class="alert alert-danger">' + esc(res.message || 'Failed') + '</div>');
              $('#zoneRows').empty();
              return;
            }
            render(res.zones || []);
          })
          .fail(() => {
            $('#statusBox').html('<div class="alert alert-danger">Could not load zones.</div>');
            $('#zoneRows').empty();
          });
      }

      function sync() {
        $('#syncBtn').prop('disabled', true);
        $('#statusBox').html('<div class="alert alert-info">Syncing zones from Geotab…</div>');
        $.post('php/operations/geotab_sync_zones.php')
          .done(res => {
            if (res.status === 'success') {
              $('#statusBox').html('<div class="alert alert-success">Synced ' + res.synced + ' zones.</div>');
              load();
            } else {
              $('#statusBox').html('<div class="alert alert-danger">' + esc(res.message || 'Sync failed') + '</div>');
            }
          })
          .fail(xhr => {
            let msg = 'Sync failed. Check php/config/geotab.php credentials.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            $('#statusBox').html('<div class="alert alert-danger">' + esc(msg) + '</div>');
          })
          .always(() => $('#syncBtn').prop('disabled', false));
      }

      $('#syncBtn').on('click', sync);
      load();
    </script>
  </body>
  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
}
