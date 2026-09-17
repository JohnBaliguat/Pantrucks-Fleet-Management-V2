<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Booking</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="datatable/datatables.min.css">
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
      .booking-count {
        width: 32px;
        height: 32px;
        background: #0d6efd;
        color: #fff;
        border-radius: 50%;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
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
            <!--  Row 1 -->
            <div class="row">
              <div class="col-md-12">
                <div class="row">
                  
                </div>
              </div>

              <!-- BOOKING -->
              <div class="col-lg-4">
                <div class="modern-card">
                  <div class="card-header">
                    <div>
                      <h4>Booking List</h4>
                      <p>List of booking</p>
                    </div>
                    <input type="text" id="bookingSearch" placeholder="Search customer...">
                  </div>

                  <div class="card-body scroll-area mb-5">

                    <?php
                    include "php/config/config.php";
                    $q = $conn->query("SELECT * FROM booking WHERE status='Active' AND quantity <> quantity_use AND costumer!='CTH' ORDER BY booking_date DESC");
                    while ($b = ($q)->fetch()) {
                    ?>
                      <div class="booking-item"
                        draggable="true"
                        data-booking="<?= $b['booking_no']; ?>">
                        <div class="booking-left">
                          <div class="row">
                            <div class="col-md-12"><span class="badge"><?= $b['booking_no']; ?></span></div>
                            <div class="col-md-12"><p><?= $b['trip_from']; ?> to <?= $b['trip_to']; ?></p></div>
                            <div class="col-md-12"><p><?= $b['container']; ?></p></div>
                          </div>
                          <small><strong>Booking Date:</strong> <?= date('M d, Y', strtotime($b['booking_date'])); ?></small>
                          <small><strong>Required Date:</strong> <?= date('M d, Y', strtotime($b['booking_daterequired'])); ?></small>
                        </div>

                        <div class="booking-right">
                          <span class="company"><?= $b['costumer']; ?></span>
                          <span class="van">-</span>
                          <strong><?= $b['quantity'] - $b['quantity_use']; ?>/<?= $b['quantity']; ?></strong>
                        </div>
                      </div>
                    <?php } ?>

                  </div>
                </div>
              </div>

              <!-- TRUCK -->
              <div class="col-lg-4">
                <div class="modern-card">
                  <div class="row m-2">
                    <div class="col-md-12">
                      <div class="card-header">
                        <div>
                          <h4>Truck</h4>
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
                              'Available' => 0,
                              'Dispatch' => 0,
                              'Shop Unit' => 0,
                              'Rescue Unit' => 0,
                            ];

                            $q_count = $conn->query("SELECT unit_status, COUNT(*) AS total FROM units WHERE unit_name NOT LIKE 'GS%' AND unit_name NOT LIKE 'FL%' GROUP BY unit_status");
                            while($c = ($q_count)->fetch()) {
                                $counts[$c['unit_status']] = $c['total'];
                            }

                            $totalUnits = array_sum($counts);
                          ?>

                          <span class="legend-item active" data-filter="all">All (<?= $totalUnits ?>)</span>
                          <span class="legend-item status-good" data-filter="Available">Available (<?= $counts['Available'] ?>)</span>
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
                        'Available' => 'status-good',
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
                        🚚 <?= $u['unit_name']; ?>
                      </div>
                    <?php } ?>
                  </div>
                </div>
              </div>

              <!-- DRIVERS -->
              <div class="col-lg-4">
                <div class="modern-card">
                  <div class="row">
                    <div class="col-md-12">
                      <div class="card-header">
                        <div>
                          <h4>Drivers</h4>
                          <p>List of Present Driver</p>
                        </div>
                        <input type="text" id="driverSearch" placeholder="Search driver...">
                      </div>
                    </div>
                    <div class="col-md-12 m-2">
                      <div class="driver-legend">
                        <span class="legend-item-driver active" data-filter="all">All</span>
                        <span class="legend-item-driver status-not-dispatch" data-filter="not-dispatch">
                          Not Dispatch
                        </span>
                        <span class="legend-item-driver status-dispatch" data-filter="dispatch">
                          Dispatch
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="card-body scroll-area" id="driverListAttend">
                    <!-- realtime drivers here -->
                  </div>
                </div>
              </div>

              <div class="col-lg-12 mt-5">
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
                      <table id="table-data" class="table table-hover mb-0 text-nowrap varient-table align-middle fs-3">
                        <thead>
                          <tr>
                            <th class="px-0 text-muted"></th>
                            <th class="px-0 text-muted">Assigned</th>
                            <th class="px-0 text-muted">Dispatch Hub</th>
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


              <div class="py-6 px-6 text-center">
                <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal fade" id="assignModal1" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Assign Booking to Unit</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <form action="php/operations/assign_booking.php" method="post">
                <input type="hidden" id="assinglocation" name="assinglocation" value="<?php echo $location; ?>">
                <input type="hidden" class="form-control" id="userName" name="userName" value="<?php echo strtoupper($data['user_lname']) . ', ' . strtoupper(substr($data['user_fname'], 0, 1)) . '' . strtoupper(substr($data['user_mname'], 0, 1)); ?>" required>
                <div class="mb-3">
                  <label class="form-label">Unit</label>
                  <input type="text" id="assignUnitName1" name="unit_name" class="form-control" readonly>
                  <!-- <input type="hidden" id="assignUnitName2" name="unit_name" class="form-control" readonly> -->
                </div>
                <div class="mb-3" hidden>
                  <label class="form-label">Booking</label>
                  <input type="text" id="assignBookingName" name="booking_no" class="form-control" readonly>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Trip Receipt<span style="color: red;">*</span></label>
                      <input type="text" id="trip_receipt" name="trip_receipt" class="form-control">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">ECS</label>
                      <input type="text" id="ecs" name="ecs" class="form-control">
                    </div>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Driver<span style="color: red;">*</span></label>
                  <input type="text" id="driver" name="driver" class="form-control" autocomplete="off" required>
                  <input type="hidden" id="driverId" name="driver_id">

                  <!-- Dropdown list -->
                  <ul id="driverList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                </div>
                <div class="mb-3 position-relative">
                  <label class="form-label">Genset</label>
                  <input type="text" id="genset" name="genset" class="form-control" autocomplete="off" required>
                  <ul id="gensetList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                </div>

                <div class="mb-3 position-relative">
                  <label class="form-label">Trailer</label>
                  <input type="text" id="trailer" name="trailer" class="form-control" autocomplete="off" required>
                  <ul id="trailerList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-primary" id="confirmAssignBtn1">Assign</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <div class="modal fade" id="unitDispatchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Dispatch Data - <span id="modalUnitName"></span></h5>
              <button type="button" class="btn btn-primary btn-sm" id="markAvailable">Mark As Available</button>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <table class="table table-striped table-bordered">
                <thead>
                  <tr>
                    <th>Booking No</th>
                    <th>Datetime</th>
                    <th>Dispatcher</th>
                    <th>Driver</th>
                    <th>Truck</th>
                    <th>Trailer</th>
                    <th>Trip Type</th>
                    <th>Container</th>
                    <th>Segment</th>
                    <th>From</th>
                    <th>To</th>
                  </tr>
                </thead>
                <tbody id="dispatchTableBody">
                  <tr><td colspan="13" class="text-center">Select a unit...</td></tr>
                </tbody>
              </table>
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
                  <label class="form-label">Drivers Name</label>
                  <input class="form-control" list="datalistOptions_driverName" name="drivers_name1" id="edit_drivers_name" readonly>
                  <datalist id="datalistOptions_driverName"></datalist>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Truck</label>
                  <input class="form-control" list="datalistOptions_truck" name="truck1" id="edit_truck" readonly>
                  <datalist id="datalistOptions_truck"></datalist>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Trip Receipt</label>
                  <input type="text" class="form-control" id="edit_tr" name="tr1">
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">ECS</label>
                  <input type="text" class="form-control" id="edit_ecs" name="ecs1">
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Trailer</label>
                  <input class="form-control" list="datalistOptions_trailer" name="trailer1" id="edit_trailer" readonly>
                  <datalist id="datalistOptions_trailer"></datalist>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Genset</label>
                  <input class="form-control" list="datalistOptions_genset" name="genset1" id="edit_genset" readonly>
                  <datalist id="datalistOptions_genset"></datalist>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Trip 1 - Container No</label>
                  <input type="text" class="form-control" id="edit_container_no" name="container_no1">
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Trip 1 - Container Status E/L</label>
                  <input type="text" class="form-control" id="edit_container_status" name="container_status1" list="containerStat">
                </div>

                <div class="col-md-12 mb-2">
                  <label class="form-label">Hauling Segment Trip 1</label>
                  <input class="form-control" list="datalistOptions_hauling_segment" name="hauling_segment1" id="edit_hauling_segment" required>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">1st Trip - Destination From</label>
                  <input class="form-control" list="datalistOptions_destination_from" name="destination_from1" id="edit_destination_from" required>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">To</label>
                  <input class="form-control" list="datalistOptions_destination_to" name="destination_to1" id="edit_destination_to" required>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Trip 2 - Container No</label>
                  <input type="text" class="form-control" id="edit_container_no2" name="container_no2">
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">Trip 2 - Container Status E/L</label>
                  <input type="text" class="form-control" id="edit_container_status2" name="container_status2" list="containerStat">
                </div>

                <div class="col-md-12 mb-2">
                  <label class="form-label">Hauling Segment Trip 2(Optional)</label>
                  <input class="form-control" list="datalistOptions_hauling_segment" name="hauling_segment2" id="edit_hauling_segment2">
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">2nd Trip - Destination From</label>
                  <input class="form-control" list="datalistOptions_destination_from1" name="destination_from12" id="edit_destination_from1" required>
                </div>

                <div class="col-md-6 mb-2">
                  <label class="form-label">To</label>
                  <input class="form-control" list="datalistOptions_destination_to1" name="destination_to12" id="edit_destination_to1" required>
                </div>
              </div>
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
              <button type="button" id="doneBtn" class="btn btn-success" style="display:none;">Done</button>
            </div>

          </div>
        </div>
      </div>

      <div class="modal fade" id="driverTripsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title" id="driverTripsTitle">Driver Trips</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <table class="table table-bordered table-sm">
                <thead class="table-light">
                  <tr>
                    <th>Booking No</th>
                    <th>Hauling Segment</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="driverTripsBody">
                  <tr>
                    <td colspan="5" class="text-center text-muted">Loading...</td>
                  </tr>
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
      <script src="assets/js/dashboard.js"></script>
      <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
      <script src="datatable/datatables.min.js"></script>

      <!-- solar icons -->
      <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
      <script>
        /* BOOKING SEARCH */
        document.getElementById('bookingSearch').addEventListener('keyup', function () {
          const searchValue = this.value.toLowerCase();
          const bookings = document.querySelectorAll('.booking-item');

          bookings.forEach(item => {
            const text = item.innerText.toLowerCase();
            item.style.display = text.includes(searchValue) ? 'flex' : 'none';
          });
        });

        /* TRUCK SEARCH */
        document.getElementById('unitSearch').addEventListener('keyup', function () {
          const searchValue = this.value.toLowerCase();
          const trucks = document.querySelectorAll('.truck-card');

          trucks.forEach(card => {
            const unitName = card.dataset.unit.toLowerCase();
            card.style.display = unitName.includes(searchValue) ? 'block' : 'none';
          });
        });


       let selectedBooking = '';
       let selectedUnit = '';

        document.querySelectorAll('.booking-item').forEach(item => {
          item.addEventListener('dragstart', () => {
            selectedBooking = item.dataset.booking;
          });
        });

        document.querySelectorAll('.truck-card').forEach(card => {

          card.addEventListener('dragover', e => {
            e.preventDefault();
            card.classList.add('drag-over');
          });

          card.addEventListener('dragleave', () => {
            card.classList.remove('drag-over');
          });

          card.addEventListener('drop', () => {
            card.classList.remove('drag-over');

            const unitId = card.dataset.unitId;
            selectedUnit = card.dataset.unit;
            

            /* CHECK FIRST */
            fetch(`php/operations/check_unit_assign.php?unit_id=${unitId}`)
              .then(res => res.json())
              .then(resp => {

                if (!resp.allowed) {
                  Swal.fire({
                    icon: 'warning',
                    title: 'Assignment Not Allowed',
                    text: resp.message
                  });
                  return;
                }

                // FILL MODAL
                document.getElementById('assignUnitName1').value = selectedUnit;
                document.getElementById('assignBookingName').value = selectedBooking;

                // SHOW MODAL
                new bootstrap.Modal(
                  document.getElementById('assignModal1')
                ).show();

              })
              .catch(() => {
                Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: 'Unable to validate unit. Please try again.'
                });
              });
          });

        });

        document.querySelectorAll('.truck-card').forEach(card => {

          card.addEventListener('click', function (e) {

            // Prevent click firing while dragging
            if (card.classList.contains('drag-over')) return;

            const unitId = this.dataset.unitId;
            const unitName = this.dataset.unitName;

            showUnitDispatch(unitId, unitName);
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

        $(document).ready(function () {
          $('#confirmAssignBtn1').click(function (e) {
              e.preventDefault();

              var booking_no = $("#assignBookingName").val();
              var trip_receipt = $("#trip_receipt").val();
              var ecs = $("#ecs").val();
              var driver = $("#driver").val();
              var driver_id = $("#driverId").val();
              var genset = $("#genset").val();
              var trailer = $("#trailer").val();
              var assignLocation = $("#assinglocation").val();
              var userName = $("#userName").val();
              var unitName = $("#assignUnitName1").val();

              if (!trip_receipt) {
                  Swal.fire({
                      text: 'Required Trip Receipt',
                      icon: 'info'
                  });
                  return;
              }

              if (!booking_no || !driver || !driver_id || !unitName) {
                  Swal.fire({
                      text: 'Please fill in all required fields',
                      icon: 'info'
                  });
                  return;
              }

              /* ===============================
              CHECK DRIVER VIOLATIONS FIRST
              =============================== */
              $.ajax({
                  url: 'php/operations/check_driver_violation.php',
                  type: 'POST',
                  data: { driver_id: driver_id },
                  dataType: 'json',
                  success: function (res) {

                      if (res.status === "violation") {

                          let violationText = '';

                          res.violations.forEach(function (v, i) {
                              violationText += `
                                  <div style="text-align:center; margin-bottom:8px;">
                                      <strong>${v.vr_type}</strong><br>
                                      ${v.vr_description}<br>
                                      <small>Date: ${v.vr_date}</small>
                                  </div>
                                  <hr>
                              `;
                          });

                          Swal.fire({
                              icon: 'warning',
                              title: 'DISPATCH BLOCKED 🚫',
                              html: `
                                  <p><strong>Driver has ACTIVE violation(s):</strong></p>
                                  ${violationText}
                                  <br>
                                  <strong style="color:red;">
                                      Please go to HR to clear the driver violation.
                                  </strong>
                              `,
                              confirmButtonText: 'OK'
                          });

                          return;
                      }

                      /* ===============================
                      PROCEED TO CONFIRM ASSIGNMENT
                      =============================== */
                      Swal.fire({
                          title: 'Confirm Assignment',
                          icon: 'question',
                          showCancelButton: true,
                          confirmButtonText: 'Confirm'
                      }).then((result) => {

                          if (!result.isConfirmed) return;

                          var formData = new FormData();
                          formData.append('booking_no', booking_no);
                          formData.append('trip_receipt', trip_receipt);
                          formData.append('ecs', ecs);
                          formData.append('driver', driver);
                          formData.append('driver_id', driver_id);
                          formData.append('genset', genset);
                          formData.append('trailer', trailer);
                          formData.append('assignLocation', assignLocation);
                          formData.append('userName', userName);
                          formData.append('unitName', unitName);

                          $.ajax({
                              url: 'php/operations/assign_booking.php',
                              type: 'POST',
                              data: formData,
                              contentType: false,
                              processData: false,
                              dataType: 'json',
                              success: function (data) {

                                  if (data.status === "success") {
                                      Swal.fire({
                                          text: data.message,
                                          icon: 'success',
                                          showConfirmButton: false,
                                          timer: 1200
                                      });

                                      $('#assignModal1').modal("hide");

                                      setTimeout(() => {
                                          window.open(
                                              "dispatcher-index.php?route=print&id=" + data.insert_id,
                                              "_blank"
                                          );
                                          location.reload();
                                      }, 1300);

                                  } else {
                                      Swal.fire({
                                          text: data.message,
                                          icon: 'error'
                                      });
                                  }
                              }
                          });
                      });
                  }
              });
          });
      });

      // SEARCH
      document.addEventListener("DOMContentLoaded", function () {
          // ===== Driver Search =====
          const driverInput = document.getElementById("driver");
          const driverIdInput = document.getElementById("driverId");
          const driverList = document.getElementById("driverList");
          let allDrivers = [];

          fetch("php/fetch/get_drivers.php")
              .then(res => res.json())
              .then(data => { allDrivers = data; });

          driverInput.addEventListener("input", function () {
              filterDropdown(this, driverList, allDrivers.map(d => d.name), (name) => {
                  driverInput.value = name;
                  const selected = allDrivers.find(d => d.name === name);
                  driverIdInput.value = selected ? selected.id : "";
              });
          });

          // ===== Genset Search =====
          const gensetInput = document.getElementById("genset");
          const gensetList = document.getElementById("gensetList");
          let allGensets = [];

          fetch("php/fetch/get_gensets.php")
              .then(res => res.json())
              .then(data => { allGensets = data; });

          gensetInput.addEventListener("input", function () {
              filterDropdown(this, gensetList, allGensets, (name) => {
                  gensetInput.value = name;
              });
          });

          // ===== Trailer Search =====
          const trailerInput = document.getElementById("trailer");
          const trailerList = document.getElementById("trailerList");
          let allTrailers = [];

          fetch("php/fetch/get_trailers.php")
              .then(res => res.json())
              .then(data => { allTrailers = data; });

          trailerInput.addEventListener("input", function () {
              filterDropdown(this, trailerList, allTrailers, (name) => {
                  trailerInput.value = name;
              });
          });

          // ===== Shared Function =====
          function filterDropdown(inputElem, listElem, dataArr, onSelect) {
              const searchVal = inputElem.value.toLowerCase();
              listElem.innerHTML = "";

              if (!searchVal) {
                  listElem.style.display = "none";
                  return;
              }

              const filtered = dataArr.filter(item => item.toLowerCase().includes(searchVal));

              if (filtered.length === 0) {
                  listElem.style.display = "none";
                  return;
              }

              filtered.forEach((item, index) => {
                  const li = document.createElement("li");
                  li.className = "list-group-item";
                  li.textContent = item;

                  // highlight first suggestion
                  if (index === 0) {
                      li.classList.add("active-suggestion");
                  }

                  li.addEventListener("click", function () {
                      onSelect(item);
                      listElem.style.display = "none";
                  });
                  listElem.appendChild(li);
              });

              listElem.style.display = "block";
          }

          // ===== Autofill + Navigation Support =====
          function attachKeyboardNav(inputElem, listElem, onSelect) {
              let activeIndex = 0;

              inputElem.addEventListener("keydown", function (e) {
                  const items = listElem.querySelectorAll("li");
                  if (!items.length) return;

                  if (e.key === "ArrowDown") {
                      e.preventDefault();
                      activeIndex = (activeIndex + 1) % items.length;
                      updateActive(items, activeIndex);
                  } 
                  else if (e.key === "ArrowUp") {
                      e.preventDefault();
                      activeIndex = (activeIndex - 1 + items.length) % items.length;
                      updateActive(items, activeIndex);
                  } 
                  else if (e.key === "Enter" || e.key === "Tab") {
                      const activeItem = items[activeIndex];
                      if (activeItem) {
                          onSelect(activeItem.textContent);
                          listElem.style.display = "none";
                      }
                  }
              });

              function updateActive(items, index) {
                  items.forEach(i => i.classList.remove("active-suggestion"));
                  items[index].classList.add("active-suggestion");
              }
          }

          // Attach keyboard nav to each input/list
          attachKeyboardNav(driverInput, driverList, (val) => {
              driverInput.value = val;
              const selected = allDrivers.find(d => d.name === val);
              driverIdInput.value = selected ? selected.id : "";
          });

          attachKeyboardNav(gensetInput, gensetList, (val) => {
              gensetInput.value = val;
          });

          attachKeyboardNav(trailerInput, trailerList, (val) => {
              trailerInput.value = val;
          });

          // ===== Hide all dropdowns on click outside =====
          document.addEventListener("click", function (e) {
              [driverList, gensetList, trailerList].forEach(list => {
                  if (!list.contains(e.target) &&
                      !driverInput.contains(e.target) &&
                      !gensetInput.contains(e.target) &&
                      !trailerInput.contains(e.target)) {
                      list.style.display = "none";
                  }
              });
          });
      });


      function showUnitDispatch(unitId, unitName) {
          currentUnitId = unitId;

          document.getElementById("modalUnitName").textContent = unitName;

          const markBtn = document.getElementById("markAvailable");
          markBtn.style.display = "none"; // hide by default

          const tbody = document.getElementById("dispatchTableBody");
          tbody.innerHTML = `<tr><td colspan="13" class="text-center">Loading...</td></tr>`;

          fetch("php/fetch/fetch_dispatch.php?unit_id=" + unitId)
            .then(res => res.json())
            .then(data => {

                const markBtn = document.getElementById("markAvailable");

                // 👇 Hide if already Available
                if (data.unit_status === "Available" || data.unit_status === "Dispatch" || data.unit_status === "Shop Unit" || data.unit_status === "Rescue Unit") {
                    markBtn.style.display = "none";
                } else {
                    markBtn.style.display = "inline-block";
                }

                if (data.dispatch.length > 0) {
                    tbody.innerHTML = "";
                    data.dispatch.forEach(row => {
                        tbody.innerHTML += `
                        <tr>
                            <td>${row.booking_no}</td>
                            <td>${row.d_datetime}</td>
                            <td>${row.d_dispatcher}</td>
                            <td>${row.d_driverName}</td>
                            <td>${row.d_truck}</td>
                            <td>${row.d_trailer ?? ''}</td>
                            <td>${row.trip_type}</td>
                            <td>${row.trip_container} (${row.trip_containerStat})</td>
                            <td>${row.trip_haulingSegment} - ${row.trip_haulingType}</td>
                            <td>${row.trip_from}</td>
                            <td>${row.trip_to}</td>
                        </tr>`;
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="13" class="text-center">No dispatch records found.</td></tr>`;
                }
            })
              .catch(() => {
                  tbody.innerHTML = `<tr><td colspan="13" class="text-center text-danger">Error loading data</td></tr>`;
              });

          new bootstrap.Modal(document.getElementById("unitDispatchModal")).show();
      }

    document.getElementById("markAvailable").addEventListener("click", function () {
        if (!currentUnitId) return;

        if (!confirm("Mark this unit as AVAILABLE?")) return;

        fetch("php/crud/update/update_unit_status.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "unit_id=" + currentUnitId
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Unit marked as AVAILABLE");

                // Optional: close modal
                bootstrap.Modal.getInstance(
                    document.getElementById("unitDispatchModal")
                ).hide();

                // Optional: reload unit list / dashboard
                location.reload();
            } else {
                alert("Failed to update unit status");
            }
        })
        .catch(() => alert("Server error"));
    });


      // DRIVERS ATTENDANCE
      function loadDrivers() {
        fetch("php/fetch/fetch_present_drivers.php")
          .then(res => res.json())
          .then(data => {
            const list = document.getElementById("driverListAttend");
            list.innerHTML = "";

            if (data.length === 0) {
              list.innerHTML = "<p class='text-muted'>No present drivers</p>";
              return;
            }

            data.forEach(driver => {
              const timeIn = new Date(driver.da_timeIn).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
              });

              let dispatchInfo = "";
              if (driver.first_dispatch) {
                const dispatchTime = new Date(driver.first_dispatch).toLocaleTimeString([], {
                  hour: '2-digit',
                  minute: '2-digit'
                });
                dispatchInfo = ` | Dispatched: ${dispatchTime}`;
              }

              const isDispatch = driver.has_dispatch == 1;
              const activeClass = isDispatch ? "active" : "";
              const status = isDispatch ? "dispatch" : "not-dispatch";

              list.innerHTML += `
                <div class="driver-card ${activeClass}" 
                    data-driver-id="${driver.driver_id}"
                    data-driver-name="${driver.driver_name}">
                    
                    <div class="booking-count">
                      ${driver.active_booking_count}
                    </div>

                    <div class="avatar">👤</div>

                    <div>
                      <strong>${driver.driver_name}</strong>
                      <p>Time In: ${timeIn}${dispatchInfo}</p>
                    </div>
                </div>
              `;
            });
          });
      }

      // realtime refresh
      loadDrivers();
      setInterval(loadDrivers, 30000);

      // search
      document.getElementById("driverSearch").addEventListener("keyup", function () {
        const search = this.value.toLowerCase();
        document.querySelectorAll(".driver-card").forEach(card => {
          card.style.display = card.dataset.name.includes(search) ? "flex" : "none";
        });
      });

      // DRIVERS MODAL TRIP

      document.addEventListener("click", function (e) {

      const card = e.target.closest(".driver-card");
      if (!card) return;

      const driverId = card.dataset.driverId;
      const driverName = card.dataset.driverName;

      document.getElementById("driverTripsTitle").textContent =
        `Current Trips - ${driverName}`;

      const tbody = document.getElementById("driverTripsBody");
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center text-muted">Loading...</td>
        </tr>
      `;

      fetch(`php/fetch/fetch_driver_trips.php?driver_id=${driverId}`)
        .then(res => res.json())
        .then(data => {

          tbody.innerHTML = "";

          if (data.length === 0) {
            tbody.innerHTML = `
              <tr>
                <td colspan="5" class="text-center text-muted">
                  No active trips
                </td>
              </tr>
            `;
            return;
          }

          data.forEach(trip => {
            tbody.innerHTML += `
              <tr>
                <td>${trip.booking_no}</td>
                <td>${trip.trip_haulingSegment}</td>
                <td>${trip.trip_from}</td>
                <td>${trip.trip_to}</td>
                <td>
                  <span class="badge bg-success">${trip.trip_status}</span>
                </td>
              </tr>
            `;
          });
        });

      new bootstrap.Modal(document.getElementById("driverTripsModal")).show();
    });


      document.querySelectorAll(".driver-legend .legend-item-driver").forEach(item => {
        item.addEventListener("click", function () {

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

      // TABLE

      const table = new DataTable('#table-data', {
        processing: true,
        serverSide: true,
        ajax: {
          url: 'table-fetch/dispatch-table.php',
          type: 'POST'
        },
        responsive: true,
        columnDefs: [{
            targets: [0, 2, 3, 4, 5],
            className: 'px-0'
          },
          {
            targets: [2, 3, 4, 5],
            className: 'text-center'
          }
        ]
      });

      $(document).ready(function() {

      $('#editRecord').click(function(e) {
        e.preventDefault(); // Prevent default form submit

        var formData = new FormData($('#editForm')[0]);

        Swal.fire({
          title: 'Confirm Update Dispatch',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: 'php/crud/update/updateRecord.php',
              type: 'POST',
              data: formData,
              contentType: false,
              processData: false,
              success: function(response) {
                Swal.fire({
                  text: response,
                  icon: 'success',
                  showConfirmButton: false,
                  timer: 1200
                });
                $('#editModal').modal('hide');
                setTimeout(() => {
                  location.reload();
                }, 1300);
              },
              error: function(xhr, status, error) {
                Swal.fire({
                  text: 'Error: ' + error,
                  icon: 'error'
                });
              }
            });
          }
        });
      });
      $('#printRecord').click(function(e) {
        e.preventDefault();
        var id = $("#edit_record_id").val();
        setTimeout(() => {
          window.open("index.php?route=print&id=" + id, "_blank");
          location.reload();
        }, 1300);

      });
    });

    function editDispatch(d_id) {
      $.ajax({
        url: 'php/fetch/get_dispatch.php',
        type: 'POST',
        data: {
          d_id: d_id
        },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            const dispatch = response.dispatch;
            const trip1 = response.trip1;
            const trip2 = response.trip2;

            $('#edit_record_id').val(dispatch.d_id);
            $('#edit_drivers_name').val(dispatch.d_driverName);
            $('#edit_truck').val(dispatch.d_truck);
            $('#edit_trailer').val(dispatch.d_trailer);
            $('#edit_genset').val(dispatch.d_genset);
            $('#edit_tr').val(dispatch.d_tripReceipt);
            $('#edit_ecs').val(dispatch.d_ecs);

            // Trip 1
            if (trip1) {
              $('#edit_container_no').val(trip1.trip_container);
              $('#edit_container_status').val(trip1.trip_containerStat);
              $('#edit_hauling_segment').val(trip1.trip_haulingSegment);
              $('#edit_destination_from').val(trip1.trip_from);
              $('#edit_destination_to').val(trip1.trip_to);
            }

            // Trip 2
            if (trip2) {
              $('#edit_container_no2').val(trip2.trip_container);
              $('#edit_container_status2').val(trip2.trip_containerStat);
              $('#edit_hauling_segment2').val(trip2.trip_haulingSegment);
              $('#edit_destination_from1').val(trip2.trip_from);
              $('#edit_destination_to1').val(trip2.trip_to);
            }

            $('#editModal').modal('show');
          } else {
            alert('Failed to fetch dispatch data.');
          }
        },
        error: function(xhr, status, error) {
          console.error('AJAX Error:', error);
          alert('An error occurred while fetching data.');
        }
      });
    }

    function deleteDispatch(d_id) {
      var d_Id = d_id;

      var form_data = {
        d_Id: d_Id

      };
      Swal.fire({
        title: 'Confirm Remove Record',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Confirm'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: "php/crud/delete/deleteRecord.php",
            type: "POST",
            data: form_data,
            dataType: "json",
            success: function(response) {
              if (response['valid'] == false) {
                Swal.fire({
                  text: response['msg'],
                  icon: 'warning'
                });
              } else {
                Swal.fire({
                  text: response['msg'],
                  icon: 'success',
                  showConfirmButton: false,
                  timer: 1200
                });
                setTimeout(() => {
                  location.reload();
                }, 1300);
              }
            }

          });
        }
      });
    }

    function markDone(id) {
      Swal.fire({
        title: 'Are you sure?',
        text: "This dispatch and trips will be marked as Done.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, mark as Done'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: 'php/crud/update/update_status.php',
            type: 'POST',
            data: {
              id: id
            },
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                Swal.fire(
                  'Updated!',
                  'The dispatch and trips are marked as Done.',
                  'success'
                ).then(() => {
                  $('#yourDataTableID').DataTable().ajax.reload(); // reload datatable
                });
              } else {
                Swal.fire('Error!', 'Failed to update status.', 'error');
              }
            },
            error: function() {
              Swal.fire('Error!', 'AJAX request failed.', 'error');
            }
          });
        }
      });
    }
      </script>
  </body>

  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
} ?>
