<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "HR-Admin") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Driver Page</title>
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
                        <h4 class="card-title">Driver Management List</h4>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0">
                        <button class="btn btn-primary btn-sm" type="button" id="attendanceModalBtn"><i class="ti ti-plus"></i> Attendance</button>
                        <button class="btn btn-primary btn-sm" type="button" id="AddModal"><i class="ti ti-plus"></i> Add Driver</button>
                      </div>
                    </div>
                    <div class="table-responsive mt-4">
                      <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data">
                        <thead>
                          <tr>
                            <th scope="col" class="px-0 text-muted">ID Number</th>
                            <th scope="col" class="px-0 text-muted">First Name</th>
                            <th scope="col" class="px-0 text-muted">Middle Name</th>
                            <th scope="col" class="px-0 text-muted">Last Name</th>
                            <th scope="col" class="px-0 text-muted">Assign Unit</th>
                            <th scope="col" class="px-0 text-muted">Assign Hauling</th>
                            <th scope="col" class="px-0 text-muted">Attendance Status</th>
                            <th scope="col" class="px-0 text-muted">Status</th>
                            <th scope="col" class="px-0 text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          include "php/config/config.php";

                          // Activate any scheduled violations whose time has arrived, so
                          // the list below reflects the resulting driver blocks.
                          require_once __DIR__ . '/../php/lib/violation_auto_activate.php';
                          pt_activate_due_violations($conn);

                          // ✅ Select drivers + their latest attendance record
                          $query = "SELECT d.*, da.da_status, da.da_date
                                    , COALESCE(av.active_violation_count, 0) AS active_violation_count
                                    , lv.active_violations AS active_violations
                                    FROM drivers d
                                    LEFT JOIN (
                                      SELECT driver_id, da_status, da_date
                                      FROM (
                                        SELECT da1.*,
                                              ROW_NUMBER() OVER (PARTITION BY driver_id ORDER BY da_date DESC) AS rn
                                        FROM drivers_attendance da1
                                        WHERE DATE(da1.da_date) = CURRENT_DATE
                                      ) ranked
                                      WHERE rn = 1
                                    ) da ON d.driver_id = da.driver_id
                                    LEFT JOIN (
                                      SELECT driver_id, COUNT(*) AS active_violation_count
                                      FROM violation_record
                                      WHERE vr_status = 'Active'
                                      GROUP BY driver_id
                                    ) av ON av.driver_id = d.driver_id
                                    LEFT JOIN (
                                      SELECT driver_id,
                                             json_agg(
                                               json_build_object('type', vr_type, 'description', vr_description)
                                               ORDER BY vr_date DESC
                                             ) AS active_violations
                                      FROM violation_record
                                      WHERE vr_status = 'Active'
                                      GROUP BY driver_id
                                    ) lv ON lv.driver_id = d.driver_id
                                    ORDER BY d.driver_id DESC";

                          $result = $conn->query($query);

                          while ($row = ($result)->fetch()) {
                            $hasActiveViolation = (int)($row['active_violation_count'] ?? 0) > 0;
                            $displayStatus = $hasActiveViolation ? 'With Violation' : $row['driver_status'];
                          ?>
                            <tr>
                              <td class="px-0">
                                <div class="d-flex align-items-center">
                                  <img src="assets/images/profile/user-1.jpg" class="rounded-circle" width="40" alt="profile" />
                                  <div class="ms-3">
                                    <h6 class="mb-0 fw-bolder"><?php echo $row['driver_idnumber']; ?></h6>
                                  </div>
                                </div>
                              </td>
                              <td class="px-0"><?php echo $row['driver_fname']; ?></td>
                              <td class="px-0"><?php echo $row['driver_mname']; ?></td>
                              <td class="px-0"><?php echo $row['driver_lname']; ?></td>
                              <td class="px-0"><?php echo $row['driver_assignunit']; ?></td>
                              <td class="px-0"><?php echo $row['driver_assignsegment']; ?></td>

                              <!-- ✅ Display Attendance Status -->
                              <td class="px-0">
                                <?php 
                                if (!empty($row['da_status'])) {
                                  if ($row['da_status'] == 'Present') {
                                    echo '<span class="badge bg-success">Present</span>';
                                  } elseif ($row['da_status'] == 'Absent') {
                                    echo '<span class="badge bg-danger">Absent</span>';
                                  } elseif ($row['da_status'] == 'On Leave') {
                                    echo '<span class="badge bg-warning text-dark">On Leave</span>';
                                  } else {
                                    echo '<span class="badge bg-secondary">'.htmlspecialchars($row['da_status']).'</span>';
                                  }
                                } else {
                                  echo '<span class="badge bg-secondary">No Record</span>';
                                }
                                ?>
                              </td>

                              <!-- Trip Status -->
                              <td class="px-0">
                                <span class="badge <?php echo $hasActiveViolation ? 'bg-danger' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($displayStatus); ?></span>
                                <?php
                                if ($hasActiveViolation && !empty($row['active_violations'])) {
                                  $violations = json_decode($row['active_violations'], true);
                                  if (is_array($violations)) {
                                    foreach ($violations as $v) {
                                      if (empty($v['type'])) continue;
                                      echo '<div class="small text-muted mt-1">';
                                      echo '<i class="ti ti-alert-triangle"></i> <span class="fw-medium">' . htmlspecialchars($v['type']) . '</span>';
                                      if (!empty($v['description'])) {
                                        echo '<br><span>' . htmlspecialchars($v['description']) . '</span>';
                                      }
                                      echo '</div>';
                                    }
                                  }
                                }
                                ?>
                              </td>

                              <td class="px-0 text-dark fw-medium text-end">
                                <div class="d-grid gap-2 d-md-block">
                                  <?php if(!$hasActiveViolation){
                                    ?>
                                  <button class="btn btn-warning btn-sm" type="button" onclick="assignViolation('<?php echo $row['driver_id']; ?>');">Violation</button>
                                  <?php } else{?>
                                    <button class="btn btn-success btn-sm" type="button" onclick="markGood('<?php echo $row['driver_id']; ?>');">Mark as Good</button>
                                    <?php if (trim((string)($row['driver_contact'] ?? '')) !== ''): ?>
                                    <button class="btn btn-outline-success btn-sm" type="button" title="Resend WhatsApp violation notice" onclick="resendWhatsApp('<?php echo $row['driver_id']; ?>');"><i class="ti ti-brand-whatsapp"></i> WhatsApp</button>
                                    <?php endif; ?>
                                    <?php }?>
                                  <button class="btn btn-primary btn-sm" type="button" onclick="openUpdateDriver('<?php echo $row['driver_id']; ?>', 
                                    '<?php echo $row['driver_idnumber']; ?>', 
                                    '<?php echo $row['driver_rfid']; ?>', 
                                    '<?php echo $row['driver_fname']; ?>', 
                                    '<?php echo $row['driver_mname']; ?>',
                                    '<?php echo $row['driver_lname']; ?>',
                                    '<?php echo $row['driver_assignunit']; ?>',
                                    '<?php echo $row['driver_assignsegment']; ?>',
                                    '<?php echo $row['driver_uname']; ?>',
                                    '<?php echo $row['driver_account_status']; ?>',
                                    '<?php echo htmlspecialchars((string)($row['driver_contact'] ?? ''), ENT_QUOTES); ?>');"><i class="ti ti-edit"></i></button>
                                  <button class="btn btn-danger btn-sm" type="button" onclick="deleteDriver('<?php echo $row['driver_id']; ?>');"><i class="ti ti-trash"></i></button>
                                </div>
                              </td>
                            </tr>
                          <?php
                          }
                          ?>
                        </tbody>
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
    <!-- Add Modal -->
    <div class="modal fade" id="addmodal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3 id="title">Add Driver</h3>
          </div>
          <div class="modal-body">
            <form id="addForm" action="php/adddriver.php" method="POST" enctype="multipart/form-data">
              <div class="row">
                <div class="input-style-1">
                  <div class="column" hidden>
                    <label for="driver_rfid">Driver RFID<span style="color: red;">*</span></label>
                    <input type="password" class="form-control" name="driver_rfid" id="driver_rfid" placeholder="Scan RFID">
                  </div>
                  <div class="column">
                    <label for="driver_IdNumber">Driver ID Number<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_IdNumber" id="driver_IdNumber" placeholder="Enter Driver ID" required>
                  </div>
                  <div class="column">
                    <label for="driver_fname">First Name<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_fname" id="driver_fname" placeholder="Enter Firstname" required>
                  </div>
                  <div class="column">
                    <label for="driver_mname">Middle Name</label>
                    <input type="text" class="form-control" name="driver_mname" id="driver_mname" placeholder="Enter Middle Name">
                  </div>
                  <div class="column">
                    <label for="driver_lname">Last Name<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_lname" id="driver_lname" placeholder="Enter Lastname" required>
                  </div>
                  <div class="column">
                    <label for="driver_contact">Contact No.</label>
                    <input type="text" class="form-control" name="driver_contact" id="driver_contact" placeholder="Enter Contact No." inputmode="tel">
                  </div>
                  <div class="column position-relative">
                    <label for="driver_assignUnit">Assign Unit<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_assignUnit" id="driver_assignUnit" placeholder="Select Unit" required>
                    <ul id="truckList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                  </div>
                  <?php
                  include 'php/config/config.php';

                  $haulingQuery = "SELECT hauling_id, hauling_segment FROM hauling ORDER BY hauling_id ASC";
                  $haulingResult = $conn->query($haulingQuery);
                  ?>

                  <div class="column">
                    <label for="driver_assignSegment">Hauling Segment <span style="color: red;">*</span></label>
                    <select class="form-select" name="driver_assignSegment" id="driver_assignSegment" required>
                      <option value="" selected disabled>-- Select Hauling Segment --</option>
                      <?php while ($row = ($haulingResult)->fetch()) { ?>
                        <option value="<?php echo htmlspecialchars($row['hauling_segment']); ?>">
                          <?php echo htmlspecialchars($row['hauling_segment']); ?>
                        </option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="column" hidden>
                    <label for="driver_username">Username<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_username" id="driver_username" placeholder="Enter Driver Username">
                  </div>
                  <div class="column" hidden>
                    <label for="driver_pass">Password<span style="color: red;">*</span></label>
                    <input type="password" class="form-control" name="driver_pass" id="driver_pass" placeholder="Enter Password">
                  </div>
                  <div class="column" hidden>
                    <label for="driver_image">Upload Image</label>
                    <input type="file" class="form-control" name="driver_image" id="driver_image" placeholder="Upload Image">
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" name="submit" id="addDriver">Save</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Modal -->

    <!-- Update Driver Modal -->
    <div class="modal fade" id="editModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3 id="title">Update Driver</h3>
          </div>
          <div class="modal-body">
            <form id="editForm" action="php/updatedriver.php" method="POST" enctype="multipart/form-data">
              <input type="hidden" name="driver_id1" id="driver_id1">
              <div class="row">
                <div class="input-style-1">
                  <div class="column">
                    <label for="driver_rfid1">Driver RFID<span style="color: red;">*</span></label>
                    <input type="password" class="form-control" name="driver_rfid1" id="driver_rfid1" placeholder="Scan RFID">
                  </div>
                  <div class="column">
                    <label for="driver_IdNumber1">Driver ID Number<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_IdNumber1" id="driver_IdNumber1" required>
                  </div>
                  <div class="column">
                    <label for="driver_fname1">First Name<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_fname1" id="driver_fname1" required>
                  </div>
                  <div class="column">
                    <label for="driver_mname1">Middle Name</label>
                    <input type="text" class="form-control" name="driver_mname1" id="driver_mname1" required>
                  </div>
                  <div class="column">
                    <label for="driver_lname1">Last Name<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_lname1" id="driver_lname1" required>
                  </div>
                  <div class="column">
                    <label for="driver_contact1">Contact No.</label>
                    <input type="text" class="form-control" name="driver_contact1" id="driver_contact1" placeholder="Enter Contact No." inputmode="tel">
                  </div>
                  <div class="column position-relative">
                    <label for="driver_assignUnit">Assign Unit<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_assignUnit1" id="driver_assignUnit1" placeholder="Select Unit" required>
                    <ul id="truckList1" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                  </div>
                   <?php
                  include 'php/config/config.php';

                  $haulingQuery = "SELECT hauling_id, hauling_segment FROM hauling ORDER BY hauling_id ASC";
                  $haulingResult = $conn->query($haulingQuery);
                  ?>

                  <div class="column">
                    <label for="driver_assignSegment1">Hauling Segment <span style="color: red;">*</span></label>
                    <select class="form-select" name="driver_assignSegment1" id="driver_assignSegment1" required>
                      <option value="" selected disabled>-- Select Hauling Segment --</option>
                      <?php while ($row = ($haulingResult)->fetch()) { ?>
                        <option value="<?php echo htmlspecialchars($row['hauling_segment']); ?>">
                          <?php echo htmlspecialchars($row['hauling_segment']); ?>
                        </option>
                      <?php } ?>
                    </select>
                  </div>

                  <div class="column" hidden>
                    <label for="driver_username1">Username<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_username1" id="driver_username1" placeholder="Enter Driver Username">
                  </div>
                  <div class="column" hidden>
                    <label for="driver_pass1">Password<span style="color: red;">*</span></label>
                    <input type="password" class="form-control" name="driver_pass1" id="driver_pass1" placeholder="Enter Password">
                  </div>
                  <div class="select-style-1">
                    <label for="status">Account Status</label>
                    <div class="select-position">
                      <select class="form-select" name="status" id="status">
                        <option value="">Select Status</option>
                        <option value="Approve">Cleared</option>
                        <option value="Fuel Violation">Fuel Violation</option>
                        <option value="Performance Notes">Performance Notes</option>
                      </select>
                    </div>
                  </div>
                  <div class="column" hidden>
                    <label for="driver_image1">Upload Image</label>
                    <input type="file" class="form-control" name="driver_image1" id="driver_image1" placeholder="Upload Image">
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" name="submit" id="UpdateDriver">Save</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Attendance Modal -->
    <div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Drivers Attendance</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <!-- Search + Save -->
            <div class="mb-2 d-flex justify-content-between">
              <input type="text" id="searchDriver" class="form-control w-25" placeholder="🔍 Search driver...">
              <button class="btn btn-success" id="saveAttendance">Save Attendance</button>
            </div>

            <!-- Full Table (NO scrollable wrapper) -->
            <table class="table table-bordered table-striped align-middle">
              <thead>
                <tr>
                  <th>Driver Name</th>
                  <th>Present</th>
                  <th>Absent</th>
                  <th>VL</th>
                  <th>SL</th>
                  <th>Date Prepared</th>
                  <th>VL/SL Start Date</th>
                  <th>VL/SL End Date</th>
                  <th>Time In</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody id="attendanceTable"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Violation Modal -->
    <div class="modal fade" id="violationModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-md">
        <div class="modal-content">
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title">Assign Violation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form id="violationForm">
              
              <!-- Hidden Driver ID -->
               <input type="hidden" id="user_id" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
              <input type="hidden" id="driver_id" name="driver_id">

              <!-- Violation Select -->
              <div class="mb-3">
                <label class="form-label">Violation</label>
                <select class="form-select" name="violation" required>
                  <option value="">-- Select Violation --</option>
                  <option value="Low Performer">Low Performer</option>
                  <option value="Over Speeding">Over Speeding</option>
                  <option value="Illegal Parking">Illegal Parking</option>
                  <option value="Excessive Idling">Excessive Idling</option>
                  <option value="Reckless Driving">Reckless Driving</option>
                  <option value="High Gas">High Gas</option>
                  <option value="Others">Others</option>
                </select>
              </div>

              <!-- Description -->
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea
                    class="form-control"
                    name="description"
                    rows="3"
                    placeholder="Enter violation details..."
                    required></textarea>
              </div>

              <!-- Optional schedule — block the driver automatically at this time. -->
              <div class="mb-1">
                <label class="form-label">Schedule for <span class="text-muted">(optional)</span></label>
                <input type="datetime-local" class="form-control" name="scheduled_at">
                <small class="text-muted">Leave blank to block the driver now. If set to a future date &amp; time, the driver is blocked automatically then.</small>
              </div>

            </form>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="violationForm" class="btn btn-warning">
              Save Violation
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Modal -->
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebarmenu.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script src="assets/libs/apexcharts/dist/apexcharts.min.js"></script>
    <script src="assets/libs/simplebar/dist/simplebar.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script src="datatable/datatables.min.js"></script>
    <script src="js/drivers-hr.js"></script>
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script>
      function assignViolation(driverId) {
          document.getElementById('driver_id').value = driverId;
          let modal = new bootstrap.Modal(document.getElementById('violationModal'));
          modal.show();
      }
      // Resend the WhatsApp violation notice to the driver's registered contact.
      function resendWhatsApp(driverId) {
          Swal.fire({ title: 'Sending WhatsApp…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
          $.ajax({
              url: 'php/operations/resend_violation_whatsapp.php',
              type: 'POST',
              data: { driver_id: driverId },
              dataType: 'json'
          }).done(function (res) {
              Swal.fire({
                  icon: (res && res.status === 'success') ? 'success' : 'error',
                  title: (res && res.status === 'success') ? 'Sent' : 'Not sent',
                  text: (res && res.message) || 'Unknown response.'
              });
          }).fail(function () {
              Swal.fire({ icon: 'error', title: 'Server Error', text: 'Unable to send WhatsApp message.' });
          });
      }
      document.addEventListener("DOMContentLoaded", function () {
       
          // ===== Truck Search =====
        const truckInput = document.getElementById("driver_assignUnit");
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

         // ===== Truck Search =====
        const truckInput1 = document.getElementById("driver_assignUnit1");
        const truckList1 = document.getElementById("truckList1");
        let allTrucks1 = [];

        fetch("php/fetch/get_trucks1.php")
            .then(res => res.json())
            .then(data => { allTrucks1 = data; });

        truckInput1.addEventListener("input", function () {
            filterDropdown(this, truckList1, allTrucks1, (name) => {
                truckInput1.value = name;
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

        attachKeyboardNav(truckInput, truckList, (val) => {
            truckInput.value = val;
        });

        attachKeyboardNav(truckInput1, truckList1, (val) => {
            truckInput1.value = val;
        });

        // ===== Hide all dropdowns on click outside =====
        document.addEventListener("click", function (e) {
            [truckList].forEach(list => {
                if (!list.contains(e.target) &&
                    !truckInput.contains(e.target) &&
                    !truckInput1.contains(e.target)) {
                    list.style.display = "none";
                }
            });
        });
    });

    document.getElementById("attendanceModalBtn").addEventListener("click", function () {
      let modal = new bootstrap.Modal(document.getElementById('attendanceModal'));
      modal.show();

      fetch("table-fetch/fetch_drivers.php")
        .then(res => res.json())
        .then(data => {
          let table = document.getElementById("attendanceTable");
          table.innerHTML = "";
          data.forEach(driver => {
            table.innerHTML += `
            <tr>
              <td>${driver.driver_name}</td>
              <td>
                <input type="radio" class="btn-check present-radio" name="status_${driver.driver_id}" id="present_${driver.driver_id}" value="Present" data-driver="${driver.driver_id}">
                <label class="btn btn-outline-success" for="present_${driver.driver_id}">Present</label>
              </td>
              <td>
                <input type="radio" class="btn-check" name="status_${driver.driver_id}" id="absent_${driver.driver_id}" value="Absent" data-driver="${driver.driver_id}">
                <label class="btn btn-outline-danger" for="absent_${driver.driver_id}">Absent</label>
              </td>
              <td>
                <input type="radio" class="btn-check vl-sl-radio" name="status_${driver.driver_id}" id="vl_${driver.driver_id}" value="VL" data-driver="${driver.driver_id}">
                <label class="btn btn-outline-warning" for="vl_${driver.driver_id}">VL</label>
              </td>
              <td>
                <input type="radio" class="btn-check vl-sl-radio" name="status_${driver.driver_id}" id="sl_${driver.driver_id}" value="SL" data-driver="${driver.driver_id}">
                <label class="btn btn-outline-info" for="sl_${driver.driver_id}">SL</label>
              </td>
              <td>
                <input type="date" class="form-control form-control-sm d-none" id="datePrepared_${driver.driver_id}" style="border: 1px solid black">
              </td>
              <td>
                <input type="date" class="form-control form-control-sm d-none" id="dateStart_${driver.driver_id}" style="border: 1px solid black">
              </td>
              <td>
                <input type="date" class="form-control form-control-sm d-none" id="vlslDate_${driver.driver_id}" style="border: 1px solid black">
              </td>
              <td>
                <input type="datetime-local" class="form-control form-control-sm d-none" id="timeIn_${driver.driver_id}" style="border: 1px solid black">
              </td>
              <td>
                <input type="text" class="form-control form-control-sm" id="remarks_${driver.driver_id}" style="border: 1px solid black">
              </td>
            </tr>
          `;
          });

          // Show inputs depending on selected radio
          document.querySelectorAll("input[type=radio]").forEach(radio => {
            radio.addEventListener("change", function () {
              let driverId = this.dataset.driver;
              let datePrepared = document.getElementById("datePrepared_" + driverId);
              let dateStart = document.getElementById("dateStart_" + driverId);
              let vlslDate = document.getElementById("vlslDate_" + driverId);
              let timeIn = document.getElementById("timeIn_" + driverId);

              if (this.value === "VL" || this.value === "SL") {
                datePrepared.classList.remove("d-none");
                dateStart.classList.remove("d-none");
                vlslDate.classList.remove("d-none");
                timeIn.classList.add("d-none");
              } else if (this.value === "Present") {
                timeIn.classList.remove("d-none");
                datePrepared.classList.add("d-none");
                dateStart.classList.add("d-none");
                vlslDate.classList.add("d-none");
              } else {
                // Absent
                datePrepared.classList.add("d-none");
                dateStart.classList.add("d-none");
                vlslDate.classList.add("d-none");
                timeIn.classList.add("d-none");
              }
            });
          });
        });
    });

    // 🔍 Search driver
    document.getElementById("searchDriver").addEventListener("keyup", function () {
      let filter = this.value.toLowerCase();
      let rows = document.querySelectorAll("#attendanceTable tr");
      rows.forEach(row => {
        let name = row.querySelector("td").innerText.toLowerCase();
        row.style.display = name.includes(filter) ? "" : "none";
      });
    });

    // ✅ Save Attendance
   document.getElementById("saveAttendance").addEventListener("click", function () {
      let rows = document.querySelectorAll("#attendanceTable tr");
      let attendanceData = [];

      rows.forEach(row => {
        let inputs = row.querySelectorAll("input[type=radio]");
        let driver_id = inputs[0].name.split("_")[1];
        let status = "";
        inputs.forEach(input => {
          if (input.checked) status = input.value;
        });

        // ✅ Get remarks properly
        let remarks = document.getElementById("remarks_" + driver_id)?.value || "";
        let datePrepared = document.getElementById("datePrepared_" + driver_id)?.value || "";
        let dateStart = document.getElementById("dateStart_" + driver_id)?.value || "";
        let vlslDate = document.getElementById("vlslDate_" + driver_id)?.value || "";
        let timeIn = document.getElementById("timeIn_" + driver_id)?.value || "";

        if (status !== "") {
          attendanceData.push({
            driver_id,
            status,
            remarks,
            datePrepared,
            dateStart,
            vlslDate,
            timeIn
          });
        }
      });

      if (attendanceData.length === 0) {
        Swal.fire({ icon: "warning", title: "No Attendance Marked", text: "Please select attendance status for at least one driver." });
        return;
      }

      fetch("php/operations/save_attendance.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "attendance=" + encodeURIComponent(JSON.stringify(attendanceData))
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === "success") {
          Swal.fire({ icon: "success", title: "Saved!", text: data.message || "Attendance has been saved successfully." });
        } else {
          Swal.fire({ icon: "error", title: "Error", text: data.message || "Something went wrong." });
        }
      })
      .catch(() => {
        Swal.fire({ icon: "error", title: "Network Error", text: "Unable to connect to the server." });
      });
    });


    $('#violationForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'php/operations/insert_violation.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Violation Assigned',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#violationModal').modal('hide');
                        $('#violationForm')[0].reset();
                        location.reload(); // optional
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'Something went wrong.'
                });
            }
        });
    });

    function markGood(driver_id) {
      Swal.fire({
          title: 'Mark driver as GOOD?',
          text: 'This will update the driver status to GOOD.',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Yes, mark as good',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#28a745'
      }).then((result) => {
          if (result.isConfirmed) {

              $.ajax({
                  url: 'php/operations/mark_driver_good.php',
                  type: 'POST',
                  data: { driver_id: driver_id },
                  dataType: 'json',
                  success: function(response) {
                      if (response.status === 'success') {
                          Swal.fire({
                              icon: 'success',
                              title: 'Updated',
                              text: response.message,
                              timer: 2000,
                              showConfirmButton: false
                          }).then(() => {
                              location.reload(); // optional
                          });
                      } else {
                          Swal.fire({
                              icon: 'error',
                              title: 'Error',
                              text: response.message
                          });
                      }
                  },
                  error: function() {
                      Swal.fire({
                          icon: 'error',
                          title: 'Server Error',
                          text: 'Unable to process request.'
                      });
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
  header("Location: hr-index.php?route=login");
  exit();
}
