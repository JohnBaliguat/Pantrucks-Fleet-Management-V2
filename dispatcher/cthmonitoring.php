<?php
session_start();

if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['Dispatcher', 'Dispatch Admin', 'Booker'], true)) {
  $role = 'dispatcher';
  include __DIR__ . '/../php/assets/cth_monitoring_body.php';
  exit;


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CTH Monitoring</title>
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

      .trailer-card,
      .filter-item {
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

      .trailer-card:hover,
      .filter-item:hover {
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

      .collapsed .arrow-icon {
        transform: rotate(0deg);
      }

      .expanded .arrow-icon {
        transform: rotate(180deg);
      }

      .history-table th,
      .history-table td {
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
                    <div class="row mb-3">
                      <div class="col-md-9">
                        <h4 class="card-title">CTH Booking Overview</h4>
                        <p class="card-subtitle">Bookings grouped by SN - Complete and Incomplete Summary</p>
                      </div>
                      <div class="col-md-3 text-end">
                        <input type="text" id="containerFilter" class="form-control" placeholder="Filter Shipping No...">
                      </div>
                    </div>

                    <div class="container-history">
                      <?php
                      // ✅ Get distinct booking_sn for customer CTH
                      $bookings = $conn->query("
                    SELECT DISTINCT booking_sn
                    FROM booking
                    WHERE costumer = 'CTH' AND booking_sn <> '' AND status != 'Disable'
                    ORDER BY booking_date DESC
                  ");

                      if ($bookings && $bookings->rowCount() > 0) {
                        $i = 1;
                        while ($b = $bookings->fetch()) {
                          $sn = $b['booking_sn'];

                          // ✅ Header info
                          $header = $conn->query("
                        SELECT 
                          booking_sn,
                          MIN(booking_date) AS booking_date,
                          MIN(booking_haulingStartDate) AS haulingStart,
                          MAX(booking_LastDateStorage) AS storage,
                          MAX(booking_LastDateDemurrage) AS demurrage,
                          MAX(booking_LastDateDetention) AS detention
                        FROM booking
                        WHERE booking_sn = '$sn' AND costumer = 'CTH' AND status != 'Disable'
                      ")->fetch();

                          // ✅ Count complete / not complete bookings
                          $count = $conn->query("
                        SELECT 
                          SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) AS complete_count,
                          SUM(CASE WHEN status <> 'Complete' THEN 1 ELSE 0 END) AS incomplete_count
                        FROM booking
                        WHERE booking_sn = '$sn' AND costumer = 'CTH' AND status != 'Disable'
                      ")->fetch();

                          $isComplete = ($count['incomplete_count'] == 0);
                          $cardClass = $isComplete ? 'completed-card' : '';
                      ?>
                          <!-- ✅ Container Card -->
                          <div class="card mt-3 filter-item <?= $cardClass ?> collapsed">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap"
                              data-bs-toggle="collapse"
                              data-bs-target="#containerHistory<?= $i ?>"
                              aria-eded="false"
                              style="cursor: pointer;">
                              <div class="d-flex flex-wrap align-items-center gap-3">
                                <strong><i class="ti ti-package"></i> SN: <?= htmlspecialchars($sn) ?></strong>
                                <small class="d-flex flex-wrap gap-3">
                                  <span><strong>Date:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($header['booking_date'])) ?: '-') ?></span>
                                  <span><strong>Hauling Start:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($header['haulingStart'])) ?: '-') ?></span>
                                  <span><strong>Storage:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($header['storage'])) ?: '-') ?></span>
                                  <span><strong>Demurrage:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($header['demurrage'])) ?: '-') ?></span>
                                  <span><strong>Detention:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($header['detention'])) ?: '-') ?></span>
                                  <span><strong>✅ Dispatched:</strong> <?= $count['complete_count'] ?>
                                    <strong>❌ Not Dispatched:</strong> <?= $count['incomplete_count'] ?></span>
                                </small>
                              </div>
                              <span class="arrow-icon">▼</span>
                            </div>

                            <!-- Collapsible section -->
                            <div id="containerHistory<?= $i ?>" class="collapse">
                              <div class="card-body">
                                <?php
                                // ✅ Query containers under this booking_sn with Dispatch info
                                $history = $conn->query("
                              SELECT 
                                b.booking_no,
                                b.booking_sn,
                                b.booking_do,
                                b.container,
                                b.container_status,
                                b.booking_activity,
                                b.trip_from,
                                b.trip_to,
                                t.deliver_dateTime,
                                t.withdraw_dateTime,
                                t.trip_status,
                                d.d_driverName,
                                d.d_truck,
                                d.d_trailer,
                                b.status AS booking_status
                              FROM booking b
                              LEFT JOIN trips t 
                                ON b.container = t.trip_container
                              LEFT JOIN dispatch d 
                                ON t.d_id = d.d_id  -- ✅ Link trips to dispatch for driver/truck/trailer
                              WHERE b.booking_sn = '$sn' 
                                AND b.costumer = 'CTH'
                                AND b.status != 'Disable'
                              ORDER BY b.container ASC
                            ");
                                ?>

                                <?php if ($history && $history->rowCount() > 0): ?>
                                  <div class="table-responsive">
                                    <table id="historyTable<?= $i ?>" class="table table-sm table-bordered history-table">
                                      <thead class="table-light">
                                        <tr>
                                          <th>Container</th>
                                          <th>Status</th>
                                          <th>Activity</th>
                                          <th>From</th>
                                          <th>To</th>
                                          <th>Driver</th>
                                          <th>Truck</th>
                                          <th>Trailer</th>
                                          <th>Deliver/Withdraw DateTime</th>
                                          <th>Trip Status</th>
                                          <th>Action</th>
                                        </tr>
                                      </thead>
                                      <tbody>
                                        <?php while ($row = $history->fetch()):
                                          $dateDisplay = ($row['booking_activity'] === 'Delivery')
                                            ? $row['deliver_datetime']
                                            : $row['withdraw_datetime'];
                                        ?>
                                          <tr class="<?= ($row['trip_status'] !== 'Done') ? 'table-warning' : 'table-success' ?>">
                                            <td><?= htmlspecialchars($row['container']) ?></td>
                                            <td><?= htmlspecialchars($row['container_status']) ?></td>
                                            <td><?= htmlspecialchars($row['booking_activity']) ?></td>
                                            <td><?= htmlspecialchars($row['trip_from'] ?: 'None') ?></td>
                                            <td><?= htmlspecialchars($row['trip_to'] ?: 'None') ?></td>
                                            <td><?= htmlspecialchars($row['d_drivername'] ?: 'None') ?></td>
                                            <td><?= htmlspecialchars($row['d_truck'] ?: 'None') ?></td>
                                            <td><?= htmlspecialchars($row['d_trailer'] ?: 'None') ?></td>
                                            <td><?= htmlspecialchars($dateDisplay ?: 'None') ?></td>
                                            <td><?= htmlspecialchars($row['trip_status'] ?: 'Not Yet Dispatch') ?></td>
                                            <td class="text-center">
                                              <?php if($row['trip_status'] !== "Done" && $row['trip_status'] !== "Active"){?>
                                              <button
                                                class="btn btn-outline-primary btn-sm assign-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignModal1"
                                                data-booking-no="<?= htmlspecialchars($row['booking_no']) ?>"
                                                data-booking-sn="<?= htmlspecialchars($row['booking_sn']) ?>"
                                                data-booking-do="<?= htmlspecialchars($row['booking_do']) ?>">
                                                <i class="ti ti-user-plus"></i> Assign
                                              </button>
                                              <?php }?>
                                            </td>
                                          </tr>
                                        <?php endwhile; ?>
                                      </tbody>
                                    </table>
                                  </div>
                                <?php else: ?>
                                  <p class="text-danger">No container history found for this SN.</p>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                      <?php
                          $i++;
                        } // end while
                      } else {
                        echo "<p class='text-danger'>No Booking Found.</p>";
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
                <input type="hidden" class="form-control" id="userName" name="userName" value="<?php echo strtoupper($data['user_lname']) . ', ' .           strtoupper(substr($data['user_fname'], 0, 1)) . '' . strtoupper(substr($data['user_mname'], 0, 1)); ?>" required>
                <div class="mb-3">
                  <label class="form-label">Broker</label>
                  <input type="text" id="broker" name="broker" class="form-control" placeholder="Enter Broker" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">EIR(OUT)</label>
                  <input type="text" id="EIROut" name="EIROut" class="form-control" placeholder="Enter EIR(OUT)" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Unit</label>
                  <input type="text" id="assignUnitName1" name="unit_name" class="form-control" autocomplete="off" required>
                  <ul id="truckList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>

                </div>
                <div class="mb-3" hidden>
                  <label class="form-label">Booking</label>
                  <input type="text" id="assignBookingName" name="booking_no" class="form-control" readonly>
                  <input type="text" id="assignBookingSn" name="booking_sn" class="form-control" readonly>
                  <input type="text" id="assignBookingDo" name="booking_do" class="form-control" readonly>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Trip Receipt</label>
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
        $(document).ready(function() {
          <?php if (isset($i) && $i > 1): ?>
            <?php for ($j = 1; $j < $i; $j++): ?>
              $('#historyTable<?= $j ?>').DataTable({
                pageLength: 5,
                lengthChange: false,
                ordering: true,
                searching: true
              });
            <?php endfor; ?>
          <?php endif; ?>


            $('#confirmAssignBtn1').click(function (e) {
              e.preventDefault();
              var broker = $("#broker").val();
              var EIROut = $("#EIROut").val();
              var booking_no = $("#assignBookingName").val();
              var booking_sn = $("#assignBookingSn").val();
              var booking_do = $("#assignBookingDo").val();
              var trip_receipt = $("#trip_receipt").val();
              var ecs = $("#ecs").val();
              var driver = $("#driver").val();
              var driver_id = $("#driverId").val();
              var genset = $("#genset").val();
              var trailer = $("#trailer").val();
              var assignLocation = $("#assinglocation").val();
              var userName = $("#userName").val();
              var unitName = $("#assignUnitName1").val();

              if (!broker || !EIROut || !booking_no || !booking_sn || !booking_do || !driver || !driver_id || !unitName) {
                  Swal.fire({
                      text: 'Please fill in all required fields',
                      icon: 'info'
                  });
                  return;
              }

              Swal.fire({
                  title: 'Confirm Assignment',
                  icon: 'question',
                  showCancelButton: true,
                  confirmButtonText: 'Confirm'
              }).then((result) => {
                  if (result.isConfirmed) {
                      var formData = new FormData();
                      formData.append('broker', broker);
                      formData.append('EIROut', EIROut);
                      formData.append('booking_no', booking_no);
                      formData.append('booking_sn', booking_sn);
                      formData.append('booking_do', booking_do);
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
                          url: 'php/operations/assign_bookingCTH.php',
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
                                      window.open("dispatcher-index.php?route=printcth&id=" + data.insert_id, "_blank");
                                      location.reload();
                                  }, 1300);
                              } else {
                                  Swal.fire({
                                      text: data.message,
                                      icon: 'error'
                                  });
                              }
                          },
                          error: function (xhr, status, error) {
                              Swal.fire({
                                  text: 'Error: ' + error,
                                  icon: 'error'
                              });
                          }
                      });
                  }
              });
          });
        });

        // ✅ Arrow Rotation
        document.querySelectorAll('.filter-item').forEach(card => {
          card.addEventListener('click', function() {
            this.classList.toggle('expanded');
            this.classList.toggle('collapsed');
          });
        });

        document.addEventListener('DOMContentLoaded', function() {
          // When "Assign" button in the table is clicked
          document.querySelectorAll('.assign-btn').forEach(button => {
            button.addEventListener('click', function() {
              const bookingNo = this.getAttribute('data-booking-no');
              const bookingSn = this.getAttribute('data-booking-sn');
              const bookingDo = this.getAttribute('data-booking-do');
              const assignModal = document.querySelector('#assignModal1');
              
              
              // ✅ Set booking number in hidden input
              assignModal.querySelector('#assignBookingDo').value = bookingDo;
              assignModal.querySelector('#assignBookingSn').value = bookingSn;
              assignModal.querySelector('#assignBookingName').value = bookingNo;
            });
          });

        });




        document.addEventListener("DOMContentLoaded", function () {
          // ===== Driver Search =====
          const driverInput = document.getElementById("driver");
          const driverIdInput = document.getElementById("driverId");
          const driverList = document.getElementById("driverList");
          let allDrivers = [];

          fetch("php/fetch/get_drivers1.php")
              .then(res => res.json())
              .then(data => { allDrivers = data; });

          driverInput.addEventListener("input", function () {
              filterDropdown(this, driverList, allDrivers.map(d => d.name), (name) => {
                  driverInput.value = name;
                  const selected = allDrivers.find(d => d.name === name);
                  driverIdInput.value = selected ? selected.id : "";
              });
          });
            // ===== Truck Search =====
          const truckInput = document.getElementById("assignUnitName1");
          const truckList = document.getElementById("truckList");
          let allTrucks = [];

          fetch("php/fetch/get_trucks1.php")
              .then(res => res.json())
              .then(data => { allTrucks = data; });

          truckInput.addEventListener("input", function () {
              filterDropdown(this, truckList, allTrucks, (name) => {
                  truckInput.value = name;
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

          attachKeyboardNav(truckInput, truckList, (val) => {
              truckInput.value = val;
          });

          attachKeyboardNav(gensetInput, gensetList, (val) => {
              gensetInput.value = val;
          });

          attachKeyboardNav(trailerInput, trailerList, (val) => {
              trailerInput.value = val;
          });

          // ===== Hide all dropdowns on click outside =====
          document.addEventListener("click", function (e) {
              [driverList, truckList, gensetList, trailerList].forEach(list => {
                  if (!list.contains(e.target) &&
                      !driverInput.contains(e.target) &&
                      !truckInput.contains(e.target) &&
                      !gensetInput.contains(e.target) &&
                      !trailerInput.contains(e.target)) {
                      list.style.display = "none";
                  }
              });
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
