<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Shop") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop Unit Report</title>
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
                    <form id="filterForm" class="row g-3 align-items-center mb-3" method="POST" action="php/shop-report.php" target="_blank" class="Excel">

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
                        <h4 class="card-title">List of Shop Record</h4>
                      </div>
                    </div>
                    <div class="table-responsive mt-4" style="overflow: hidden;">
                      <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data">
                        <thead>
                          <tr>
                            <th class="px-0 text-muted text-center">Driver</th>
                            <th class="px-0 text-muted text-center">Truck Unit</th>
                            <th class="px-0 text-muted text-center">Start Time</th>
                            <th class="px-0 text-muted text-center">End Time</th>
                            <th class="px-0 text-muted text-center">Age</th>
                            <th class="px-0 text-muted text-center">Manpower Assigned</th>
                            <th class="px-0 text-muted text-center">Status</th>
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
      $(document).ready(function() {
        let table = $('#table-data').DataTable({
          "ajax": "table-fetch/fetch_shop_report.php",
          "columns": [{
              "data": "driver"
            },
            {
              "data": "unit"
            },
            {
              "data": "start"
            },
            {
              "data": "end"
            },
            {
              "data": "age"
            },
            {
              "data": "manpower"
            },
            {
              "data": "status"
            }
          ]
        });

        function updateLiveTimes() {
          $(".live-time").each(function() {
            let now = new Date();
            $(this).text(now.toLocaleString());
          });

          $(".live-age").each(function() {
            let start = new Date($(this).data("start"));
            let now = new Date();
            let diff = Math.floor((now - start) / 1000);

            let days = Math.floor(diff / 86400);
            let hours = Math.floor((diff % 86400) / 3600).toString().padStart(2, '0');
            let mins = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
            let secs = Math.floor(diff % 60).toString().padStart(2, '0');

            $(this).text(days + " Days " + hours + ":" + mins + ":" + secs);
          });
        }

        setInterval(updateLiveTimes, 1000);
        updateLiveTimes();
      });
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: shop-index.php?route=login");
  exit();
}
