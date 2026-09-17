<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sumifru Monitoring</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
<link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
      .container-history {
        width: 100%;
        max-width: 100% !important;
        margin: 0 auto;
        padding: 0 1rem;
      }

      .trailer-card, .filter-item {
        display: block;
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #fff;
        padding: 12px 16px;
        box-sizing: border-box;
      }

      .trailer-card:hover, .filter-item:hover {
        background: #f8f9fa;
        border-color: #ccc;
      }

      .card-header {
          flex-wrap: wrap;
          gap: 10px;
        }

        .card-header small span {
          display: inline-block;
          margin-right: 10px;
          white-space: nowrap;
        }

        .arrow-icon {
          font-size: 1.2rem;
          transition: transform 0.3s;
          cursor: pointer;
        }

      .collapsed .arrow-icon { transform: rotate(0deg); }
      .expanded .arrow-icon { transform: rotate(180deg); }

      .history-table th, .history-table td {
        padding: 6px 12px;
        font-size: 0.9rem;
        white-space: nowrap;
      }

      .container {
        width: 100%;
        max-width: none !important;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 8px;
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
        <?php include 'navbar.php';
        $id = $_SESSION['user_id'];
        $query = "SELECT * FROM \"user\" WHERE user_id = '$id'";
        $result = $conn->query($query);
        $data = ($result)->fetch();
        $fname = $data['user_fname'];
        $firstLetter = substr($data['user_lname'], 0, 1);

        $location = $data['user_assignlocation'];
        ?>
        <!--  Header End -->
        <div class="body-wrapper-inner">
          <div class="container-fluid">
            <div class="col-lg-12">
              <div class="card w-100">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-9">
                      <h4 class="card-title">Container History Overview</h4>
                      <p class="card-subtitle">Container Activity - Empty and Loaded Status</p>
                    </div>
                    <div class="col-md-3 text-end">
                      <input type="text" id="containerFilter" class="form-control" placeholder="Filter container...">
                    </div>
                  </div>

                  <div class="container-history">
                    <?php
                    include "php/config/config.php";

                    $containers = $conn->query("
                      SELECT DISTINCT 
                        t.trip_container, 
                        COALESCE(t.costumer, d.costumer) AS costumer, t.trip_departuredatetime, t.trip_arrivaldatetime, t.trip_pharrivaldatetime
                      FROM trips t
                      LEFT JOIN dispatch d ON d.d_id = t.d_id
                      WHERE t.trip_container IS NOT NULL 
                        AND t.trip_container <> '' 
                        AND (d.costumer = 'SUMI' OR t.costumer = 'SUMI')
                        AND t.trip_departuredatetime = '0000-00-00 00:00:00' 
                        AND t.trip_arrivaldatetime = '0000-00-00 00:00:00' 
                        AND t.trip_pharrivaldatetime = '0000-00-00 00:00:00' 
                      ORDER BY t.trip_container ASC
                    ");

                    if ($containers->rowCount() > 0) {
                      $i = 1;
                      while ($container = $containers->fetch()) {
                        $containerName = $container['trip_container'];

                        // ✅ Get the latest transaction for this container
                        $latest = $conn->query("
                          SELECT 
                              t.trip_from, 
                              t.trip_to, 
                              d.d_truck, 
                              d.d_trailer,
                              d.d_tripreceipt,
                              d_ecs, 
                              t.trip_status, 
                              t.costumer AS trip_costumer, 
                              d.costumer AS dispatch_costumer, 
                              d.d_datetime, 
                              t.trip_departuredatetime, 
                              t.trip_arrivaldatetime, 
                              t.trip_pharrivaldatetime,
                              d.d_drivername, 
                              d.d_dispatcher
                          FROM trips t
                          LEFT JOIN dispatch d ON d.d_id = t.d_id
                          WHERE t.trip_container = '".pt_pg_escape($conn, $containerName)."' 
                            AND (d.costumer = 'SUMI' OR t.costumer = 'SUMI')
                            AND t.trip_departuredatetime = '0000-00-00 00:00:00'
                            AND t.trip_arrivaldatetime = '0000-00-00 00:00:00'
                            AND t.trip_pharrivaldatetime = '0000-00-00 00:00:00'
                          ORDER BY t.trip_id DESC 
                          LIMIT 1
                      ")->fetch();
                    ?>
                    <!-- Container Card -->
                    <div class="card mt-3 filter-item collapsed" data-bs-toggle="collapse" data-bs-target="#containerHistory<?= $i ?>">
                      <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                          <strong><i class="ti ti-package"></i> <?= htmlspecialchars($containerName) ?></strong>

                          <?php if ($latest): ?>
                            <small class="d-flex flex-wrap gap-3">
                              <span><strong>From:</strong> <?= htmlspecialchars($latest['trip_from']) ?></span>
                              <span><strong>To:</strong> <?= htmlspecialchars($latest['trip_to']) ?></span>
                              <span><strong>Driver:</strong> <?= htmlspecialchars($latest['d_drivername']) ?></span>
                              <span><strong>Truck:</strong> <?= htmlspecialchars($latest['d_truck']) ?></span>
                              <span><strong>Trailer:</strong> <?= htmlspecialchars($latest['d_trailer']) ?></span>
                              <span><strong>Trip Reciept:</strong> <?= htmlspecialchars($latest['d_tripreceipt']) ?></span>
                              <span><strong>ESC:</strong> <?= htmlspecialchars($latest['d_ecs']) ?></span>
                              <span><strong>Date:</strong> <?= htmlspecialchars(date('M d, Y H:i A', strtotime($latest['d_datetime']))) ?></span>
                              <span><strong>Arrival CY:</strong> <?Php if($latest['trip_arrivaldatetime'] != '0000-00-00 00:00:00'){ ?> <?= htmlspecialchars(date('M d, Y H:i A', strtotime($latest['trip_arrivaldatetime']))) ?> <?php }?></span>
                              <span><strong>Departure:</strong> <?Php if($latest['trip_departuredatetime'] != '0000-00-00 00:00:00'){ ?> <?= htmlspecialchars(date('M d, Y H:i A', strtotime($latest['trip_departuredatetime']))) ?> <?php }?></span>
                              <span><strong>Arrival PH:</strong> <?Php if($latest['trip_pharrivaldatetime'] != '0000-00-00 00:00:00'){ ?> <?= htmlspecialchars(date('M d, Y H:i A', strtotime($latest['trip_pharrivaldatetime']))) ?> <?php }?></span>
                            </small>
                          <?php endif; ?>
                        </div>
                        <span class="arrow-icon">▼</span>
                      </div>

                      <!-- Collapsible section -->
                      <div id="containerHistory<?= $i ?>" class="collapse">
                        <div class="card-body">
                          <?php
                            // Combine Empty and Loaded into one query
                            $history = $conn->query("
                              SELECT 
                                t.trip_id,
                                t.container_activity, 
                                t.trip_containerstat, 
                                t.trip_status, 
                                t.trip_from, 
                                t.trip_to,
                                t.costumer,
                                t.trip_arrivaldatetime,
                                t.trip_departuredatetime,
                                t.trip_pharrivaldatetime,
                                d.costumer,
                                d.d_datetime,
                                d.d_truck, 
                                d.d_trailer, 
                                d.d_drivername,
                                d.d_tripreceipt,
                                d_ecs, 
                                d.d_dispatcher
                              FROM trips t
                              LEFT JOIN dispatch d ON d.d_id = t.d_id
                              WHERE t.trip_container = '".pt_pg_escape($conn, $containerName)."' AND (d.costumer = 'SUMI' OR t.costumer = 'SUMI')
                              AND t.trip_departuredatetime = '0000-00-00 00:00:00' 
                              AND t.trip_arrivaldatetime = '0000-00-00 00:00:00' 
                              AND t.trip_pharrivaldatetime = '0000-00-00 00:00:00' 
                              ORDER BY d.d_datetime DESC
                            ");
                          ?>

                          <?php if ($history->rowCount() > 0): ?>
                            <div class="table-responsive">
                              <table id="historyTable<?= $i ?>" class="table table-sm table-bordered history-table">
                                <thead class="table-light">
                                  <tr>
                                    <th>Activity</th>
                                    <th>Status</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Truck</th>
                                    <th>Trailer</th>
                                    <th>Driver</th>
                                    <th>Trip Reciept</th>
                                    <th>ESC</th>
                                    <th>Dispatcher</th>
                                    <th>Date</th>
                                    <th>Arrival Cy</th>
                                    <th>Arrival Departure</th>
                                    <th>Arrival PH</th>
                                    <th>Action</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <?php while ($row = $history->fetch()): ?>
                                    <tr class="<?= ($row['trip_containerstat'] === 'Empty') ? 'table-warning' : 'table-success' ?>">
                                      <td><?= htmlspecialchars($row['container_activity']) ?></td>
                                      <td><?= htmlspecialchars($row['trip_containerstat']) ?></td>
                                      <td><?= htmlspecialchars($row['trip_from']) ?></td>
                                      <td><?= htmlspecialchars($row['trip_to']) ?></td>
                                      <td><?= htmlspecialchars($row['d_truck']) ?></td>
                                      <td><?= htmlspecialchars($row['d_trailer']) ?></td>
                                      <td><?= htmlspecialchars($row['d_drivername']) ?></td>
                                      <td><?= htmlspecialchars($row['d_tripreceipt']) ?></td>
                                      <td><?= htmlspecialchars($row['d_ecs']) ?></td>
                                      <td><?= htmlspecialchars($row['d_dispatcher']) ?></td>
                                      <td><?= htmlspecialchars(date('M d, Y H:i A', strtotime($row['d_datetime']))) ?></td>
                                      <td><?Php if($row['trip_arrivaldatetime'] != '0000-00-00 00:00:00'){ ?> <?= htmlspecialchars(date('M d, Y H:i A', strtotime($row['trip_arrivaldatetime']))) ?> <?php }?></td>
                                      <td><?Php if($row['trip_departuredatetime'] != '0000-00-00 00:00:00'){ ?> <?= htmlspecialchars(date('M d, Y H:i A', strtotime($row['trip_departuredatetime']))) ?> <?php }?></td>
                                      <td><?Php if($row['trip_pharrivaldatetime'] != '0000-00-00 00:00:00'){ ?> <?= htmlspecialchars(date('M d, Y H:i A', strtotime($row['trip_pharrivaldatetime']))) ?> <?php }?></td>
                                      <td><button class="btn btn-sm btn-secondary view-time" 
                                                  data-id="<?= htmlspecialchars($row['trip_id']) ?>">
                                            <i class="ti ti-edit"></i>
                                          </button></td>
                                    </tr>
                                  <?php endwhile; ?>
                                </tbody>
                              </table>
                            </div>
                          <?php else: ?>
                            <p class="text-danger">No history records found.</p>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <?php 
                        $i++;
                      } // end while
                    } else {
                      echo "<p class='text-danger'>No containers found.</p>";
                    }
                    ?>
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
  <!-- Map Modal -->
      <div class="modal fade" id="mapModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Trip Route Map</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div id="map" style="height:500px; width:100%;"></div>
              <hr>
              <div id="tripDetails" class="p-2"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <div class="modal fade" id="timeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            
            <div class="modal-header">
              <h5 class="modal-title">Trip Time Details</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
              <input type="hidden" id="trip_id">

              <div class="mb-3">
                <label class="form-label">Arrival CY</label>
                <input type="datetime-local" id="arrival_cy" class="form-control">
              </div>

              <div class="mb-3">
                <label class="form-label">Departure</label>
                <input type="datetime-local" id="departure" class="form-control">
              </div>

              <div class="mb-3">
                <label class="form-label">Arrival PH</label>
                <input type="datetime-local" id="arrival_ph" class="form-control">
              </div>
            </div>
            
            <div class="modal-footer">
              <button type="button" id="saveBtn" class="btn btn-primary">Save</button>
              <button type="button" id="doneBtn" class="btn btn-success" style="display:none;">Done</button>
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
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDi9dpeJZM1GkdSfovy2ufBWQZFabMrSRA&libraries=places"></script>
     <script src="js/driver-dashboard.js"></script>
     <script>
        // ✅ Filter Function
        document.getElementById("containerFilter").addEventListener("keyup", function() {
          let filter = this.value.toLowerCase();
          document.querySelectorAll(".filter-item").forEach(function(card) {
            let text = card.innerText.toLowerCase();
            card.style.display = text.includes(filter) ? "" : "none";
          });
        });

        // ✅ Initialize DataTables
        $(document).ready(function(){
          <?php for($j=1; $j<$i; $j++): ?>
            $('#historyTable<?= $j ?>').DataTable({
              "pageLength": 5,
              "lengthChange": false,
              "ordering": true,
              "searching": true
            });
          <?php endfor; ?>
        });

        // ✅ Arrow Rotation
        document.querySelectorAll('.filter-item').forEach(card => {
          card.addEventListener('click', function () {
            this.classList.toggle('expanded');
            this.classList.toggle('collapsed');
          });
        });
     </script>
  </body>


  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
} ?>
