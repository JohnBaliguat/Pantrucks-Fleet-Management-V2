<?php
// Shared body for the Booking Segments editor.
// Included by `dispatcher/booking-segments.php` and `admin/booking-segments.php`.
// Required vars from caller: $role ('admin'|'dispatcher'), $baseRoute (string).

$relativeRoot = ($role === 'admin') ? __DIR__ . '/../config/config.php' : __DIR__ . '/../config/config.php';
include $relativeRoot;

// Master lookups for the inline editors.
$customerRows = [];
$res = $conn->query("SELECT customer_code, customer_name FROM customer ORDER BY customer_code ASC");
while ($r = ($res)->fetch()) { $customerRows[] = $r; }

$locationRows = [];
$res = $conn->query("SELECT location_name FROM location ORDER BY location_name ASC");
while ($r = ($res)->fetch()) { $locationRows[] = $r; }

$truckRows = [];
$res = $conn->query("SELECT unit_name FROM units WHERE unit_type = 'truck' AND maintenance_blocked = FALSE ORDER BY unit_name ASC");
while ($r = ($res)->fetch()) { $truckRows[] = $r; }

$gensetRows = [];
$res = $conn->query("SELECT unit_name FROM units WHERE unit_type = 'genset' AND maintenance_blocked = FALSE ORDER BY unit_name ASC");
while ($r = ($res)->fetch()) { $gensetRows[] = $r; }

$trailerRows = [];
$res = $conn->query("SELECT trailer_name FROM trailer WHERE maintenance_blocked = FALSE ORDER BY trailer_name ASC");
while ($r = ($res)->fetch()) { $trailerRows[] = $r; }

$driverRows = [];
$res = $conn->query("SELECT driver_id, CONCAT(driver_lname, ', ', driver_fname) AS driver_name FROM drivers ORDER BY driver_lname ASC");
while ($r = ($res)->fetch()) { $driverRows[] = $r; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Booking Segments</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">

    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
        <a class="d-flex justify-content-center" href="#"><img src="assets/images/logos/pantrucks.png" alt="" width="122"></a>
      </div>
      <div class="d-lg-flex align-items-center gap-2">
        <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3>
      </div>
    </div>

    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>

      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Booking Segments</h4>
                  <p class="card-subtitle">A booking can be split into multiple legs. Each leg can have its own client, locations, driver, truck, trailer, genset, status, and scheduled time. Cancelling a leg <em>after</em> assignment marks it as a foul trip.</p>

                  <div class="row mt-4 align-items-end">
                    <div class="col-md-6">
                      <label for="bookingSearch" class="form-label">Booking No</label>
                      <input type="text" class="form-control" id="bookingSearch" list="bookingNoList" placeholder="Type or pick a booking number">
                      <datalist id="bookingNoList"></datalist>
                    </div>
                    <div class="col-md-6">
                      <button class="btn btn-primary" id="loadSegmentsBtn"><i class="ti ti-search"></i> Load Segments</button>
                    </div>
                  </div>

                  <div id="bookingSummary" class="mt-4" style="display:none;">
                    <div class="alert alert-info mb-0">
                      <div id="bookingSummaryBody"></div>
                    </div>
                  </div>

                  <div class="table-responsive mt-4">
                    <table class="table table-bordered align-middle" id="segmentsTable">
                      <thead class="table-light">
                        <tr>
                          <th>#</th>
                          <th>Trip</th>
                          <th>Client</th>
                          <th>From → To</th>
                          <th>Driver / Truck / Trailer / Genset</th>
                          <th>Scheduled</th>
                          <th>Status</th>
                          <th class="text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody id="segmentsBody">
                        <tr><td colspan="8" class="text-center text-muted">Pick a booking number above to load its segments.</td></tr>
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

  <!-- Edit Segment Modal -->
  <div class="modal fade" id="editSegmentModal">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="editSegmentForm">
          <div class="modal-header">
            <h5 class="modal-title">Edit Segment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="seg_trip_id" name="trip_id">
            <input type="hidden" id="seg_d_id" name="d_id">
            <div class="row">
              <div class="col-md-6">
                <label class="form-label">Client (segment_costumer)</label>
                <input list="seg_customers" class="form-control" id="seg_costumer" name="segment_costumer">
                <datalist id="seg_customers">
                  <?php foreach ($customerRows as $c) { echo '<option value="' . htmlspecialchars($c['customer_code']) . '">' . htmlspecialchars($c['customer_name']) . '</option>'; } ?>
                </datalist>
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-control" id="seg_status" name="segment_status">
                  <option value="Pending">Pending</option>
                  <option value="Assigned">Assigned</option>
                  <option value="EnRoute">En Route</option>
                  <option value="Delivered">Delivered</option>
                  <option value="Cancelled">Cancelled</option>
                  <option value="Foul">Foul</option>
                </select>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Pickup (From)</label>
                <input list="seg_locations" class="form-control" id="seg_from" name="trip_from">
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Destination (To)</label>
                <input list="seg_locations" class="form-control" id="seg_to" name="trip_to">
                <datalist id="seg_locations">
                  <?php foreach ($locationRows as $l) { echo '<option value="' . htmlspecialchars($l['location_name']) . '">'; } ?>
                </datalist>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Driver</label>
                <select class="form-control" id="seg_driver" name="driver_id">
                  <option value="">— unchanged —</option>
                  <?php foreach ($driverRows as $d) { echo '<option value="' . (int)$d['driver_id'] . '">' . htmlspecialchars($d['driver_name']) . '</option>'; } ?>
                </select>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Truck</label>
                <input list="seg_trucks" class="form-control" id="seg_truck" name="d_truck">
                <datalist id="seg_trucks">
                  <?php foreach ($truckRows as $t) { echo '<option value="' . htmlspecialchars($t['unit_name']) . '">'; } ?>
                </datalist>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Trailer</label>
                <input list="seg_trailers" class="form-control" id="seg_trailer" name="d_trailer">
                <datalist id="seg_trailers">
                  <?php foreach ($trailerRows as $t) { echo '<option value="' . htmlspecialchars($t['trailer_name']) . '">'; } ?>
                </datalist>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Genset</label>
                <input list="seg_gensets" class="form-control" id="seg_genset" name="d_genset">
                <datalist id="seg_gensets">
                  <?php foreach ($gensetRows as $g) { echo '<option value="' . htmlspecialchars($g['unit_name']) . '">'; } ?>
                </datalist>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Scheduled at</label>
                <input type="datetime-local" class="form-control" id="seg_scheduled" name="scheduled_at">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="js/booking-segments.js"></script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
