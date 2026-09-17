<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Dispatcher") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispatching</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="datatable/datatables.min.css">
    <link rel="stylesheet" href="datatable/dataTables.columnFilter.css">
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
      .done-row {
        background-color: #d4edda !important;
        /* light green */
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
            <!-- Phase 14 — legacy manual-dispatch screen, see CHANGES.md. -->
            <div class="alert alert-warning d-flex align-items-center justify-content-between mb-3">
              <div>
                <b>Legacy screen.</b> Please use the new <b>Dispatch Board</b> — drag-and-drop assignment with live driver GPS and smart recommendations.
              </div>
              <a href="dispatch-tiles" class="btn btn-warning btn-sm"><i class="ti ti-arrow-right"></i> Open Dispatch Board</a>
            </div>
            <!--  Row 1 -->
            <div class="row">
              <div class="col-lg-9">
                <div class="row">
                  <div class="col-md-4">
                  <div class="card overflow-hidden">
                    <div class="card-body pb-0">
                      <div class="pb-3 d-flex align-items-center">
                        <span class="btn btn-info rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-calendar fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4">Available Booking</h5>
                        </div>
                        <div class="ms-auto">
                          <span id="" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="card overflow-hidden">
                    <div class="card-body pb-0">
                      <div class="pb-3 d-flex align-items-center">
                        <span class="btn btn-warning rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-calendar fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4">Booking Serve</h5>
                        </div>
                        <div class="ms-auto">
                          <span id="" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="card overflow-hidden">
                    <div class="card-body pb-0">
                      <div class="pb-3 d-flex align-items-center">
                        <span class="btn btn-success rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-calendar fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4">Booking Complete</h5>
                        </div>
                        <div class="ms-auto">
                          <span id="" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                </div>
                
                <div class="col-md-12">
                  <div class="modern-card">
                    <div class="row m-2">
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
                            'Available' => 0,
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
                
              </div>


              <?php
              include "php/config/config.php";

              // Count trailers based on status
              $query = "SELECT unit_status, COUNT(*) as count FROM units WHERE unit_name NOT LIKE 'GS%' GROUP BY unit_status";
              $result = $conn->query($query);

              // Initialize counts
              $counts = [
                'good' => 0,
                'dispatch' => 0,
                'shop unit' => 0,
                'rescue' => 0
              ];

              while ($row = ($result)->fetch()) {
                $status = strtolower($row['unit_status']);
                if (isset($counts[$status])) {
                  $counts[$status] = $row['count'];
                }
              }
              ?>


              <div class="col-lg-3">
                <div class="row">
                  <div class="col-md-12">
                    <div class="card overflow-hidden">
                      <div class="card-body pb-0">
                        <div class="d-flex align-items-start">
                          <div>
                            <h4 class="card-title">Truck Stats</h4>
                            <p class="card-subtitle">Number of truck by Status</p>
                          </div>
                        </div>
                        <div class="mt-4 pb-3 d-flex align-items-center">
                          <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-truck fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Truck In Base</h5>
                          </div>
                          <div class="ms-auto">
                            <span class="badge bg-secondary-subtle text-muted"><?= $counts['good'] ?></span>
                          </div>
                        </div>
                        <div class="py-3 d-flex align-items-center">
                          <span class="btn btn-success rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-truck-delivery fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Truck In Trip</h5>
                          </div>
                          <div class="ms-auto">
                            <span class="badge bg-secondary-subtle text-muted"><?= $counts['dispatch'] ?></span>
                          </div>
                        </div>
                        <div class="py-3 d-flex align-items-center">
                          <span class="btn btn-warning rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-car-crane fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Rescue Units</h5>
                          </div>
                          <div class="ms-auto">
                            <span class="badge bg-secondary-subtle text-muted"><?= $counts['rescue'] ?></span>
                          </div>
                        </div>
                        <div class="pt-3 mb-7 d-flex align-items-center">
                          <span class="btn btn-danger rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-truck-off fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Shop Units</h5>
                          </div>
                          <div class="ms-auto">
                            <span class="badge bg-secondary-subtle text-muted"><?= $counts['shop unit'] ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-12">
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
                </div>
              </div>


              <div class="col-lg-12 mt-4">
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

            </div>
            <div class="py-6 px-6 text-center">
              <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- VIEW MODAL -->

    <!-- Modal -->
    <div class="modal fade" id="unitDispatchModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Dispatch Data - <span id="modalUnitName"></span> </h5>
            <button type="button" class="btn btn-primary btn-sm" id="markAvailable" style="margin-left: 5px;">Mark As Available</button>
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
                <tr>
                  <td colspan="13" class="text-center">Select a unit...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="assignBookingModal" tabindex="-1">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-4 shadow">
          <div class="modal-header">
            <h5 class="modal-title">
              Assign Booking - <span id="assignUnitName"></span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <?php
          include 'php/config/config.php';

          $locationQuery = "SELECT location_name FROM location ORDER BY location_id ASC";
          $locationResult = $conn->query($locationQuery);

          $locationQuery1 = "SELECT location_name FROM location ORDER BY location_id ASC";
          $locationResult1 = $conn->query($locationQuery1);

          $locationQuery2 = "SELECT location_name FROM location ORDER BY location_id ASC";
          $locationResult2 = $conn->query($locationQuery2);

          $locationQuery3 = "SELECT location_name FROM location ORDER BY location_id ASC";
          $locationResult3 = $conn->query($locationQuery3);

          $locationQuery4 = "SELECT location_name FROM location ORDER BY location_id ASC";
          $locationResult4 = $conn->query($locationQuery4);

          $locationQuery5 = "SELECT location_name FROM location ORDER BY location_id ASC";
          $locationResult5 = $conn->query($locationQuery5);

          $locationQuery6 = "SELECT location_name FROM location ORDER BY location_id ASC";
          $locationResult6 = $conn->query($locationQuery6);

          $locationQuery7 = "SELECT location_name FROM location ORDER BY location_id ASC";
          $locationResult7 = $conn->query($locationQuery7);

          ?>
          <div class="modal-body">
            <form action="php/assign_booking1.php" method="post">
              <input type="hidden" id="assinglocation" name="assinglocation" value="<?php echo $location; ?>">
              <input type="hidden" class="form-control" id="userName" name="userName" value="<?php echo strtoupper($data['user_lname']) . ', ' . strtoupper(substr($data['user_fname'], 0, 1)) . '' . strtoupper(substr($data['user_mname'], 0, 1)); ?>" required>

              <!-- GENERAL INFO -->
              <div class="row mb-3">
                <input type="hidden" id="assignUnitName1" name="unit_name">

                <div class="col-md-6">
                  <div class="mb-3 position-relative">
                    <label class="form-label">Driver <span style="color: red;">*</span></label>
                    <input type="text" id="driver" class="form-control" placeholder="-- Select Driver --" required>
                    <input type="hidden" id="driverId" name="driver_id">
                    <ul id="driverList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="mb-3 position-relative">
                    <label class="form-label">Genset</label>
                    <input type="text" id="genset" class="form-control" placeholder="-- Select Genset --">
                    <ul id="gensetList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>

                  </div>

                </div>
              </div>

              <!-- TABS -->
              <ul class="nav nav-pills mb-3" id="bookingTabs">
                <li class="nav-item">
                  <button class="nav-link active" type="button" data-bs-toggle="pill" data-bs-target="#booking1">Booking 1</button>
                </li>
                <li class="nav-item">
                  <button class="nav-link" type="button" data-bs-toggle="pill" data-bs-target="#booking2">Booking 2</button>
                </li>
              </ul>

              <div class="tab-content">

                <!-- BOOKING 1 -->
                <div class="tab-pane fade show active" id="booking1">
                  <div class="row g-3">
                    <div class="col-md-12">
                      <div class="mb-3 position-relative">
                        <input class="form-control" name="b1_bookingNo" id="b1_bookingNo" placeholder="-- Select Booking --">
                        <ul id="b1_bookingList" class="list-group position-absolute w-100" style="z-index: 1050; display: none; max-height: 200px; overflow-y: auto;"></ul>
                      </div>
                    </div>
                    <input type="hidden" id="booking_no1" name="booking_no1" class="form-control">
                    <div class="col-md-4">
                      <div class="mb-3 position-relative">
                        <input class="form-control" name="b1_trailer" id="b1_trailer" placeholder="-- Select Trailer --">
                        <ul id="b1_trailerList" class="list-group position-absolute w-100" style="z-index: 1050; display: none; max-height: 200px; overflow-y: auto;"></ul>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <input class="form-control" name="b1_receipt" id="b1_receipt" placeholder="Enter Trip Receipt">
                    </div>
                    <div class="col-md-4">
                      <input class="form-control" name="b1_ecs" id="b1_ecs" placeholder="Enter ECS">
                    </div>

                    <div class="col-md-6">
                      <input class="form-control" list="datalistOptions_destination_from" name="b1_trip1_from" id="b1_trip1_from" placeholder="Select Trip 1 Destination From">
                      <datalist id="datalistOptions_destination_from">
                        <?php
                        while ($row5 = ($locationResult)->fetch()) {
                          echo "<option value=\"{$row5['location_name']}\">";
                        }
                        ?>
                      </datalist>
                    </div>
                    <div class="col-md-6">
                      <input class="form-control" list="datalistOptions_destination_from1" name="b1_trip1_to" id="b1_trip1_to" placeholder="Select Trip 1 Destination To">
                      <datalist id="datalistOptions_destination_from1">
                        <?php
                        while ($row5 = ($locationResult1)->fetch()) {
                          echo "<option value=\"{$row5['location_name']}\">";
                        }
                        ?>
                      </datalist>
                    </div>

                    <div class="col-md-6">
                      <input class="form-control" list="datalistOptions_destination_from2" name="b1_trip2_from" id="b1_trip2_from" placeholder="Select Trip 2 Destination From">
                      <datalist id="datalistOptions_destination_from2">
                        <?php
                        while ($row5 = ($locationResult2)->fetch()) {
                          echo "<option value=\"{$row5['location_name']}\">";
                        }
                        ?>
                      </datalist>
                    </div>
                    <div class="col-md-6">
                      <input class="form-control" list="datalistOptions_destination_from3" name="b1_trip2_to" id="b1_trip2_to" placeholder="Select Trip 2 Destination To">
                      <datalist id="datalistOptions_destination_from3">
                        <?php
                        while ($row5 = ($locationResult3)->fetch()) {
                          echo "<option value=\"{$row5['location_name']}\">";
                        }
                        ?>
                      </datalist>
                    </div>
                  </div>
                </div>

                <!-- BOOKING 2 -->
                <div class="tab-pane fade" id="booking2">
                  <div class="row g-3">
                    <div class="col-md-12">
                      <div class="mb-3 position-relative">
                        <input class="form-control" name="b2_bookingNo" id="b2_bookingNo" placeholder="-- Select Booking --">
                        <ul id="b2_bookingList" class="list-group position-absolute w-100" style="z-index: 1050; display: none; max-height: 200px; overflow-y: auto;"></ul>
                      </div>
                    </div>
                    <input type="hidden" id="booking_no2" name="booking_no2" class="form-control">
                    <div class="col-md-4">
                      <div class="mb-3 position-relative">
                        <input class="form-control" name="b2_trailer" id="b2_trailer" placeholder="-- Select Trailer --">
                        <ul id="b2_trailerList" class="list-group position-absolute w-100" style="z-index: 1050; display: none; max-height: 200px; overflow-y: auto;"></ul>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <input class="form-control" name="b2_receipt" id="b2_receipt" placeholder="Enter Trip Receipt">
                    </div>
                    <div class="col-md-4">
                      <input class="form-control" name="b2_ecs" id="b2_ecs" placeholder="Enter ECS">
                    </div>

                    <div class="col-md-6">
                      <input class="form-control" list="datalistOptions_destination_from4" name="b2_trip1_from" id="b2_trip1_from" placeholder="Select Trip 1 Destination From">
                      <datalist id="datalistOptions_destination_from4">
                        <?php
                        while ($row5 = ($locationResult4)->fetch()) {
                          echo "<option value=\"{$row5['location_name']}\">";
                        }
                        ?>
                      </datalist>
                    </div>
                    <div class="col-md-6">
                      <input class="form-control" list="datalistOptions_destination_from5" name="b2_trip1_to" id="b2_trip1_to" placeholder="Select Trip 1 Destination To">
                      <datalist id="datalistOptions_destination_from5">
                        <?php
                        while ($row5 = ($locationResult5)->fetch()) {
                          echo "<option value=\"{$row5['location_name']}\">";
                        }
                        ?>
                      </datalist>
                    </div>

                    <div class="col-md-6">
                      <input class="form-control" list="datalistOptions_destination_from6" name="b2_trip2_from" id="b2_trip2_from" placeholder="Select Trip 2 Destination From">
                      <datalist id="datalistOptions_destination_from6">
                        <?php
                        while ($row5 = ($locationResult6)->fetch()) {
                          echo "<option value=\"{$row5['location_name']}\">";
                        }
                        ?>
                      </datalist>
                    </div>
                    <div class="col-md-6">
                      <input class="form-control" list="datalistOptions_destination_from7" name="b2_trip2_to" id="b2_trip2_to" placeholder="Select Trip 2 Destination To">
                      <datalist id="datalistOptions_destination_from7">
                        <?php
                        while ($row5 = ($locationResult7)->fetch()) {
                          echo "<option value=\"{$row5['location_name']}\">";
                        }
                        ?>
                      </datalist>
                    </div>
                  </div>
                </div>

              </div>

              <div class="modal-footer mt-3">
                <button type="submit" class="btn btn-primary" id="confirmAssignBtn1">Assign Booking</button>
              </div>

            </form>
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

    <div class="modal fade" id="availabilityChecklistModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Return Checklist</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="checklist_unit_id">
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-body">
                <h5 class="card-title mb-1">Truck for this shift</h5>
                <p class="text-muted small mb-3">Confirm the truck being returned to Available.</p>
                <input type="text" id="checklist_unit_name" class="form-control form-control-lg" readonly>
              </div>
            </div>
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="card-title mb-1">Pre-Departure Inspection</h5>
                <p class="text-muted small mb-3">Tap each item to confirm it's good. All must pass before you can flip to <em>Available</em>.</p>
                <div class="d-grid gap-2 mt-3" id="availabilityChecklistItems">
                  <button type="button" class="btn btn-outline-secondary text-start d-flex align-items-center justify-content-between checklist-item" data-key="fuel_ok">
                    <span><i class="ti ti-gas-station me-2"></i>Fuel topped up</span>
                    <span class="check-state"><i class="ti ti-circle"></i></span>
                  </button>
                  <button type="button" class="btn btn-outline-secondary text-start d-flex align-items-center justify-content-between checklist-item" data-key="tyres_ok">
                    <span><i class="ti ti-circle-dot me-2"></i>Tyres inflated, no damage</span>
                    <span class="check-state"><i class="ti ti-circle"></i></span>
                  </button>
                  <button type="button" class="btn btn-outline-secondary text-start d-flex align-items-center justify-content-between checklist-item" data-key="lights_ok">
                    <span><i class="ti ti-bulb me-2"></i>Headlights, signals, brake lights</span>
                    <span class="check-state"><i class="ti ti-circle"></i></span>
                  </button>
                  <button type="button" class="btn btn-outline-secondary text-start d-flex align-items-center justify-content-between checklist-item" data-key="cargo_area_ok">
                    <span><i class="ti ti-box me-2"></i>Cargo area clean and secure</span>
                    <span class="check-state"><i class="ti ti-circle"></i></span>
                  </button>
                  <button type="button" class="btn btn-outline-secondary text-start d-flex align-items-center justify-content-between checklist-item" data-key="genset_ok">
                    <span><i class="ti ti-engine me-2"></i>Genset functional (if applicable)</span>
                    <span class="check-state"><i class="ti ti-circle"></i></span>
                  </button>
                </div>
                <div class="mt-3">
                  <label class="form-label">Remarks (optional)</label>
                  <textarea id="availability_remarks" class="form-control" rows="3" placeholder="Anything the workshop should know"></textarea>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" id="submitAvailabilityChecklist" class="btn btn-primary" disabled>
              <i class="ti ti-check"></i> Submit and Go Available
            </button>
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
    <script src="datatable/dataTables.columnFilter.js"></script>
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script src="js/dispatch.js?v=<?= urlencode((string)@filemtime(__DIR__ . '/../js/dispatch.js')) ?>"></script>
    <script>
      // DRIVER ATTENDANCE

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
                    data-name="${driver.driver_name.toLowerCase()}"
                    data-status="${status}">
                    
                    <!-- LEFT: ACTIVE BOOKING COUNT -->
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
      setInterval(loadDrivers, 20000);

      // search
      document.getElementById("driverSearch").addEventListener("keyup", function() {
        const search = this.value.toLowerCase();
        document.querySelectorAll(".driver-card").forEach(card => {
          card.style.display = card.dataset.name.includes(search) ? "flex" : "none";
        });
      });


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

      // MODAL SHOW FOR VIEW DATA

      document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".truck-card").forEach(card => {
          card.addEventListener("click", function() {
            const unitId = this.dataset.unitId;
            const unitName = this.dataset.unit;
            const status = this.dataset.status;

            if (status === "Available") {
              showAssignBooking(unitId, unitName);
            } else {
              showUnitDispatch(unitId, unitName);
            }
          });
        });
      });

      let currentUnitName = "";
      const availabilityChecklistState = {
        fuel_ok: 0,
        tyres_ok: 0,
        lights_ok: 0,
        cargo_area_ok: 0,
        genset_ok: 0
      };

      function refreshAvailabilitySubmit() {
        const allOk = Object.values(availabilityChecklistState).every(v => v === 1);
        document.getElementById("submitAvailabilityChecklist").disabled = !allOk;
      }

      function resetAvailabilityChecklist() {
        Object.keys(availabilityChecklistState).forEach(k => availabilityChecklistState[k] = 0);
        document.querySelectorAll(".checklist-item").forEach(btn => {
          btn.classList.remove("btn-success");
          btn.classList.add("btn-outline-secondary");
          const stateNode = btn.querySelector(".check-state");
          if (stateNode) stateNode.innerHTML = '<i class="ti ti-circle"></i>';
        });
        document.getElementById("availability_remarks").value = "";
        refreshAvailabilitySubmit();
      }

      function showUnitDispatch(unitId, unitName) {
        currentUnitId = unitId;
        currentUnitName = unitName;

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

      document.querySelectorAll(".checklist-item").forEach(btn => {
        btn.addEventListener("click", function() {
          const key = this.dataset.key;
          availabilityChecklistState[key] = availabilityChecklistState[key] ? 0 : 1;
          this.classList.toggle("btn-success", !!availabilityChecklistState[key]);
          this.classList.toggle("btn-outline-secondary", !availabilityChecklistState[key]);
          const stateNode = this.querySelector(".check-state");
          if (stateNode) {
            stateNode.innerHTML = availabilityChecklistState[key]
              ? '<i class="ti ti-circle-check text-white"></i>'
              : '<i class="ti ti-circle"></i>';
          }
          refreshAvailabilitySubmit();
        });
      });

      document.getElementById("markAvailable").addEventListener("click", function() {
        if (!currentUnitId) return;
        resetAvailabilityChecklist();
        document.getElementById("checklist_unit_id").value = currentUnitId;
        document.getElementById("checklist_unit_name").value = currentUnitName || document.getElementById("modalUnitName").textContent || "";
        new bootstrap.Modal(document.getElementById("availabilityChecklistModal")).show();
      });

      document.getElementById("submitAvailabilityChecklist").addEventListener("click", function() {
        const form = new URLSearchParams({
          unit_id: document.getElementById("checklist_unit_id").value,
          fuel_ok: String(availabilityChecklistState.fuel_ok),
          tyres_ok: String(availabilityChecklistState.tyres_ok),
          lights_ok: String(availabilityChecklistState.lights_ok),
          cargo_area_ok: String(availabilityChecklistState.cargo_area_ok),
          genset_ok: String(availabilityChecklistState.genset_ok),
          remarks: document.getElementById("availability_remarks").value || ''
        });

        fetch("php/crud/update/update_unit_status.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded"
          },
          body: form.toString()
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            Swal.fire({
              icon: "success",
              title: "Unit Available",
              text: data.message || "Checklist submitted successfully.",
              timer: 1400,
              showConfirmButton: false
            }).then(() => {
              bootstrap.Modal.getInstance(document.getElementById("availabilityChecklistModal"))?.hide();
              bootstrap.Modal.getInstance(document.getElementById("unitDispatchModal"))?.hide();
              location.reload();
            });
          } else {
            Swal.fire({ icon: "error", text: data.message || "Failed to update unit status" });
          }
        })
        .catch(() => Swal.fire({ icon: "error", text: "Server error" }));
      });

      function showAssignBooking(unitId, unitName) {
        document.getElementById("assignUnitName").textContent = unitName;
        document.getElementById("assignUnitName1").value = unitName;

        new bootstrap.Modal(document.getElementById("assignBookingModal")).show();
      }



      // Search item

      document.addEventListener("DOMContentLoaded", function() {

        function setupSearch(inputId, listId, url, mapFn, onSelect) {
          const input = document.getElementById(inputId);
          const list = document.getElementById(listId);
          let data = [];
          let index = 0;

          fetch(url)
            .then(res => res.json())
            .then(json => data = json);

          input.addEventListener("input", function() {
            const val = this.value.toLowerCase();
            list.innerHTML = "";
            index = 0;

            if (!val) {
              list.style.display = "none";
              return;
            }

            const mapped = mapFn(data);
            const filtered = mapped.filter(x => x.toLowerCase().includes(val));

            if (!filtered.length) {
              list.style.display = "none";
              return;
            }

            filtered.forEach((item, i) => {
              const li = document.createElement("li");
              li.className = "list-group-item";
              li.textContent = item;
              if (i === 0) li.classList.add("active-suggestion");

              li.onclick = () => {
                onSelect(item, data);
                list.style.display = "none";
              };

              list.appendChild(li);
            });

            list.style.display = "block";
          });

          input.addEventListener("keydown", e => {
            const items = list.querySelectorAll("li");
            if (!items.length) return;

            if (e.key === "ArrowDown") index = (index + 1) % items.length;
            if (e.key === "ArrowUp") index = (index - 1 + items.length) % items.length;

            if (e.key === "Enter") {
              e.preventDefault();
              items[index].click();
            }

            items.forEach(i => i.classList.remove("active-suggestion"));
            items[index].classList.add("active-suggestion");
          });

          // close when clicking outside
          document.addEventListener("click", e => {
            if (!list.contains(e.target) && !input.contains(e.target)) {
              list.style.display = "none";
            }
          });
        }

        // DRIVER
        setupSearch(
          "driver",
          "driverList",
          "php/fetch/get_drivers2.php",
          d => d.map(x => x.name),
          (val, data) => {
            document.getElementById("driver").value = val;
            const selected = data.find(d => d.name === val);
            document.getElementById("driverId").value = selected ? selected.id : "";
          }
        );

        // GENSET
        setupSearch(
          "genset",
          "gensetList",
          "php/fetch/get_gensets.php",
          d => d,
          val => document.getElementById("genset").value = val
        );

        // BOOKING 1
        setupSearch(
          "b1_bookingNo",
          "b1_bookingList",
          "php/fetch/get_bookingNo.php",
          d => d.map(x => x.name),
          (val, data) => {
            document.getElementById("b1_bookingNo").value = val;
            const selected = data.find(d => d.name === val);
            document.getElementById("booking_no1").value = selected ? selected.id : "";
          }
        );

        // BOOKING 2
        setupSearch(
          "b2_bookingNo",
          "b2_bookingList",
          "php/fetch/get_bookingNo.php",
          d => d.map(x => x.name),
          (val, data) => {
            document.getElementById("b2_bookingNo").value = val;
            const selected = data.find(d => d.name === val);
            document.getElementById("booking_no2").value = selected ? selected.id : "";
          }
        );

        // TRAILER BOOKING 1
        setupSearch(
          "b1_trailer",
          "b1_trailerList",
          "php/fetch/get_trailers.php",
          d => d,
          val => document.getElementById("b1_trailer").value = val
        );

        // TRAILER BOOKING 2
        setupSearch(
          "b2_trailer",
          "b2_trailerList",
          "php/fetch/get_trailers.php",
          d => d,
          val => document.getElementById("b2_trailer").value = val
        );

      });

      $(document).ready(function() {

        $('#confirmAssignBtn1').click(function(e) {
          e.preventDefault();

          let driver = $("#driver").val().trim();
          let driver_id = $("#driverId").val().trim();
          let genset = $("#genset").val().trim();
          let assignLocation = $("#assinglocation").val();
          let userName = $("#userName").val();
          let unitName = $("#assignUnitName1").val();

          if (!driver || !driver_id || !unitName) {
            Swal.fire({
              icon: "info",
              text: "Driver and Unit required"
            });
            return;
          }

          let booking1 = {
            booking_no: $("#booking_no1").val(),
            trailer: $("#b1_trailer").val(),
            receipt: $("#b1_receipt").val(),
            ecs: $("#b1_ecs").val(),
            trip1_from: $("#b1_trip1_from").val(),
            trip1_to: $("#b1_trip1_to").val(),
            trip2_from: $("#b1_trip2_from").val(),
            trip2_to: $("#b1_trip2_to").val()
          };

          let booking2 = {
            booking_no: $("#booking_no2").val(),
            trailer: $("#b2_trailer").val(),
            receipt: $("#b2_receipt").val(),
            ecs: $("#b2_ecs").val(),
            trip1_from: $("#b2_trip1_from").val(),
            trip1_to: $("#b2_trip1_to").val(),
            trip2_from: $("#b2_trip2_from").val(),
            trip2_to: $("#b2_trip2_to").val()
          };

          $.ajax({
            url: "php/operations/assign_booking1.php",
            type: "POST",
            dataType: "json",
            data: {
              driver: driver,
              driver_id: driver_id,
              genset: genset,
              assignLocation: assignLocation,
              userName: userName,
              unitName: unitName,
              booking1: JSON.stringify(booking1),
              booking2: JSON.stringify(booking2)
            },
            success: function(data) {

              if (data.status === "success") {
                Swal.fire({
                  icon: "success",
                  text: data.message,
                  timer: 1200,
                  showConfirmButton: false
                });

                setTimeout(() => {
                  if (data.dispatch_ids && data.dispatch_ids.length > 0) {

                    window.open(
                      "dispatcher-index.php?route=print1&ids=" + data.dispatch_ids.join(','),
                      "_blank"
                    );
                  }

                  location.reload();
                }, 1300);

              } else {
                Swal.fire({
                  icon: "error",
                  text: data.message
                });
              }
            },
            error: function(xhr) {
              console.log(xhr.responseText);
              Swal.fire({
                icon: "error",
                text: "Server error. Check console."
              });
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
