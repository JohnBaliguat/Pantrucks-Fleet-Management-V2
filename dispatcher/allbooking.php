<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Dispatcher") {


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
              
            <div class="col-lg-12">
              <div class="row">
               
                <!-- Booking List Card -->
                <div class="col-md-12">
                  <div class="card overflow-hidden">
                    <div class="card-body pb-0">
                       <div class="row">
                          <div class="col-md-3 mb-3">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search Booking...">
                          </div>
                          <div class="col-md-7 mb-3">
                            
                          </div>
                          <div class="col-md-2 mb-3 text-end">
                            <a href="dispatch-mybook" class="btn btn-primary">Back</a>
                          </div>
                      </div>
                      <div class="d-flex align-items-start mb-3">  
                        <div>
                          <h4 class="card-title">Booking List</h4>
                          <p class="card-subtitle">Drag and Drop to Reorder</p>
                        </div>
                      </div>
                      <div class="text-body-secondary pt-3 mb-4 scroll-container">
                        <div class="booking-list-container">
                        <div id="bookingColumns" class="booking-columns">
                          <!-- Columns by customer will be rendered here -->
                        </div>
                      </div>
                      </div>
                      
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
                      <input type="hidden" class="form-control" id="userName" name="userName" value="<?php echo strtoupper($data['user_lname']) . ', ' . 					strtoupper(substr($data['user_fname'], 0, 1)) . '' . strtoupper(substr($data['user_mname'], 0, 1)); ?>" required>
                      <div class="mb-3">
                        <label class="form-label">Unit</label>
                        <input type="text" id="assignUnitName1" name="unit_name" class="form-control" autocomplete="off" required>
                        <ul id="truckList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>

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

            <div class="py-6 px-6 text-center">
              <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
            </div>
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
    <script src="js/allbooking.js"></script>
  </body>

  </html>
<?php
} else {
  header("Location: dispatcher-index.php?route=login");
  exit();
} ?>
