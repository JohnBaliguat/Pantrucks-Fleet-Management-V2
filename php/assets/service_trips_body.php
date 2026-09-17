<?php
// Service Trips list. Required vars: $role ('admin'|'dispatcher').
// Lists every service trip (non-booking dispatch) with a printable receipt.
require_once __DIR__ . '/../config/config.php';
$stLocationNames = [];
$r = $conn->query("SELECT location_name FROM location ORDER BY location_name ASC");
while ($r && ($x = $r->fetch())) { $stLocationNames[] = $x['location_name']; }
$stTrailerNames = [];
$r = $conn->query("SELECT trailer_name FROM trailer ORDER BY trailer_name ASC");
while ($r && ($x = $r->fetch())) { $stTrailerNames[] = $x['trailer_name']; }
$stGensetNames = [];
$r = $conn->query("SELECT unit_name FROM units WHERE unit_type = 'genset' ORDER BY unit_name ASC");
while ($r && ($x = $r->fetch())) { $stGensetNames[] = $x['unit_name']; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Service Trips</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Service Trips</h3>
    </div>
    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3"><div class="card-body">
            <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
              <div>
                <h4 class="card-title mb-0">Service Trips</h4>
                <p class="card-subtitle">Non-booking trips — repositioning, fuel runs, shop visits, trailer pickups, etc.</p>
              </div>
              <span class="ms-auto small text-muted" id="stMeta"></span>
            </div>

            <!-- Filters -->
            <div class="row g-2 mb-3">
              <div class="col-6 col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" class="form-control form-control-sm" id="stFrom">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" class="form-control form-control-sm" id="stTo">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select class="form-select form-select-sm" id="stStatus">
                  <option value="">All</option>
                  <option value="Active">Active</option>
                  <option value="Done">Done</option>
                </select>
              </div>
              <div class="col-12 col-md-3 d-flex align-items-end">
                <button class="btn btn-primary btn-sm" id="stApply"><i class="ti ti-filter"></i> Apply date / status</button>
              </div>
            </div>

            <div class="table-responsive">
              <table id="serviceTripsTable" class="table table-bordered table-sm align-middle" style="width:100%">
                <thead class="table-light"><tr>
                  <th>Date</th><th>Trip Receipt</th><th>Driver / Truck</th><th>Route</th>
                  <th>Reason</th><th>Equipment</th><th>Status</th><th></th>
                </tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Update Service Trip modal -->
  <div class="modal fade" id="stEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-edit"></i> Update Service Trip</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="seDispatchId">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Service Reason <span class="text-danger">*</span></label>
              <select class="form-select" id="seReason">
                <option value="Repositioning">Repositioning</option>
                <option value="Fuel Run">Fuel Run</option>
                <option value="Shop Visit">Shop Visit</option>
                <option value="Trailer Pickup">Trailer Pickup</option>
                <option value="Trailer Return">Trailer Return</option>
                <option value="Genset Pickup">Genset Pickup</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Container No</label>
              <input type="text" class="form-control text-uppercase" id="seContainer" maxlength="20" autocomplete="off" placeholder="Optional">
            </div>
            <div class="col-md-6">
              <label class="form-label">From <span class="text-danger">*</span></label>
              <input class="form-control" list="seLocations" id="seFrom" autocomplete="off" placeholder="Origin">
            </div>
            <div class="col-md-6">
              <label class="form-label">To <span class="text-danger">*</span></label>
              <input class="form-control" list="seLocations" id="seTo" autocomplete="off" placeholder="Destination">
            </div>
            <datalist id="seLocations">
              <?php foreach ($stLocationNames as $ln): ?><option value="<?php echo htmlspecialchars($ln); ?>"><?php endforeach; ?>
            </datalist>
            <div class="col-md-6">
              <label class="form-label">Trailer</label>
              <input class="form-control" list="seTrailers" id="seTrailer" autocomplete="off" placeholder="-- None --">
              <datalist id="seTrailers">
                <?php foreach ($stTrailerNames as $tn): ?><option value="<?php echo htmlspecialchars($tn); ?>"><?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-6">
              <label class="form-label">Genset</label>
              <input class="form-control" list="seGensets" id="seGenset" autocomplete="off" placeholder="-- None --">
              <datalist id="seGensets">
                <?php foreach ($stGensetNames as $gn): ?><option value="<?php echo htmlspecialchars($gn); ?>"><?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-12">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" id="seRemarks" rows="2" maxlength="255" placeholder="Optional notes"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="seSubmit"><i class="ti ti-device-floppy"></i> Save Changes</button>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script src="datatable/datatables.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script>
    var serviceTable = new DataTable('#serviceTripsTable', {
      processing: true,
      serverSide: true,
      order: [[0, 'desc']],
      pageLength: 25,
      lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
      ajax: {
        url: 'table-fetch/service-trips-table.php',
        type: 'POST',
        data: function (d) {
          d.from   = $('#stFrom').val() || '';
          d.to     = $('#stTo').val() || '';
          d.status = $('#stStatus').val() || '';
        }
      },
      columnDefs: [
        { targets: [5, 7], orderable: false }  // Equipment, action
      ],
      drawCallback: function (settings) {
        $('#stMeta').text((settings._iRecordsTotal || 0) + ' service trip(s)');
      }
    });

    // Custom filters reload the server-side data; the search box is DataTables'.
    $('#stApply').on('click', function () { serviceTable.ajax.reload(); });
    $('#stStatus').on('change', function () { serviceTable.ajax.reload(); });

    // Open the Update modal pre-filled from the row's data attributes.
    $(document).on('click', '.st-edit', function () {
      var $b = $(this);
      $('#seDispatchId').val($b.data('d-id'));
      $('#seReason').val(String($b.data('reason') || 'Repositioning'));
      $('#seContainer').val(String($b.data('container') || ''));
      $('#seFrom').val(String($b.data('from') || ''));
      $('#seTo').val(String($b.data('to') || ''));
      $('#seTrailer').val(String($b.data('trailer') || ''));
      $('#seGenset').val(String($b.data('genset') || ''));
      $('#seRemarks').val(String($b.data('remarks') || ''));
      $('#stEditModal').modal('show');
    });

    $('#seSubmit').on('click', function () {
      var from = $('#seFrom').val().trim();
      var to = $('#seTo').val().trim();
      var reason = $('#seReason').val();
      if (!from) { Swal.fire({ icon: 'warning', text: 'From is required.' }); return; }
      if (!to)   { Swal.fire({ icon: 'warning', text: 'To is required.' }); return; }
      if (!reason){ Swal.fire({ icon: 'warning', text: 'Service Reason is required.' }); return; }
      $('#seSubmit').prop('disabled', true);
      $.post('php/operations/update_service_trip.php', {
        d_id: $('#seDispatchId').val(),
        trip_from: from, trip_to: to,
        service_reason: reason,
        service_remarks: $('#seRemarks').val().trim(),
        container: String($('#seContainer').val() || '').trim().toUpperCase(),
        trailer: $('#seTrailer').val().trim(),
        genset: $('#seGenset').val().trim()
      }, null, 'json')
        .done(function (res) {
          if (res && res.status === 'success') {
            $('#stEditModal').modal('hide');
            Swal.fire({ icon: 'success', text: res.message || 'Updated.', timer: 1300, showConfirmButton: false });
            serviceTable.ajax.reload(null, false);
          } else {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Update failed.' });
          }
        })
        .fail(function () { Swal.fire({ icon: 'error', text: 'Network error.' }); })
        .always(function () { $('#seSubmit').prop('disabled', false); });
    });

    // Mark a service trip as Done.
    $(document).on('click', '.st-done', function () {
      var dId = $(this).data('d-id');
      Swal.fire({
        icon: 'question', title: 'Mark this service trip as Done?',
        showCancelButton: true, confirmButtonText: 'Yes, mark done', confirmButtonColor: '#16a34a'
      }).then(function (r) {
        if (!r.isConfirmed) return;
        $.post('php/operations/complete_service_trip.php', { d_id: dId }, null, 'json')
          .done(function (res) {
            if (res && res.status === 'success') {
              Swal.fire({ icon: 'success', text: res.message || 'Done.', timer: 1300, showConfirmButton: false });
              serviceTable.ajax.reload(null, false);
            } else {
              Swal.fire({ icon: 'error', text: (res && res.message) || 'Failed.' });
            }
          })
          .fail(function () { Swal.fire({ icon: 'error', text: 'Network error.' }); });
      });
    });
  </script>
</body>
</html>
