<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Gate-Guard') {
    header("Location: gate-index.php?route=login");
    exit();
}
$pageTitle = 'Gate Incident Report';
include 'gate/_layout_top.php';
?>
<div class="row">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <h4 class="card-title">Report an Incident</h4>
      <p class="card-subtitle">Use for unauthorised vehicles, damaged goods, access violations, etc.</p>
      <form id="incForm" enctype="multipart/form-data" class="mt-3">
        <div class="mb-3">
          <label class="form-label">Type</label>
          <select class="form-control" name="incident_type" required>
            <option value="">— pick —</option>
            <option value="unauthorised">Unauthorised vehicle</option>
            <option value="damaged_goods">Damaged goods</option>
            <option value="access_violation">Access violation</option>
            <option value="cargo">Cargo issue</option>
            <option value="exception">Other exception</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Severity</label>
          <select class="form-control" name="severity">
            <option value="low">Low</option>
            <option value="med" selected>Medium</option>
            <option value="high">High</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Truck plate (if known)</label>
          <input class="form-control" name="truck_plate" placeholder="e.g. PM651">
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Photo (optional)</label>
          <input class="form-control" type="file" name="photo" accept="image/*" capture="environment">
        </div>
        <button class="btn btn-danger w-100" type="submit"><i class="ti ti-alert-triangle"></i> Submit Incident</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <h4 class="card-title">Recent Incidents</h4>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light"><tr>
            <th>Time</th><th>Type</th><th>Severity</th><th>Truck</th><th>Description</th><th>Status</th>
          </tr></thead>
          <tbody id="incBody"><tr><td colspan="6" class="text-center text-muted">Loading…</td></tr></tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>
<div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>

<script>
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function loadIncidents(){
  $.getJSON('php/fetch/incident_list.php', { source: 'gate' }, function(res){
    if (res.status !== 'success') return;
    var html = res.rows.map(function(r){
      var sevCls = r.severity === 'high' ? 'bg-danger' : (r.severity === 'med' ? 'bg-warning text-dark' : 'bg-secondary');
      var statusCls = r.status === 'open' ? 'bg-warning text-dark' : (r.status === 'resolved' ? 'bg-success' : 'bg-info');
      return '<tr><td>' + escapeHtml(r.reported_at) + '</td>'
        + '<td>' + escapeHtml(r.incident_type) + '</td>'
        + '<td><span class="badge ' + sevCls + '">' + escapeHtml(r.severity) + '</span></td>'
        + '<td>' + escapeHtml(r.truck_plate || '-') + '</td>'
        + '<td>' + escapeHtml(r.description) + '</td>'
        + '<td><span class="badge ' + statusCls + '">' + escapeHtml(r.status) + '</span></td></tr>';
    }).join('');
    $('#incBody').html(html || '<tr><td colspan="6" class="text-center text-muted">No incidents yet.</td></tr>');
  });
}
$(loadIncidents);
$('#incForm').on('submit', function(e){
  e.preventDefault();
  var fd = new FormData(this);
  $.ajax({
    url: 'php/operations/report_incident.php',
    method: 'POST', data: fd, contentType: false, processData: false, dataType: 'json',
    success: function(res){
      Swal.fire({ icon: res.status === 'success' ? 'success' : 'error', text: res.message, timer: 1500, showConfirmButton: false });
      if (res.status === 'success') { document.getElementById('incForm').reset(); loadIncidents(); }
    },
    error: function(){ Swal.fire({icon:'error',text:'Network error'}); }
  });
});
</script>
<?php include 'gate/_layout_bottom.php'; ?>
