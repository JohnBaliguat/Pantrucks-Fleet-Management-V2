<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Visual") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Truck Report</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="datatable/datatables.min.css">
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
  .truck-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    transition: 0.2s;
  }

  .truck-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.12);
  }


  .truck-count {
    font-size: 34px;
    font-weight: 800;
    color: #007bff;
  }

  .truck-sub {
    font-size: 20px;
    color: #666;
  }

  .used {
    color: #28a745;
    font-weight: 700;
  }

  .unused {
    color: #dc3545;
    font-weight: 700;
  }
</style>
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
                    <form id="filterForm" class="row g-3 align-items-center mb-3" method="POST" action="php/reports/truck-report.php" target="_blank" class="Excel">
                    <div class="col-auto">
                        <label for="truck" class="col-form-label">Truck</label>
                      </div>
                      <div class="col-auto position-relative">
                        <input type="text" id="truck" name="truck" class="form-control" placeholder="Select Truck">
                        <ul id="truckList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                      </div>  
                    <div class="col-auto">
                        <label for="customer" class="col-form-label">Customer</label>
                      </div>
                      <div class="col-auto">
                        <select class="form-select" aria-label="Customer select menu" id="customer" name="customer">
                          <option selected disabled>Select Customer</option>
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
              <!-- Total Trips -->
              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-truck fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Total Trips</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="totalTrips" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Total Hours -->
              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-clock fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Total Hours</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="totalHours" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Average Hours -->
              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-chart-line fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Average Hours</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="avgHours" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-4">
                <div class="row">
                  <div class="col-md-6">
                    <div class="truck-card">

                      <div class="d-flex justify-content-between">

                        <!-- Used Trucks -->
                        <div>
                          <div class="truck-count" id="usedCount">0</div>
                          <div class="truck-sub used">Used Trucks</div>
                        </div>
                      </div>
                    </div>

                  </div>
                  <div class="col-md-6">
                    <div class="truck-card">

                      <div class="d-flex justify-content-between">

                        <!-- Unused Trucks -->
                        <div>
                          <div class="truck-count" style="color:#dc3545;" id="unusedCount">0</div>
                          <div class="truck-sub unused">Unused Trucks</div>
                        </div>

                      </div>
                    </div>

                  </div>

                </div>

                <div class="row mt-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-body">
                        <div class="d-md-flex align-items-center">
                          <div>
                            <h4 class="card-title">Unused Truck List</h4>
                          </div>
                        </div>

                        <table id="truckTable" class="table table-bordered table-striped mt-3">
                          <thead>
                            <tr>
                              <th>Unit Name</th>
                              <th>Status</th>
                            </tr>
                          </thead>
                          <tbody></tbody>
                        </table>

                      </div>
                    </div>
                  </div>
                </div>
                
              </div>
              
              <!-- <div class="col-4">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Truck Transaction</h4>
                        <p class="card-subtitle">
                          List of Trucks and their total transaction
                        </p>
                      </div>
                    </div>
                    <div id="chart-container-trucks" style="height: 685px; overflow-y: auto;">
                      <div id="Truckchart" style="width: 96%;"></div>
                    </div>

                  </div>
                </div>
              </div> -->
              <div class="col-lg-8">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Trucks Trasaction Report</h4>
                      </div>
                    </div>
                    <div class="table-responsive mt-4" style="overflow: hidden;">
                      <table id="table-data" class="table mb-0 text-nowrap varient-table align-middle fs-3">
                        <thead>
                          <tr>
                            <th class="px-0 text-muted">Trucks</th>
                            <th class="px-0 text-muted">Assigned Driver</th>
                            <th class="px-0 text-muted text-center">Date</th>
                            <th class="px-0 text-muted">Start Location</th>
                            <th class="px-0 text-muted">Last Location</th>
                            <th class="px-0 text-muted text-center">Hauling Segment</th>
                            <th class="px-0 text-muted text-center">Hauling Type</th>
                            <th class="px-0 text-muted">Time Dispatched</th>
                            <th class="px-0 text-muted">Dispatched By</th>
                            <th class="px-0 text-muted">Dispatch Hub</th>
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
    <script src="js/truck-report.js"></script>
  </body>

  </html>
<?php
} else {
  header("Location: visual-index.php?route=login");
  exit();
} ?>
