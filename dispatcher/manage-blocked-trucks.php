<?php
session_start();
$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatch Admin', 'Admin'], true)) {
  header("Location: dispatcher-index.php?route=login");
  exit();
}
include "php/config/config.php";

// Self-heal columns so the page works even before the migration is applied.
pt_ensure_column($conn, 'units', 'dispatch_blocked',      "BOOLEAN NOT NULL DEFAULT FALSE");
pt_ensure_column($conn, 'units', 'dispatch_block_reason', "VARCHAR(255) NOT NULL DEFAULT ''");
pt_ensure_column($conn, 'units', 'dispatch_blocked_by',   "INTEGER NOT NULL DEFAULT 0");
pt_ensure_column($conn, 'units', 'dispatch_blocked_at',   "TIMESTAMP NULL");

$rows = $conn->query(
  "SELECT u.unit_name, u.unit_plate, u.unit_status,
          u.dispatch_blocked, u.dispatch_block_reason, u.dispatch_blocked_at,
          NULLIF(TRIM(CONCAT(bu.user_fname, ' ', bu.user_lname)), '') AS blocked_by_name
     FROM units u
     LEFT JOIN \"user\" bu ON bu.user_id = u.dispatch_blocked_by
    WHERE u.unit_name NOT LIKE 'GS%'
    ORDER BY u.dispatch_blocked DESC, u.unit_name ASC"
)->fetchAll();
$blockedCount = 0;
foreach ($rows as $r) { if ((int)$r['dispatch_blocked'] === 1) $blockedCount++; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Blocked Trucks</title>
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
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Pantrucks Fleet Management System</h3>
    </div>
    <?php include 'sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3">
            <div class="card-body">
              <div class="d-md-flex align-items-center mb-3">
                <div>
                  <h4 class="card-title mb-0">Blocked Trucks</h4>
                  <p class="card-subtitle">Block a truck to stop it being assigned to any booking. Unblock to allow assignment again. This is separate from Maintenance blocks.</p>
                </div>
                <div class="ms-auto mt-2 mt-md-0">
                  <span class="badge bg-danger fs-2">Blocked: <?= $blockedCount ?></span>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table text-nowrap align-middle fs-3" id="table-data">
                  <thead>
                    <tr>
                      <th class="text-muted">Truck</th>
                      <th class="text-muted">Plate No</th>
                      <th class="text-muted">Unit Status</th>
                      <th class="text-muted">Dispatch Block</th>
                      <th class="text-muted">Reason</th>
                      <th class="text-muted">Blocked By / When</th>
                      <th class="text-muted text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $r):
                      $name = htmlspecialchars($r['unit_name']);
                      $blocked = (int)$r['dispatch_blocked'] === 1; ?>
                      <tr>
                        <td class="fw-bolder"><?= $name ?></td>
                        <td><?= htmlspecialchars($r['unit_plate']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(trim($r['unit_status']) ?: 'Good') ?></span></td>
                        <td>
                          <?= $blocked
                            ? '<span class="badge bg-danger">Blocked</span>'
                            : '<span class="badge bg-success">Assignable</span>' ?>
                        </td>
                        <td class="text-wrap" style="max-width:260px;"><?= $blocked ? htmlspecialchars($r['dispatch_block_reason']) : '—' ?></td>
                        <td>
                          <?php if ($blocked): ?>
                            <?= htmlspecialchars($r['blocked_by_name'] ?: '—') ?>
                            <div class="text-muted small"><?= htmlspecialchars($r['dispatch_blocked_at'] ?? '') ?></div>
                          <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-end">
                          <?php if ($blocked): ?>
                            <button class="btn btn-success btn-sm btn-unblock" data-truck="<?= $name ?>"><i class="ti ti-lock-open"></i> Unblock</button>
                          <?php else: ?>
                            <button class="btn btn-danger btn-sm btn-block" data-truck="<?= $name ?>"><i class="ti ti-lock"></i> Block</button>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>
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
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
    $(function () {
      try { $('#table-data').DataTable({ order: [], pageLength: 25 }); } catch (e) {}
    });

    $(document).on('click', '.btn-block', function () {
      var truck = $(this).data('truck');
      Swal.fire({
        title: 'Block ' + truck + '?',
        input: 'text',
        inputLabel: 'Reason (required)',
        inputPlaceholder: 'e.g. Brake issue, pending inspection…',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Block',
        confirmButtonColor: '#dc3545',
        inputValidator: function (v) { if (!v || !v.trim()) return 'A reason is required.'; }
      }).then(function (res) {
        if (!res.isConfirmed) return;
        $.post('php/operations/dispatch_block_truck.php',
          { unit_name: truck, reason: res.value },
          function (r) {
            if (r.status === 'success') {
              Swal.fire({ icon: 'success', text: r.message, timer: 1400, showConfirmButton: false })
                .then(function () { location.reload(); });
            } else {
              Swal.fire({ icon: 'error', text: r.message || 'Failed' });
            }
          }, 'json').fail(function (xhr) {
            Swal.fire({ icon: 'error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Network error.' });
          });
      });
    });

    $(document).on('click', '.btn-unblock', function () {
      var truck = $(this).data('truck');
      Swal.fire({
        title: 'Unblock ' + truck + '?',
        text: 'It will be assignable again.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Unblock',
        confirmButtonColor: '#198754'
      }).then(function (res) {
        if (!res.isConfirmed) return;
        $.post('php/operations/dispatch_unblock_truck.php',
          { unit_name: truck },
          function (r) {
            if (r.status === 'success') {
              Swal.fire({ icon: 'success', text: r.message, timer: 1400, showConfirmButton: false })
                .then(function () { location.reload(); });
            } else {
              Swal.fire({ icon: 'error', text: r.message || 'Failed' });
            }
          }, 'json').fail(function (xhr) {
            Swal.fire({ icon: 'error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Network error.' });
          });
      });
    });
  </script>
</body>
</html>
