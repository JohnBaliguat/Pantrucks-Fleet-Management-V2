<?php
session_start();

if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ["Dispatcher", "Dispatch Admin"], true)) {


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
                        <a class="btn btn-outline-primary btn-sm" href="dispatch-printDriverRegistration" target="_blank" rel="noopener"><i class="ti ti-printer"></i> Print Registration Form</a>
                        <button class="btn btn-primary btn-sm" type="button" id="attendanceModalBtn"><i class="ti ti-plus"></i> Attendance</button>
                        <button class="btn btn-primary btn-sm" type="button" id="AddModal" hidden><i class="ti ti-plus"></i> Add Driver</button>
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
                            <th scope="col" class="px-0 text-muted">Assign Base</th>
                            <th scope="col" class="px-0 text-muted">Attendance Status</th>
                            <th scope="col" class="px-0 text-muted">Trip Status</th>
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

                          // ✅ Select drivers + their latest attendance record.
                          // Violation state is derived from violation_record (the single
                          // source of truth used by dispatch gating), NOT from
                          // drivers.driver_status — that column is overwritten by trip /
                          // shift operations and drifts out of sync with real violations.
                          $query = "SELECT d.*, da.da_status, da.da_date, da.da_timein, da.da_timeout
                                    , COALESCE(av.active_violation_count, 0) AS active_violation_count
                                    , lv.active_violations AS active_violations
                                    FROM drivers d
                                    LEFT JOIN (
                                      SELECT driver_id, da_status, da_date, da_timein, da_timeout
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
                              <td class="px-0"><?php echo $row['driver_assignbase']; ?></td>

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
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($row['da_status']) . '</span>';
                                  }
                                  // Date + time of the attendance record.
                                  $daDate = !empty($row['da_date'])    ? date('M d, Y', strtotime($row['da_date']))    : '';
                                  $tIn    = !empty($row['da_timein'])   ? date('h:i A',  strtotime($row['da_timein']))  : '';
                                  $tOut   = !empty($row['da_timeout'])  ? date('h:i A',  strtotime($row['da_timeout'])) : '';
                                  $showOut = $tOut !== '' && !empty($row['da_timein']) && strtotime($row['da_timeout']) > strtotime($row['da_timein']);
                                  if ($daDate !== '' || $tIn !== '') {
                                    echo '<div class="small text-muted mt-1">';
                                    if ($daDate !== '') echo '<i class="ti ti-calendar"></i> ' . htmlspecialchars($daDate);
                                    if ($tIn !== '') echo '<br><i class="ti ti-clock"></i> In: ' . htmlspecialchars($tIn) . ($showOut ? ' &middot; Out: ' . htmlspecialchars($tOut) : '');
                                    echo '</div>';
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
                                  <?php if(!$hasActiveViolation){ ?>
                                    <button class="btn btn-warning btn-sm" type="button" onclick="assignViolation('<?php echo $row['driver_id']; ?>');">Violation</button>
                                  <?php } else { ?>
                                    <button class="btn btn-success btn-sm" type="button" onclick="markGood('<?php echo $row['driver_id']; ?>');">Mark as Good</button>
                                  <?php } ?>
                                  <button class="btn btn-dark btn-sm" type="button" onclick="openHustling('<?php echo $row['driver_id']; ?>', '<?php echo htmlspecialchars($row['driver_assignunit'] ?? '', ENT_QUOTES); ?>');" title="Create DICT Hustling day">Hustling</button>
                                  <button class="btn btn-primary btn-sm" type="button" onclick="openUpdateDriver('<?php echo $row['driver_id']; ?>',
                                    '<?php echo $row['driver_idnumber']; ?>', 
                                    '<?php echo $row['driver_rfid']; ?>', 
                                    '<?php echo $row['driver_fname']; ?>', 
                                    '<?php echo $row['driver_mname']; ?>',
                                    '<?php echo $row['driver_lname']; ?>',
                                    '<?php echo $row['driver_assignunit']; ?>',
                                    '<?php echo $row['driver_assignsegment']; ?>',
                                    '<?php echo $row['driver_uname']; ?>',
                                    '<?php echo $row['driver_pass']; ?>',
                                    '<?php echo $row['driver_account_status']; ?>',
                                    '<?php echo $row['driver_assignbase']; ?>');"><i class="ti ti-edit"></i></button>
                                  <button class="btn btn-danger btn-sm" type="button" onclick="deleteDriver('<?php echo $row['driver_id']; ?>');" hidden><i class="ti ti-trash"></i></button>
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

                  <div class="column">
                    <div class="select-style-1">
                      <label for="driver_assignBase">Select Assign Location</label>
                      <div class="select-position">
                        <select class="form-select" name="driver_assignBase1" id="driver_assignBase1">
                          <option value="PTSI">PTSI</option>
                          <option value="CONSOL">CONSOL</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="column" hidden>
                    <label for="driver_username1">Username<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="driver_username1" id="driver_username1" placeholder="Enter Driver Username">
                  </div>
                  <div class="column" hidden>
                    <label for="driver_pass1">Password<span style="color: red;">*</span></label>
                    <input type="password" class="form-control" name="driver_pass1" id="driver_pass1" placeholder="Enter Password">
                  </div>
                  <div class="select-style-1" hidden>
                    <label for="status">Account Status</label>
                    <div class="select-position">
                      <select class="form-select" name="status" id="status">
                        <option value=""></option>
                        <option value="Approve">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Decline">Decline</option>
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
    <!-- MODAL -->

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

    <!-- End of Modal -->
    <!-- Violation Modal (block a driver from dispatch) -->
    <div class="modal fade" id="violationModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-md">
        <div class="modal-content">
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title">Assign Violation (Block Driver)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="violationForm">
              <input type="hidden" id="user_id" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
              <input type="hidden" id="driver_id" name="driver_id">
              <div class="mb-3">
                <label class="form-label">Violation</label>
                <select class="form-select" name="violation" id="violationType" required>
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
              <div class="mb-3 d-none" id="violationOtherWrap">
                <label class="form-label">Please specify</label>
                <input type="text" class="form-control" name="violation_other" id="violationOther"
                    placeholder="Enter the type of violation...">
              </div>
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"
                    placeholder="Enter violation details..." required></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="violationForm" class="btn btn-warning">Save Violation</button>
          </div>
        </div>
      </div>
    </div>

    <!-- DICT Hustling Day Modal -->
    <div class="modal fade" id="hustlingModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-md">
        <div class="modal-content">
          <div class="modal-header bg-dark text-white">
            <h5 class="modal-title">Create DICT Hustling Day</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small">Creates one hustling dispatch for today. The driver then logs each container from their app.</p>
            <form id="hustlingForm">
              <input type="hidden" name="driver_id" id="hs_driver_id">
              <div class="mb-3">
                <label class="form-label">Truck <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase" name="truck" id="hs_truck" required>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">Trailer</label>
                  <input type="text" class="form-control" name="trailer" id="hs_trailer">
                </div>
                <div class="col-6">
                  <label class="form-label">Genset</label>
                  <input type="text" class="form-control" name="genset" id="hs_genset">
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="hustlingForm" class="btn btn-dark">Create Hustling Day</button>
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
    <script src="js/drivers.js"></script>
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script>
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
        $("#attendanceModalBtn").click(function() {
          $("#attendanceModal").modal("show");
        });

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

      document.addEventListener("DOMContentLoaded", function() {
        // ===== Driver Search =====
        const driverInput = document.getElementById("driver");
        const driverIdInput = document.getElementById("driverId");
        const driverList = document.getElementById("driverList");
        let allDrivers = [];

        fetch("php/fetch/get_attendance_drivers.php")
          .then(res => res.json())
          .then(data => {
            allDrivers = data;
          });

        driverInput.addEventListener("input", function() {
          driverIdInput.value = "";
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
          .then(data => {
            allTrucks = data;
          });

        truckInput.addEventListener("input", function() {
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
          .then(data => {
            allGensets = data;
          });

        gensetInput.addEventListener("input", function() {
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
          .then(data => {
            allTrailers = data;
          });

        trailerInput.addEventListener("input", function() {
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

            li.addEventListener("click", function() {
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

          inputElem.addEventListener("keydown", function(e) {
            const items = listElem.querySelectorAll("li");
            if (!items.length) return;

            if (e.key === "ArrowDown") {
              e.preventDefault();
              activeIndex = (activeIndex + 1) % items.length;
              updateActive(items, activeIndex);
            } else if (e.key === "ArrowUp") {
              e.preventDefault();
              activeIndex = (activeIndex - 1 + items.length) % items.length;
              updateActive(items, activeIndex);
            } else if (e.key === "Enter" || e.key === "Tab") {
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
        document.addEventListener("click", function(e) {
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
      document.addEventListener("DOMContentLoaded", function() {

        // ===== Truck Search =====
        const truckInput = document.getElementById("driver_assignUnit");
        const truckList = document.getElementById("truckList");
        let allTrucks = [];

        fetch("php/fetch/get_trucks.php")
          .then(res => res.json())
          .then(data => {
            allTrucks = data;
          });

        truckInput.addEventListener("input", function() {
          filterDropdown(this, truckList, allTrucks, (name) => {
            truckInput.value = name;
          });
        });

        // ===== Truck Search =====
        const truckInput1 = document.getElementById("driver_assignUnit1");
        const truckList1 = document.getElementById("truckList1");
        let allTrucks1 = [];

        fetch("php/fetch/get_trucks.php")
          .then(res => res.json())
          .then(data => {
            allTrucks1 = data;
          });

        truckInput1.addEventListener("input", function() {
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

            li.addEventListener("click", function() {
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

          inputElem.addEventListener("keydown", function(e) {
            const items = listElem.querySelectorAll("li");
            if (!items.length) return;

            if (e.key === "ArrowDown") {
              e.preventDefault();
              activeIndex = (activeIndex + 1) % items.length;
              updateActive(items, activeIndex);
            } else if (e.key === "ArrowUp") {
              e.preventDefault();
              activeIndex = (activeIndex - 1 + items.length) % items.length;
              updateActive(items, activeIndex);
            } else if (e.key === "Enter" || e.key === "Tab") {
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
        document.addEventListener("click", function(e) {
          [truckList].forEach(list => {
            if (!list.contains(e.target) &&
              !truckInput.contains(e.target) &&
              !truckInput1.contains(e.target)) {
              list.style.display = "none";
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
    <script>
      // ---- Block driver (via violation) / unblock ("Mark as Good") ----
      function assignViolation(driverId) {
        document.getElementById('driver_id').value = driverId;
        new bootstrap.Modal(document.getElementById('violationModal')).show();
      }

      // Show the "Please specify" text box only when "Others" is chosen.
      $('#violationType').on('change', function () {
        const isOther = this.value === 'Others';
        const wrap = document.getElementById('violationOtherWrap');
        const other = document.getElementById('violationOther');
        wrap.classList.toggle('d-none', !isOther);
        other.required = isOther;
        if (!isOther) other.value = '';
      });

      $('#violationForm').on('submit', function (e) {
        e.preventDefault();

        // When "Others" is selected, send the typed text as the violation type.
        let data = $(this).serializeArray();
        if ($('#violationType').val() === 'Others') {
          const other = $('#violationOther').val().trim();
          if (!other) {
            Swal.fire({ icon: 'warning', title: 'Please specify', text: 'Enter the type of violation.' });
            return;
          }
          data = data.filter(f => f.name !== 'violation' && f.name !== 'violation_other');
          data.push({ name: 'violation', value: other });
        }

        $.ajax({
          url: 'php/operations/insert_violation.php',
          type: 'POST',
          data: $.param(data),
          dataType: 'json',
          success: function (response) {
            if (response.status === 'success') {
              Swal.fire({ icon: 'success', title: 'Driver Blocked', text: response.message, timer: 2000, showConfirmButton: false })
                .then(() => { $('#violationModal').modal('hide'); $('#violationForm')[0].reset(); location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: response.message });
            }
          },
          error: function () { Swal.fire({ icon: 'error', title: 'Server Error', text: 'Something went wrong.' }); }
        });
      });

      // ---- Create DICT Hustling day for a driver ----
      function openHustling(driverId, assignedTruck) {
        document.getElementById('hs_driver_id').value = driverId;
        document.getElementById('hs_truck').value = assignedTruck || '';
        document.getElementById('hs_trailer').value = '';
        document.getElementById('hs_genset').value = '';
        new bootstrap.Modal(document.getElementById('hustlingModal')).show();
      }
      $('#hustlingForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
          url: 'php/operations/create_hustling_dispatch.php',
          type: 'POST',
          data: $(this).serialize(),
          dataType: 'json',
          success: function (response) {
            if (response.status === 'success') {
              Swal.fire({ icon: 'success', title: 'Hustling Day Created', text: response.message, timer: 2200, showConfirmButton: false })
                .then(() => { $('#hustlingModal').modal('hide'); });
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: response.message });
            }
          },
          error: function () { Swal.fire({ icon: 'error', title: 'Server Error', text: 'Something went wrong.' }); }
        });
      });

      function markGood(driver_id) {
        Swal.fire({
          title: 'Unblock this driver?',
          text: 'This will clear the block and set the driver status to Good.',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Yes, unblock',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#28a745'
        }).then((result) => {
          if (!result.isConfirmed) return;
          $.ajax({
            url: 'php/operations/mark_driver_good.php',
            type: 'POST',
            data: { driver_id: driver_id },
            dataType: 'json',
            success: function (response) {
              if (response.status === 'success') {
                Swal.fire({ icon: 'success', title: 'Unblocked', text: response.message, timer: 2000, showConfirmButton: false })
                  .then(() => location.reload());
              } else {
                Swal.fire({ icon: 'error', title: 'Error', text: response.message });
              }
            },
            error: function () { Swal.fire({ icon: 'error', title: 'Server Error', text: 'Unable to process request.' }); }
          });
        });
      }
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: dispatcher-index.php?route=login");
  exit();
}
