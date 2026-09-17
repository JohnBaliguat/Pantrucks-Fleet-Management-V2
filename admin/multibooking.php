<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Multiple Booking</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>

<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">

    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-flex align-items-center">
      <h3 class="text-white mb-0 fs-5 ms-auto">Pantrucks Fleet Management System</h3>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="d-md-flex align-items-center">
                        <div>
                          <h4 class="card-title">Add Multiple Booking</h4>
                          <p class="card-subtitle">
                            Add several container lines under one booking header.
                          </p>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6 d-md-flex align-items-end justify-content-end">
                      <div class="d-md-flex align-items-center">
                        <div>
                          <a href="addbook" class="btn btn-primary">Back</a>
                        </div>
                      </div>
                    </div>
                  </div>

                  <?php
                    include 'php/config/config.php';

                    $haulingQuery = "SELECT hauling_segment FROM hauling ORDER BY hauling_id ASC";
                    $haulingResult = $conn->query($haulingQuery);

                    $locationQuery = "SELECT location_name FROM location ORDER BY location_id ASC";
                    $locationResult = $conn->query($locationQuery);

                    $locationQuery1 = "SELECT location_name FROM location ORDER BY location_id ASC";
                    $locationResult1 = $conn->query($locationQuery1);

                    $locationQuery2 = "SELECT location_name FROM location ORDER BY location_id ASC";
                    $locationResult2 = $conn->query($locationQuery2);
                  ?>

                  <form id="addForm1" action="php/crud/add/addbooking1.php" method="POST" enctype="multipart/form-data">
                    <div class="row mb-3">
                      <div class="col-md-6">
                        <label for="booking_no" class="form-label">Booking No</label>
                        <input type="text" class="form-control" id="booking_no" name="booking_no" readonly required>
                      </div>

                      <div class="col-md-6">
                        <?php
                          $customerQuery2 = "SELECT customer_id, customer_code FROM customer ORDER BY customer_id ASC";
                          $customerResult2 = $conn->query($customerQuery2);
                        ?>
                        <label for="costumer" class="form-label">Customer Name<span class="text-danger">*</span></label>
                        <select class="form-control" id="costumer" name="costumer" required>
                          <option value="" selected>-- Select Customer --</option>
                          <?php while ($row2 = ($customerResult2)->fetch()) { ?>
                            <option value="<?php echo htmlspecialchars($row2['customer_code']); ?>">
                              <?php echo htmlspecialchars($row2['customer_code']); ?>
                            </option>
                          <?php } ?>
                        </select>
                      </div>
                    </div>

                    <div class="row mb-3">
                      <div class="col-md-6">
                        <label for="booking_date" class="form-label">Date Requested<span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="booking_date" name="booking_date" required>
                      </div>
                      <div class="col-md-6 dateRequired">
                        <label for="booking_required" class="form-label">Date Required<span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="booking_required" name="booking_required" required>
                      </div>

                      <div class="col-md-3 bookingSN" style="display: none;">
                        <label for="cth_trade_type" class="form-label">Import / Export<span class="text-danger">*</span></label>
                        <select class="form-select mb-2" id="cth_trade_type" name="cth_trade_type">
                          <option value="Import" selected>Import</option>
                          <option value="Export">Export</option>
                        </select>
                        <label for="booking_sn" class="form-label" id="booking_sn_label">Shipment Number</label>
                        <input type="text" class="form-control" id="booking_sn" name="booking_sn">
                      </div>
                      <div class="col-md-3 bookingDO" style="display: none;">
                        <label for="booking_do" class="form-label">DO</label>
                        <input type="text" class="form-control" id="booking_do" name="booking_do">
                      </div>
                    </div>

                    <div class="row mb-3" id="cthdate" style="display: none;">
                      <div class="col-md-3">
                        <label for="haulingStart" class="form-label">Hauling Start<span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="haulingStart" name="haulingStart">
                      </div>
                      <div class="col-md-3">
                        <label for="lastDayStorage" class="form-label">Last Day of Storage<span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="lastDayStorage" name="lastDayStorage">
                      </div>
                      <div class="col-md-3">
                        <label for="lastDayDemurrage" class="form-label">Last Day of Demurrage<span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="lastDayDemurrage" name="lastDayDemurrage">
                      </div>
                      <div class="col-md-3">
                        <label for="lastDayDetention" class="form-label">Last Day of Detention<span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="lastDayDetention" name="lastDayDetention">
                      </div>
                    </div>

                    <h5 class="mt-4">Booking Details</h5>
                    <table class="table table-bordered" id="bookingTable">
                      <thead class="table-light">
                        <tr>
                          <th>Container</th>
                          <th>Seal</th>
                          <th>Activity</th>
                          <th>Status</th>
                          <th>Hauling Segment</th>
                          <th>From</th>
                          <th>To</th>
                          <th class="cth-only-col" style="display:none;">Return Location</th>
                          <th>Quantity</th>
                          <th><button type="button" class="btn btn-success btn-sm" id="addRowBtn">+</button></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td><input type="text" name="container[]" class="form-control" required></td>
                          <td><input type="text" name="container_seal[]" class="form-control" required></td>
                          <td>
                            <input type="text" class="form-control" list="bookingActivity" name="booking_activity[]" required>
                            <datalist id="bookingActivity">
                              <option value="WITHDRAW">
                              <option value="DELIVER">
                              <option value="N/A">
                            </datalist>
                          </td>
                          <td>
                            <input type="text" class="form-control" list="containerStat" name="container_status[]" required>
                            <datalist id="containerStat">
                              <option value="EMPTY">
                              <option value="LOADED">
                              <option value="N/A">
                            </datalist>
                          </td>
                          <td>
                            <input type="text" class="form-control" list="datalistOptions_hauling_segment" name="hauling_segment[]" required>
                            <datalist id="datalistOptions_hauling_segment">
                              <?php while ($row4 = ($haulingResult)->fetch()) { echo "<option value='" . htmlspecialchars($row4['hauling_segment']) . "'>"; } ?>
                            </datalist>
                          </td>
                          <td>
                            <input type="text" class="form-control" list="datalistOptions_destination_from" name="trip_from[]" required>
                            <datalist id="datalistOptions_destination_from">
                              <?php while ($row5 = ($locationResult)->fetch()) { echo "<option value='" . htmlspecialchars($row5['location_name']) . "'>"; } ?>
                            </datalist>
                          </td>
                          <td>
                            <input type="text" class="form-control" list="datalistOptions_destination_to" name="trip_to[]" required>
                            <datalist id="datalistOptions_destination_to">
                              <?php while ($row6 = ($locationResult1)->fetch()) { echo "<option value='" . htmlspecialchars($row6['location_name']) . "'>"; } ?>
                            </datalist>
                          </td>
                          <td class="cth-only-col" style="display:none;">
                            <input type="text" class="form-control" list="datalistOptions_destination_return" name="return_location[]">
                            <datalist id="datalistOptions_destination_return">
                              <?php while ($row7 = ($locationResult2)->fetch()) { echo "<option value='" . htmlspecialchars($row7['location_name']) . "'>"; } ?>
                            </datalist>
                          </td>
                          <td><input type="number" name="quantity[]" class="form-control" min="1" value="1" readonly></td>
                          <td><button type="button" class="btn btn-danger btn-sm removeRowBtn">x</button></td>
                        </tr>
                      </tbody>
                    </table>
                  </form>

                  <div class="row">
                    <div class="col-md-12">
                      <a href="addbook" class="btn btn-secondary">Close</a>
                      <button type="submit" class="btn btn-primary" id="saveBookingBtn3">Save</button>
                    </div>
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
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="datatable/datatables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
    $(document).ready(function () {
      function syncReturnLocationColumn() {
          const isCth = ($("#costumer").val() || '').trim().toUpperCase() === 'CTH';
          $('.cth-only-col').toggle(isCth);
          $('#bookingTable tbody input[name="return_location[]"]').prop('required', isCth);
          if (!isCth) {
              $('#bookingTable tbody input[name="return_location[]"]').val('');
          }
      }

      function syncCustomerMode() {
          const isCth = ($("#costumer").val() || '').trim().toUpperCase() === 'CTH';

          $('.dateRequired').toggle(!isCth);
          $('.bookingSN, .bookingDO').toggle(isCth);
          $('#cthdate').toggle(isCth);

          $('#booking_required').prop('required', !isCth);
          $('#booking_sn, #booking_do, #haulingStart, #lastDayStorage, #lastDayDemurrage, #lastDayDetention')
            .prop('required', isCth);

          if (!isCth) {
              $('#booking_sn, #booking_do, #haulingStart, #lastDayStorage, #lastDayDemurrage, #lastDayDetention').val('');
              $('#cth_trade_type').val('Import');
          }

          syncCthTradeType();
          syncReturnLocationColumn();
      }

      function syncCthTradeType() {
          const isExport = ($('#costumer').val() || '').trim().toUpperCase() === 'CTH' && $('#cth_trade_type').val() === 'Export';
          $('#booking_sn_label').text(isExport ? 'ATW' : 'Shipment Number');
          $('#booking_sn').attr('placeholder', isExport ? 'Enter ATW' : 'Enter Shipment Number');
      }

      function fetchBookingPreview() {
          const customer = ($("#costumer").val() || '').trim();
          const firstRow = $("#bookingTable tbody tr:first");
          const from = (firstRow.find('input[name="trip_from[]"]').val() || '').trim();
          const to = (firstRow.find('input[name="trip_to[]"]').val() || '').trim();

          fetch(
              'php/fetch/get_next_booking_no.php?customer=' + encodeURIComponent(customer) +
              '&from=' + encodeURIComponent(from) +
              '&to=' + encodeURIComponent(to)
          )
          .then((r) => r.text())
          .then((data) => {
              $("#booking_no").val(data);
          })
          .catch(() => {
              $("#booking_no").val('(auto-generated on save)');
          });
      }

      $("#addRowBtn").on("click", function () {
          const selectedCustomer = ($("#costumer").val() || '').trim().toUpperCase();
          const firstRow = $("#bookingTable tbody tr:first");

          const getVal = (name) => {
              if (selectedCustomer === "CTH" && firstRow.length) {
                  return firstRow.find(`input[name="${name}[]"]`).val() || "";
              }
              return "";
          };

          const newRow = `
              <tr>
                  <td><input type="text" name="container[]" class="form-control" required></td>
                  <td><input type="text" name="container_seal[]" class="form-control" required value="${getVal('container_seal')}"></td>
                  <td>
                      <input type="text" class="form-control" list="bookingActivity" name="booking_activity[]" required value="${getVal('booking_activity')}">
                  </td>
                  <td>
                      <input type="text" class="form-control" list="containerStat" name="container_status[]" required value="${getVal('container_status')}">
                  </td>
                  <td>
                      <input type="text" class="form-control" list="datalistOptions_hauling_segment" name="hauling_segment[]" required value="${getVal('hauling_segment')}">
                  </td>
                  <td>
                      <input type="text" class="form-control" list="datalistOptions_destination_from" name="trip_from[]" required value="${getVal('trip_from')}">
                  </td>
                  <td>
                      <input type="text" class="form-control" list="datalistOptions_destination_to" name="trip_to[]" required value="${getVal('trip_to')}">
                  </td>
                  <td class="cth-only-col" style="display:${selectedCustomer === 'CTH' ? '' : 'none'};">
                      <input type="text" class="form-control" list="datalistOptions_destination_return" name="return_location[]" value="${getVal('return_location')}"${selectedCustomer === 'CTH' ? ' required' : ''}>
                  </td>
                  <td><input type="number" name="quantity[]" class="form-control" min="1" value="1" readonly></td>
                  <td><button type="button" class="btn btn-danger btn-sm removeRowBtn">x</button></td>
              </tr>
          `;

          $("#bookingTable tbody").append(newRow);
          syncReturnLocationColumn();
      });

      $(document).on("click", ".removeRowBtn", function () {
          if ($("#bookingTable tbody tr").length <= 1) {
              Swal.fire({
                  text: 'At least one booking row is required',
                  icon: 'info'
              });
              return;
          }
          $(this).closest("tr").remove();
          fetchBookingPreview();
      });

      $("#saveBookingBtn3").click(function (e) {
          e.preventDefault();
          syncCustomerMode();

          let valid = true;
          $("#addForm1 [required]").each(function () {
              if ($(this).is('[readonly]')) {
                  return true;
              }
              if ($(this).val().trim() === "") {
                  valid = false;
                  $(this).focus();
                  return false;
              }
          });

          if (!valid) {
              Swal.fire({
                  text: 'Please fill in all required fields',
                  icon: 'info'
              });
              return;
          }

          if ($("#bookingTable tbody tr").length === 0) {
              Swal.fire({
                  text: 'At least one booking row is required',
                  icon: 'info'
              });
              return;
          }

          Swal.fire({
              title: 'Confirm Save Booking?',
              icon: 'question',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Confirm'
          }).then((result) => {
              if (result.isConfirmed) {
                  const formData = new FormData($('#addForm1')[0]);

                  $.ajax({
                      url: 'php/crud/add/addbooking1.php',
                      type: 'POST',
                      data: formData,
                      contentType: false,
                      cache: false,
                      processData: false,
                      success: function (response) {
                          let res;
                          if (response && typeof response === 'object') {
                              res = response;
                          } else {
                              try {
                                  res = JSON.parse(response);
                              } catch (err) {
                                  Swal.fire({
                                      html: '<pre style="text-align:left;white-space:pre-wrap;">' + $('<div>').text(response).html() + '</pre>',
                                      icon: 'error'
                                  });
                                  return;
                              }
                          }

                          Swal.fire({
                              html: res.message,
                              icon: res.status,
                              showConfirmButton: false,
                              timer: 2000
                          });

                          if (res.status === "success") {
                              $('#addForm1')[0].reset();
                              syncCustomerMode();
                              fetchBookingPreview();
                              setTimeout(() => {
                                  location.reload();
                              }, 2200);
                          }
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

      $('#costumer').on('change', function () {
          syncCustomerMode();
          fetchBookingPreview();
      });

      $('#cth_trade_type').on('change', syncCthTradeType);

      $(document).on('input change', '#bookingTable tbody input[name="trip_from[]"], #bookingTable tbody input[name="trip_to[]"]', function () {
          fetchBookingPreview();
      });

      syncCustomerMode();
      fetchBookingPreview();
    });
  </script>
</body>

</html>
<?php
} else {
    header("Location: login");
    exit();
}
?>
