<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trailer</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="datatable/datatables.min.css">
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
      
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
        <?php include 'navbar.php'; ?>
        <!--  Header End -->
        <div class="body-wrapper-inner">
          <div class="container-fluid">
            <!--  Row 1 -->
            <div class="row">
              <?php
              include "php/config/config.php";

              // Count trailers based on status
              $query = "SELECT trailer_status, COUNT(*) as count FROM trailer GROUP BY trailer_status";
              $result = $conn->query($query);

              // Initialize counts
              $counts = [
                'good' => 0,
                'dispatch' => 0,
                'under maintenance' => 0,
                'disposed unit' => 0
              ];

              while ($row = ($result)->fetch()) {
                $status = strtolower($row['trailer_status']);
                $counts[$status] = $row['count'];
              }
              ?>


              <div class="col-lg-3">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="d-flex align-items-start">
                      <div>
                        <h4 class="card-title">Trailer Stats</h4>
                        <p class="card-subtitle">Number of Trailers</p>
                      </div>
                    </div>

                    <!-- In Base -->
                    <div class="mt-4 pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-truck fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Trailers In Base</h5>
                      </div>
                      <div class="ms-auto">
                        <span class="badge bg-secondary-subtle text-muted">
                          <?= $counts['good'] ?>
                        </span>
                      </div>
                    </div>

                    <!-- In Use -->
                    <div class="py-3 d-flex align-items-center">
                      <span class="btn btn-success rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-truck-delivery fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Trailers In Use</h5>
                      </div>
                      <div class="ms-auto">
                        <span class="badge bg-secondary-subtle text-muted">
                          <?= $counts['dispatch'] ?>
                        </span>
                      </div>
                    </div>

                    <!-- In Maintenance -->
                    <div class="py-3 d-flex align-items-center">
                      <span class="btn btn-warning rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-truck-delivery fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Trailers In Maintenance</h5>
                      </div>
                      <div class="ms-auto">
                        <span class="badge bg-secondary-subtle text-muted">
                          <?= $counts['under maintenance'] ?>
                        </span>
                      </div>
                    </div>

                    <!-- Disposed -->
                    <div class="pt-3 mb-7 d-flex align-items-center">
                      <span class="btn btn-danger rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-truck-off fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Disposed Trailers</h5>
                      </div>
                      <div class="ms-auto">
                        <span class="badge bg-secondary-subtle text-muted">
                          <?= $counts['disposed unit'] ?>
                        </span>
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
                        <h4 class="card-title">Trailer Management List</h4>
                      </div>
                      <div class="ms-auto mt-3 mt-md-0">
                        <div class="d-grid gap-2 d-md-block">
                          <button class="btn btn-info btn-sm" type="button" id="UpdateInfoModal"><i class="ti ti-edit"></i> Update Information</button>
                          <button class="btn btn-primary btn-sm" type="button" id="AddModal"><i class="ti ti-plus"></i> Add Trailer</button>
                        </div>
                        
                      </div>
                    </div>
                    <div class="table-responsive mt-4">
                      <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data">
                        <thead>
                          <tr>
                            <th scope="col" class="px-0">
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="select-all">
                              </div>
                            </th>
                            <th scope="col" class="px-0 text-muted">Date Updated</th>
                            <th scope="col" class="px-0 text-muted">Trailer</th>
                            <th scope="col" class="px-0 text-muted">Plate No</th>
                            <th scope="col" class="px-0 text-muted">Assign To</th>
                            <th scope="col" class="px-0 text-muted">Location</th>
                            <th scope="col" class="px-0 text-muted">Recorded By</th>
                            <th scope="col" class="px-0 text-muted">Approved By</th>
                            <th scope="col" class="px-0 text-muted">Status</th>
                            <th scope="col" class="px-0 text-muted text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          include "php/config/config.php";
                          $query = "SELECT trailer_id, trailer_name, trailer_plateno, trailer_assignto, trailer_location, trailer_status, t_recordedby, t_approvedby, t_date
                                    FROM trailer";
                          $result = $conn->query($query);
                          while ($row = ($result)->fetch()) {
                          ?>
                            <tr>
                              <td class="px-0">
                                 <div class="form-check">
                                    <input class="form-check-input row-checkbox" type="checkbox" value="<?php echo $row['trailer_id']; ?>">
                                  </div>
                              </td>
                              <td class="px-0">
                                <div class="d-flex align-items-center">
                                  <img src="assets/images/profile/trailers.png" class="rounded-circle" width="40" alt="trailer" />
                                  <div class="ms-3">
                                    <h6 class="mb-0 fw-bolder"><?php echo $row['trailer_name']; ?></h6>
                                  </div>
                                </div>
                              </td>
                              <td class="px-0"><?php 
                                      if (!empty($row['t_date']) && $row['t_date'] !== '0000-00-00 00:00:00') {
                                          echo date("F d, Y H:i", strtotime($row['t_date']));
                                      } else {
                                          echo "-";
                                      }
                                  ?></td>
                              <td class="px-0"><?php echo $row['trailer_plateno']; ?></td>
                              <td class="px-0"><?php echo $row['trailer_assignto']; ?></td>
                              <td class="px-0"><?php echo $row['trailer_location']; ?></td>
                              <td class="px-0"><?php echo $row['t_recordedby']; ?></td>
                              <td class="px-0"><?php echo $row['t_approvedby']; ?></td>
                              <td class="px-0">
                                <?php
                                $status = trim($row['trailer_status']);
                                if ($status === "" || $status === "Good") {
                                  echo '<span class="badge bg-info">Good</span>';
                                } elseif ($status === "Under Maintenance") {
                                  echo '<span class="badge bg-warning text-dark">Under Maintenance</span>';
                                } elseif ($status === "Disposed") {
                                  echo '<span class="badge bg-danger">Disposed</span>';
                                } else {
                                  echo '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
                                }
                                ?>
                              </td>
                              <td class="px-0 text-dark fw-medium text-end">
                                <div class="d-grid gap-2 d-md-block">
                                  <button class="btn btn-primary btn-sm" type="button"
                                    onclick="openEditModal('<?php echo $row['trailer_id']; ?>', 
                                                            '<?php echo $row['trailer_name']; ?>', 
                                                            '<?php echo $row['trailer_plateno']; ?>',
                                                            '<?php echo $row['trailer_assignto']; ?>',
                                                            '<?php echo $row['trailer_location']; ?>');">
                                    <i class="ti ti-edit"></i>
                                  </button>
                                  <button class="btn btn-danger btn-sm" type="button" onclick="deleteTrailer('<?php echo $row['trailer_id']; ?>');">
                                    <i class="ti ti-trash"></i>
                                  </button>
                                </div>
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

    <!-- Add Trailer Modal -->
    <div class="modal fade" id="addmodal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3 id="title">Add Trailer</h3>
          </div>
          <div class="modal-body">
            <form id="addForm" action="php/addtrailer.php" method="POST" enctype="multipart/form-data">
              <div class="row">
                <div class="input-style-1">
                  <div class="column">
                    <label for="trailer_name">Trailer Name</label>
                    <input type="text" class="form-control" name="trailer_name" id="trailer_name" placeholder="Enter Trailer Name" required>
                  </div>
                  <div class="column">
                    <label for="trailer_plateNo">Trailer Plate Number</label>
                    <input type="text" class="form-control" name="trailer_plateNo" id="trailer_plateNo" placeholder="Enter Plate Number" required>
                  </div>
                  <div class="column">
                    <label for="trailer_location">Location</label>
                    <input type="text" class="form-control" name="trailer_location" id="trailer_location" placeholder="Enter Location" required>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" name="submit" id="addTrailer">Save</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Add Modal -->

    <!-- Update Trailer Modal -->
    <div class="modal fade" id="editModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h3 id="title">Update Trailer</h3>
          </div>
          <div class="modal-body">
            <form id="editForm" action="php/updatetrailer.php" method="POST" enctype="multipart/form-data">
              <input type="hidden" name="trailer_id1" id="trailer_id1">
              <div class="row">
                <div class="input-style-1">
                  <div class="column">
                    <label for="trailer_name_update">Trailer Name</label>
                    <input type="text" class="form-control" name="trailer_name_update" id="trailer_name_update" required>
                  </div>
                  <div class="column">
                    <label for="trailer_plateNo_update">Trailer Plate Number</label>
                    <input type="text" class="form-control" name="trailer_plateNo_update" id="trailer_plateNo_update" placeholder="Enter Plate Number" required>
                  </div>
                  <div class="column">
                    <label for="trailer_location">Location</label>
                    <input type="text" class="form-control" name="trailer_location_update" id="trailer_location_update" placeholder="Enter Location" required>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success btn-sm" name="submit" id="UpdateTrailer">Update</button>
            <button class="btn btn-info btn-sm" name="submit" id="markGood">Good</button>
            <button class="btn btn-warning btn-sm" type="button" id="markMaintenance">Under Maintenance</button>
            <button class="btn btn-danger btn-sm" name="submit" id="markDisposed">Dispose</button>
            <button data-bs-dismiss="modal" class="btn btn-secondary btn-sm">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End of Update Modal -->

    <!-- Update Information Modal -->
    <div class="modal fade" id="updateInfoModal" tabindex="-1" aria-labelledby="updateInfoModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="updateInfoForm">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="updateInfoModalLabel">Update Trailer Information</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="trailer_ids" id="trailer_ids">

              <div class="mb-3">
                <label class="form-label">Location<span style="color: red;">*</span></label>
                <input type="text" class="form-control" name="location" id="location" placeholder="Enter Location" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Recorded By<span style="color: red;">*</span></label>
                <input type="text" class="form-control" name="recordedBy" id="recordedBy" placeholder="Enter Full Name of the Recorded" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Approved By<span style="color: red;">*</span></label>
                <input type="text" class="form-control" name="approvedBy" id="approvedBy" placeholder="Enter Full Name of the Approver" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Container Name(Optional if there is container)</label>
                <input type="text" class="form-control" name="container" placeholder="Enter Container">
              </div>
              <div class="mb-3">
                <label class="form-label">Remarks</label>
                <input type="text" class="form-control" name="remarks" placeholder="Enter Remarks">
              </div>
              <div class="mb-3">
                <label class="form-label">Date & Time<span style="color: red;">*</span></label>
                <input type="datetime-local" class="form-control" name="dateTime" id="dateTime" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success">Save</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Remarks Modal -->
    <div class="modal fade" id="remarksModal" tabindex="-1" aria-labelledby="remarksModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="remarksModalLabel">Add Maintenance Remarks</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <textarea id="maintenanceRemarks" name="maintenanceRemarks" class="form-control" rows="4" placeholder="Enter remarks here..."></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" id="confirmRemarksBtn" class="btn btn-primary">Continue</button>
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
    <script src="js/trailer.js?v=<?php echo @filemtime(__DIR__ . '/../js/trailer.js') ?: time(); ?>"></script>
    <script>
      $(document).ready(function () {
          var table = $('#table-data').DataTable();

          // Handle select-all
          $('#select-all').on('click', function(){
            var checked = this.checked;
            $('#table-data tbody input.row-checkbox').prop('checked', checked);
          });

          $('#table-data tbody').on('change', '.row-checkbox', function(){
            if(!this.checked){
              $('#select-all').prop('checked', false);
            } else {
              if ($('#table-data tbody .row-checkbox:checked').length === $('#table-data tbody .row-checkbox').length) {
                $('#select-all').prop('checked', true);
              }
            }
          });

          // Open modal when button clicked
          $('#UpdateInfoModal').on('click', function(){
              var selected = [];
                table.$('.row-checkbox:checked').each(function(){
                    selected.push($(this).val());
                });

              if(selected.length === 0){
                  Swal.fire({
                      icon: 'warning',
                      title: 'No Trailers Selected',
                      text: 'Please select at least one trailer to update.',
                      confirmButtonColor: '#3085d6'
                  });
                  return;
              }

              $('#trailer_ids').val(selected.join(",")); 
              $('#updateInfoModal').modal('show');
          });

          // Handle form submit
          $('#updateInfoForm').on('submit', function(e){
              e.preventDefault();

              var location = $("#location").val();
              var recordedBy = $("#recordedBy").val();
              var approvedBy = $("#approvedBy").val();
              var dateTime = $("#dateTime").val();
              if (location == "" || recordedBy == "" || approvedBy == "" || dateTime == "") {
                  Swal.fire({
                      text: 'Please fill in all required fields',
                      icon: 'info'
                  });
                  return;
              }
              $.ajax({
                  url: "php/crud/update/update_trailer_info.php",
                  type: "POST",
                  data: $(this).serialize(),
                  success: function(response){
                      Swal.fire({
                          icon: 'success',
                          title: 'Updated!',
                          text: response,
                          confirmButtonColor: '#28a745'
                      }).then(() => {
                          $('#updateInfoModal').modal('hide');
                          location.reload();
                      });
                  },
                  error: function(){
                      Swal.fire({
                          icon: 'error',
                          title: 'Error!',
                          text: 'Something went wrong while updating trailers.',
                          confirmButtonColor: '#d33'
                      });
                  }
              });
          });

         $('#markMaintenance').click(function(e) {
            e.preventDefault();

            // Hide edit modal first before showing remarks
            $('#editModal').modal('hide');

            // Use a small delay to avoid modal overlap issues
            setTimeout(() => {
              $('#remarksModal').modal('show');
            }, 300);
          });

          // When the user clicks "Confirm" in the remarks modal
          $('#confirmRemarksBtn').click(function() {
            let remarks = $('#maintenanceRemarks').val().trim();

            if (remarks === '') {
              Swal.fire({
                text: 'Please enter remarks before proceeding.',
                icon: 'warning'
              });
              return;
            }

            let formData = new FormData($('#editForm')[0]);
            formData.append('remarks', remarks); // Add remarks to form data

            // Ask for final confirmation only here
            Swal.fire({
              title: 'Are you sure you want to mark this trailer as Under Maintenance?',
              icon: 'question',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Confirm'
            }).then((result) => {
              if (result.isConfirmed) {
                $.ajax({
                  url: 'php/operations/undermaintenance.php',
                  type: 'POST',
                  data: formData,
                  contentType: false,
                  cache: false,
                  processData: false,
                  success: function(data) {
                    Swal.fire({
                      text: data,
                      icon: 'success',
                      showConfirmButton: false,
                      timer: 1200
                    });
                    $('#remarksModal').modal('hide');
                    setTimeout(() => {
                      location.reload();
                    }, 1300);
                  },
                  error: function(xhr, status, error) {
                    Swal.fire({
                      text: 'Error: ' + error,
                      icon: 'error'
                    });
                  }
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
  header("Location: index.php?route=login");
  exit();
}
