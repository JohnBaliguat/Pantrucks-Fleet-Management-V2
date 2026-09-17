<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Dispatcher") {


?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trip Report</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
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
                            <h4 class="card-title">Generate Report</h4>
                          </div>
                        </div>
                          <form class="row g-3 align-items-center mb-3">
                            <div class="col-auto">
                              <label for="Drivers Name" class="col-form-label">Drivers Name</label>
                            </div>
                            <div class="col-auto">
                              <input class="form-control" list="datalistOptions_driverName" name="drivers_name" id="drivers_name" placeholder="Enter Driver's Name" required>
                            <datalist id="datalistOptions_driverName">
                              <option value="Sample">
                            </datalist>
                            </div>
                            <div class="col-auto">
                              <label for="fromDate" class="col-form-label">From</label>
                            </div>
                            <div class="col-auto">
                              <input type="datetime-local" id="fromDate" name="fromDate" class="form-control">
                            </div>
                            <div class="col-auto">
                              <label for="toDate" class="col-form-label">To</label>
                            </div>
                            <div class="col-auto">
                              <input type="datetime-local" id="toDate" name="toDate" class="form-control">
                            </div>
                            <div class="col-auto">
                              <button type="submit" class="btn btn-primary">Generate</button>
                            </div>
                          </form>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="card overflow-hidden">
                      <div class="card-body pb-0">
                        <div class="card">
                          <img src="assets/images/profile/user-5.jpg" class="card-img-top" alt="...">
                          <div class="card-body text-center">
                            <h5 class="card-title">Driver Name</h5>
                            <p class="card-text">Some quick example text to build on the card title and make up the bulk of
                              the
                              card's content.</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>


                  <div class="col-md-8">
                    <div class="card">
                      <div class="card-body">
                        <div class="d-md-flex align-items-center">
                          <div>
                            <h4 class="card-title">Trip Report</h4>
                          </div>
                        </div>
                        <div class="table-responsive mt-4">
                          <table class="table mb-0 text-nowrap varient-table align-middle fs-3">
                            <thead>
                              <tr>
                                <th scope="col" class="px-0 text-muted">
                                  Truck
                                </th>
                                <th scope="col" class="px-0 text-muted">STD</th>
                                <th scope="col" class="px-0 text-muted">Plate No</th>
                                <th scope="col" class="px-0 text-muted">Assign To</th>
                                <th scope="col" class="px-0 text-muted">Status</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td class="px-0">
                                  <div class="d-flex align-items-center">
                                    <img src="assets/images/profile/tucklogo.png" class="rounded-circle" width="40"
                                      alt="flexy" />
                                    <div class="ms-3">
                                      <h6 class="mb-0 fw-bolder">PM810</h6>
                                      <span class="text-muted">At PTSI Base</span>
                                    </div>
                                  </div>
                                </td>
                                <td class="px-0">2.2</td>
                                <td class="px-0">12222</td>
                                <td class="px-0">Sample</td>
                                <td class="px-0">
                                  <span class="badge bg-info">Good</span>
                                </td>
                              </tr>
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
  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/apexcharts/dist/apexcharts.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script src="assets/js/dashboard.js"></script>
  
  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
</body>

</html>
<?php
} else {
    header("Location: dispatcher-index.php?route=login");
    exit();
} ?>
