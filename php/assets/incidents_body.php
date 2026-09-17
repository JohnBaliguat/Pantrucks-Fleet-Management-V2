<?php
// Shared body for the Incidents management page.
// Required vars from caller: $role ('admin'|'dispatcher'), $baseRoute (string).
include __DIR__ . '/../config/config.php';

// Driver picker for the reassign modal — only Good-status drivers,
// not currently dispatched.
$availableDrivers = [];
$res = $conn->query("SELECT driver_id, CONCAT(driver_lname, ', ', driver_fname, ' ', driver_mname) AS dn,
                                   driver_assignsegment AS seg
                            FROM drivers
                            WHERE driver_status = 'Good'
                            ORDER BY driver_lname ASC LIMIT 500");
while ($r = ($res)->fetch()) { $availableDrivers[] = $r; }

$truckRows = [];
$res = $conn->query("SELECT unit_name FROM units WHERE unit_type='truck' AND unit_status='Good' AND maintenance_blocked = FALSE ORDER BY unit_name ASC");
while ($r = ($res)->fetch()) { $truckRows[] = $r; }

$gensetRows = [];
$res = $conn->query("SELECT unit_name FROM units WHERE unit_type='genset' AND unit_status='Good' AND maintenance_blocked = FALSE ORDER BY unit_name ASC");
while ($r = ($res)->fetch()) { $gensetRows[] = $r; }

$trailerRows = [];
$res = $conn->query("SELECT trailer_name FROM trailer WHERE trailer_status='Good' AND maintenance_blocked = FALSE ORDER BY trailer_name ASC");
while ($r = ($res)->fetch()) { $trailerRows[] = $r; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Incidents</title>
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
              <div class="card"><div class="card-body">
                <div class="d-md-flex align-items-center mb-3">
                  <div>
                    <h4 class="card-title">Incidents</h4>
                    <p class="card-subtitle">Delays, breakdowns, exceptions, gate violations. Acknowledge to take ownership, Reassign to spin up a new dispatch on the same booking, Resolve when closed.</p>
                  </div>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="filterStatus">
                      <option value="open" selected>Open</option>
                      <option value="acknowledged">Acknowledged</option>
                      <option value="resolved">Resolved</option>
                      <option value="all">All</option>
                    </select>
                    <select class="form-select form-select-sm" id="filterSeverity">
                      <option value="all" selected>All severities</option>
                      <option value="high">High</option>
                      <option value="med">Medium</option>
                      <option value="low">Low</option>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary" id="refreshIncidents"><i class="ti ti-refresh"></i></button>
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-bordered align-middle">
                    <thead class="table-light"><tr>
                      <th>#</th><th>Reported</th><th>Type</th><th>Severity</th>
                      <th>Truck / Booking</th><th>Driver</th><th>Description</th>
                      <th>Status</th><th class="text-end">Action</th>
                    </tr></thead>
                    <tbody id="incBody"><tr><td colspan="9" class="text-center text-muted">Loading…</td></tr></tbody>
                  </table>
                </div>
              </div></div>
            </div>
          </div>
          <div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Reassign modal -->
  <div class="modal fade" id="reassignModal">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="reassignForm">
          <div class="modal-header">
            <h5 class="modal-title">Re-assign Dispatch</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="inc_id" id="ra_inc_id">
            <input type="hidden" name="d_id"   id="ra_d_id">
            <div class="alert alert-warning mb-3" id="ra_summary"></div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">New Driver <span class="text-danger">*</span></label>
                <select class="form-control" name="new_driver_id" id="ra_driver" required>
                  <option value="">— pick available driver —</option>
                  <?php foreach ($availableDrivers as $d) {
                    echo '<option value="' . (int)$d['driver_id'] . '">' . htmlspecialchars($d['dn']) . ' &mdash; ' . htmlspecialchars($d['seg'] ?: '(no segment)') . '</option>';
                  } ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">New Truck <span class="text-danger">*</span></label>
                <input list="ra_truck_list" class="form-control" name="new_truck" id="ra_truck" required>
                <datalist id="ra_truck_list">
                  <?php foreach ($truckRows as $t) { echo '<option value="' . htmlspecialchars($t['unit_name']) . '">'; } ?>
                </datalist>
              </div>
              <div class="col-md-6">
                <label class="form-label">Trailer (optional override)</label>
                <input list="ra_trailer_list" class="form-control" name="new_trailer" id="ra_trailer" placeholder="leave blank to keep">
                <datalist id="ra_trailer_list">
                  <?php foreach ($trailerRows as $t) { echo '<option value="' . htmlspecialchars($t['trailer_name']) . '">'; } ?>
                </datalist>
              </div>
              <div class="col-md-6">
                <label class="form-label">Genset (optional override)</label>
                <input list="ra_genset_list" class="form-control" name="new_genset" id="ra_genset" placeholder="leave blank to keep">
                <datalist id="ra_genset_list">
                  <?php foreach ($gensetRows as $g) { echo '<option value="' . htmlspecialchars($g['unit_name']) . '">'; } ?>
                </datalist>
              </div>
              <div class="col-12">
                <label class="form-label">Reason / Notes</label>
                <textarea class="form-control" name="notes" rows="2" placeholder="Why are we reassigning?"></textarea>
              </div>
            </div>
            <div class="form-text mt-3">
              A new <code>dispatch</code> row is created with the same booking, your chosen driver, truck, and (optionally) overridden trailer/genset. The original dispatch is left intact for audit; the incident's <code>reassigned_d_id</code> points at the new row, and a <code>workflow_event</code> is recorded.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning">Re-assign</button>
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
  <script src="js/incidents.js"></script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
