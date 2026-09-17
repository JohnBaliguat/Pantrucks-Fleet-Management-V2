<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Dispatcher") {


?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Genset</title>
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
            <?php
            include "php/config/config.php";

            // Count trailers based on status
           $query = "SELECT unit_status, COUNT(*) as count FROM units WHERE unit_name LIKE 'GS%' GROUP BY unit_status";
            $result = $conn->query($query);

            // Initialize counts
            $counts = [
                'good' => 0,
                'on trip' => 0,
                'shop unit' => 0,
                'rescue' => 0
            ];

            while ($row = ($result)->fetch()) {
                $status = strtolower($row['unit_status']);
                $counts[$status] = $row['count'];
            }
            ?>
            

            <div class="col-lg-3">
              <div class="card overflow-hidden">
                <div class="card-body pb-0">
                  <div class="d-flex align-items-start">
                    <div>
                      <h4 class="card-title">Genset Stats</h4>
                      <p class="card-subtitle">Number of genset by Status</p>
                    </div>
                  </div>
                  <div class="mt-4 pb-3 d-flex align-items-center">
                    <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                      <i class="ti ti-bolt fs-6"></i>
                    </span>
                    <div class="ms-3">
                      <h5 class="mb-0 fw-bolder fs-4">Genset In Base</h5>
                    </div>
                    <div class="ms-auto">
                      <span class="badge bg-secondary-subtle text-muted"><?= $counts['good'] ?></span>
                    </div>
                  </div>
                  <div class="py-3 d-flex align-items-center">
                    <span class="btn btn-success rounded-circle round-48 hstack justify-content-center">
                      <i class="ti ti-bolt fs-6"></i>
                    </span>
                    <div class="ms-3">
                      <h5 class="mb-0 fw-bolder fs-4">Genset In Trip</h5>
                    </div>
                    <div class="ms-auto">
                      <span class="badge bg-secondary-subtle text-muted"><?= $counts['on trip'] ?></span>
                    </div>
                  </div>
                  <div class="py-3 d-flex align-items-center">
                    <span class="btn btn-warning rounded-circle round-48 hstack justify-content-center">
                      <i class="ti ti-bolt fs-6"></i>
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
                      <i class="ti ti-bolt-off fs-6"></i>
                    </span>
                    <div class="ms-3">
                      <h5 class="mb-0 fw-bolder fs-4">Shop Unit</h5>
                    </div>
                    <div class="ms-auto">
                      <span class="badge bg-secondary-subtle text-muted"><?= $counts['shop unit'] ?></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-9">
              <div class="card">
                <div class="card-body">
                  <div class="d-md-flex align-items-center">
                    <div>
                      <h4 class="card-title">Genset Management List</h4>
                    </div>
                    <div class="ms-auto mt-3 mt-md-0">
                      <button class="btn btn-primary btn-sm" type="button" id="AddModal"><i class="ti ti-plus"></i> Add Genset</button>
                    </div>
                  </div>
                  <div class="table-responsive mt-4">
                    <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data">
                      <thead>
                        <tr>
                          <th scope="col" class="px-0 text-muted">
                            Genset Name
                          </th>
                          <th scope="col" class="px-0 text-muted">STD</th>
                          <th scope="col" class="px-0 text-muted">Plate No</th>
                          <th scope="col" class="px-0 text-muted">Assign To</th>
                          <th scope="col" class="px-0 text-muted">Status</th>
                          <th scope="col" class="px-0 text-muted text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php include "php/config/config.php";
                    $query = "SELECT * FROM units WHERE unit_name LIKE 'GS%' ORDER BY unit_id DESC";
                    $result = $conn->query($query);
                    $count = 0;
                    while ($row = ($result)->fetch()) {
                      $count++;
                  ?>
                        <tr>
                          <td class="px-0">
                            <div class="d-flex align-items-center">
                              <img src="assets/images/profile/genset.png" class="rounded-circle" width="40"
                                alt="flexy" />
                              <div class="ms-3">
                                <h6 class="mb-0 fw-bolder"><?php echo $row['unit_name']; ?></h6>
                                <span class="text-muted">At <?php echo $row['unit_address']; ?></span>
                              </div>
                            </div>
                          </td>
                          <td class="px-0"><?php echo $row['unit_std']; ?></td>
                          <td class="px-0"><?php echo $row['unit_plate']; ?></td>
                          <td class="px-0"><?php echo $row['unit_assign']; ?></td>
                          <td class="px-0">
                            <?php
                            $status = trim($row['unit_status']);
                            if ($status === "" || $status === "Good") {
                              echo '<span class="badge bg-info">Good</span>';
                            } elseif ($status === "Shop Unit") {
                              echo '<span class="badge bg-warning text-dark">Shop Unit</span>';
                            } elseif ($status === "Disposed Unit") {
                              echo '<span class="badge bg-danger">Disposed Unit</span>';
                            } else {
                              echo '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
                            }
                            ?>
                          </td>
                          <td class="px-0 text-dark fw-medium text-end">
                            <div class="d-grid gap-2 d-md-block">
                              <button class="btn btn-primary btn-sm" type="button" onclick="openEditModal('<?php echo $row['unit_id']; ?>', 
                                    '<?php echo $row['unit_name']; ?>', 
                                    '<?php echo $row['unit_std']; ?>',
                                    '<?php echo $row['unit_plate']; ?>',
                                    '<?php echo $row['unit_address']; ?>');"><i class="ti ti-edit"></i></button>
                              <button class="btn btn-danger btn-sm" type="button" onclick="deleteUnit('<?php echo $row['unit_id']; ?>');"><i class="ti ti-trash"></i></button>
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
  <!-- Add Truck Unit Modal -->
      <div class="modal fade" id="addmodal">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h3 id="title">Add Unit</h3>
            </div>
            <div class="modal-body">
              <form id="addForm" action="php/addtruck.php" method="POST" enctype="multipart/form-data">
                <div class="row">
                  <div class="input-style-1">
                    <div class="column">
                      <label for="unit_name">Unit Name</label>
                      <input type="text" class="form-control" name="unit_name" id="unit_name" placeholder="Enter Unit Name" required>
                    </div>
                    <div class="column" hidden>
                      <label for="unit_plate">Unit Plate Number</label>
                      <input type="text" class="form-control" name="unit_plate" id="unit_plate" placeholder="Enter Unit Plate Number">
                    </div>
                    <div class="column" hidden>
                      <label for="unit_location">Location</label>
                      <input type="text" class="form-control" name="unit_location" id="unit_location" placeholder="Enter Location">
                    </div>
                    <div class="column">
                      <label for="unit_std">Unit Standard Ratio</label>
                      <input type="number" class="form-control" name="unit_std" id="unit_std" placeholder="Enter Unit Standard Ratio">
                    </div>
                  </div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button class="btn btn-success" name="submit" id="addTruck">Save</button>
              <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>
      </div>
      <!-- End of Modal -->

      <!-- Update Truck Unit Modal -->
      <div class="modal fade" id="editModal">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h3 id="title">Update Unit</h3>
            </div>
            <div class="modal-body">
              <form id="editForm" action="php/updatetruck.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="unit_id1" id="unit_id1">
                <div class="row">
                  <div class="input-style-1">
                    <div class="column">
                      <label for="unit_name_update">Unit Name</label>
                      <input type="text" class="form-control" name="unit_name_update" id="unit_name_update" required>
                    </div>
                    <div class="column" hidden>
                      <label for="unit_plate">Unit Plate Number</label>
                      <input type="text" class="form-control" name="unit_plate_update" id="unit_plate_update" placeholder="Enter Unit Plate Number">
                    </div>
                    <div class="column" hidden>
                      <label for="unit_location">Location</label>
                      <input type="text" class="form-control" name="unit_location_update" id="unit_location_update" placeholder="Enter Location">
                    </div>
                    <div class="column">
                      <label for="unit_std_update">Unit Standard</label>
                      <input type="number" class="form-control" name="unit_std_update" id="unit_std_update" required>
                    </div>
                  </div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button class="btn btn-success btn-sm" name="submit" id="UpdateTruck">Update</button>
              <button class="btn btn-warning btn-sm" name="submit" id="FixUnit">Fix Unit</button>
              <button class="btn btn-warning btn-sm" name="submit" id="shopUnit">Shop Unit</button>
              <button class="btn btn-danger btn-sm" name="submit" id="disposeUnit">Dispose Unit</button>
              <button data-bs-dismiss="modal" class="btn btn-secondary btn-sm">Cancel</button>
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
  <script src="js/genset.js"></script>
  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
</body>

</html>
<?php
} else {
    header("Location: dispatcher-index.php?route=login");
    exit();
}
