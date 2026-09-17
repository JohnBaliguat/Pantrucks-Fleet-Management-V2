<?php
session_start();

if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ["Dispatcher", "Dispatch Admin", "Booker"], true)) {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitoring</title>
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
            <!--  Row 1 -->
            <div class="row">
             <?php
                include "php/config/config.php";

                // Fetch customers dynamically (customer_code only)
                $customers = [];
                $customerQuery = "SELECT customer_code FROM customer";
                $resultCust = $conn->query($customerQuery);

                if ($resultCust && ($resultCust)->rowCount() > 0) {
                    while ($row = ($resultCust)->fetch()) {
                        $customers[] = $row['customer_code'];
                    }
                }

                // Function to get counts
                function getCounts($conn, $customers)
                {
                    $query = "
                        SELECT costumer, COUNT(*) AS count
                        FROM booking
                        WHERE status = 'Active'
                          AND (quantity - quantity_use) > 0
                          AND costumer <> ''
                        GROUP BY costumer
                    ";

                    $result = $conn->query($query);

                    // Initialize counts for all customers (codes from DB)
                    $counts = array_fill_keys($customers, 0);

                    while ($row = ($result)->fetch()) {
                        $cust = $row['costumer'];
                        if (array_key_exists($cust, $counts)) {
                            $counts[$cust] = $row['count'];
                        }
                    }

                    return $counts;
                }

                // If this is an AJAX request, return JSON and exit
                if (isset($_GET['ajax'])) {
                    echo json_encode(getCounts($conn, $customers));
                    exit;
                }

                // Otherwise, normal page load
                $counts = getCounts($conn, $customers);
                ?>

              <div class="col-lg-2">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="d-flex align-items-start">
                      <div>
                        <h4 class="card-title">Booking Overview</h4>
                        <p class="card-subtitle">Active Booking Count by Customer</p>
                      </div>
                    </div>

                    <?php foreach ($customers as $cust): ?>
                      <div class="py-3 d-flex align-items-center customer-filter" data-customer="<?= htmlspecialchars($cust) ?>" style="cursor: pointer;">
                        <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-clipboard-check fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4"><?= htmlspecialchars($cust) ?></h5>
                        </div>
                        <div class="ms-auto">
                          <span class="badge bg-secondary-subtle text-muted" id="count-<?= htmlspecialchars($cust) ?>"><?= $counts[$cust] ?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>

                  </div>
                </div>
              </div>

              <div class="col-lg-10">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Transaction</h4>
                        <p class="card-subtitle">
                          Driver and Vehicle Transaction
                        </p>
                      </div>
                    </div>
                    <div class="table-responsive mt-4" style="overflow: hidden;">
                      <table id="table-data" class="table mb-0 text-nowrap varient-table align-middle fs-3">
                        <thead>
                          <tr>
                             <th class="px-0 text-muted"></th>
                            <th class="px-0 text-muted">Assigned</th>
                            <th class="px-0 text-muted">Date/Time</th>
                            <th class="px-0 text-muted text-center">Trip</th>
                            <th class="px-0 text-muted text-center">Hauling</th>
                            <th class="px-0 text-muted">Dispatched By</th>
                            <th class="px-0 text-muted text-end">Actions</th>
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


    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <form id="editForm" action="php/updateRecord.php" method="POST" enctype="multipart/form-data">
            <div class="modal-header">
              <h5 class="modal-title" id="editModalLabel">Dispatch Record</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
              <input type="hidden" name="record_id" id="edit_record_id">
              <div class="row">
                <div class="col-md-6 mb-2">
                  <label class="form-label">Drivers Name<span style="color: red;">*</span></label>
                  <input class="form-control" list="datalistOptions_driverName" name="drivers_name1" id="edit_drivers_name" required>
                  <datalist id="datalistOptions_driverName"></datalist>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Truck</label>
                  <input class="form-control" list="datalistOptions_truck" name="truck1" id="edit_truck" required>
                  <datalist id="datalistOptions_truck"></datalist>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Trip Receipt</label>
                  <input type="text" class="form-control" id="edit_tr" name="tr1" required>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">ECS</label>
                  <input type="text" class="form-control" id="edit_ecs" name="ecs1">
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Trailer</label>
                  <input class="form-control" list="datalistOptions_trailer" name="trailer1" id="edit_trailer" required>
                  <datalist id="datalistOptions_trailer"></datalist>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Genset</label>
                  <input class="form-control" list="datalistOptions_genset" name="genset1" id="edit_genset" required>
                  <datalist id="datalistOptions_genset"></datalist>
                </div>

                <input type="text" name="shippingSN1" id="shippingSN1" hidden>
              </div>

              <hr class="my-3">
              <div class="d-flex align-items-center mb-2">
                <h6 class="mb-0">Trips</h6>
                <small class="ms-2 text-muted">All trips under this dispatch &mdash; Phase 2 segments included.</small>
              </div>
              <!-- Dynamic trip fieldsets rendered by editDispatch() via JS. -->
              <div id="dynamicTripsBox"></div>
            </div>

            <div class="modal-footer">
              <button name="submit" id="printRecord" class="btn btn-success">Print</button>
              <button name="submit" id="editRecord" class="btn btn-primary">Update Dispatch</button>
            </div>

            <!-- Shared Datalist for Status -->
            <datalist id="containerStat">
              <option value="EMPTY">
              <option value="LOADED">
            </datalist>
          </form>
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
              <button type="button" id="doneBtn" class="btn btn-success" style="display:none;">Mark Delivered</button>
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
    <script src="js/dispatch-monitoring.js"></script>
    <script>
      $('#printRecord').click(function(e) {
        e.preventDefault();

        var id = $("#edit_record_id").val();
        var shippingSN1 = $("#shippingSN1").val().trim(); // get value and remove spaces

        setTimeout(() => {
          if (shippingSN1 !== "") {
            // if shippingSN1 is not empty
            window.open("dispatcher-index.php?route=printcth&id=" + id, "_blank");
          } else {
            // if shippingSN1 is empty
            window.open("index.php?route=print&id=" + id, "_blank");
          }
          location.reload();
        }, 1300);
      });
       $(document).on('click', '.view-time', function () {
        let tripId = $(this).data('id');
        $('#trip_id').val(tripId);

        // Reset buttons while loading
        $('#saveBtn').show();
        $('#doneBtn').hide();

        // Fetch trip data via AJAX
        $.getJSON('php/fetch/get_tripDateTime.php', { trip_id: tripId }, function (data) {
          if (data.status === 'success') {
            // Fill datetime fields if available
            $('#arrival_cy').val(data.trip.trip_arrivalDateTime || '');
            $('#departure').val(data.trip.trip_departureDateTime || '');
            $('#arrival_ph').val(data.trip.trip_pharrivalDateTime || '');

            // Check inputs to toggle Save/Done
            checkInputs();
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: data.message
            });
          }
        });

        $('#timeModal').modal('show');
      });

      // Function to check inputs
      function checkInputs() {
        let allFilled = $('#arrival_cy').val() && $('#departure').val() && $('#arrival_ph').val();
        
        if (allFilled) {
          $('#saveBtn').hide();
          $('#doneBtn').show();
        } else {
          $('#saveBtn').show();
          $('#doneBtn').hide();
        }
      }

      // Bind input events
      $('#arrival_cy, #departure, #arrival_ph').on('input change', checkInputs);

      // Save button click
      $('#saveBtn').on('click', function () {
        let tripData = {
          trip_id: $('#trip_id').val(),
          arrival_cy: $('#arrival_cy').val(),
          departure: $('#departure').val(),
          arrival_ph: $('#arrival_ph').val(),
          action: 'save'
        };

        $.post('php/crud/update/update_tripDateTime.php', tripData, function (response) {
          if (response.status === 'success') {
            Swal.fire({
              icon: 'success',
              title: 'Saved!',
              text: response.message,
              timer: 2000,
              showConfirmButton: false
            }).then(() => {
              $('#timeModal').modal('hide');
              location.reload();
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: response.message
            });
          }
        }, 'json');
      });

      // Delivered button click
      $('#doneBtn').on('click', function () {
        let tripData = {
          trip_id: $('#trip_id').val(),
          arrival_cy: $('#arrival_cy').val(),
          departure: $('#departure').val(),
          arrival_ph: $('#arrival_ph').val(),
          action: 'done'
        };

        $.post('php/crud/update/update_tripDateTime.php', tripData, function (response) {
          if (response.status === 'success') {
            Swal.fire({
              icon: 'success',
              title: 'Trip Delivered!',
              text: response.message,
              timer: 2000,
              showConfirmButton: false
            }).then(() => {
              $('#timeModal').modal('hide');
              location.reload();
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: response.message
            });
          }
        }, 'json');
      });
    </script>
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  </body>
  

  </html>
<?php
} else {
  header("Location: dispatcher-index.php?route=login");
  exit();
} ?>
