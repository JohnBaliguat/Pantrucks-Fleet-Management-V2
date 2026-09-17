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
            <!--  Row 1 -->
            <div class="row">

              <div class="col-md-12">
                <div class="modern-card">
                  <div class="row m-2">
                    <div class="col-md-12 text-end mt-2">
                      <div class="btn-group" role="group" aria-label="Basic example">
                        <a class="btn btn-outline-primary" href="dispatch-dashboard-attendance">Driver Attendance</a>
                        <a class="btn btn-outline-primary" href="dispatch-dashboard-trailer">Trailer Movement</a>
                        <a href="dispatch-dashboard" class="btn btn-outline-primary">Dashboard</a>
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="card-header">
                        <div>
                          <h4>Dispatch</h4>
                          <p>List of Available truck</p>
                        </div>
                        <input type="text" id="unitSearch" placeholder="Search truck...">
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="truck-legend">
                        <?php
                        // Count trucks by status
                        $counts = [
                          'Dispatch' => 0,
                          'Shop Unit' => 0,
                          'Rescue Unit' => 0,
                        ];

                        $q_count = $conn->query("SELECT unit_status, COUNT(*) AS total FROM units WHERE unit_name NOT LIKE 'GS%' AND unit_name NOT LIKE 'FL%' GROUP BY unit_status");
                        while ($c = ($q_count)->fetch()) {
                          $counts[$c['unit_status']] = $c['total'];
                        }

                        $totalUnits = array_sum($counts);
                        ?>

                        <span class="legend-item active" data-filter="all">All (<?= $totalUnits ?>)</span>
                        <span class="legend-item status-dispatch" data-filter="Dispatch">Dispatch (<?= $counts['Dispatch'] ?>)</span>
                        <span class="legend-item status-shop" data-filter="Shop Unit">Shop (<?= $counts['Shop Unit'] ?>)</span>
                        <span class="legend-item status-rescue" data-filter="Rescue Unit">Rescue (<?= $counts['Rescue Unit'] ?>)</span>
                      </div>
                    </div>
                  </div>


                  <div class="card-body scroll-area grid mb-5">
                    <?php
                    $q = $conn->query("SELECT * FROM units WHERE unit_name NOT LIKE 'GS%' AND unit_name NOT LIKE 'FL%'");
                    while ($u = ($q)->fetch()) {

                      $statusClass = match ($u['unit_status']) {
                        'Shop Unit' => 'status-shop',
                        'Rescue Unit' => 'status-rescue',
                        'Dispatch' => 'status-dispatch',
                        default => ''
                      };
                    ?>
                      <div class="truck-card <?= $statusClass; ?>"
                        data-unit-id="<?= $u['unit_id']; ?>"
                        data-unit="<?= $u['unit_name']; ?>"
                        data-status="<?= $u['unit_status']; ?>">
                        <i class="ti ti-truck" style="font-size: 20px;"></i> <?= $u['unit_name']; ?>
                      </div>
                    <?php } ?>
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

    <div class="modal fade" id="truckMovementModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              Truck Movement - <span id="modalTruckName"></span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>


          <div class="modal-body">
            <form id="filterForm">
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
                  <th>Dispatch Time</th>
                  <th>Driver</th>
                  <th>Customer</th>
                  <th>Trip Type</th>
                  <th>From</th>
                  <th>To</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="movementTableBody">
              </tbody>
            </table>
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
    <script src="js/dashboard.js"></script>
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


      document.addEventListener("DOMContentLoaded", function () {

        let selectedTruck = "";

        // When truck card is clicked
        document.querySelectorAll(".truck-card").forEach(card => {
          card.addEventListener("click", function () {

            selectedTruck = this.dataset.unit; // truck name
            document.getElementById("modalTruckName").innerText = selectedTruck;

            loadTruckMovement(); // load all records first

            let modal = new bootstrap.Modal(document.getElementById('truckMovementModal'));
            modal.show();
          });
        });

        // Generate button (Filter)
        document.getElementById("submit").addEventListener("click", function () {
          loadTruckMovement();
        });

        function loadTruckMovement() {

          let fromDate = document.getElementById("fromDate").value;
          let toDate   = document.getElementById("toDate").value;

          fetch("table-fetch/fetch_truck_movement.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "truck_id=" + encodeURIComponent(selectedTruck) +
                  "&fromDate=" + encodeURIComponent(fromDate) +
                  "&toDate=" + encodeURIComponent(toDate)
          })
          .then(response => response.text())
          .then(data => {
            document.getElementById("movementTableBody").innerHTML = data;
          });
        }

      });
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: dispatcher-index.php?route=login");
  exit();
} ?>
