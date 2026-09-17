<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Segment Page</title>
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
              <div class="col-lg-6">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Hauling Segment Management List</h4>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0">
                        <button class="btn btn-primary btn-sm" type="button" id="addSegment"><i class="ti ti-plus"></i> Add Segment</button>
                      </div>
                    </div>
                    <div class="table-responsive mt-4">
                      <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data">
                        <thead>
                          <tr>
                            <th scope="col" class="px-0 text-muted">No.</th>
                            <th scope="col" class="px-0 text-muted">Segment</th>
                            <th scope="col" class="px-0 text-muted">Type</th>
                            <th scope="col" class="px-0 text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          include "php/config/config.php";
                          $count = 0;
                          $query = "SELECT hauling_id, hauling_segment, hauling_type FROM hauling ORDER BY hauling_id DESC";
                          $result = $conn->query($query);

                          while ($row = ($result)->fetch()) {
                            $count++;
                          ?>
                            <tr>
                              <td class="px-0"><?php echo $count; ?></td>
                              <td class="px-0"><?php echo $row['hauling_segment']; ?></td>
                              <td class="px-0"><?php echo $row['hauling_type']; ?></td>
                              <td class="px-0 text-dark fw-medium text-end">
                                <div class="d-grid gap-2 d-md-block">
                                  <button class="btn btn-primary btn-sm" type="button" onclick="openUpdateHauling(
                                '<?php echo $row['hauling_id']; ?>',
                                '<?php echo $row['hauling_segment']; ?>',
                                '<?php echo $row['hauling_type']; ?>'
                              );"><i class="ti ti-edit"></i></button>
                                  <button class="btn btn-danger btn-sm" type="button" onclick="deleteHauling('<?php echo $row['hauling_id']; ?>');"><i class="ti ti-trash"></i></button>
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
              <div class="col-lg-6">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Customer Management List</h4>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0">
                        <button class="btn btn-primary btn-sm" type="button" id="addcustomer"><i class="ti ti-plus"></i> Add Customenr</button>
                      </div>
                    </div>
                    <div class="table-responsive mt-4">
                      <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data1">
                        <thead>
                          <tr>
                            <th scope="col" class="px-0 text-muted">No</th>
                            <th scope="col" class="px-0 text-muted">Customer Code</th>
                            <th scope="col" class="px-0 text-muted">Customer Name</th>
                            <th scope="col" class="px-0 text-muted">Segment</th>
                            <th scope="col" class="px-0 text-muted">Trade Type</th>
                            <th scope="col" class="px-0 text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          include "php/config/config.php";
                          $count = 0;
                          $query = "SELECT * FROM customer ORDER BY customer_id DESC";
                          $result = $conn->query($query);

                          while ($row = ($result)->fetch()) {
                            $count++;
                          ?>
                            <tr>
                              <td class="px-0">
                                <?php echo $count; ?>
                              </td>
                              <td class="px-0"><?php echo $row['customer_code']; ?></td>
                              <td class="px-0"><?php echo $row['customer_name']; ?></td>
                              <td class="px-0"><?php echo htmlspecialchars($row['customer_segment'] ?? ''); ?></td>
                              <td class="px-0"><?php
                                $tt = ($row['trade_type'] ?? 'Export') === 'Import' ? 'Import' : 'Export';
                                echo '<span class="badge ' . ($tt === 'Import' ? 'bg-info' : 'bg-secondary') . '">' . $tt . '</span>';
                              ?></td>
                              <td class="px-0 text-dark fw-medium text-end">
                                <div class="d-grid gap-2 d-md-block">
                                  <button class="btn btn-primary btn-sm" type="button" onclick="openUpdateCustomer(
                                '<?php echo $row['customer_id']; ?>',
                                '<?php echo htmlspecialchars($row['customer_code'], ENT_QUOTES); ?>',
                                '<?php echo htmlspecialchars($row['customer_name'], ENT_QUOTES); ?>',
                                '<?php echo htmlspecialchars($row['customer_segment'] ?? '', ENT_QUOTES); ?>',
                                '<?php echo htmlspecialchars($tt, ENT_QUOTES); ?>'
                              );"><i class="ti ti-edit"></i></button>
                                  <button class="btn btn-danger btn-sm" type="button" onclick="deleteCustomer('<?php echo $row['customer_id']; ?>');"><i class="ti ti-trash"></i></button>
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
            <div class="row">
              <div class="col-lg-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Location Management List</h4>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0">
                        <button class="btn btn-primary btn-sm" type="button" id="addLocation"><i class="ti ti-plus"></i> Add Location</button>
                      </div>
                    </div>
                    <div class="table-responsive mt-4">
                      <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data2">
                        <thead>
                          <tr>
                            <th scope="col" class="px-0 text-muted">No</th>
                            <th scope="col" class="px-0 text-muted">Location Name</th>
                            <th scope="col" class="px-0 text-muted">latitude</th>
                            <th scope="col" class="px-0 text-muted">longitude</th>
                            <th scope="col" class="px-0 text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          include "php/config/config.php";
                          $count = 0;
                          $query = "SELECT * FROM location ORDER BY location_id DESC";
                          $result = $conn->query($query);

                          while ($row = ($result)->fetch()) {
                            $count++;
                          ?>
                            <tr>
                              <td class="px-0">
                                <?php echo $count; ?>
                              </td>
                              <td class="px-0"><?php echo $row['location_name']; ?></td>
                              <td class="px-0"><?php echo $row['latitude']; ?></td>
                              <td class="px-0"><?php echo $row['longitude']; ?></td>
                              <td class="px-0 text-dark fw-medium text-end">
                                <div class="d-grid gap-2 d-md-block">
                                  <button class="btn btn-primary btn-sm" type="button" onclick="openUpdateLocation(
                                '<?php echo $row['location_id']; ?>',
                                '<?php echo $row['location_name']; ?>',
                                '<?php echo $row['latitude']; ?>',
                                '<?php echo $row['longitude']; ?>'
                              );"><i class="ti ti-edit"></i></button>
                                  <button class="btn btn-danger btn-sm" type="button" onclick="deleteLocation('<?php echo $row['location_id']; ?>');"><i class="ti ti-trash"></i></button>
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
    <!-- Add Hauling Modal -->
    <div class="modal fade" id="addHaulingModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Add Hauling</h3>
          </div>
          <div class="modal-body">
            <form id="addHaulingForm" action="php/addhauling.php" method="POST">
              <div class="mb-3">
                <label for="hauling_segment">Segment</label>
                <input type="text" class="form-control" id="hauling_segment" name="hauling_segment" placeholder="Enter Segment" required>
              </div>
              <div class="mb-3">
                <label for="hauling_type">Type</label>
                <input type="text" class="form-control" id="hauling_type" name="hauling_type" placeholder="Enter Type" required>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" id="saveHauling">Save</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Modal -->

    <!-- Update Driver Modal -->
    <div class="modal fade" id="updateHaulingModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Update Hauling</h3>
          </div>
          <div class="modal-body">
            <form id="updateHaulingForm" action="php/updatehauling.php" method="POST">
              <input type="text" class="form-control" id="hauling_id" name="hauling_id" hidden>
              <div class="mb-3">
                <label for="hauling_segment">Segment</label>
                <input type="text" class="form-control" id="hauling_segment1" name="hauling_segment1" placeholder="Enter Segment" required>
              </div>
              <div class="mb-3">
                <label for="hauling_type">Type</label>
                <input type="text" class="form-control" id="hauling_type1" name="hauling_type1" placeholder="Enter Type" required>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" id="updateHauling">Update</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Modal -->


    <!-- Add Location Modal -->
    <div class="modal fade" id="addLocationModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Add Location</h3>
          </div>
          <div class="modal-body">
            <form id="addLocationForm" action="php/addlocation.php" method="POST">
              <div class="mb-3">
                <label for="location_name">Location Name</label>
                <input type="text" class="form-control" id="location_name" name="location_name" placeholder="Enter Location Name" required>
              </div>
              <div class="mb-3">
                <label for="latitude">Location latitude</label>
                <input type="text" class="form-control" id="latitude" name="latitude" placeholder="Enter Location latitude" required>
              </div>
              <div class="mb-3">
                <label for="longitude">Location longitude</label>
                <input type="text" class="form-control" id="longitude" name="longitude" placeholder="Enter Location longitude" required>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" id="saveLocation">Save</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Modal -->


    <!-- Update Location Modal -->
    <div class="modal fade" id="updateLocationModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Update Location</h3>
          </div>
          <div class="modal-body">
            <form id="updateLocationForm" action="php/updatelocation.php" method="POST">
              <div class="mb-3">
                <input type="text" id="location_id" name="location_id" hidden>
                <label for="location_name1">Location Name</label>
                <input type="text" class="form-control" id="location_name1" name="location_name1" placeholder="Enter Location Name" required>
              </div>
              <div class="mb-3">
                <label for="latitude1">Location latitude</label>
                <input type="text" class="form-control" id="latitude1" name="latitude1" placeholder="Enter Location latitude" required>
              </div>
              <div class="mb-3">
                <label for="longitude1">Location longitude</label>
                <input type="text" class="form-control" id="longitude1" name="longitude1" placeholder="Enter Location longitude" required>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" id="updateLocation">Update</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Modal -->

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Add Customer</h3>
          </div>
          <div class="modal-body">
            <form id="addCustomerForm" action="php/addcustomer.php" method="POST">
              <div class="mb-3">
                <label for="customer_code">Customer Code</label>
                <input type="text" class="form-control" id="customer_code" name="customer_code" placeholder="Enter Customer Code" required>
              </div>
              <div class="mb-3">
                <label for="customer_name">Customer Name</label>
                <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Enter Customer Name" required>
              </div>
              <div class="mb-3">
                <label for="customer_segment">Customer Segment</label>
                <input type="text" class="form-control" id="customer_segment" name="customer_segment" placeholder="e.g. DOLE, SUMITOMO, CTH">
                <small class="text-muted">Used by the client portal to auto-fill segment on booking and by segment-specific monitoring pages.</small>
              </div>
              <div class="mb-3">
                <label for="trade_type">Trade Type</label>
                <select class="form-select" id="trade_type" name="trade_type">
                  <option value="Export" selected>Export (booking starts Empty → Mark Loaded)</option>
                  <option value="Import">Import (booking starts Loaded → Empty Return)</option>
                </select>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" id="saveCustomer">Save</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Modal -->


    <!-- Update Customer Modal -->
    <div class="modal fade" id="updateCustomerModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Update Customer</h3>
          </div>
          <div class="modal-body">
            <form id="updateCustomerForm" action="php/updatecustomer.php" method="POST">
              <!-- hidden id -->
              <input type="hidden" id="edit_customer_id" name="customer_id">

              <div class="mb-3">
                <label for="edit_customer_code">Customer Code</label>
                <input type="text" class="form-control" id="edit_customer_code" name="customer_code" required>
              </div>
              <div class="mb-3">
                <label for="edit_customer_name">Customer Name</label>
                <input type="text" class="form-control" id="edit_customer_name" name="customer_name" required>
              </div>
              <div class="mb-3">
                <label for="edit_customer_segment">Customer Segment</label>
                <input type="text" class="form-control" id="edit_customer_segment" name="customer_segment" placeholder="e.g. DOLE, SUMITOMO, CTH">
              </div>
              <div class="mb-3">
                <label for="edit_trade_type">Trade Type</label>
                <select class="form-select" id="edit_trade_type" name="trade_type">
                  <option value="Export">Export (booking starts Empty → Mark Loaded)</option>
                  <option value="Import">Import (booking starts Loaded → Empty Return)</option>
                </select>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-primary" id="updateCustomer">Update</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Update Modal -->


    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebarmenu.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script src="assets/libs/apexcharts/dist/apexcharts.min.js"></script>
    <script src="assets/libs/simplebar/dist/simplebar.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script src="datatable/datatables.min.js"></script>
    <script src="js/segment.js"></script>
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  </body>

  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
}
