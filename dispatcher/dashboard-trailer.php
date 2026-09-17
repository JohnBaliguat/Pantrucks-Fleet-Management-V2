<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Dispatcher") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
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
            <!--  -->


            <dvi class="row">

              <div class="col-md-12">
                <div class="modern-card">
                  <div class="row m-2">
                    <div class="col-md-12 text-end mt-2">
                      <div class="btn-group" role="group" aria-label="Basic example">
                        <a class="btn btn-outline-primary" href="dispatch-dashboard-attendance">Driver Attendance</a>
                        <a class="btn btn-outline-primary" href="dispatch-dashboard-truck">Truck Movement</a>
                        <a href="dispatch-dashboard" class="btn btn-outline-primary">Dashboard</a>
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="card-header">
                        <div>
                          <h4>Trailer Movement</h4>
                          <p>Click the trailer to show the movement history of the trailer.</p>
                        </div>
                        <input type="text" id="unitSearch" placeholder="Search trailer...">
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="truck-legend">
                        <?php
                        // Count trucks by status
                        $counts = [
                          'Available' => 0,
                          'Dispatch' => 0,
                          'Under Maintenance' => 0,
                          'Rescue Unit' => 0,
                        ];

                        $q_count = $conn->query("SELECT trailer_status, COUNT(*) AS total FROM trailer GROUP BY trailer_status");
                        while ($c = ($q_count)->fetch()) {
                          $counts[$c['trailer_status']] = $c['total'];
                        }

                        $totalUnits = array_sum($counts);
                        ?>

                        <span class="legend-item active" data-filter="all">All (<?= $totalUnits ?>)</span>
                        <span class="legend-item status-good" data-filter="Available" hidden>Available (<?= $counts['Available'] ?>)</span>
                        <span class="legend-item status-dispatch" data-filter="Dispatch" hidden>Dispatch (<?= $counts['Dispatch'] ?>)</span>
                        <span class="legend-item status-shop" data-filter="Under Maintenance">Shop (<?= $counts['Under Maintenance'] ?>)</span>
                        <span class="legend-item status-rescue" data-filter="Rescue Unit" hidden>Rescue (<?= $counts['Rescue Unit'] ?>)</span>
                      </div>
                    </div>
                  </div>


                  <div class="card-body scroll-area grid mb-5">
                    <?php
                    $q = $conn->query("SELECT * FROM trailer");
                    while ($u = ($q)->fetch()) {

                      $statusClass = match ($u['trailer_status']) {
                        'Available' => 'status-good',
                        'Under Maintenance' => 'status-shop',
                        'Rescue Unit' => 'status-rescue',
                        'Dispatch' => 'status-dispatch',
                        default => ''
                      };
                    ?>
                      <div class="truck-card <?= $statusClass; ?>"
                        data-unit-id="<?= $u['trailer_id']; ?>"
                        data-unit="<?= $u['trailer_name']; ?>"
                        data-status="<?= $u['trailer_status']; ?>">
                        <i class="ti ti-archive"></i> <?= $u['trailer_name']; ?>
                      </div>
                    <?php } ?>
                  </div>
                </div>

              </div>

            </dvi>
            <!--  Row 1 -->

            <div class="modal fade" id="trailerMovementModal" tabindex="-1">
              <div class="modal-dialog modal-xl">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">
                      Trailer Movement - <span id="modalTrailerName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>

                  <div class="modal-body">
                    <form id="filterForm">
                      <input type="hidden" id="selectedTrailer" name="trailer_id">

                      <div class="row mb-4">
                        <div class="col-1"><label for="fromDate" class="col-form-label">From</label></div>
                        <div class="col-4"><input type="date" id="fromDate" name="fromDate" class="form-control"></div>
                        <div class="col-1"><label for="toDate" class="col-form-label">To</label></div>
                        <div class="col-4"><input type="date" id="toDate" name="toDate" class="form-control"></div>
                        <div class="col-1"><button type="button" id="submit" class="btn btn-primary">Generate</button></div>
                      </div>
                    </form>
                    <table class="table table-bordered table-striped">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Location</th>
                          <th>Recorded Type</th>
                          <th>Driver</th>
                          <th>Container</th>
                          <th>Remarks</th>
                          <th>Recorded By</th>
                        </tr>
                      </thead>
                      <tbody id="movementTableBody">
                      </tbody>
                    </table>
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
    <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script src="datatable/datatables.min.js"></script>
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script>
      document.querySelectorAll(".driver-legend .legend-item-driver").forEach(item => {
        item.addEventListener("click", function() {

          // active state
          document.querySelectorAll(".driver-legend .legend-item-driver")
            .forEach(i => i.classList.remove("active"));
          this.classList.add("active");

          const filter = this.dataset.filter;

          document.querySelectorAll(".driver-card").forEach(card => {
            if (filter === "all") {
              card.style.display = "flex";
            } else {
              card.style.display =
                card.dataset.status === filter ? "flex" : "none";
            }
          });
        });
      });

      /* TRUCK SEARCH */
      document.getElementById('unitSearch').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const trucks = document.querySelectorAll('.truck-card');

        trucks.forEach(card => {
          const unitName = card.dataset.unit.toLowerCase();
          card.style.display = unitName.includes(searchValue) ? 'block' : 'none';
        });
      });

      const legendItems = document.querySelectorAll('.legend-item');
      const truckCards = document.querySelectorAll('.truck-card');

      legendItems.forEach(item => {
        item.addEventListener('click', () => {

          // active state
          legendItems.forEach(i => i.classList.remove('active'));
          item.classList.add('active');

          const filter = item.dataset.filter;

          truckCards.forEach(card => {
            const status = card.dataset.status;

            if (filter === 'all' || status === filter) {
              card.style.display = 'block';
            } else {
              card.style.display = 'none';
            }
          });
        });
      });


      document.addEventListener("DOMContentLoaded", function() {

        function loadMovement(trailerName, fromDate = "", toDate = "") {
          let params = new URLSearchParams();
          params.append("trailer_id", trailerName);

          if (fromDate && toDate) {
            params.append("fromDate", fromDate);
            params.append("toDate", toDate);
          }

          fetch("table-fetch/fetch_trailer_movement.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded"
            },
            body: params.toString()
          })
          .then(response => response.text())
          .then(data => {
            document.getElementById("movementTableBody").innerHTML = data;
          });
        }

        document.querySelectorAll(".truck-card").forEach(card => {
          card.addEventListener("click", function() {

            let trailerName = this.dataset.unit;

            document.getElementById("modalTrailerName").innerText = trailerName;
            document.getElementById("selectedTrailer").value = trailerName;

            // Clear date fields when opening modal
            document.getElementById("fromDate").value = "";
            document.getElementById("toDate").value = "";

            loadMovement(trailerName);

            let modal = new bootstrap.Modal(document.getElementById('trailerMovementModal'));
            modal.show();
          });
        });

        document.getElementById("submit").addEventListener("click", function() {
          let trailerName = document.getElementById("selectedTrailer").value;
          let fromDate = document.getElementById("fromDate").value;
          let toDate = document.getElementById("toDate").value;

          if (!fromDate || !toDate) {
            alert("Please select both From and To dates.");
            return;
          }

          loadMovement(trailerName, fromDate, toDate);
        });

      });
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: dispatcher-index.php?route=login");
  exit();
} ?>
