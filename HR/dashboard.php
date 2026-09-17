<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "User") {


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
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
      .driver-card {
        user-select: none;
        border: none;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      .driver-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
      }

      .driver-name {
        font-weight: 600;
        font-size: 16px;
        color: #212529;
      }

      .violation-text {
        font-size: 14px;
        color: #6c757d;
      }

      .status-badge {
        font-size: 12px;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
      }

      .status-pending {
        background-color: #dc3545;
      }

      .status-cleared {
        background-color: #198754;
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
            <div class="row">
              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-gas-station fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">FUEL</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="fuelCount" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-car fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">DRIVING</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="gpsCount" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-chart-line fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">PERFORMANCE</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="performanceCount" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-lg-12">
                <div class="card w-100">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-10">
                        <div class="d-md-flex align-items-center">
                          <div>
                            <h4 class="card-title">Drivers With Violations</h4>
                            <p class="card-subtitle">
                              List of Drivers With Pending Violations
                            </p>
                          </div>
                          
                        </div>
                      </div>
                      <div class="col-md-2">
                          <div>
                            <input 
                            type="text" 
                            id="driverSearch" 
                            class="form-control" 
                            placeholder="Search driver or violation...">
                          </div>
                      </div>

                    </div>
                    
                    <!-- BODY -->
                    <div class="row mt-3 g-3">

                    <?php
                    include "php/config/config.php";

                    $query = "
                    SELECT
                        d.driver_id,
                        CONCAT(d.driver_fname, ' ', d.driver_lname) AS driver_name,
                        STRING_AGG(v.vr_type, ', ') AS violations,
                        STRING_AGG(
                            v.vr_type || COALESCE(' ' || v.vr_description, ''), ' '
                        ) AS violations_search,
                        json_agg(
                            json_build_object('type', v.vr_type, 'description', v.vr_description)
                            ORDER BY v.vr_date DESC
                        ) AS violations_detail,
                        COUNT(v.vr_id) AS pending_count
                    FROM drivers d
                    INNER JOIN violation_record v
                        ON d.driver_id = v.driver_id
                    WHERE v.vr_status = 'Active'
                    GROUP BY d.driver_id
                    ORDER BY driver_name ASC
                    ";
                    $result = $conn->query($query);

                    if (($result)->rowCount() > 0):
                      while ($row = ($result)->fetch()):
                    ?>

                      <div class="col-md-4 driver-item"
                        data-name="<?= strtolower($row['driver_name']); ?>"
                        data-violation="<?= htmlspecialchars(strtolower($row['violations_search']), ENT_QUOTES); ?>">

                      <div class="card driver-card h-100"
                          role="button"
                          style="cursor: pointer;"
                          onclick="markGood(<?= $row['driver_id']; ?>)">

                        <div class="card-body">

                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="driver-name">
                              <?= htmlspecialchars($row['driver_name']); ?>
                            </div>

                            <span class="badge status-badge status-pending">
                              Pending
                            </span>
                          </div>

                          <div class="violation-text mb-0">
                            <strong>Violations:</strong>
                            <?php
                            $vlist = json_decode($row['violations_detail'], true);
                            if (is_array($vlist)) {
                              foreach ($vlist as $v) {
                                if (empty($v['type'])) continue;
                                echo '<div class="mt-1">&bull; <span class="fw-medium">' . htmlspecialchars($v['type']) . '</span>';
                                if (!empty($v['description'])) {
                                  echo ' &ndash; ' . htmlspecialchars($v['description']);
                                }
                                echo '</div>';
                              }
                            } else {
                              echo ' ' . htmlspecialchars($row['violations']);
                            }
                            ?>
                          </div>

                        </div>
                      </div>
                    </div>

                    <?php
                      endwhile;
                    else:
                    ?>

                      <!-- No Active Violations -->
                      <div class="col-12 text-center">
                        <i class="bi bi-check-circle-fill fs-2 text-success"></i>
                        <p class="violation-text mt-2">
                          No drivers with active violations
                        </p>
                      </div>

                    <?php endif; ?>

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
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script>
      function loadViolationCounts() {
        fetch("php/fetch/fetch_violation_count.php")
          .then(res => res.json())
          .then(data => {
            document.getElementById("fuelCount").innerText = data.FUEL;
            document.getElementById("gpsCount").innerText = data.GPS;
            document.getElementById("performanceCount").innerText = data.PERFORMANCE;
          });
      }

      // load immediately
      loadViolationCounts();

      // refresh every 5 seconds
      setInterval(loadViolationCounts, 30000);
      document.getElementById('driverSearch').addEventListener('keyup', function () {
        const value = this.value.toLowerCase();
        document.querySelectorAll('.driver-item').forEach(card => {
          const name = card.dataset.name;
          const violation = card.dataset.violation;
          card.style.display =
            name.includes(value) || violation.includes(value)
              ? ''
              : 'none';
        });
      });

  //     function markGood(driver_id) {
  //     Swal.fire({
  //         title: 'Mark driver as GOOD?',
  //         text: 'This will update the driver status to GOOD.',
  //         icon: 'question',
  //         showCancelButton: true,
  //         confirmButtonText: 'Yes, mark as good',
  //         cancelButtonText: 'Cancel',
  //         confirmButtonColor: '#28a745'
  //     }).then((result) => {
  //         if (result.isConfirmed) {

  //             $.ajax({
  //                 url: 'php/mark_driver_good.php',
  //                 type: 'POST',
  //                 data: { driver_id: driver_id },
  //                 dataType: 'json',
  //                 success: function(response) {
  //                     if (response.status === 'success') {
  //                         Swal.fire({
  //                             icon: 'success',
  //                             title: 'Updated',
  //                             text: response.message,
  //                             timer: 2000,
  //                             showConfirmButton: false
  //                         }).then(() => {
  //                             location.reload(); // optional
  //                         });
  //                     } else {
  //                         Swal.fire({
  //                             icon: 'error',
  //                             title: 'Error',
  //                             text: response.message
  //                         });
  //                     }
  //                 },
  //                 error: function() {
  //                     Swal.fire({
  //                         icon: 'error',
  //                         title: 'Server Error',
  //                         text: 'Unable to process request.'
  //                     });
  //                 }
  //             });

  //         }
  //     });
  // }
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: user-index.php?route=login");
  exit();
} ?>
