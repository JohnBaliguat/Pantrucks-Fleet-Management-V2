<?php
session_start();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== "Admin") {
  header("Location: index.php?route=login");
  exit();
}

include "php/config/config.php";
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trip Rates</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>

<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">

    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
        <a class="d-flex justify-content-center" href="#">
          <img src="assets/images/logos/pantrucks.png" alt="" width="122">
        </a>
      </div>
      <div class="d-lg-flex align-items-center gap-2">
        <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3>
      </div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>

      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="row">
            <div class="col-lg-12">
              <div class="card">
                <div class="card-body">
                  <div class="d-md-flex align-items-center">
                    <div>
                      <h4 class="card-title">Trip Rates (Piece Rate)</h4>
                      <p class="text-muted mb-0 fs-3">Driver earnings are computed from these rates at dispatch time. Leave Segment blank to set a default rate for the activity that applies to any segment.</p>
                    </div>
                    <div class="ms-auto mt-3 mt-md-0">
                      <button class="btn btn-primary btn-sm" type="button" id="addTripRateBtn"><i class="ti ti-plus"></i> Add Trip Rate</button>
                    </div>
                  </div>
                  <?php
                  $needsRateCount = 0;
                  try {
                    $needsRateCount = (int)$conn->query("SELECT COUNT(*) FROM trip_rates WHERE auto_created = TRUE AND total_rates = 0")->fetchColumn();
                  } catch (Throwable $e) { /* auto_created column may not exist yet */ }
                  if ($needsRateCount > 0): ?>
                    <div class="alert alert-warning d-flex align-items-center mt-4 mb-0" role="alert">
                      <i class="ti ti-alert-triangle me-2 fs-5"></i>
                      <div><b><?= $needsRateCount ?></b> SKU<?= $needsRateCount === 1 ? '' : 's' ?> auto-added at dispatch have no rate yet — look for the <span class="badge bg-warning text-dark">Needs rate</span> badge below and set the peso amount.</div>
                    </div>
                  <?php endif; ?>
                  <div class="table-responsive mt-4">
                    <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="tripRatesTable">
                      <thead>
                        <tr>
                          <th class="px-0 text-muted">No.</th>
                          <th class="px-0 text-muted">Segment</th>
                          <th class="px-0 text-muted">Activity</th>
                          <th class="px-0 text-muted">Trip Key (Segment.HaulingType.ContainerStat)</th>
                          <th class="px-0 text-muted text-end">Base Rate</th>
                          <th class="px-0 text-muted text-end">Additional</th>
                          <th class="px-0 text-muted text-end">Total Rate</th>
                          <th class="px-0 text-muted text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $count = 0;
                        // auto_created may not exist on a legacy DB — degrade gracefully.
                        $hasAuto = false;
                        try { $hasAuto = (bool)$conn->query("SELECT 1 FROM information_schema.columns WHERE table_name='trip_rates' AND column_name='auto_created' LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
                        $autoSel = $hasAuto ? ', auto_created' : '';
                        // Un-priced auto-added SKUs first, so the admin sees what needs a rate.
                        $order = $hasAuto
                          ? "ORDER BY (auto_created AND total_rates = 0) DESC, segment ASC, activity ASC"
                          : "ORDER BY segment ASC, activity ASC";
                        $stmt = $conn->query("SELECT id, segment, activity, trip_key, base_rate, additional, total_rates{$autoSel} FROM trip_rates {$order}");
                        while ($row = $stmt->fetch()) {
                          $count++;
                          $segment = htmlspecialchars($row['segment'] ?? '', ENT_QUOTES);
                          $activity = htmlspecialchars($row['activity'] ?? '', ENT_QUOTES);
                          $tripKey = htmlspecialchars($row['trip_key'] ?? '', ENT_QUOTES);
                          $needsRate = $hasAuto && !empty($row['auto_created']) && (float)$row['total_rates'] == 0.0;
                        ?>
                          <tr>
                            <td class="px-0"><?= $count ?></td>
                            <td class="px-0"><?= $segment === '' ? '<span class="text-muted fst-italic">(any)</span>' : $segment ?></td>
                            <td class="px-0"><?= $activity ?></td>
                            <td class="px-0"><?= $tripKey === '' ? '<span class="text-muted fst-italic">—</span>' : '<span class="font-monospace">' . $tripKey . '</span>' ?><?php if ($needsRate): ?> <span class="badge bg-warning text-dark" title="Auto-added at dispatch — set a rate">Needs rate</span><?php endif; ?></td>
                            <td class="px-0 text-end"><?= number_format((float)$row['base_rate'], 2) ?></td>
                            <td class="px-0 text-end"><?= number_format((float)$row['additional'], 2) ?></td>
                            <td class="px-0 text-end fw-bolder"><?= number_format((float)$row['total_rates'], 2) ?></td>
                            <td class="px-0 text-dark fw-medium text-end">
                              <div class="d-grid gap-2 d-md-block">
                                <button class="btn btn-primary btn-sm" type="button"
                                  onclick="openUpdateTripRate(<?= (int)$row['id'] ?>, '<?= $segment ?>', '<?= $activity ?>', '<?= $tripKey ?>', '<?= $row['base_rate'] ?>', '<?= $row['additional'] ?>')">
                                  <i class="ti ti-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" type="button" onclick="deleteTripRate(<?= (int)$row['id'] ?>)">
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

  <!-- Add Trip Rate Modal -->
  <div class="modal fade" id="addTripRateModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Add Trip Rate</h3>
        </div>
        <div class="modal-body">
          <form id="addTripRateForm">
            <div class="mb-3">
              <label class="form-label">Segment</label>
              <input type="text" class="form-control" name="segment" placeholder="e.g. DOLE-PORT, ABC CATEEL — leave blank for default">
              <small class="text-muted">Leave blank to set a default rate for this activity across any segment.</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Activity <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="activity" placeholder="e.g. Delivery, Loaded, Empty" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Trip Key (Segment.HaulingType.ContainerStat)</label>
              <input type="text" class="form-control font-monospace" name="trip_key" placeholder="e.g. GOOD FARMER.Reefer Van.Loaded">
              <small class="text-muted">The trip's hauling segment + hauling type + container status joined by dots. Used to match a trip to this rate on the coupon.</small>
            </div>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">Base Rate</label>
                <input type="number" step="0.01" min="0" class="form-control" name="base_rate" value="0">
              </div>
              <div class="col-6">
                <label class="form-label">Additional</label>
                <input type="number" step="0.01" min="0" class="form-control" name="additional" value="0">
              </div>
            </div>
            <div class="mt-2 text-muted fs-3">Total = Base + Additional (computed automatically).</div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-success" id="saveTripRate">Save</button>
          <button type="button" data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Update Trip Rate Modal -->
  <div class="modal fade" id="updateTripRateModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Update Trip Rate</h3>
        </div>
        <div class="modal-body">
          <form id="updateTripRateForm">
            <input type="hidden" name="id" id="upd_tr_id">
            <div class="mb-3">
              <label class="form-label">Segment</label>
              <input type="text" class="form-control" name="segment" id="upd_tr_segment" placeholder="(leave blank for default)">
            </div>
            <div class="mb-3">
              <label class="form-label">Activity <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="activity" id="upd_tr_activity" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Trip Key (Segment.HaulingType.ContainerStat)</label>
              <input type="text" class="form-control font-monospace" name="trip_key" id="upd_tr_tripkey" placeholder="e.g. GOOD FARMER.Reefer Van.Loaded">
              <small class="text-muted">The trip's hauling segment + hauling type + container status joined by dots.</small>
            </div>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">Base Rate</label>
                <input type="number" step="0.01" min="0" class="form-control" name="base_rate" id="upd_tr_base">
              </div>
              <div class="col-6">
                <label class="form-label">Additional</label>
                <input type="number" step="0.01" min="0" class="form-control" name="additional" id="upd_tr_add">
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="updateTripRateBtn">Update</button>
          <button type="button" data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="datatable/datatables.min.js"></script>
  <script>
    $(function () {
      $('#tripRatesTable').DataTable({ order: [[1, 'asc'], [2, 'asc']] });

      $('#addTripRateBtn').on('click', function () {
        $('#addTripRateForm')[0].reset();
        new bootstrap.Modal('#addTripRateModal').show();
      });

      $('#saveTripRate').on('click', function () {
        $.post('php/crud/add/addtriprate.php', $('#addTripRateForm').serialize())
          .done(function (resp) {
            if (resp.status === 'success') {
              Swal.fire('Saved', resp.message, 'success').then(() => location.reload());
            } else {
              Swal.fire('Error', resp.message || 'Save failed', 'error');
            }
          })
          .fail(() => Swal.fire('Error', 'Network error', 'error'));
      });

      $('#updateTripRateBtn').on('click', function () {
        $.post('php/crud/update/updatetriprate.php', $('#updateTripRateForm').serialize())
          .done(function (resp) {
            if (resp.status === 'success') {
              Swal.fire('Updated', resp.message, 'success').then(() => location.reload());
            } else {
              Swal.fire('Error', resp.message || 'Update failed', 'error');
            }
          })
          .fail(() => Swal.fire('Error', 'Network error', 'error'));
      });
    });

    function openUpdateTripRate(id, segment, activity, tripKey, baseRate, additional) {
      $('#upd_tr_id').val(id);
      $('#upd_tr_segment').val(segment);
      $('#upd_tr_activity').val(activity);
      $('#upd_tr_tripkey').val(tripKey);
      $('#upd_tr_base').val(baseRate);
      $('#upd_tr_add').val(additional);
      new bootstrap.Modal('#updateTripRateModal').show();
    }

    function deleteTripRate(id) {
      Swal.fire({
        title: 'Delete this rate?',
        text: 'Existing trips that were stamped with this rate keep their value. Only future dispatches lose it.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete'
      }).then((result) => {
        if (!result.isConfirmed) return;
        $.post('php/crud/delete/deletetriprate.php', { id })
          .done(function (resp) {
            if (resp.status === 'success') {
              Swal.fire('Deleted', resp.message, 'success').then(() => location.reload());
            } else {
              Swal.fire('Error', resp.message || 'Delete failed', 'error');
            }
          })
          .fail(() => Swal.fire('Error', 'Network error', 'error'));
      });
    }
  </script>
</body>

</html>
