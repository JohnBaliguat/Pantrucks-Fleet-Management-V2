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
    <style>
      .driver-card {
          padding: 10px;
          border-radius: 8px;
          margin-bottom: 10px;
          font-weight: 500;
          cursor: pointer;
          display: flex;
          justify-content: space-between;
          align-items: center;
          flex-wrap: nowrap;
      }

      .status-present {
          background-color: #d4edda;
          color: #155724;
      }

      .status-absent {
          background-color: #f8d7da;
          color: #721c24;
      }

      .status-leave {
          background-color: #fff3cd;
          color: #856404;
      }

      .driver-info {
          flex: 1;
      }

      .dispatch-status {
          background-color: #d4edda;
          color: #155724;
          padding: 2px 5px;
          border-radius: 4px;
          font-size: 12px;
          font-weight: 600;
          white-space: nowrap;
          margin-left: 10px;
      }

      .dispatch-legend {
          background-color: #a9d6e5;
          color: #0582ca;
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

              <div class="col-md-12">
                <div class="modern-card">
                  <div class="row m-2 mt-3">
                    <div class="col-md-6">
                      <div class="d-flex gap-2 align-items-end">
                        <div class="col">
                          <label for="dateFrom" class="form-label">From Date:</label>
                          <input type="date" id="dateFrom" class="form-control" value="<?= date('Y-m-d'); ?>">
                        </div>
                        <div class="col">
                          <label for="dateTo" class="form-label">To Date:</label>
                          <input type="date" id="dateTo" class="form-control" value="<?= date('Y-m-d'); ?>">
                        </div>
                        <div class="col">
                          <label for="driverAssign" class="form-label">Assign Base:</label>
                          <select id="driverAssign" class="form-control">
                            <option value="">All</option>
                            <option value="PTSI">PTSI</option>
                            <option value="Consol">Consol</option>
                          </select>
                        </div>
                        <button id="filterBtn" class="btn btn-primary">Filter</button>
                      </div>
                    </div>
                    <div class="col-md-6 text-end mt-2">
                      <div class="btn-group" role="group" aria-label="Basic example">
                        <a class="btn btn-outline-primary" href="dispatch-dashboard-truck">Truck Movement</a>
                        <a class="btn btn-outline-primary" href="dispatch-dashboard-trailer">Trailer Movement</a>
                        <a href="dispatch-dashboard" class="btn btn-outline-primary">Dashboard</a>
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="card-header">
                        <div>
                          <h4>Attendance</h4>
                          <p>List of Drivers</p>
                        </div>
                        <input type="text" id="driverSearch" placeholder="Search driver...">
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="driver-legend">
                        <?php
                        $dateToday = date('Y-m-d');

                        // Get all drivers once
                        $allDriversQuery = $conn->query("SELECT driver_id, driver_fname, driver_lname, driver_assignbase FROM drivers");
                        $driversArray = [];
                        while ($d = ($allDriversQuery)->fetch()) {
                            $driversArray[$d['driver_id']] = $d;
                        }

                        // Get all attendance for today (present/absent) in one query
                        $allAttendanceQuery = $conn->query("
                            SELECT driver_id, da_status, da_timeIn FROM drivers_attendance 
                            WHERE da_date = '$dateToday'
                        ");
                        $attendanceByDriver = [];
                        while ($a = ($allAttendanceQuery)->fetch()) {
                            if (!isset($attendanceByDriver[$a['driver_id']])) {
                                $attendanceByDriver[$a['driver_id']] = [];
                            }
                            $attendanceByDriver[$a['driver_id']][] = $a;
                        }

                        // Get all leave records in one query
                        $allLeaveQuery = $conn->query("
                            SELECT DISTINCT driver_id FROM drivers_attendance 
                            WHERE '$dateToday' BETWEEN vl_sl_dateStart AND vl_sl_date
                            AND da_status IN ('VL', 'SL')
                        ");
                        $leaveDrivers = [];
                        while ($l = ($allLeaveQuery)->fetch()) {
                            $leaveDrivers[$l['driver_id']] = true;
                        }

                        // Get all active dispatch trips in one query
                        // consider various trip_status values to flag active
                        $dispatchQuery = $conn->query("
                            SELECT DISTINCT d.driver_id
                            FROM dispatch d
                            INNER JOIN trips t ON d.d_id = t.d_id
                            WHERE t.trip_status NOT IN ('Done','Completed','Cancelled')
                              AND d.driver_id IS NOT NULL
                        ");
                        $dispatchedDrivers = [];
                        while ($disp = ($dispatchQuery)->fetch()) {
                            $dispatchedDrivers[$disp['driver_id']] = true;
                        }

                        // Count statuses
                        $present = 0;
                        $leave = 0;
                        $absent = 0;
                        $dispatched = 0;

                        foreach ($driversArray as $driverId => $driver) {
                            if (isset($dispatchedDrivers[$driverId])) {
                                $dispatched++;
                            }
                            
                            if (isset($leaveDrivers[$driverId])) {
                                $leave++;
                            } elseif (isset($attendanceByDriver[$driverId])) {
                                $attendance = $attendanceByDriver[$driverId][0];
                                if ($attendance['da_timein'] !== null) {
                                    $present++;
                                } else {
                                    $absent++;
                                }
                            } else {
                                $absent++;
                            }
                        }

                        $totalDrivers = count($driversArray);
                        ?>

                        <span class="legend-item-driver active" data-filter="all" style="margin-right: 5px;">
                          All (<?= $totalDrivers ?>)
                        </span>

                        <span class="legend-item-driver status-present" data-filter="Present" style="margin-right: 5px;">
                          Present (<?= $present ?>)
                        </span>

                        <span class="legend-item-driver status-absent" data-filter="Absent" style="margin-right: 5px;">
                          Absent (<?= $absent ?>)
                        </span>

                        <span class="legend-item-driver status-leave" data-filter="Leave" style="margin-right: 5px;">
                          Leave (<?= $leave ?>)
                        </span>

                        <span class="legend-item-driver dispatch-legend" data-filter="Dispatched">
                          Dispatched (<?= $dispatched ?>)
                        </span>
                      </div>
                    </div>
                  </div>


                  <div class="card-body scroll-area grid mb-5">
                    <?php
                    foreach ($driversArray as $driverId => $driver) {
                        
                        // Determine status using pre-fetched data
                        if (isset($leaveDrivers[$driverId])) {
                            $status = "Leave";
                            $statusClass = "status-leave";
                            $icon = "ti-calendar";
                        } elseif (isset($attendanceByDriver[$driverId])) {
                            $attendance = $attendanceByDriver[$driverId][0];
                            if ($attendance['da_timein'] !== null) {
                                $status = "Present";
                                $statusClass = "status-present";
                                $icon = "ti-check";
                            } else {
                                $status = "Absent";
                                $statusClass = "status-absent";
                                $icon = "ti-x";
                            }
                        } else {
                            $status = "Absent";
                            $statusClass = "status-absent";
                            $icon = "ti-x";
                        }
                    ?>
                        <div class="driver-card <?= $statusClass; ?>"
                            data-driver-id="<?= $driverId; ?>"
                            data-name="<?= $driver['driver_fname'] . " " . $driver['driver_lname']; ?>"
                            data-assign="<?= $driver['driver_assignbase'] ?? ''; ?>"
                            data-status="<?= $status; ?>"
                            data-dispatched="<?= isset($dispatchedDrivers[$driverId]) ? 'yes' : 'no'; ?>">>
                            
                            <div class="driver-info">
                                <i class="ti <?= $icon; ?>" style="font-size: 18px;"></i>
                                <?= $driver['driver_fname'] . " " . $driver['driver_lname']; ?>
                                <br>
                                <small><?= $status; ?></small>
                            </div>
                            
                            <?php if (isset($dispatchedDrivers[$driverId])): ?>
                                <div class="dispatch-status">Dispatched</div>
                            <?php endif; ?>
                        </div>

                    <?php } ?>
                    </div>
                </div>

              </div>
            </div>
            <div class="modal fade" id="attendanceModal" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">

                <div class="modal-header">
                  <h5 class="modal-title">Attendance Status</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="php/operations/save_attend.php" method="post">
                  <div class="modal-body">
                    <div class="row">
                      <div class="col-7 text-end mt-2">
                        <label class="form-label">Control No:</label>
                      </div>
                      <div class="col-5">
                        <input type="text" class="form-control" id="controlNo" name="controlNo">
                      </div>
                    </div>

                    <!-- Radio Buttons -->
                    <div class="mb-3 position-relative">
                      <label class="form-label">Driver<span style="color: red;">*</span></label>
                      <input type="text" id="driver" name="driver" class="form-control" autocomplete="off" placeholder="-- Select Driver --" required>
                      <input type="hidden" id="driverId" name="driver_id">

                      <!-- Dropdown list -->
                      <ul id="driverList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-bold">Select Status:</label>

                      <div class="btn-group w-100" role="group">

                        <input type="radio" class="btn-check status-radio"
                          name="attendance_status" id="present" value="Present" autocomplete="off">
                        <label class="btn btn-outline-success" for="present">Present</label>

                        <input type="radio" class="btn-check status-radio"
                          name="attendance_status" id="absent" value="Absent" autocomplete="off">
                        <label class="btn btn-outline-danger" for="absent">Absent</label>

                        <input type="radio" class="btn-check status-radio"
                          name="attendance_status" id="vl" value="VL" autocomplete="off">
                        <label class="btn btn-outline-info" for="vl">VL</label>

                        <input type="radio" class="btn-check status-radio"
                          name="attendance_status" id="sl" value="SL" autocomplete="off">
                        <label class="btn btn-outline-warning" for="sl">SL</label>

                      </div>
                    </div>

                    <!-- Leave Section -->
                    <div class="leave-section border rounded p-3 bg-light">
                      <div class="mb-3">
                        <label class="form-label">Date Prepared</label>
                        <input type="date" class="form-control" id="datePrepared">
                      </div>

                      <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="leave_start">
                      </div>

                      <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" id="leave_end">
                      </div>

                      <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" rows="3" id="leave_remarks"></textarea>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="saveAttend">Save</button>
                  </div>
                </form>
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
    <script src="js/dashboard.js"></script>
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    <script>
      document.querySelectorAll(".driver-legend .legend-item-driver").forEach(item => {
      item.addEventListener("click", function() {

        document.querySelectorAll(".driver-legend .legend-item-driver")
          .forEach(i => i.classList.remove("active"));

        this.classList.add("active");

        const filter = this.dataset.filter;

        document.querySelectorAll(".driver-card").forEach(card => {
          if (filter === "all") {
            card.style.display = "flex";
          } else if (filter === "Dispatched") {
            const show = card.dataset.dispatched === "yes";
            card.style.display = show ? "flex" : "none";
          } else {
            card.style.display =
              card.dataset.status === filter ? "flex" : "none";
          }
        });

        if (filter === "Dispatched") {
          console.log("dispatched cards:", document.querySelectorAll('.driver-card[data-dispatched="yes"]').length);
        }
      });
    });

      /* DRIVER SEARCH */
      document.getElementById('driverSearch').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const drivers = document.querySelectorAll('.driver-card');

        drivers.forEach(card => {
          const driverName = card.dataset.name.toLowerCase();
          card.style.display = driverName.includes(searchValue) ? 'flex' : 'none';
        });
      });

      /* DRIVER CARD CLICK - OPEN MODAL */
      document.querySelectorAll('.driver-card').forEach(card => {
        card.addEventListener('click', function() {
          const driverId = this.dataset.driverId;
          const driverName = this.dataset.name;
          
          // Populate modal with driver information
          document.getElementById('driverId').value = driverId;
          document.getElementById('driver').value = driverName;
          
          // Open the modal
          const modal = new bootstrap.Modal(document.getElementById('attendanceModal'), {});
          modal.show();
        });
      });

      /* SAVE ATTENDANCE */
      document.getElementById('saveAttend').addEventListener('click', function(e) {
        e.preventDefault();
        
        const form = document.getElementById('attendanceForm');
        const driverId = document.getElementById('driverId').value;
        const status = document.querySelector('input[name="attendance_status"]:checked');
        
        if (!driverId) {
          alert('Please select a driver');
          return;
        }
        
        if (!status) {
          alert('Please select an attendance status');
          return;
        }
        
        // Submit the form
        form.submit();
      });

      /* FILTER BUTTON */
      document.getElementById('filterBtn').addEventListener('click', function() {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        const driverAssign = document.getElementById('driverAssign').value;

        const drivers = document.querySelectorAll('.driver-card');

        drivers.forEach(card => {
          let show = true;

          // Filter by driver assignment
          if (driverAssign && card.dataset.assign !== driverAssign) {
            show = false;
          }

          card.style.display = show ? 'flex' : 'none';
        });
      });

      /* RESET FILTERS ON DATE CHANGE */
      document.getElementById('dateFrom').addEventListener('change', function() {
        // Optional: Auto-apply filter on date change
      });

      document.getElementById('dateTo').addEventListener('change', function() {
        // Optional: Auto-apply filter on date change
      });


      document.addEventListener('DOMContentLoaded', () => {
          fetchLatestControlNo();
      });

      async function fetchLatestControlNo() {
          try {
              const response = await fetch('php/fetch/get_latest_control2.php');
              const data = (await response.text()).trim();

              document.getElementById('controlNo').value = data;

          } catch (error) {
              console.error('Failed to fetch latest control number:', error);
          }
      }


      $(document).ready(function() {
        $('#saveAttend').click(function(e) {
          e.preventDefault();

          let controlNo = $('#controlNo').val();
          let driver_id = $('#driverId').val();
          let status = $('input[name="attendance_status"]:checked').val();
          let dateStart = $('#leave_start').val();
          let dateEnd = $('#leave_end').val();
          let remarks = $('#leave_remarks').val();
          let datePrepared = $('#datePrepared').val();

          if (!driver_id) {
            Swal.fire("Warning", "Please select a driver.", "warning");
            return;
          }

          if (!status) {
            Swal.fire("Warning", "Please select attendance status.", "warning");
            return;
          }

          // If Leave, validate dates
          if ((status === "VL" || status === "SL") && (!dateStart || !dateEnd)) {
            Swal.fire("Warning", "Start and End date required for Leave.", "warning");
            return;
          }

          $.ajax({
            url: "php/operations/save_attend.php",
            type: "POST",
            dataType: "json",
            data: {
              driver_id: driver_id,
              controlNo: controlNo,
              status: status,
              datePrepared: datePrepared,
              dateStart: dateStart,
              dateEnd: dateEnd,
              remarks: remarks
            },
            success: function(response) {

              if (response.status === "success") {

                Swal.fire({
                  icon: "success",
                  title: "Saved!",
                  text: response.message
                }).then(() => {

                  $('#attendanceModal').modal('hide');
                  $('#attendanceModal form')[0].reset();
                  $('.leave-section').hide();

                  setTimeout(() => {
                    if (status === "Present") {
                      window.open(
                        "dispatcher-index.php?route=printJob&id=" + response.insert_id,
                        "_blank"
                      );
                    }
                    location.reload();
                  }, 1300);

                });

              } else {
                Swal.fire("Error", response.message, "error");
              }
            },
            error: function() {
              Swal.fire("Error", "Unable to connect to the server.", "error");
            }
          });
        });
      });

      const radios = document.querySelectorAll('.status-radio');
      const leaveSection = document.querySelector('.leave-section');

      radios.forEach(radio => {
        radio.addEventListener('change', function() {
          if (this.value === 'VL' || this.value === 'SL') {
            leaveSection.style.display = 'block';
          } else {
            leaveSection.style.display = 'none';
          }
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
