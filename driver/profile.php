<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Driver") {
  include "php/config/config.php";
  $id = $_SESSION['user_id'];
  $query = "SELECT * FROM drivers WHERE driver_id = '$id'";
  $result = $conn->query($query);
  $data = ($result)->fetch();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>Profile - Driver</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/driver-modern.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper">
    
    <div class="app-topstrip">
      <div class="d-flex align-items-center justify-content-between w-100">
        <img src="assets/images/logos/pantrucks.png" alt="Logo">
        <h3>My Profile</h3>
      </div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>
      <div class="container-fluid">
        
        <!-- Profile Header -->
        <div class="profile-header">
          <div class="position-relative d-inline-block">
            <img src="php/assets/uploads/<?php echo !empty($data['driver_image']) ? $data['driver_image'] : 'user-profile.jpg'; ?>" 
                 class="profile-avatar" alt="Profile" id="profilePreview">
            <label for="input-file1" class="position-absolute bottom-0 end-0 bg-white rounded-circle p-2 shadow-sm" 
                   style="cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
              <i class="ti ti-camera text-dark"></i>
            </label>
          </div>
          <h3><?php echo $data['driver_fname'] . ' ' . $data['driver_lname']; ?></h3>
          <p>Driver ID: <?php echo $id; ?></p>
        </div>

        <!-- Edit Form -->
        <form id="editFormDriver" class="form-section">
          <div class="form-section-title">Personal Information</div>
          
          <input type="file" accept="image/*" id="input-file1" name="input-file1" hidden>
          <input type="hidden" name="user_Id1" value="<?php echo $id; ?>">
          
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control-modern" name="username1" value="<?php echo $data['driver_uname']; ?>">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control-modern" name="user_fname1" value="<?php echo $data['driver_fname']; ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control-modern" name="user_lname1" value="<?php echo $data['driver_lname']; ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">M.I.</label>
              <input type="text" class="form-control-modern" name="user_mname1" value="<?php echo $data['driver_mname'] ?? ''; ?>" maxlength="1">
            </div>
          </div>

          <div class="form-section-title mt-4">Security</div>
          
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control-modern" name="user_pass1" id="user_pass1" placeholder="Leave blank to keep current">
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" class="form-control-modern" id="conPassword" placeholder="Repeat new password">
          </div>

          <button type="submit" class="btn-modern btn-primary-modern w-100 mt-3" id="updateDriver">
            <i class="ti ti-device-floppy"></i> Save Changes
          </button>
        </form>

      </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="mobile-nav">
      <a href="driver-dashboard" class="nav-item">
        <i class="ti ti-smart-home"></i>
        <span>Home</span>
      </a>
      <a href="driver-unit" class="nav-item">
        <i class="ti ti-truck"></i>
        <span>Unit</span>
      </a>
      <a href="driver-tripReport" class="nav-item">
        <i class="ti ti-clipboard-list"></i>
        <span>Reports</span>
      </a>
      <button onclick="sosAlert()" class="nav-item sos-btn">
        <i class="ti ti-alert-triangle"></i>
        <span>SOS</span>
      </button>
    </div>

  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="driver/driver-upload.js?v=<?php echo @filemtime(__DIR__ . '/driver-upload.js') ?: time(); ?>"></script>

  <script>
    // Image Preview
    $('#input-file1').on('change', function() {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          $('#profilePreview').attr('src', e.target.result);
        };
        reader.readAsDataURL(file);
      }
    });

    // Form Submit
    $('#editFormDriver').submit(function(e) {
      e.preventDefault();

      const form = this;
      const $btn = $('#updateDriver');
      const pass1 = $('#user_pass1').val();
      const pass2 = $('#conPassword').val();

      if (pass1 && pass1 !== pass2) {
        Swal.fire({ icon: 'error', title: 'Oops', text: 'Passwords do not match!' });
        return;
      }

      Swal.fire({
        title: 'Save Changes?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Update'
      }).then((result) => {
        if (!result.isConfirmed) return;

        DriverUpload.setBtnBusy($btn, true, 'Saving...');

        const rawAvatar = document.getElementById('input-file1').files[0];
        const prep = rawAvatar
          ? DriverUpload.compressPhoto(rawAvatar).then(function (compressed) {
              const formData = new FormData(form);
              // Replace the original file with the compressed copy.
              formData.set('input-file1', compressed, compressed.name);
              return formData;
            })
          : Promise.resolve(new FormData(form));

        prep.then(function (formData) {
          $.ajax({
            url: 'php/crud/update/update-driverprofile.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 120000
          }).done(function () {
            Swal.fire({
              icon: 'success',
              title: 'Updated!',
              text: 'Profile saved successfully',
              timer: 1500,
              showConfirmButton: false
            });
          }).fail(function (xhr, textStatus) {
            Swal.fire({ icon: 'error', title: 'Error', text: DriverUpload.errorMessage(xhr, textStatus) });
          }).always(function () {
            DriverUpload.setBtnBusy($btn, false);
          });
        });
      });
    });

    function sosAlert() {
      Swal.fire({
        title: '🚨 Emergency SOS',
        text: 'Send distress signal?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Send SOS'
      }).then((result) => {
        if (result.isConfirmed) {
          $.post("php/operations/send_sos.php", { driver_id: "<?php echo $id; ?>" }, function() {
            Swal.fire('Sent!', 'Help is on the way.', 'success');
          });
        }
      });
    }
  </script>
</body>
</html>
<?php
} else {
  header("Location: driver-index.php?route=login");
  exit();
}
?>
