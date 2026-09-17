<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Shop") {


?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shop Unit</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <style>
    .highlight-red {
      background-color: #f8d7da !important;
    }
    .manpower-btn {
      display: inline-block;
      padding: 8px 14px;
      border: 2px solid #0d6efd;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      user-select: none;
      transition: all 0.2s ease;
      background-color: #fff;
      color: #0d6efd;
    }
    .manpower-btn input {
      display: none;
    }
    .manpower-btn.active {
      background-color: #0d6efd;
      color: #fff;
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
                      <h4 class="card-title">List of Units in Shop</h4>
                    </div>
                  </div>
                  <div class="table-responsive mt-4" style="overflow: hidden;">
                    <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="table-data">
                    <thead>
                      <tr>
                        <th scope="col" class="px-0 text-muted text-center">Drivers</th>
                        <th scope="col" class="px-0 text-muted text-center">Truck Unit</th>
                        <th scope="col" class="px-0 text-muted text-center">Start Time</th>
                        <th scope="col" class="px-0 text-muted text-center">End Time</th>
                        <th scope="col" class="px-0 text-muted text-center">Age</th>
                        <th scope="col" class="px-0 text-muted text-center">Action</th>
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

    <div class="modal fade" id="dispatchModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">Assign Manpower</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <input type="hidden" id="rescue_id">

            <input type="text" id="searchManpower"
                  class="form-control mb-3"
                  placeholder="Search manpower (name or role)">

            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Select</th>
                  <th>Name</th>
                  <th>Role</th>
                </tr>
              </thead>
              <tbody id="manpowerTable"></tbody>
            </table>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" id="submitAssigned">Submit</button>
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
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="datatable/datatables.min.js"></script>
  
  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
    $(document).ready(function(){
      let table = $('#table-data').DataTable({
        "ajax": "table-fetch/fetch_shop.php",
        "columns": [
          { "data": "driver" },
          { "data": "unit" },
          { "data": "start" },
          { "data": "end" },
          { "data": "age" },
          { "data": "action" }
        ]
      });

      function updateLiveTimes() {
      $(".live-time").each(function(){
        let start = $(this).data("start");
        let now   = new Date();
        $(this).text(now.toLocaleString());
      });

      $(".live-age").each(function(){
        let start = new Date($(this).data("start"));
        let now   = new Date();
        let diff  = Math.floor((now - start) / 1000); // seconds

        let days = Math.floor(diff / 86400);
        let hours = Math.floor((diff % 86400) / 3600).toString().padStart(2,'0');
        let mins  = Math.floor((diff % 3600) / 60).toString().padStart(2,'0');
        let secs  = Math.floor(diff % 60).toString().padStart(2,'0');

        $(this).text(days + " Days " + hours + ":" + mins + ":" + secs);
      });
    }

    setInterval(updateLiveTimes, 1000);
    updateLiveTimes();

    $("#submitAssigned").on("click", function () {

      let rescue_id = $("#rescue_id").val();
      let manpower = [];

      $(".manpower-check:checked").each(function () {
          manpower.push($(this).val());
      });

      if (manpower.length === 0) {
          Swal.fire("Warning", "Please select at least one manpower.", "warning");
          return;
      }

      $.ajax({
          url: "php/assigned_shop.php",
          type: "POST",
          dataType: "json",
          data: {
              rescue_id: rescue_id,
              manpower: manpower
          },
          success: function (res) {
              if (res.status === "success") {
                  Swal.fire("Success", res.message, "success");
                  $("#dispatchModal").modal("hide");

                  $("#table-data").DataTable().ajax.reload();
              } else {
                  Swal.fire("Error", res.message, "error");
              }
          }
      });
  });
    });


    // Toggle active style
    $(document).on("change", ".manpower-check", function () {
        $(this).closest(".manpower-btn")
              .toggleClass("active", this.checked);
    });

    // Search manpower
    $("#searchManpower").on("keyup", function () {
        let value = $(this).val().toLowerCase();
        $("#manpowerTable tr").filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // CLICK ASSIGN BUTTON → OPEN MODAL
    $(document).on("click", ".assign-btn", function () {

        let shop_id = $(this).data("id");
        $("#rescue_id").val(shop_id); // reuse field

        $.ajax({
            url: "php/fetch_manpower.php",
            type: "GET",
            dataType: "json",
            success: function (data) {

                let rows = "";

                data.forEach(mp => {
                    rows += `
                      <tr>
                        <td class="text-center">
                          <label class="manpower-btn">
                            <input type="checkbox"
                                  class="manpower-check"
                                  value="${mp.smp_id}">
                            Assign
                          </label>
                        </td>
                        <td>${mp.smp_fname} ${mp.smp_mname} ${mp.smp_lname}</td>
                        <td>${mp.smp_role}</td>
                      </tr>
                    `;
                });

                $("#manpowerTable").html(rows);
                $("#dispatchModal").modal("show");
            }
        });
    });


    $(document).on("click", ".good-btn", function() {
        var s_id = $(this).data("id");

        Swal.fire({
            title: "Are you sure?",
            text: "This unit status will be updated to Good.",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#28a745",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, mark as Good",
            cancelButtonText: "Cancel"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "php/mark_pending_shopStat.php",
                    type: "POST",
                    data: { s_id: s_id },
                    success: function(response) {
                        Swal.fire({
                            icon: "success",
                            title: "Updated!",
                            text: response,
                            confirmButtonColor: "#28a745",
                            confirmButtonText: "OK"
                        }).then(() => {
                            location.reload(); // refresh table
                        });
                    },
                    error: function() {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Something went wrong while updating the unit.",
                            confirmButtonColor: "#d33"
                        });
                    }
                });
            }
        });
    });
    
  </script>
</body>
</html>
<?php
} else {
    header("Location: shop-index.php?route=login");
    exit();
}
