<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Geotab Devices</title>
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
                        <h4 class="card-title">Geotab Device Mapping</h4>
                        <p class="card-subtitle">
                          Link each Geotab GO device to a truck. The device's VIN is captured on link.
                        </p>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0 d-flex gap-2">
                        <button id="saveAllBtn" class="btn btn-success btn-sm" disabled>
                          <i class="ti ti-device-floppy"></i> Save all changes (<span id="changeCount">0</span>)
                        </button>
                        <button id="reloadBtn" class="btn btn-primary btn-sm">
                          <i class="ti ti-refresh"></i> Reload from Geotab
                        </button>
                      </div>
                    </div>

                    <div id="statusBox"></div>

                    <div class="table-responsive mt-2">
                      <table class="table text-nowrap align-middle fs-3 mb-0">
                        <thead>
                          <tr>
                            <th class="text-muted">Geotab Device</th>
                            <th class="text-muted">Serial</th>
                            <th class="text-muted">Plate (Geotab)</th>
                            <th class="text-muted">VIN</th>
                            <th class="text-muted">Linked Unit</th>
                            <th class="text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody id="deviceRows">
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
      let UNITS = [];

      function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
      }

      function unitOptions(selectedUnitId, deviceId) {
        // A unit already linked to a DIFFERENT device is disabled to avoid confusion.
        let opts = '<option value="">— not linked —</option>';
        UNITS.forEach(u => {
          const linkedElsewhere = u.geotab_device_id && u.geotab_device_id !== deviceId;
          const sel = (u.unit_id === selectedUnitId) ? ' selected' : '';
          const dis = linkedElsewhere ? ' disabled' : '';
          const tag = linkedElsewhere ? ' (linked)' : (u.unit_plate ? ' · ' + u.unit_plate : '');
          opts += `<option value="${u.unit_id}"${sel}${dis}>${esc(u.unit_name)}${esc(tag)}</option>`;
        });
        return opts;
      }

      function render(devices) {
        const $tb = $('#deviceRows').empty();
        if (!devices.length) {
          $tb.append('<tr><td colspan="6" class="text-center text-muted py-5">No Geotab devices returned.</td></tr>');
          return;
        }
        devices.forEach(d => {
          // Current selection defaults to the suggestion; the "initial" (saved)
          // state is the actual linked unit only, so a suggested row counts as a
          // pending change to be saved.
          const linkedUnitId = d.linked_unit ? d.linked_unit.unit_id : (d.suggested_unit_id || null);
          const initial = d.linked_unit ? String(d.linked_unit.unit_id) : '';
          const isSuggestion = !d.linked_unit && d.suggested_unit_id;
          const badge = d.linked_unit
            ? '<span class="badge bg-success">Linked</span>'
            : (isSuggestion ? '<span class="badge bg-warning text-dark">Suggested</span>' : '<span class="badge bg-secondary">Unlinked</span>');
          const $tr = $(`
            <tr data-device="${esc(d.device_id)}">
              <td><h6 class="mb-0 fw-bolder">${esc(d.name)}</h6>${badge}</td>
              <td>${esc(d.serial)}</td>
              <td>${esc(d.plate) || '<span class="text-muted">—</span>'}</td>
              <td>${esc(d.vin) || '<span class="text-muted">—</span>'}</td>
              <td><select class="form-select form-select-sm unit-select" data-initial="${esc(initial)}" style="min-width:220px">${unitOptions(linkedUnitId, d.device_id)}</select></td>
              <td class="text-end">
                <button class="btn btn-primary btn-sm save-link">Save</button>
              </td>
            </tr>`);
          $tr.find('.save-link').on('click', () => saveLink(d.device_id, $tr));
          $tr.find('.unit-select').on('change', refreshChangeState);
          $tb.append($tr);
        });
        refreshChangeState();
      }

      // Rows whose selection differs from the saved state.
      function changedRows() {
        const out = [];
        $('#deviceRows tr[data-device]').each(function () {
          const $sel = $(this).find('.unit-select');
          const cur = String($sel.val() || '');
          const init = String($sel.data('initial') || '');
          if (cur !== init) {
            out.push({ device_id: String($(this).data('device')), cur, init });
          }
        });
        return out;
      }

      function refreshChangeState() {
        const n = changedRows().length;
        $('#changeCount').text(n);
        $('#saveAllBtn').prop('disabled', n === 0);
        // Highlight changed rows.
        $('#deviceRows tr[data-device]').each(function () {
          const $sel = $(this).find('.unit-select');
          const changed = String($sel.val() || '') !== String($sel.data('initial') || '');
          $(this).toggleClass('table-warning', changed);
        });
      }

      function saveAll() {
        const rows = changedRows();
        if (!rows.length) return;
        // Build one payload entry per changed row:
        //  - selected a truck (cur set)       -> link that unit to this device
        //  - cleared to "not linked" (cur '') -> unlink the unit it was on (init)
        const payload = [];
        rows.forEach(r => {
          if (r.cur !== '') {
            payload.push({ unit_id: parseInt(r.cur, 10), device_id: r.device_id });
          } else if (r.init !== '') {
            payload.push({ unit_id: parseInt(r.init, 10), device_id: '' });
          }
        });
        if (!payload.length) return;
        $('#saveAllBtn').prop('disabled', true);
        $.post('php/crud/update/link_geotab_devices_bulk.php', { links: JSON.stringify(payload) })
          .done(res => {
            if (res.status === 'success') {
              let html = 'Linked ' + res.saved + (res.unlinked ? (', unlinked ' + res.unlinked) : '') + '.';
              if (res.skipped && res.skipped.length) {
                html += '<br><br><b>Skipped:</b><br>' + res.skipped.map(esc).join('<br>');
              }
              Swal.fire({ icon: res.skipped && res.skipped.length ? 'warning' : 'success', title: 'Saved', html });
              load();
            } else {
              Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unknown error' });
              refreshChangeState();
            }
          })
          .fail(xhr => {
            let msg = 'Request failed';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            Swal.fire({ icon: 'error', title: 'Failed', text: msg });
            refreshChangeState();
          });
      }

      function saveLink(deviceId, $tr) {
        const unitId = $tr.find('.unit-select').val();
        $tr.find('.save-link').prop('disabled', true);
        $.post('php/crud/update/link_geotab_device.php', { unit_id: unitId, device_id: unitId ? deviceId : '' })
          .done(res => {
            if (res.status === 'success') {
              Swal.fire({ icon: 'success', title: res.linked ? 'Linked' : 'Unlinked',
                text: res.vin ? ('VIN captured: ' + res.vin) : '', timer: 1600, showConfirmButton: false });
              load();
            } else {
              Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unknown error' });
              $tr.find('.save-link').prop('disabled', false);
            }
          })
          .fail(xhr => {
            let msg = 'Request failed';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            Swal.fire({ icon: 'error', title: 'Failed', text: msg });
            $tr.find('.save-link').prop('disabled', false);
          });
      }

      function load() {
        $('#statusBox').empty();
        $('#deviceRows').html('<tr><td colspan="6" class="text-center text-muted py-5">Loading…</td></tr>');
        $.getJSON('php/fetch/geotab_unmatched_devices.php')
          .done(res => {
            if (res.status !== 'success') {
              $('#statusBox').html('<div class="alert alert-danger">' + esc(res.message || 'Failed to load') + '</div>');
              $('#deviceRows').empty();
              return;
            }
            UNITS = res.units || [];
            render(res.devices || []);
          })
          .fail(xhr => {
            let msg = 'Could not reach Geotab. Check the connection on the Geotab Connection page.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            $('#statusBox').html('<div class="alert alert-danger">' + esc(msg) + '</div>');
            $('#deviceRows').empty();
          });
      }

      $('#reloadBtn').on('click', load);
      $('#saveAllBtn').on('click', saveAll);
      load();
    </script>
  </body>
  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
}
