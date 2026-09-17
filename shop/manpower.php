<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Shop") {


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
                            <th class="px-0 text-muted">Name</th>
                            <th class="px-0 text-muted">Role</th>
                            <th class="px-0 text-muted">Status</th>
                            <th class="px-0 text-muted text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            include 'php/config/config.php';

                            $st = $conn->query("
                                SELECT smp_id, smp_fname, smp_mname, smp_lname, smp_role, smp_status 
                                FROM shop_manpower 
                                ORDER BY smp_id DESC
                            ");

                            while ($data = ($st)->fetch()) {

                                // status badge
                                if ($data['smp_status'] == "Active") {
                                    $status_class = "primary";
                                } elseif ($data['smp_status'] == "Inactive") {
                                    $status_class = "danger";
                                } else {
                                    $status_class = "secondary";
                                }
                            ?>
                            <tr>
                                <!-- Name -->
                                <td class="px-0">
                                <div class="d-flex align-items-center">
                                    <div class="ms-1">
                                    <h6 class="mb-0 fw-bolder">
                                        <?php
                                        echo $data['smp_fname'] . ' ' .
                                            $data['smp_mname'] . '. ' .
                                            $data['smp_lname'];
                                        ?>
                                    </h6>
                                    </div>
                                </div>
                                </td>

                                <!-- Role -->
                                <td class="px-0">
                                <?php echo $data['smp_role']; ?>
                                </td>

                                <!-- Status -->
                                <td class="px-0">
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo $data['smp_status']; ?>
                                </span>
                                </td>

                                <!-- Action -->
                                <td class="px-0 text-end">
                                <button class="btn btn-primary btn-sm"
                                    onclick="openUpdateManpower(
                                    '<?php echo $data['smp_id']; ?>',
                                    '<?php echo $data['smp_fname']; ?>',
                                    '<?php echo $data['smp_mname']; ?>',
                                    '<?php echo $data['smp_lname']; ?>',
                                    '<?php echo $data['smp_role']; ?>',
                                    '<?php echo $data['smp_status']; ?>'
                                    )">
                                    <i class="ti ti-edit"></i>
                                </button>

                                <button class="btn btn-danger btn-sm"
                                     onclick="deleteManpower('<?php echo $data['smp_id']; ?>')">
                                    <i class="ti ti-trash"></i>
                                </button>
                                </td>
                            </tr>
                            <?php } ?>
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
  <!-- Add Shop Manpower Modal -->
    <div class="modal fade" id="addmodal">
    <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
            <h3>Add Shop Manpower</h3>
        </div>

        <div class="modal-body">
            <form id="addForm" method="POST">
            <div class="row">
                <div class="input-style-1">

                <div class="column">
                    <label>First Name</label>
                    <input type="text" class="form-control" id="smp_fname" name="smp_fname" required>
                </div>

                <div class="column">
                    <label>Middle Name</label>
                    <input type="text" class="form-control" id="smp_mname" name="smp_mname" required>
                </div>

                <div class="column">
                    <label>Last Name</label>
                    <input type="text" class="form-control" id="smp_lname" name="smp_lname" required>
                </div>

                <div class="column">
                    <div class="select-style-1">
                    <label>Role</label>
                    <div class="select-position">
                        <select class="form-select" id="smp_role" name="smp_role" required>
                        <option value="">-- Select Role --</option>
                        <option value="Rescuer">Rescuer</option>
                        <option value="Mechanic">Mechanic</option>
                        </select>
                    </div>
                    </div>
                </div>

                </div>
            </div>
            </form>
        </div>

        <div class="modal-footer">
            <button class="btn btn-success" id="addManpower">Save</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
        </div>

        </div>
    </div>
    </div>
    <!-- End Modal -->


    <!-- Edit Shop Manpower Modal -->
    <div class="modal fade" id="editModal">
    <div class="modal-dialog">
        <div class="modal-content">

        <div class="modal-header">
            <h3>Edit Shop Manpower</h3>
        </div>

        <div class="modal-body">
            <form id="editForm">
            <input type="hidden" id="smp_id1" name="smp_id1">

            <div class="column">
                <label>First Name</label>
                <input type="text" class="form-control" id="smp_fname1" name="smp_fname1" required>
            </div>

            <div class="column">
                <label>Middle Name</label>
                <input type="text" class="form-control" id="smp_mname1" name="smp_mname1" required>
            </div>

            <div class="column">
                <label>Last Name</label>
                <input type="text" class="form-control" id="smp_lname1" name="smp_lname1" required>
            </div>

            <div class="column">
                <label>Role</label>
                <select class="form-select" id="smp_role1" name="smp_role1">
                <option value="Rescuer">Rescuer</option>
                <option value="Mechanic">Mechanic</option>
                </select>
            </div>

            <div class="column">
                <label>Status</label>
                <select class="form-select" id="smp_status1" name="smp_status1">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                </select>
            </div>

            </form>
        </div>

        <div class="modal-footer">
            <button class="btn btn-success" id="updateManpower">Update</button>
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>

        </div>
    </div>
    </div>
    <!-- End Modal -->
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
  <script>
    $(document).ready(function () {
    $("#Mnav").attr({
        "class": "nav-link dropdown-toggle active"
    });
    $('#table-data').DataTable();

    $("#AddModal").click(function () {
        $("#addmodal").modal("show");
    });
    $("#close1").click(function () {
        $('#addForm')[0].reset();
        $("#addmodal").modal("hide");
    });
    $('#addManpower').click(function (e) {
        e.preventDefault();

        var smp_fname = $("#smp_fname").val();
        var smp_mname = $("#smp_mname").val();
        var smp_lname = $("#smp_lname").val();
        var smp_role  = $("#smp_role").val();

        if (smp_fname === "" || smp_mname === "" || smp_lname === "" || smp_role === "") {
            Swal.fire({
                text: 'Please fill in all required fields',
                icon: 'info'
            });
            return;
        }

        Swal.fire({
            title: 'Confirm Add Shop Manpower',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirm'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'php/addShopManpower.php',
                    type: 'POST',
                    data: {
                        smp_fname: smp_fname,
                        smp_mname: smp_mname,
                        smp_lname: smp_lname,
                        smp_role: smp_role
                    },
                    success: function (data) {
                        Swal.fire({
                            text: data,
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 1200
                        });
                        $('#addForm')[0].reset();
                        $("#addmodal").modal("hide");
                        setTimeout(() => {
                            location.reload();
                        }, 1300);
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

    $('#updateManpower').click(function () {

        Swal.fire({
            title: 'Confirm Update Shop Manpower',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirm'
        }).then((result) => {

            if (result.isConfirmed) {
                $.ajax({
                    url: 'php/updateShopManpower.php',
                    type: 'POST',
                    data: $('#editForm').serialize(),
                    success: function (data) {
                        Swal.fire({
                            text: data,
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 1200
                        });
                        $('#editModal').modal('hide');
                        setTimeout(() => location.reload(), 1300);
                    },
                    error: function (xhr) {
                        Swal.fire({
                            text: xhr.responseText,
                            icon: 'error'
                        });
                    }
                });
            }
        });
    });

    
});

function openUpdateManpower(smp_id, smp_fname, smp_mname, smp_lname, smp_role, smp_status) {
    $('#smp_id1').val(smp_id);
    $('#smp_fname1').val(smp_fname);
    $('#smp_mname1').val(smp_mname);
    $('#smp_lname1').val(smp_lname);
    $('#smp_role1').val(smp_role);
    $('#smp_status1').val(smp_status);

    $('#editModal').modal('show');
}

function deleteManpower(smp_id) {

    Swal.fire({
        title: 'Confirm Remove Shop Manpower',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Confirm'
    }).then((result) => {

        if (result.isConfirmed) {
            $.ajax({
                url: "php/deleteShopManpower.php",
                type: "POST",
                data: { smp_id: smp_id },
                dataType: "json",
                success: function (response) {

                    if (response.valid === false) {
                        Swal.fire({
                            text: response.msg,
                            icon: 'warning'
                        });
                    } else {
                        Swal.fire({
                            text: response.msg,
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 1200
                        });
                        setTimeout(() => location.reload(), 1300);
                    }
                },
                error: function () {
                    Swal.fire({
                        text: 'Delete failed!',
                        icon: 'error'
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
    header("Location: shop-index.php?route=login");
    exit();
}
