<?php
session_start();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== "HR-Admin") {
  header("Location: index.php?route=login");
  exit();
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Driver Payroll</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <style>
    .stat-card { border-radius: 14px; padding: 18px 22px; }
    .stat-card .icon { width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; color: #fff; font-size: 22px; }
    .stat-card .value { font-size: 28px; font-weight: 700; line-height: 1.1; }
    .stat-card .label { color: #6b7a90; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; }
  </style>
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

    <?php include 'sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>

      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="row mb-3">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title mb-1">Driver Payroll</h4>
                  <p class="text-muted mb-3 fs-3">Read-only view of driver earnings sourced from Trip Rates. Use this to cross-check payroll batches.</p>
                  <form id="payrollFilter" class="row g-3 align-items-end">
                    <div class="col-md-3">
                      <label class="form-label">Date From</label>
                      <input type="date" class="form-control" id="dateFrom" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Date To</label>
                      <input type="date" class="form-control" id="dateTo" required>
                    </div>
                    <div class="col-md-3">
                      <button type="submit" class="btn btn-primary"><i class="ti ti-search"></i> Load</button>
                      <button type="button" class="btn btn-outline-secondary" id="exportCsvBtn"><i class="ti ti-download"></i> Export CSV</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <div class="card stat-card d-flex flex-row align-items-center gap-3">
                <span class="icon bg-primary"><i class="ti ti-users"></i></span>
                <div><div class="value" id="statDrivers">0</div><div class="label">Drivers Paid</div></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card stat-card d-flex flex-row align-items-center gap-3">
                <span class="icon bg-success"><i class="ti ti-route"></i></span>
                <div><div class="value" id="statTrips">0</div><div class="label">Total Trips</div></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card stat-card d-flex flex-row align-items-center gap-3">
                <span class="icon bg-warning"><i class="ti ti-cash"></i></span>
                <div><div class="value" id="statTotal">0.00</div><div class="label">Total Payout (₱)</div></div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-hover align-middle fs-3 mb-0" id="payrollTable">
                      <thead>
                        <tr>
                          <th>Driver</th>
                          <th class="text-end">Trips</th>
                          <th class="text-end">Earnings (₱)</th>
                          <th class="text-end">Action</th>
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
  </div>

  <div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title" id="detailTitle">Trip Details</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table align-middle fs-3" id="detailTable">
              <thead>
                <tr>
                  <th>Date</th><th>Booking</th><th>Trip</th><th>Truck</th><th>Customer</th>
                  <th>Segment</th><th>SKU</th><th>From → To</th><th>Status</th>
                  <th class="text-end">Piece Rate (₱)</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="datatable/datatables.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script>
    (function () {
      const today = new Date();
      const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
      const fmt = (d) => d.toISOString().slice(0, 10);
      $('#dateFrom').val(fmt(monthStart));
      $('#dateTo').val(fmt(today));

      let dt;
      function load() {
        $.getJSON('php/fetch/get_payroll_data.php', { date_from: $('#dateFrom').val(), date_to: $('#dateTo').val() })
          .done(function (resp) {
            if (!resp.success) { Swal.fire('Error', resp.message || 'Failed', 'error'); return; }
            $('#statDrivers').text(resp.summary.total_drivers);
            $('#statTrips').text(resp.summary.total_trips);
            $('#statTotal').text(Number(resp.summary.grand_total).toLocaleString('en-PH', { minimumFractionDigits: 2 }));
            if (dt) dt.destroy();
            const $body = $('#payrollTable tbody').empty();
            resp.rows.forEach(r => $body.append(`
              <tr>
                <td><span class="fw-bolder">${escapeHtml(r.driver_name)}</span><br><small class="text-muted">ID ${r.driver_id}</small></td>
                <td class="text-end">${r.total_trips}</td>
                <td class="text-end fw-bolder">${Number(r.total_earnings).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" onclick="showDetail(${r.driver_id}, '${escapeHtml(r.driver_name).replace(/'/g, "\\'")}')"><i class="ti ti-eye"></i> View</button>
                </td>
              </tr>`));
            dt = $('#payrollTable').DataTable({ order: [[2, 'desc']], destroy: true });
          })
          .fail(() => Swal.fire('Error', 'Network error', 'error'));
      }
      $('#payrollFilter').on('submit', e => { e.preventDefault(); load(); });
      load();

      window.showDetail = function (driverId, name) {
        $('#detailTitle').text(`Trips: ${name} (${$('#dateFrom').val()} → ${$('#dateTo').val()})`);
        $.getJSON('php/fetch/get_payroll_data.php', { date_from: $('#dateFrom').val(), date_to: $('#dateTo').val(), driver_id: driverId })
          .done(function (resp) {
            const $body = $('#detailTable tbody').empty();
            (resp.details || []).forEach(t => $body.append(`
              <tr>
                <td>${escapeHtml(t.d_datetime || '').slice(0,16)}</td>
                <td>${escapeHtml(t.booking_no || '')}</td>
                <td>${escapeHtml(t.trip_type || '')}</td>
                <td>${escapeHtml(t.d_truck || '')}</td>
                <td>${escapeHtml(t.costumer || '')}</td>
                <td>${escapeHtml(t.trip_haulingsegment || '')}</td>
                <td>${escapeHtml(t.trip_sku || t.container_activity || '')}</td>
                <td>${escapeHtml(t.trip_from || '')} → ${escapeHtml(t.trip_to || '')}</td>
                <td>${escapeHtml(t.trip_status || '')}</td>
                <td class="text-end fw-bolder">${Number(t.piece_rate || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
              </tr>`));
            new bootstrap.Modal('#detailModal').show();
          });
      };

      $('#exportCsvBtn').on('click', function () {
        const rows = [['Driver', 'Driver ID', 'Trips', 'Earnings (PHP)']];
        $('#payrollTable tbody tr').each(function () {
          const cells = $(this).find('td');
          const name = $(cells[0]).find('.fw-bolder').text();
          const id   = $(cells[0]).find('.text-muted').text().replace(/^ID\s*/, '');
          rows.push([name, id, $(cells[1]).text().trim(), $(cells[2]).text().trim()]);
        });
        const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `payroll_${$('#dateFrom').val()}_${$('#dateTo').val()}.csv`;
        a.click();
      });

      function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
      }
    })();
  </script>
</body>

</html>
