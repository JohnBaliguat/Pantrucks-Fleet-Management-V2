<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Dispatcher") {


?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gate</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
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
            <div class="col-lg-4">
              <div class="card overflow-hidden">
                <div class="card-body pb-0">
                  <div class="card">
                    <img id="driver_img" src="assets/images/profile/user-7.jpg" class="card-img-top" alt="...">
                    <div class="card-body">
                      <h5 class="card-title" id="driver_name">Driver Name</h5>
                      <p class="card-text"><b>TRUCK:</b> <span id="truck_name">-</span></p>
                      <p class="card-text"><b>TRAILER:</b> <span id="trailer_name">-</span></p>
                      <p class="card-text"><b>GENSET:</b> <span id="genset_name">-</span></p>
                      <p class="card-text"><b>Trip Segment:</b> <span id="trip_segment">-</span></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-8">
               <div class="card">
                <div class="card-body">
                  <div class="d-md-flex align-items-center">
                    <div>
                      <h4 class="card-title">List of Entries</h4>
                      <input type="password" id="rfid_no" style="width: 0; opacity: 0;">
                    </div>
                  </div>
                  <div class="table-responsive mt-4" style="overflow: hidden;">
                    <table class="table mb-0 text-nowrap varient-table align-middle fs-3" id="attend-table">
                      <thead>
                        <tr>
                          <th scope="col" class="px-0 text-muted">Assigned</th>
                          <th scope="col" class="px-0 text-muted">Segment</th>
                          <th scope="col" class="px-0 text-muted">Date</th>
                          <th scope="col" class="px-0 text-muted">Time</th>
                          <th scope="col" class="px-0 text-muted">Status</th>
                        </tr>
                      </thead>
                      <tbody>

                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Row 2 — Phase 3 Gate Queue panel -->
          <div class="row mt-3">
            <div class="col-12">
              <div class="card"><div class="card-body">
                <div class="d-md-flex align-items-center mb-2">
                  <div>
                    <h4 class="card-title mb-0">Gate Queue</h4>
                    <p class="card-subtitle">Vehicles waiting at the gate. Approve to authorise entry; deny to refuse.</p>
                  </div>
                  <div class="ms-auto">
                    <button class="btn btn-sm btn-outline-secondary" id="refreshQueueBtn"><i class="ti ti-refresh"></i> Refresh</button>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light"><tr>
                      <th>Requested</th><th>Truck</th><th>Driver</th><th>Booking</th><th>Notes</th><th class="text-end">Action</th>
                    </tr></thead>
                    <tbody id="gateQueueBody"><tr><td colspan="6" class="text-center text-muted">Loading…</td></tr></tbody>
                  </table>
                </div>
              </div></div>
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
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="datatable/datatables.min.js"></script>
  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="js/gate.js"></script>
  <script>
  // Phase 3 — gate queue approval panel (dispatcher view).
  function escapeHtmlQ(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function renderGateQueue(){
    $.getJSON('php/fetch/gate_queue.php', { status: 'pending' }, function(res){
      if (res.status !== 'success') return;
      if (!res.rows.length) {
        $('#gateQueueBody').html('<tr><td colspan="6" class="text-center text-muted">No vehicles waiting.</td></tr>');
        return;
      }
      var html = res.rows.map(function(r){
        return '<tr data-gq-id="' + r.gq_id + '">'
          + '<td>' + escapeHtmlQ(r.requested_at) + '</td>'
          + '<td>' + escapeHtmlQ(r.truck_plate) + '</td>'
          + '<td>' + escapeHtmlQ(r.d_driverName || '-') + '</td>'
          + '<td>' + escapeHtmlQ(r.booking_no || '-') + '</td>'
          + '<td>' + escapeHtmlQ(r.notes || '') + '</td>'
          + '<td class="text-end">'
          +   '<button class="btn btn-sm btn-success me-1 q-approve">Approve</button>'
          +   '<button class="btn btn-sm btn-danger q-deny">Deny</button>'
          + '</td></tr>';
      }).join('');
      $('#gateQueueBody').html(html);
    });
  }
  function decideQueue(gqId, decision){
    $.post('php/operations/gate_queue_decide.php', { gq_id: gqId, decision: decision }, function(res){
      Swal.fire({ icon: res.status === 'success' ? 'success' : 'error', text: res.message, timer: 1200, showConfirmButton: false });
      renderGateQueue();
    }, 'json');
  }
  $('#gateQueueBody').on('click', '.q-approve', function(){ decideQueue($(this).closest('tr').data('gq-id'), 'approved'); });
  $('#gateQueueBody').on('click', '.q-deny',    function(){ decideQueue($(this).closest('tr').data('gq-id'), 'denied'); });
  $('#refreshQueueBtn').on('click', renderGateQueue);
  $(renderGateQueue);
  setInterval(renderGateQueue, 10000);
  </script>
</body>


</html>
<?php
} else {
    header("Location: dispatcher-index.php?route=login");
    exit();
}
