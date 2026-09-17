<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "User") {


?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
   <style>
      /* =========== settings css ============== */
      .profile-image {
        margin-right: 20px;
        position: relative;
        z-index: 1;
      }

      .profile-image img {
        width: 100%;
      }

      .profile-image .update-image {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 30px;
        height: 30px;
        background: #efefef;
        border: 2px solid #fff;
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 50%;
        cursor: pointer;
        z-index: 99;
      }

      .profile-image .update-image:hover {
        opacity: 0.9;
      }

      .profile-image .update-image input {
        opacity: 0;
        position: absolute;
        width: 100%;
        height: 100%;
        cursor: pointer;
        z-index: 99;
      }

      .profile-image .update-image label {
        cursor: pointer;
        z-index: 99;
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
       include "navbar.php";
      $id = $_SESSION['user_id'];
      $query = "SELECT * FROM \"user\" WHERE user_id = '$id'";
      $result = $conn->query($query);
      $data = ($result)->fetch();
      $fname = $data['user_fname'];
      $firstLetter = substr($data['user_lname'], 0, 1);
     ?>
      
      <!--  Header End -->
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <!--  Row 1 -->
          <div class="row">
            <div class="col-lg-4">
              <div class="card overflow-hidden">
                <div class="card-body pb-0">
                  <div class="card">
                    <div class="profile-image">
                    <img src="php/assets/uploads/<?php if (!empty($data['user_image'])) {
                                              echo $data['user_image'];
                                            } else {
                                              echo "UserImage.png";
                                            } ?>" class="card-img-top" alt="...">
                      <div class="update-image">
                        <input type="file" />
                        <label for="input-file1" class="profileImage"><i class='ti ti-cloud-upload'></i></label>
                      </div>
                      </div>
                    <div class="card-body text-center">
                      <h5 class="card-title"><?php echo $data['user_fname'] . ' ' . $data['user_mname'] . '. ' . $data['user_lname']; ?></h5>
                      <p class="card-text"><?php echo $data['user_type']; ?></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-8">
               <div class="card">
                <div class="card-body">
                  <div class="d-md-flex align-items-center">
                    <div>
                      <h4 class="card-title">User Information</h4>
                    </div>
                  </div>
                    <div class="row">
                      <form class="form" id="editForm" action="../php/crud/update/update-profile.php" method="POST" enctype="multipart/form-data">
                        <div class="row">

                          <input type="file" accept="image/jpeg, image/png, image/jpg" id="input-file1" name="input-file1" hidden>
                          <input type="hidden" class="id" id="user_Id1" name="user_Id1" value="<?php echo $_SESSION['user_id']; ?>">
                          <div class="col-md-6 mb-3">
                            <div class="input-style-1">
                              <label for="name">Username</label>
                              <input type="text" class="form-control" name="username1" id="username1" value="<?php echo $data['user_name']; ?>">
                            </div>
                          </div>
                          <div class="col-md-6 mb-3">
                            <div class="input-style-1">
                              <label for="name">Email</label>
                              <input type="text" class="form-control" name="user_email1" id="user_email1" value="<?php echo $data['user_email']; ?>">
                            </div>
                          </div>
                          <div class="col-md-5 mb-3">
                            <div class="input-style-1">
                              <label for="fname">First Name</label>
                              <input type="text" class="form-control" name="user_fname1" id="user_fname1" placeholder="Enter First Name" value="<?php echo $data['user_fname']; ?>">
                            </div>
                          </div>
                          <div class="col-md-5 mb-3">
                            <div class="input-style-1">
                              <label for="lname">Last Name</label>
                              <input type="text" class="form-control" name="user_lname1" id="user_lname1" placeholder="Enter Last Name" value="<?php echo $data['user_lname']; ?>">
                            </div>
                          </div>
                          <div class="col-md-2 mb-3">
                            <div class="input-style-1">
                              <label for="lname">Middle Initial</label>
                              <input type="text" class="form-control" name="user_mname1" id="user_mname1" placeholder="Enter Middle Initial" value="<?php echo $data['user_mname'] ? $data['user_mname'] : ""; ?>">
                            </div>
                          </div>

                          <div class="col-md-6 mb-3">
                            <div class="input-style-1">
                              <label for="newPassword">New Password</label>
                              <input type="password" class="form-control" name="user_pass1" id="user_pass1" placeholder="Enter New Password">
                            </div>
                          </div>
                          <div class="col-md-6 mb-3">
                            <div class="input-style-1">
                              <label for="conPassword">Repeat New Password</label>
                              <input type="password" class="form-control" id="conPassword" placeholder="Repeat New Password">
                            </div>
                          </div>
                          <div class="col-md-12 mb-3">
                            <div class="modal-footer">
                              <button class="btn btn-primary" name="submit" id="updateUser">Update</button>
                            </div>
                          </div>
                        </div>
                      </form>
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
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="datatable/datatables.min.js"></script>
  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="js/profile.js"></script>
</body>

</html>
<?php
} else {
    header("Location: user-index.php?route=login");
    exit();
}
