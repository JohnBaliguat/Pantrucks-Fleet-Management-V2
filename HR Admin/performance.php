<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "HR-Admin") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Performance</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="datatable/datatables.min.css" />

  </head>

  <body>
    <!--  Body Wrapper -->
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
      data-sidebar-position="fixed" data-header-position="fixed">

      <!--  App Topstrip -->
      <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
          <a class="d-flex justify-content-center" href="#">
            <img src="assets/images/logos/pantrucks.png" alt="" width="122">
          </a>
        </div>

        <div class="d-lg-flex align-items-center gap-2">
          <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3>
          <div class="d-flex align-items-center justify-content-center gap-2">
          </div>
        </div>

      </div>
      <!-- Sidebar Start -->
      <?php include 'sidebar.php'; ?>
      <!--  Sidebar End -->
      <!--  Main wrapper -->
      <div class="body-wrapper">
        <!--  Header Start -->
        <?php include 'navbar.php'; ?>
        <!--  Header End -->
        <div class="body-wrapper-inner">
            <div class="container-fluid">
                  <div class="row">
                    <div class="col-lg-12">
                      <div class="card w-100">
                        <div class="card-body">
                          <div class="d-md-flex align-items-center">
                            <div>
                              <h4 class="card-title">Driver Performance</h4>
                              <p class="card-subtitle">
                                Review trips and attendance statistics for each driver. Use the filters below to narrow the results.
                              </p>
                            </div>
                          </div>
                          
                          <!-- BODY -->
                          <div class="row mt-3 g-3">
                            <div class="col-12">
                              <div class="row g-3 mb-3" id="perfSummary">
                                <div class="col-md-3">
                                  <div class="card border-0 bg-success-subtle h-100">
                                    <div class="card-body">
                                      <div class="text-success text-uppercase small fw-semibold">Top Performer</div>
                                      <div class="fs-4 fw-bold" id="sumTopPerformer">0</div>
                                    </div>
                                  </div>
                                </div>
                                <div class="col-md-3">
                                  <div class="card border-0 bg-warning-subtle h-100">
                                    <div class="card-body">
                                      <div class="text-warning-emphasis text-uppercase small fw-semibold">Needs Coaching</div>
                                      <div class="fs-4 fw-bold" id="sumNeedsCoaching">0</div>
                                    </div>
                                  </div>
                                </div>
                                <div class="col-md-3">
                                  <div class="card border-0 bg-danger-subtle h-100">
                                    <div class="card-body">
                                      <div class="text-danger text-uppercase small fw-semibold">Critical Review</div>
                                      <div class="fs-4 fw-bold" id="sumCriticalReview">0</div>
                                    </div>
                                  </div>
                                </div>
                                <div class="col-md-3">
                                  <div class="card border-0 bg-secondary-subtle h-100">
                                    <div class="card-body">
                                      <div class="text-secondary text-uppercase small fw-semibold">With Active Violation</div>
                                      <div class="fs-4 fw-bold" id="sumActiveViolations">0</div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <div class="row gx-2 gy-2 mb-3">
                                <div class="col-md-2">
                                  <label for="fromDate" class="form-label">From</label>
                                  <input type="date" id="fromDate" class="form-control" />
                                </div>
                                <div class="col-md-2">
                                  <label for="toDate" class="form-label">To</label>
                                  <input type="date" id="toDate" class="form-control" />
                                </div>
                                <div class="col-md-3">
                                  <label for="driverFilter" class="form-label">Driver</label>
                                  <select id="driverFilter" class="form-control">
                                    <option value="">All Drivers</option>
                                    <?php
                                    // load list of drivers for filtering
                                    include 'php/config/config.php';
                                    $drvQ = "SELECT driver_id, driver_fname, driver_lname FROM drivers ORDER BY driver_lname, driver_fname";
                                    $drvRes = $conn->query($drvQ);
                                    while ($d = ($drvRes)->fetch()) {
                                        $name = htmlspecialchars($d['driver_fname'] . ' ' . $d['driver_lname']);
                                        echo "<option value='" . $d['driver_id'] . "'>$name</option>";
                                    }
                                    ?>
                                  </select>
                                </div>
                                <div class="col-md-3">
                                  <label for="segmentFilter" class="form-label">Segment</label>
                                  <select id="segmentFilter" class="form-control">
                                    <option value="">All Segments</option>
                                    <?php
                                    // load distinct hauling segments from drivers table
                                    include 'php/config/config.php';
                                    $segQ = "SELECT DISTINCT driver_assignsegment FROM drivers ORDER BY driver_assignsegment";
                                    $segRes = $conn->query($segQ);
                                    while ($s = ($segRes)->fetch()) {
                                        $segName = htmlspecialchars($s['driver_assignsegment']);
                                        if ($segName !== '') {
                                            echo "<option value='$segName'>$segName</option>";
                                        }
                                    }
                                    ?>
                                  </select>
                                </div>
                                <div class="col-md-2 align-self-end">
                                  <button id="applyFilter" class="btn btn-primary w-100">Apply</button>
                                </div>
                              </div>
                              <div class="table-responsive">
                                <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="performance-table">
                                  <thead>
                                    <tr>
                                      <th class="px-0 text-muted">ID</th>
                                      <th class="px-0 text-muted">Driver Name</th>
                                      <th class="px-0 text-muted">Segment</th>
                                      <th class="px-0 text-muted">Total Trips</th>
                                      <th class="px-0 text-muted">Done</th>
                                      <th class="px-0 text-muted">Pending</th>
                                      <th class="px-0 text-muted">Days Present</th>
                                      <th class="px-0 text-muted">Days Absent</th>
                                      <th class="px-0 text-muted">Days Leave</th>
                                      <th class="px-0 text-muted">Active Violations</th>
                                      <th class="px-0 text-muted">Efficiency %</th>
                                      <th class="px-0 text-muted">Present %</th>
                                      <th class="px-0 text-muted">Recommendation</th>
                                    </tr>
                                  </thead>
                                  <tbody></tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                      </div>
                    </div>
                  </div>
            
            <!-- Trip Details Modal -->
            <div class="modal fade" id="tripDetailsModal" tabindex="-1" aria-labelledby="tripDetailsLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="tripDetailsLabel">Trip Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <!-- Segment Summary -->
                    <div class="mb-3">
                      <h6 class="mb-2">Trips by Segment</h6>
                      <div id="segmentSummary" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <!-- Trips Table with Scrollbar -->
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                      <table class="table table-hover" id="tripDetailsTable">
                        <thead style="position: sticky; top: 0; z-index: 10;">
                          <tr>
                            <th>Trip ID</th>
                            <th>Unit</th>
                            <th>Customer</th>
                            <th>Hauling Segment</th>
                            <th>Status</th>
                            <th>Date</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
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
    <script src="assets/libs/apexcharts/dist/apexcharts.min.js"></script>
    <script src="assets/libs/simplebar/dist/simplebar.js"></script>
   
    
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="datatable/datatables.min.js"></script>
  <script>
    $(document).ready(function() {
      // activate nav if necessary
      $("#Mnav").attr({ "class": "nav-link dropdown-toggle active" });

      function renderSummary(rows) {
        var summary = {
          top: 0,
          coaching: 0,
          critical: 0,
          violations: 0
        };

        rows.forEach(function(row) {
          var recommendation = String(row.recommendation || '');
          var activeViolations = parseInt(row.active_violations || 0, 10);
          if (recommendation === 'Top Performer') summary.top++;
          if (recommendation === 'Needs Coaching') summary.coaching++;
          if (recommendation === 'Critical Review') summary.critical++;
          if (activeViolations > 0) summary.violations++;
        });

        $('#sumTopPerformer').text(summary.top);
        $('#sumNeedsCoaching').text(summary.coaching);
        $('#sumCriticalReview').text(summary.critical);
        $('#sumActiveViolations').text(summary.violations);
      }

      var table = $('#performance-table').DataTable({
        "ajax": {
          "url": "table-fetch/performance-table-hradmin.php",
          "type": "POST",
          "data": function(d) {
            d.fromDate = $('#fromDate').val();
            d.toDate = $('#toDate').val();
            d.segmentFilter = $('#segmentFilter').val();
            d.driverFilter = $('#driverFilter').val();
          },
          "dataSrc": function(json) {
            var rows = Array.isArray(json.data) ? json.data : [];
            renderSummary(rows);
            return rows;
          }
        },
        "columnDefs": [
          {
            "targets": 1,
            "render": function(data, type, row) {
              if (type === 'display') {
                var url = 'index.php?route=hra-performanceDriver&driver_id=' + encodeURIComponent(row.driver_id || '')
                  + '&fromDate=' + encodeURIComponent($('#fromDate').val() || '')
                  + '&toDate=' + encodeURIComponent($('#toDate').val() || '');
                return '<a href="' + url + '" class="fw-semibold text-primary text-decoration-none">' + data + '</a>';
              }
              return data;
            }
          },
          {
            "targets": 3,
            "render": function(data, type, row) {
              if (type === 'display') {
                return '<a href="#" class="trip-link" data-driver-id="' + row.driver_id + '" data-driver-name="' + row.driver_name + '">' + data + '</a>';
              }
              return data;
            }
          },
          {
            "targets": 10,
            "render": function(data, type, row) {
              if (type === 'display') {
                var percent = parseFloat(data);
                var color = 'success';
                if (percent < 50) color = 'danger';
                else if (percent < 75) color = 'warning';
                return '<div class="d-flex align-items-center gap-2">' +
                  '<div class="flex-grow-1">' +
                  '<div class="progress" style="height: 20px;">' +
                  '<div class="progress-bar bg-' + color + '" role="progressbar" style="width: ' + percent + '%;" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100"></div>' +
                  '</div>' +
                  '</div>' +
                  '<small class="text-nowrap fw-bold">' + data + '%</small>' +
                  '</div>';
              }
              return data;
            }
          },
          {
            "targets": 11,
            "render": function(data, type, row) {
              if (type === 'display') {
                var percent = parseFloat(data);
                var color = 'success';
                if (percent < 50) color = 'danger';
                else if (percent < 75) color = 'warning';
                return '<div class="d-flex align-items-center gap-2">' +
                  '<div class="flex-grow-1">' +
                  '<div class="progress" style="height: 20px;">' +
                  '<div class="progress-bar bg-' + color + '" role="progressbar" style="width: ' + percent + '%;" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100"></div>' +
                  '</div>' +
                  '</div>' +
                  '<small class="text-nowrap fw-bold">' + data + '%</small>' +
                  '</div>';
              }
              return data;
            }
          },
          {
            "targets": 12,
            "render": function(data, type, row) {
              if (type === 'display') {
                var cls = 'secondary';
                if (data === 'Top Performer') cls = 'success';
                else if (data === 'Needs Coaching') cls = 'warning';
                else if (data === 'Critical Review') cls = 'danger';
                else if (data === 'No Activity') cls = 'secondary';
                return '<span class="badge bg-' + cls + '">' + data + '</span>';
              }
              return data;
            }
          }
        ],
        "columns": [
          { data: 'driver_id' },
          { data: 'driver_name' },
          { data: 'segment' },
          { data: 'total_trips' },
          { data: 'trips_done' },
          { data: 'trips_pending' },
          { data: 'days_present' },
          { data: 'days_absent' },
          { data: 'days_leave' },
          { data: 'active_violations' },
          { data: 'efficiency_percent' },
          { data: 'percent_present' },
          { data: 'recommendation' }
        ],
        "order": [[1, 'asc']],
        "responsive": true,
        "autoWidth": false
      });

      // Handle trip link click
      $(document).on('click', '.trip-link', function(e) {
        e.preventDefault();
        var driverId = $(this).data('driver-id');
        var driverName = $(this).data('driver-name');
        var fromDate = $('#fromDate').val();
        var toDate = $('#toDate').val();
        
        $('#tripDetailsLabel').text('Trip Details - ' + driverName);
        $('#segmentSummary').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
        $('#tripDetailsTable tbody').html('<tr><td colspan="6" class="text-center">Loading...</td></tr>');
        
        $.ajax({
          url: 'table-fetch/trip-details.php',
          type: 'POST',
          data: {
            driverId: driverId,
            fromDate: fromDate,
            toDate: toDate
          },
          success: function(response) {
            var data = JSON.parse(response);
            var trips = data.trips;
            var segmentSummary = data.segment_summary;
            var html = '';
            
            // Display segment summary
            var summaryHtml = '';
            if (segmentSummary.length === 0) {
              summaryHtml = '<span class="badge bg-secondary">No segments</span>';
            } else {
              segmentSummary.forEach(function(seg) {
                summaryHtml += '<span class="badge bg-info">' + seg.segment + ': ' + seg.count + '</span>';
              });
            }
            $('#segmentSummary').html(summaryHtml);
            
            // Display trips
            if (trips.length === 0) {
              html = '<tr><td colspan="6" class="text-center">No trips found</td></tr>';
            } else {
              trips.forEach(function(trip) {
                html += '<tr>' +
                  '<td>' + trip.trip_id + '</td>' +
                  '<td>' + trip.unit + '</td>' +
                  '<td>' + trip.customer + '</td>' +
                  '<td>' + trip.segment + '</td>' +
                  '<td>' + trip.status + '</td>' +
                  '<td>' + trip.date + '</td>' +
                  '</tr>';
              });
            }
            $('#tripDetailsTable tbody').html(html);
            $('#tripDetailsModal').modal('show');
          },
          error: function() {
            $('#segmentSummary').html('');
            $('#tripDetailsTable tbody').html('<tr><td colspan="6" class="text-center text-danger">Error loading trips</td></tr>');
            $('#tripDetailsModal').modal('show');
          }
        });
      });

      $('#applyFilter').on('click', function() {
        table.ajax.reload();
      });
    });
  </script>
  </body>

  </html>
<?php
} else {
  header("Location: hr-index.php?route=login");
  exit();
} ?>
