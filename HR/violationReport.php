<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "User") {


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
                    <form id="filterForm" class="row g-3 align-items-center mb-3" method="POST" action="php/reports/violation-report.php" target="_blank">
                    <div class="col-auto">
                        <label class="col-form-label">Violation</label>
                    </div>

                    <div class="col-auto">
                        <select class="form-select" id="violation" name="violation">
                        <option value="">-- Select Violation --</option>
                        <option value="Low Performer">Low Performer</option>
                        <option value="Over Speeding">Over Speeding</option>
                        <option value="Illegal Parking">Illegal Parking</option>
                        <option value="Excessive Idling">Excessive Idling</option>
                        <option value="Reckless Driving">Reckless Driving</option>
                        <option value="High Gas">High Gas</option>
                    </select>
                    </div>

                    <div class="col-auto">
                        <label class="col-form-label">From</label>
                    </div>
                    <div class="col-auto">
                        <input type="date" id="fromDate" class="form-control">
                    </div>

                    <div class="col-auto">
                        <label class="col-form-label">To</label>
                    </div>
                    <div class="col-auto">
                        <input type="date" id="toDate" class="form-control">
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
                            <th class="px-0 text-muted">Driver's Name</th>
                            <th class="px-0 text-muted text-center">Violation Type</th>
                            <th class="px-0 text-muted">Description</th>
                            <th class="px-0 text-muted text-center">Status</th>
                            <th class="px-0 text-muted text-center">Date</th>
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
    <script>
        const table = new DataTable('#table-data', {
            processing: true,
            serverSide: true,
            ajax: {
                url: 'table-fetch/violation-table.php',
                type: 'POST',
                data: function (d) {
                d.fromDate = $('#fromDate').val();
                d.toDate   = $('#toDate').val();
                d.violation = $('#violation').val();
                }
            },
            responsive: true
            });

            $('#submit').on('click', function (e) {
            e.preventDefault();
            table.ajax.reload();
            });
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: user-index.php?route=login");
  exit();
} ?>
