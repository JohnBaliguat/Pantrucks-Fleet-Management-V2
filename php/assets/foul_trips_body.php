<?php
// Shared body for the Foul Trips approval queue.
// Included by `dispatcher/foul-trips.php` and `admin/foul-trips.php`.
// Required vars from caller: $role ('admin'|'dispatcher').
require_once __DIR__ . '/../config/config.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Foul Trips</title>
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
                  <div class="d-md-flex align-items-center">
                    <div>
                      <h4 class="card-title">Foul Trips — Payroll Approval</h4>
                      <p class="text-muted mb-0 fs-3">A leg cancelled <em>after</em> assignment is a foul trip. Its rate counts toward the driver's earnings <b>only after you approve it here</b>. Clean cancellations are never paid and don't appear.</p>
                    </div>
                    <div class="ms-auto mt-3 mt-md-0">
                      <select id="statusFilter" class="form-select form-select-sm" style="min-width:180px;">
                        <option value="pending" selected>Pending approval</option>
                        <option value="approved">Approved</option>
                        <option value="all">All foul trips</option>
                      </select>
                    </div>
                  </div>

                  <div class="table-responsive mt-4">
                    <table class="table text-nowrap align-middle fs-3 mb-0">
                      <thead>
                        <tr>
                          <th class="text-muted">Date</th>
                          <th class="text-muted">Driver / Truck</th>
                          <th class="text-muted">Booking</th>
                          <th class="text-muted">SKU / Route</th>
                          <th class="text-muted">Reason</th>
                          <th class="text-muted text-end">Rate</th>
                          <th class="text-muted">Status</th>
                          <th class="text-muted text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody id="foulBody">
                        <tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
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
  <script>
    function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function peso(n) { return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }); }

    function load() {
      var status = $('#statusFilter').val();
      $('#foulBody').html('<tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>');
      $.getJSON('php/fetch/foul_trips_pending.php', { status: status }, function (res) {
        if (res.status !== 'success') {
          $('#foulBody').html('<tr><td colspan="8" class="text-center text-danger py-4">' + escapeHtml(res.message || 'Failed') + '</td></tr>');
          return;
        }
        if (!res.rows.length) {
          var msg = status === 'pending' ? 'Nothing pending — all foul trips reviewed ✓' : 'No foul trips.';
          $('#foulBody').html('<tr><td colspan="8" class="text-center text-success py-4">' + msg + '</td></tr>');
          return;
        }
        $('#foulBody').html(res.rows.map(function (r) {
          var statusCell = r.approved
            ? '<span class="badge bg-success">Approved</span>' + (r.approved_by ? '<div class="small text-muted">' + escapeHtml(r.approved_by) + ' · ' + escapeHtml(r.approved_at) + '</div>' : '')
            : '<span class="badge bg-warning text-dark">Pending</span>';
          var action = r.approved
            ? '<button class="btn btn-outline-danger btn-sm foul-revoke" data-id="' + r.trip_id + '">Revoke</button>'
            : '<button class="btn btn-success btn-sm foul-approve" data-id="' + r.trip_id + '">Approve for payroll</button>';
          return '<tr>' +
            '<td><small>' + escapeHtml(r.date) + '</small><div class="small text-muted">cancelled ' + escapeHtml(r.cancelled_at) + '</div></td>' +
            '<td>' + escapeHtml(r.driver || '—') + '<div class="small text-muted">' + escapeHtml(r.truck || '') + '</div></td>' +
            '<td><small>' + escapeHtml(r.booking_no || '') + '</small></td>' +
            '<td><span class="font-monospace">' + escapeHtml(r.trip_sku || r.segment || '—') + '</span><div class="small text-muted">' + escapeHtml(r.route) + '</div></td>' +
            '<td><small>' + escapeHtml(r.cancelled_reason || '—') + '</small></td>' +
            '<td class="text-end fw-bold">' + peso(r.piece_rate) + '</td>' +
            '<td>' + statusCell + '</td>' +
            '<td class="text-end">' + action + '</td>' +
          '</tr>';
        }).join(''));
      }).fail(function () {
        $('#foulBody').html('<tr><td colspan="8" class="text-center text-danger py-4">Network error</td></tr>');
      });
    }

    function act(tripId, revoke) {
      $.post('php/operations/approve_foul_trip.php', { trip_id: tripId, revoke: revoke ? 1 : 0 }, function (res) {
        if (res.status === 'success') {
          Swal.fire({ icon: 'success', text: res.message, timer: 1600, showConfirmButton: false });
          load();
        } else {
          Swal.fire({ icon: 'error', text: res.message || 'Failed' });
        }
      }, 'json').fail(function (xhr) {
        Swal.fire({ icon: 'error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Network error' });
      });
    }

    $(document).on('click', '.foul-approve', function () {
      var id = $(this).data('id');
      Swal.fire({
        title: 'Approve this foul trip?',
        text: 'Its rate will be added to the driver’s earnings.',
        icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, approve'
      }).then(function (r) { if (r.isConfirmed) act(id, false); });
    });
    $(document).on('click', '.foul-revoke', function () {
      var id = $(this).data('id');
      Swal.fire({
        title: 'Revoke approval?',
        text: 'This foul trip will no longer count toward earnings.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, revoke'
      }).then(function (r) { if (r.isConfirmed) act(id, true); });
    });
    $('#statusFilter').on('change', load);
    load();
  </script>
</body>
</html>
