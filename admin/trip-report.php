<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trip Report</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="datatable/datatables.min.css">
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
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
            <!--  Row 1 -->
            <div class="row">
              <div class="col-lg-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Generate Report</h4>
                      </div>
                    </div>
                    <form id="filterForm" class="row g-3 align-items-center mb-3" method="POST" action="php/reports/trip-report.php" target="_blank" class="Excel">
                      <div class="col-auto">
                        <label for="customer" class="col-form-label">Customer</label>
                      </div>
                      <div class="col-auto">
                        <select class="form-select" aria-label="Customer select menu" id="customer" name="customer">
                          <option value="">All Customers</option>
                          <option value="ABC">ABC</option>
                          <option value="CTH">CTH</option>
                          <option value="DPC">DPC</option>
                          <option value="DM">DM</option>
                          <option value="DICT">DICT</option>
                          <option value="DOLE">DOLE</option>
                          <option value="FARM">FARM</option>
                          <option value="GF">GF</option>
                          <option value="SUMI">SUMI</option>
                          <option value="TDC">TDC</option>
                        </select>
                      </div>
                      <div class="col-auto">
                        <label for="fromDate" class="col-form-label">From</label>
                      </div>
                      <div class="col-auto">
                        <input type="datetime-local" id="fromDate" name="fromDate" class="form-control">
                      </div>
                      <div class="col-auto">
                        <label for="toDate" class="col-form-label">To</label>
                      </div>
                      <div class="col-auto">
                        <input type="datetime-local" id="toDate" name="toDate" class="form-control">
                      </div>
                      <div class="col-auto">
                        <button type="button" id="submit" class="btn btn-primary">Generate</button>
                      </div>
                      <div class="col-auto">
                        <button type="submit" class="btn btn-success">Export</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <div class="col-lg-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Trip Report</h4>
                      </div>
                    </div>
                    <div class="table-responsive mt-4" style="overflow: hidden;">
                      <table id="table-data" class="table mb-0 text-nowrap varient-table align-middle fs-3">
                        <thead>
                          <tr>
                            <th class="px-0 text-muted"></th>
                            <th class="px-0 text-muted">Assigned</th>
                            <th class="px-0 text-muted text-center">Date</th>
                            <th class="px-0 text-muted">Dispatch Hub</th>
                            <th class="px-0 text-muted text-center">1st Trip</th>
                            <th class="px-0 text-muted text-center">2nd Trip</th>
                            <th class="px-0 text-muted text-center">Hauling Segment</th>
                            <th class="px-0 text-muted text-center">Hauling Type</th>
                            <th class="px-0 text-muted text-center">POD</th>
                            <th class="px-0 text-muted">Time Dispatched</th>
                            <th class="px-0 text-muted">Dispatched By</th>
                            <th class="px-0 text-muted text-center">Action</th>
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
    <script src="assets/js/dashboard.js"></script>
    <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script src="datatable/datatables.min.js"></script>

    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <!-- Trip Report Edit Modal -->
    <div class="modal fade" id="tripReportEditModal" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Trip Report</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="tre_d_id">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Date / Time</label>
                <input type="datetime-local" class="form-control" id="tre_d_datetime">
              </div>
              <div class="col-md-6">
                <label class="form-label">Dispatch Hub</label>
                <input type="text" class="form-control" id="tre_d_dispatchhub">
              </div>
            </div>
            <hr>
            <h6 class="text-muted mb-2">Trip 1</h6>
            <div class="row g-2 mb-3" id="tre_trip1_block">
              <input type="hidden" id="tre_trip1_id">
              <div class="col-md-6"><label class="form-label small">From</label><input class="form-control form-control-sm" id="tre_trip1_from"></div>
              <div class="col-md-6"><label class="form-label small">To</label><input class="form-control form-control-sm" id="tre_trip1_to"></div>
              <div class="col-md-4"><label class="form-label small">Container No</label><input class="form-control form-control-sm" id="tre_trip1_container"></div>
              <div class="col-md-4"><label class="form-label small">Container Status</label><input class="form-control form-control-sm" id="tre_trip1_containerstat"></div>
              <div class="col-md-4"><label class="form-label small">Trip Status</label><input class="form-control form-control-sm" id="tre_trip1_status"></div>
              <div class="col-md-6"><label class="form-label small">Hauling Segment</label><input class="form-control form-control-sm" id="tre_trip1_segment"></div>
              <div class="col-md-6"><label class="form-label small">Hauling Type</label><input class="form-control form-control-sm" id="tre_trip1_type"></div>
            </div>
            <hr>
            <h6 class="text-muted mb-2">Trip 2 <small class="text-muted">(leave empty if not applicable)</small></h6>
            <div class="row g-2 mb-2" id="tre_trip2_block">
              <input type="hidden" id="tre_trip2_id">
              <div class="col-md-6"><label class="form-label small">From</label><input class="form-control form-control-sm" id="tre_trip2_from"></div>
              <div class="col-md-6"><label class="form-label small">To</label><input class="form-control form-control-sm" id="tre_trip2_to"></div>
              <div class="col-md-4"><label class="form-label small">Container No</label><input class="form-control form-control-sm" id="tre_trip2_container"></div>
              <div class="col-md-4"><label class="form-label small">Container Status</label><input class="form-control form-control-sm" id="tre_trip2_containerstat"></div>
              <div class="col-md-4"><label class="form-label small">Trip Status</label><input class="form-control form-control-sm" id="tre_trip2_status"></div>
              <div class="col-md-6"><label class="form-label small">Hauling Segment</label><input class="form-control form-control-sm" id="tre_trip2_segment"></div>
              <div class="col-md-6"><label class="form-label small">Hauling Type</label><input class="form-control form-control-sm" id="tre_trip2_type"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="tre_save_btn">Save Changes</button>
          </div>
        </div>
      </div>
    </div>

    <script src="js/trip-report.js"></script>
  </body>

  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
} ?>
