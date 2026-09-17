<?php
// Shared read-only view of currently blocked units, for dispatcher / admin.
// Required vars: $role.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Blocked Units</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Blocked Units</h3>
    </div>
    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3"><div class="card-body">
            <div class="d-flex align-items-center mb-2">
              <div>
                <h4 class="card-title mb-0">Currently Blocked Units</h4>
                <p class="card-subtitle">Read-only. The Maintenance role can block / release units.</p>
              </div>
              <div class="ms-auto"><button class="btn btn-sm btn-outline-secondary" id="refreshBtn"><i class="ti ti-refresh"></i></button></div>
            </div>
            <div class="row mb-3">
              <div class="col-md-3"><div class="card text-bg-danger"><div class="card-body"><h6 class="text-white mb-1">Blocked</h6><h2 class="text-white mb-0" id="s_blocked">—</h2></div></div></div>
              <div class="col-md-3"><div class="card text-bg-warning"><div class="card-body"><h6 class="text-white mb-1">High severity</h6><h2 class="text-white mb-0" id="s_high">—</h2></div></div></div>
              <div class="col-md-3"><div class="card text-bg-info"><div class="card-body"><h6 class="text-white mb-1">Overdue return</h6><h2 class="text-white mb-0" id="s_overdue">—</h2></div></div></div>
              <div class="col-md-3"><div class="card text-bg-secondary"><div class="card-body"><h6 class="text-white mb-1">Cost open (PHP)</h6><h2 class="text-white mb-0" id="s_cost">—</h2></div></div></div>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead class="table-light"><tr>
                  <th>Code</th><th>Kind</th><th>Category</th><th>Severity</th><th>Reason</th><th>Since</th><th>Expected return</th>
                </tr></thead>
                <tbody id="blockedBody"><tr><td colspan="7" class="text-center text-muted">Loading…</td></tr></tbody>
              </table>
            </div>
          </div></div>
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
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
  function sevBadge(s){var c=s==='high'?'bg-danger':s==='med'?'bg-warning text-dark':'bg-secondary';return '<span class="badge '+c+'">'+esc(s)+'</span>';}
  function load(){
    $.getJSON('php/fetch/maintenance_active.php', function(res){
      if (res.status !== 'success') return;
      $('#s_blocked').text(res.summary.blocked);
      $('#s_high').text(res.summary.high);
      $('#s_overdue').text(res.summary.overdue);
      $('#s_cost').text(parseFloat(res.summary.cost || 0).toFixed(2));
      if (!res.rows.length) { $('#blockedBody').html('<tr><td colspan="7" class="text-center text-muted">No units blocked.</td></tr>'); return; }
      var html = res.rows.map(function(r){
        return '<tr><td><strong>' + esc(r.unit_code) + '</strong></td>'
          + '<td><span class="badge bg-secondary">' + esc(r.unit_kind) + '</span></td>'
          + '<td>' + esc(r.category) + '</td>'
          + '<td>' + sevBadge(r.severity) + '</td>'
          + '<td>' + esc(r.reason) + '</td>'
          + '<td>' + esc(r.blocked_at) + '</td>'
          + '<td>' + esc(r.expected_return || '—') + '</td></tr>';
      }).join('');
      $('#blockedBody').html(html);
    });
  }
  $('#refreshBtn').on('click', load);
  load();
  setInterval(load, 30000);
  </script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
