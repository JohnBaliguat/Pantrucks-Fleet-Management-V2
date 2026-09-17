<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Shop") {


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
    <style>
      /* Scrollable container */
      .truck-grid-container {
        overflow-x: auto;
        padding-right: 5px;
        max-height: 820px;
      }

      /* Optional: style scrollbar */
      .truck-grid-container::-webkit-scrollbar {
        height: 6px;
      }

      .truck-grid-container::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 3px;
      }

      /* Grid layout */
      .truck-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 15px;
        white-space: nowrap;
        /* prevents wrapping in some cases */
      }

      /* Card styles (your existing ones) */
      .truck-card {
        background: #f2f2f2;
        border-radius: 20px;
        padding: 20px 10px;
        text-align: center;
        font-weight: 600;
        box-shadow: 0px 2px 6px rgba(0, 0, 0, 0.1);
      }

      .truck-card i {
        font-size: 35px;
        margin-bottom: 10px;
        display: block;
      }

      /* Status Colors */
      .available {
        background: #f5f5f5;
      }

      .dispatch {
        background: #c8ffbf;
      }

      .rescue {
        background: #fff7a8;
      }

      .shop {
        background: #ffb6b6;
      }

      /* STATUS LEGEND */
      .status-legend .status-dot {
        width: 12px;
        height: 12px;
        display: inline-block;
        border-radius: 50%;
        margin-right: 5px;
        vertical-align: middle;
      }

      .status-dot.available {
        background: #eaeaea;
      }

      .status-dot.ontrip {
        background: #b9f7ac;
      }

      .status-dot.rescue {
        background: #ffe97a;
      }

      .status-dot.shop {
        background: #ff8b8b;
      }

      .status-filter {
        cursor: pointer;
        user-select: none;
        padding: 3px 6px;
        border-radius: 5px;
        transition: 0.2s;
      }

      .status-filter.active {
        background: rgba(0, 0, 0, 0.1);
        font-weight: bold;
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
              <div class="col-lg-7">
                <div class="card w-100">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-9">
                        <h4 class="card-title">Transation Overview</h4>
                        <div class="status-legend mb-3 mt-3 text-center">
                          <span class="status-filter" data-status="good" hidden>
                            <span class="status-dot good"></span> Available
                          </span>

                          <span class="status-filter ms-3" data-status="dispatch" hidden>
                            <span class="status-dot ontrip"></span> On Trip
                          </span>

                          <span class="status-filter ms-3" data-status="rescue unit" hidden>
                            <span class="status-dot rescue unit"></span> Rescue
                          </span>

                          <span class="status-filter ms-3" data-status="shop" hidden>
                            <span class="status-dot shop"></span> Shop
                          </span>
                        </div>

                      </div>
                      <div class="col-md-3">
                        <input type="text" id="truckSearch" class="form-control" placeholder="Search truck...">
                      </div>
                    </div>
                    <div>
                    </div>
                    <!-- Body -->
                    <!-- TRUCK GRID -->
                    <div class="truck-grid-container mt-3">
                      <div class="truck-grid">
                        <?php
                        include 'php/config/config.php';
                        $sql = "SELECT unit_id, unit_name, unit_status 
                                  FROM units 
                                  WHERE unit_status IN ('Rescue', 'Shop Unit') AND unit_name NOT LIKE 'GS%' AND unit_name NOT LIKE 'FL%'";

                        $result = $conn->query($sql);

                        while ($row = ($result)->fetch()) {
                          $status = strtolower($row['unit_status']); // Dispatch → dispatch
                          echo '
                                  <div class="truck-card ' . $status . '">
                                      <i class="ti ti-truck card-icon"></i>
                                      <p>' . $row['unit_name'] . '</p>
                                  </div>
                              ';
                        }
                        ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="row">
                  <div class="col-md-12">
                    <div class="card overflow-hidden">
                      <div class="card-body pb-0">
                        <div class="pb-3 d-flex align-items-center">
                          <span class="btn btn-warning rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-car-crane fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Rescue Unit</h5>
                          </div>
                          <div class="ms-auto">
                            <span id="rescueCount" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-12">
                    <div class="card overflow-hidden">
                      <div class="card-body pb-0">
                        <div class="pb-3 d-flex align-items-center">
                          <span class="btn btn-danger rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-truck-off fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Shop Unit</h5>
                          </div>
                          <div class="ms-auto">
                            <span id="shopCount" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                          </div>
                        </div>
                      </div>
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
    <script>
      function loadUnitCount() {
          $.ajax({
              url: "php/get_unit_count.php",
              method: "GET",
              dataType: "json",
              success: function (data) {
                  $("#rescueCount").text(data.rescue_count);
                  $("#shopCount").text(data.shop_count);
              }
          });
      }

      // initial load
      loadUnitCount();

      // refresh every 3 seconds (real-time feel)
      setInterval(loadUnitCount, 3000);
      const statusFilters = document.querySelectorAll(".status-filter");
      const searchInput = document.getElementById("truckSearch");

      function applyFilters() {
        const activeStatuses = [...document.querySelectorAll(".status-filter.active")]
          .map(el => el.getAttribute("data-status"));

        const searchValue = searchInput.value.toLowerCase();

        const cards = document.querySelectorAll(".truck-card");

        cards.forEach(card => {
          const cardText = card.querySelector("p").textContent.toLowerCase();

          const statusClass = [...card.classList].find(c => ["good", "dispatch", "rescue unit", "shop"].includes(c));

          // status match
          const statusMatch =
            activeStatuses.length === 0 || activeStatuses.includes(statusClass);

          // search match
          const searchMatch = cardText.includes(searchValue);

          if (statusMatch && searchMatch) {
            card.style.display = "block";
          } else {
            card.style.display = "none";
          }
        });
      }

      /* STATUS FILTER CLICK */
      statusFilters.forEach(filter => {
        filter.addEventListener("click", function() {
          this.classList.toggle("active");
          applyFilters();
        });
      });

      /* SEARCH INPUT */
      searchInput.addEventListener("keyup", applyFilters);
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: shop-index.php?route=login");
  exit();
} ?>
