<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Gate-Guard') {
    header("Location: gate-index.php?route=login");
    exit();
}
$pageTitle = 'Gate Dashboard';
include 'gate/_layout_top.php';
?>
<div class="row">
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-primary"><div class="card-body">
      <h6 class="card-title text-white mb-1">Today IN</h6>
      <h2 class="text-white mb-0" id="stat_in">&mdash;</h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-success"><div class="card-body">
      <h6 class="card-title text-white mb-1">Today OUT</h6>
      <h2 class="text-white mb-0" id="stat_out">&mdash;</h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-warning"><div class="card-body">
      <h6 class="card-title text-white mb-1">Pending Queue</h6>
      <h2 class="text-white mb-0" id="stat_queue">&mdash;</h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-danger"><div class="card-body">
      <h6 class="card-title text-white mb-1">Open Incidents</h6>
      <h2 class="text-white mb-0" id="stat_incidents">&mdash;</h2>
    </div></div>
  </div>
</div>

<div class="row mt-3">
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <div class="d-flex align-items-center mb-3">
        <h4 class="card-title mb-0">Recent Movements</h4>
        <a href="gate-checkin" class="btn btn-primary btn-sm ms-auto"><i class="ti ti-qrcode"></i> New Check-In / Out</a>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
          <thead class="table-light"><tr>
            <th>Time</th><th>Direction</th><th>Truck</th><th>Trailer</th><th>Genset</th><th>Driver</th><th>Verified</th>
          </tr></thead>
          <tbody id="gateLogBody">
            <tr><td colspan="7" class="text-center text-muted">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <h4 class="card-title">Pending Queue</h4>
      <p class="card-subtitle">Vehicles waiting for dispatcher approval to enter.</p>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr>
            <th>Truck</th><th>Driver</th><th>Requested</th><th>Status</th>
          </tr></thead>
          <tbody id="queueBody"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>
<div class="py-6 px-6 text-center">
  <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
</div>
<script>
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function refreshDashboard(){
  $.getJSON('php/fetch/gate_log_recent.php', function(res){
    if(res.status!=='success') return;
    $('#stat_in').text(res.today_in);
    $('#stat_out').text(res.today_out);
    var html = res.rows.map(function(r){
      var verifiedBadge = r.verified==1
        ? '<span class="badge bg-success">OK</span>'
        : '<span class="badge bg-danger" title="'+escapeHtml(r.mismatch_reason||'')+'">Mismatch</span>';
      var dirBadge = r.direction==='IN'
        ? '<span class="badge bg-primary">IN</span>'
        : '<span class="badge bg-secondary">OUT</span>';
      return '<tr><td>'+escapeHtml(r.logged_at)+'</td><td>'+dirBadge+'</td>'
        + '<td>'+escapeHtml(r.truck_plate)+'</td>'
        + '<td>'+escapeHtml(r.trailer_code||'-')+'</td>'
        + '<td>'+escapeHtml(r.genset_code||'-')+'</td>'
        + '<td>'+escapeHtml(r.driver_name||'-')+'</td>'
        + '<td>'+verifiedBadge+'</td></tr>';
    }).join('');
    $('#gateLogBody').html(html || '<tr><td colspan="7" class="text-center text-muted">No movements yet today.</td></tr>');
  });
  $.getJSON('php/fetch/gate_queue.php', function(res){
    if(res.status!=='success') return;
    $('#stat_queue').text(res.rows.length);
    var html = res.rows.map(function(r){
      return '<tr><td>'+escapeHtml(r.truck_plate)+'</td>'
        + '<td>'+escapeHtml(r.d_driverName||'-')+'</td>'
        + '<td>'+escapeHtml(r.requested_at)+'</td>'
        + '<td><span class="badge bg-warning text-dark">Pending</span></td></tr>';
    }).join('');
    $('#queueBody').html(html || '<tr><td colspan="4" class="text-center text-muted">No queue.</td></tr>');
  });
  $.getJSON('php/fetch/incident_open.php', function(res){
    if(res.status!=='success') return;
    $('#stat_incidents').text(res.count);
  });
}
$(refreshDashboard);
setInterval(refreshDashboard, 10000);
</script>
<?php include 'gate/_layout_bottom.php'; ?>
