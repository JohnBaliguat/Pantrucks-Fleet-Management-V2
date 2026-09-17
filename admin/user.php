<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {


?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Page</title>
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
                      <h4 class="card-title">User Management List</h4>
                    </div>
                    <div class="ms-auto mt-3 mt-md-0">
                      <button class="btn btn-primary btn-sm" type="button" id="AddModal"><i class="ti ti-plus"></i> Add User</button>
                    </div>
                  </div>
                  <div class="table-responsive mt-4">
                    <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data">
                      <thead>
                        <tr>
                          <th scope="col" class="px-0 text-muted">Name</th>
                          <th scope="col" class="px-0 text-muted">Username</th>
                          <th scope="col" class="px-0 text-muted">Email</th>
                          <th scope="col" class="px-0 text-muted">Status</th>
                          <th scope="col" class="px-0 text-muted text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                      include 'php/config/config.php';
                      $count = 0;

                      $st = $conn->query("SELECT * FROM \"user\" ORDER BY user_id DESC");

                      while ($data = ($st)->fetch()) {
                        $count++;
                        // Default keeps unknown/legacy statuses (e.g. "Active")
                        // rendering safely instead of an undefined-variable warning.
                        $status_class = "secondary";
                        $status_text  = $data['user_accountstat'] ?: "—";
                        if ($data['user_accountstat'] == "Approve" || $data['user_accountstat'] == "Active") {
                          $status_class = "primary";
                          $status_text = $data['user_accountstat'];
                        } elseif ($data['user_accountstat'] == "Pending") {
                          $status_class = "warning";
                          $status_text = "Pending...";
                        } elseif ($data['user_accountstat'] == "Not Approve") {
                          $status_class = "danger";
                          $status_text = "Not Approve";
                        }
                      ?>
                        <tr>
                          <td class="px-0">
                            <div class="d-flex align-items-center">
                              <img src="php/assets/uploads/<?php echo $data['user_image']; ?>" width="40"
                                alt="flexy" />
                              <div class="ms-3">
                                <h6 class="mb-0 fw-bolder"><?php echo $data['user_fname'] . ' ' . $data['user_mname'] . '. ' . $data['user_lname']; ?></h6>
                                <span class="text-muted"><?php echo $data['user_type']; ?></span>
                              </div>
                            </div>
                          </td>
                          <td class="px-0"><?php echo $data['user_name']; ?></td>
                          <td class="px-0"><?php echo $data['user_email']; ?></td>
                          <td class="px-0">
                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                          </td>
                          <td class="px-0 text-dark fw-medium text-end">
                            <div class="d-grid gap-2 d-md-block">
                              <button class="btn btn-primary btn-sm" type="button" onclick="openUpdateUser('<?php echo $data['user_id']; ?>',
                                    '<?php echo htmlspecialchars($data['user_name'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['user_fname'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['user_lname'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['user_mname'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['user_assignlocation'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['user_email'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['user_pass'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['user_type'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['user_accountstat'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($data['customer_code'] ?? '', ENT_QUOTES); ?>');"><i class="ti ti-edit"></i></button>
                              <button class="btn btn-danger btn-sm" type="button" onclick="deleteUser('<?php echo $data['user_id']; ?>');"><i class="ti ti-trash"></i></button>
                            </div>
                          </td>
                        </tr>
                        <?php
                      }
                      ?>
                      </tbody>
                    </table>
                  </div>
                  <?php
                    $customerCodeRows = [];
                    $cres = $conn->query("SELECT customer_code, customer_name FROM customer ORDER BY customer_code ASC");
                    while ($cres && ($crow = ($cres)->fetch())) {
                      $customerCodeRows[] = $crow;
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
  <!-- Add Modal -->
    <div class="modal fade" id="addmodal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3 id="title">Add User</h3>
          </div>
          <div class="modal-body">
            <form id="addForm" action="php/crud/add/adduser.php" method="POST" enctype="multipart/form-data">
              <div class="row">
                <div class="input-style-1">
                  <div class="column">
                    <label for="user_fname">First Name</label>
                    <input type="text" class="form-control" name="user_fname" id="user_fname" placeholder="Enter Firstname" required>
                  </div>
                  <div class="column">
                    <label for="user_lname">Last Name</label>
                    <input type="text" class="form-control" name="user_lname" id="user_lname" placeholder="Enter Lastname" required>
                  </div>
                  <div class="column">
                    <label for="user_mname">Middle Name</label>
                    <input type="text" class="form-control" name="user_mname" id="user_mname" placeholder="Enter Middle Name" required>
                  </div>
                  <div class="column">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" name="username" id="username" placeholder="Enter Username" required>
                  </div>
                  <div class="column">
                    <label for="user_email">Email</label>
                    <input type="email" class="form-control" name="user_email" id="user_email" placeholder="Enter Email Address" required>
                  </div>
                  <div class="column">
                    <label for="user_pass">Password</label>
                    <input type="password" class="form-control" name="user_pass" id="user_pass" placeholder="Enter Password" required>
                  </div>
                  <div class="column">
                    <div class="select-style-1">
                      <label for="user_typ">Select User Type</label>
                      <div class="select-position">
                        <select class="form-select" name="user_type" id="user_type">
                          <option value="Admin">Admin</option>
                          <option value="User">User</option>
                          <option value="HR-Admin">HR Admin</option>
                          <option value="Dispatcher">Dispatcher</option>
                          <option value="Dispatch Admin">Dispatch Admin</option>
                          <option value="Booker">Booker</option>
                          <option value="Shop">Shop</option>
                          <option value="Rescue">Rescue</option>
                          <option value="Visual">Visual</option>
                          <option value="Gate-Guard">Gate Guard</option>
                          <option value="Maintenance">Maintenance</option>
                          <option value="Client">Client</option>
                          <option value="Gastender">Gas Tender</option>
                          <option value="Payroll">Payroll</option>
                          <option value="Executive">Executive</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="column">
                    <div class="select-style-1">
                      <label for="user_assignLocation">Select Assign Location</label>
                      <div class="select-position">
                        <select class="form-select" name="user_assignLocation" id="user_assignLocation">
                          <option value="PTSI">PTSI</option>
                          <option value="CONSOL">CONSOL</option>
                          <option value="ADMIN">ADMIN</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="column client-only" data-client-only data-user-type-target="user_type" style="display:none;">
                    <label for="customer_code">Customer Code (Client only)</label>
                    <input type="text" class="form-control" name="customer_code" id="customer_code" list="customer_code_options" placeholder="Search customer code or name" autocomplete="off">
                    <datalist id="customer_code_options">
                      <?php foreach ($customerCodeRows as $customerRow): ?>
                        <option value="<?php echo htmlspecialchars($customerRow['customer_code']); ?>" label="<?php echo htmlspecialchars($customerRow['customer_name']); ?>">
                      <?php endforeach; ?>
                    </datalist>
                    <small class="text-muted">Search by customer code or name. Saves the customer code only.</small>
                  </div>
                  <div class="column">
                    <label for="user_image">Upload Image</label>
                    <input type="file" class="form-control" name="user_image" id="user_image" placeholder="Upload Image" required>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" name="submit" id="addUser">Save</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Modal -->

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3 id="title">Edit User Infromation</h3>
          </div>
          <div class="modal-body">
            <form id="editForm" action="php/crud/update/updateuser.php" method="POST" enctype="multipart/form-data">
              <div class="row">
              <div class="column">
                    <div class="select-style-1">
                      <label for="user_stat1">User Status</label>
                      <div class="select-position">
                        <select class="form-select" name="user_stat1" id="user_stat1">
                          <option value="Approve">Approve</option>
                          <option value="Pending">Pending</option>
                          <option value="Not Approve">Not Approve</option>
                        </select>
                      </div>
                    </div>
                  </div>
                <div class="input-style-1">
                  <div class="column">
                    <label for="user_fname1">First Name</label>
                    <input type="hidden" class="form-control" name="user_Id1" id="user_Id1" required>
                    <input type="text" class="form-control" name="user_fname1" id="user_fname1" placeholder="Enter Firstname" required>
                  </div>
                  <div class="column">
                    <label for="user_lname1">Last Name</label>
                    <input type="text" class="form-control" name="user_lname1" id="user_lname1" placeholder="Enter Lastname" required>
                  </div>
                  <div class="column">
                    <label for="user_mname1">Middle Name</label>
                    <input type="text" class="form-control" name="user_mname1" id="user_mname1" placeholder="Enter Middle Name" required>
                  </div>
                  <div class="column">
                    <label for="username1">Username</label>
                    <input type="text" class="form-control" name="username1" id="username1" placeholder="Enter Username" required>
                  </div>
                  <div class="column">
                    <label for="user_email1">Email</label>
                    <input type="email" class="form-control" name="user_email1" id="user_email1" placeholder="Enter Email Address" required>
                  </div>
                  <div class="column">
                    <label for="user_pass1">Password</label>
                    <input type="password" class="form-control" name="user_pass1" id="user_pass1" placeholder="Enter Password" required>
                  </div>
                  <div class="column">
                    <div class="select-style-1">
                      <label for="user_type1">Select User Type</label>
                      <div class="select-position">
                        <select class="form-select" name="user_type1" id="user_type1">
                          <option value="Admin">Admin</option>
                          <option value="User">User</option>
                          <option value="Dispatcher">Dispatcher</option>
                          <option value="Dispatch Admin">Dispatch Admin</option>
                          <option value="Booker">Booker</option>
                          <option value="Shop">Shop</option>
                          <option value="Rescue">Rescue</option>
                          <option value="HR-Admin">HR Admin</option>
                          <option value="Visual">Visual</option>
                          <option value="Gate-Guard">Gate Guard</option>
                          <option value="Maintenance">Maintenance</option>
                          <option value="Client">Client</option>
                          <option value="Gastender">Gas Tender</option>
                          <option value="Payroll">Payroll</option>
                          <option value="Executive">Executive</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="column">
                    <div class="select-style-1">
                      <label for="user_assignLocation1">Select Assign Location</label>
                      <div class="select-position">
                        <select class="form-select" name="user_assignLocation1" id="user_assignLocation1">
                          <option value="PTSI">PTSI</option>
                          <option value="CONSOL">CONSOL</option>
                          <option value="ADMIN">ADMIN</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="column client-only" data-client-only data-user-type-target="user_type1" style="display:none;">
                    <label for="customer_code1">Customer Code (Client only)</label>
                    <input type="text" class="form-control" name="customer_code1" id="customer_code1" list="customer_code_options_edit" placeholder="Search customer code or name" autocomplete="off">
                    <datalist id="customer_code_options_edit">
                      <?php foreach ($customerCodeRows as $customerRow): ?>
                        <option value="<?php echo htmlspecialchars($customerRow['customer_code']); ?>" label="<?php echo htmlspecialchars($customerRow['customer_name']); ?>">
                      <?php endforeach; ?>
                    </datalist>
                    <small class="text-muted">Search by customer code or name. Saves the customer code only.</small>
                  </div>
                  <div class="column">
                    <label for="user_image1">Upload Image</label>
                    <input type="file" class="form-control" name="user_image1" id="user_image1" placeholder="Upload Image" required>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" name="submit" id="UpdateUser">Update</button>
            <button data-bs-dismiss="modal" id="close1" class="btn btn-secondary">Cancel</button>
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
  <script src="js/user.js?v=<?php echo @filemtime(__DIR__ . '/../js/user.js') ?: time(); ?>"></script>
  
  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
</body>
</html>
<?php
} else {
    header("Location: index.php?route=login");
    exit();
}
