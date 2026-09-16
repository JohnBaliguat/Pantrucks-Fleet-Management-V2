<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Location Coordinates</title>
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
                    <div class="d-md-flex align-items-center mb-2">
                      <div>
                        <h4 class="card-title">Location Coordinates</h4>
                        <p class="card-subtitle">Trip origins/destinations with no map coordinates. Fill these in so routes and arrival detection work.</p>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0 d-flex gap-2">
                        <button id="saveAllBtn" class="btn btn-success btn-sm" disabled>
                          <i class="ti ti-device-floppy"></i> Save all filled (<span id="fillCount">0</span>)
                        </button>
                        <button id="reloadBtn" class="btn btn-outline-primary btn-sm"><i class="ti ti-refresh"></i> Reload</button>
                      </div>
                    </div>
                    <div id="summary" class="text-muted mb-2" style="font-size:13px"></div>
                    <div id="statusBox"></div>
                    <div class="table-responsive mt-2">
                      <table class="table text-nowrap align-middle fs-3 mb-0">
                        <thead>
                          <tr>
                            <th class="text-muted">Location</th>
                            <th class="text-muted">Used by</th>
                            <th class="text-muted">Status</th>
                            <th class="text-muted">Latitude</th>
                            <th class="text-muted">Longitude</th>
                            <th class="text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody id="locRows">
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
      function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

      function refreshFill() {
        let n = 0;
        $('#locRows tr[data-name]').each(function () {
          const lat = $(this).find('.lat').val().trim(), lng = $(this).find('.lng').val().trim();
          if (lat !== '' && lng !== '') n++;
        });
        $('#fillCount').text(n);
        $('#saveAllBtn').prop('disabled', n === 0);
      }

      function render(missing) {
        const $tb = $('#locRows').empty();
        if (!missing.length) {
          $tb.append('<tr><td colspan="6" class="text-center text-success py-5"><i class="ti ti-circle-check"></i> Every trip location has coordinates.</td></tr>');
          refreshFill();
          return;
        }
        missing.forEach(m => {
          const status = m.has_row
            ? '<span class="badge bg-warning text-dark">Blank coords</span>'
            : '<span class="badge bg-secondary">No location row</span>';
          const gmaps = 'https://www.google.com/maps/search/' + encodeURIComponent(m.name);
          const $tr = $(`
            <tr data-name="${esc(m.name)}">
              <td class="fw-bolder">${esc(m.name)}
                  <a href="${gmaps}" target="_blank" rel="noopener" class="ms-1" title="Find on Google Maps"><i class="ti ti-external-link"></i></a></td>
              <td>${m.uses}</td>
              <td>${status}</td>
              <td><input type="text" class="form-control form-control-sm lat" style="width:130px" placeholder="7.1907"></td>
              <td><input type="text" class="form-control form-control-sm lng" style="width:130px" placeholder="125.4553"></td>
              <td class="text-end"><button class="btn btn-primary btn-sm save-one">Save</button></td>
            </tr>`);
          // Paste "lat, lng" into the latitude box to auto-split.
          $tr.find('.lat').on('paste', function (e) {
            const txt = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
            const m2 = txt.match(/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/);
            if (m2) { e.preventDefault(); $(this).val(m2[1]); $tr.find('.lng').val(m2[2]); refreshFill(); }
          });
          $tr.find('.lat, .lng').on('input', refreshFill);
          $tr.find('.save-one').on('click', () => saveOne(m.name, $tr));
          $tb.append($tr);
        });
        refreshFill();
      }

      function saveOne(name, $tr) {
        const lat = $tr.find('.lat').val().trim(), lng = $tr.find('.lng').val().trim();
        if (lat === '' || lng === '') { Swal.fire({ icon: 'info', title: 'Enter both lat and lng' }); return; }
        $tr.find('.save-one').prop('disabled', true);
        $.post('php/crud/update/save_location_coords.php', { name, lat, lng })
          .done(res => {
            if (res.status === 'success' && res.saved) { Swal.fire({ icon: 'success', title: 'Saved', timer: 1000, showConfirmButton: false }); $tr.fadeOut(200, () => { $tr.remove(); refreshFill(); }); }
            else { Swal.fire({ icon: 'error', title: 'Failed', text: (res.skipped && res.skipped[0]) || res.message || 'Unknown error' }); $tr.find('.save-one').prop('disabled', false); }
          })
          .fail(() => { Swal.fire({ icon: 'error', title: 'Failed' }); $tr.find('.save-one').prop('disabled', false); });
      }

      function saveAll() {
        const rows = [];
        $('#locRows tr[data-name]').each(function () {
          const lat = $(this).find('.lat').val().trim(), lng = $(this).find('.lng').val().trim();
          if (lat !== '' && lng !== '') rows.push({ name: $(this).data('name'), lat, lng });
        });
        if (!rows.length) return;
        $('#saveAllBtn').prop('disabled', true);
        $.post('php/crud/update/save_location_coords.php', { rows: JSON.stringify(rows) })
          .done(res => {
            if (res.status === 'success') {
              let html = 'Saved ' + res.saved + ' location' + (res.saved === 1 ? '' : 's') + '.';
              if (res.skipped && res.skipped.length) html += '<br><br><b>Skipped:</b><br>' + res.skipped.map(esc).join('<br>');
              Swal.fire({ icon: res.skipped && res.skipped.length ? 'warning' : 'success', title: 'Saved', html });
              load();
            } else { Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unknown error' }); refreshFill(); }
          })
          .fail(() => { Swal.fire({ icon: 'error', title: 'Failed' }); refreshFill(); });
      }

      function load() {
        $('#locRows').html('<tr><td colspan="6" class="text-center text-muted py-5">Loading…</td></tr>');
        $.getJSON('php/fetch/locations_missing_coords.php')
          .done(res => {
            if (res.status !== 'success') { $('#statusBox').html('<div class="alert alert-danger">' + esc(res.message || 'Failed') + '</div>'); $('#locRows').empty(); return; }
            $('#summary').text(res.missing_count + ' location(s) need coordinates · ' + res.ok_count + ' already mapped');
            render(res.missing || []);
          })
          .fail(() => { $('#statusBox').html('<div class="alert alert-danger">Could not load locations.</div>'); $('#locRows').empty(); });
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
