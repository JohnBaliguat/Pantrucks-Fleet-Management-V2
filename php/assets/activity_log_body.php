<?php
// Admin Activity Log viewer. Required vars: $role ('admin').
// Merged timeline of activity_log (logins / high-value actions) + workflow_event
// (dispatch lifecycle), rendered with DataTables server-side processing via
// table-fetch/activity-log-table.php. Excel export posts to
// php/reports/activity-log-report.php.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Activity Log</title>
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
      <h3 class="text-white mb-0 fs-5">Activity Log</h3>
    </div>
    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3"><div class="card-body">
            <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
              <div>
                <h4 class="card-title mb-0">User Activity Log</h4>
                <p class="card-subtitle">Logins, dispatch actions, and system changes across all users.</p>
              </div>
              <div class="ms-auto">
                <button class="btn btn-success" id="btnExport"><i class="ti ti-file-spreadsheet"></i> Export to Excel</button>
              </div>
            </div>

            <!-- Custom filters (passed to the server-side endpoint). Text search
                 uses the DataTables search box. -->
            <div class="row g-2 mb-3">
              <div class="col-6 col-md-3">
                <label class="form-label small mb-1">From</label>
                <input type="date" class="form-control form-control-sm" id="fltFrom">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small mb-1">To</label>
                <input type="date" class="form-control form-control-sm" id="fltTo">
              </div>
              <div class="col-8 col-md-3">
                <label class="form-label small mb-1">Role</label>
                <select class="form-select form-select-sm" id="fltRole">
                  <option value="">All roles</option>
                  <option>Admin</option>
                  <option>Dispatcher</option>
                  <option>Dispatch Admin</option>
                  <option>Driver</option>
                  <option>Gate-Guard</option>
                  <option>Booker</option>
                  <option>Shop</option>
                  <option>Maintenance</option>
                  <option>HR-Admin</option>
                  <option>User</option>
                  <option>Client</option>
                  <option>Gastender</option>
                </select>
              </div>
              <div class="col-4 col-md-3 d-flex align-items-end">
                <button class="btn btn-primary btn-sm w-100" id="btnApply"><i class="ti ti-filter"></i> Apply</button>
              </div>
            </div>

            <div class="table-responsive">
              <table id="activityTable" class="table table-bordered table-sm align-middle" style="width:100%">
                <thead class="table-light"><tr>
                  <th style="white-space:nowrap;">Date/Time</th>
                  <th>User</th>
                  <th>Role</th>
                  <th>Action</th>
                  <th>Details</th>
                  <th>Booking</th>
                  <th>Source</th>
                </tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Hidden form used to POST the current filters to the Excel export. -->
  <form id="exportForm" action="php/reports/activity-log-report.php" method="post" target="_blank" class="d-none">
    <input type="hidden" name="from"   id="expFrom">
    <input type="hidden" name="to"     id="expTo">
    <input type="hidden" name="role"   id="expRole">
    <input type="hidden" name="search" id="expSearch">
  </form>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script src="datatable/datatables.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script>
    var activityTable = new DataTable('#activityTable', {
      processing: true,
      serverSide: true,
      order: [[0, 'desc']],
      pageLength: 50,
      lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
      ajax: {
        url: 'table-fetch/activity-log-table.php',
        type: 'POST',
        data: function (d) {
          // Append the custom filters to every server-side request.
          d.from = $('#fltFrom').val() || '';
          d.to   = $('#fltTo').val() || '';
          d.role = $('#fltRole').val() || '';
        }
      },
      columnDefs: [
        { targets: 6, orderable: false }  // Source badge
      ]
    });

    // Apply custom filters → reload the current page of data.
    $('#btnApply').on('click', function () { activityTable.ajax.reload(); });
    $('#fltRole').on('change', function () { activityTable.ajax.reload(); });

    // Export honours the active filters + the DataTables search term.
    $('#btnExport').on('click', function () {
      $('#expFrom').val($('#fltFrom').val() || '');
      $('#expTo').val($('#fltTo').val() || '');
      $('#expRole').val($('#fltRole').val() || '');
      $('#expSearch').val(activityTable.search() || '');
      $('#exportForm').trigger('submit');
    });
  </script>
</body>
</html>
