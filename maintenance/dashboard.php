<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Maintenance') { header("Location: login"); exit(); }
$pageTitle = 'Dashboard';
include 'maintenance/_layout_top.php';
?>
<div class="row">
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-danger"><div class="card-body">
      <h6 class="card-title text-white mb-1">Currently Blocked</h6>
      <h2 class="text-white mb-0" id="stat_blocked">&mdash;</h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-warning"><div class="card-body">
      <h6 class="card-title text-white mb-1">High Severity</h6>
      <h2 class="text-white mb-0" id="stat_high">&mdash;</h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-info"><div class="card-body">
      <h6 class="card-title text-white mb-1">Overdue Return</h6>
      <h2 class="text-white mb-0" id="stat_overdue">&mdash;</h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-success"><div class="card-body">
      <h6 class="card-title text-white mb-1">Cost (open)</h6>
      <h2 class="text-white mb-0" id="stat_cost">&mdash;</h2>
    </div></div>
  </div>
</div>

<div class="row mt-3">
  <div class="col-12">
    <div class="card"><div class="card-body">
      <div class="d-flex align-items-center mb-3">
        <h4 class="card-title mb-0">Currently Blocked Units</h4>
        <a href="maintenance-units" class="btn btn-primary btn-sm ms-auto"><i class="ti ti-plus"></i> Block / Release Units</a>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered align-middle" id="blockedTable" style="width:100%">
          <thead class="table-light"><tr>
            <th>Code</th><th>Kind</th><th>Category</th><th>Severity</th><th>Reason</th><th>Blocked at</th><th>Expected return</th>
          </tr></thead>
          <tbody id="blockedBody"><tr><td colspan="7" class="text-center text-muted">Loading…</td></tr></tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>

<script>
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function sevBadge(s){var c=s==='high'?'bg-danger':s==='med'?'bg-warning text-dark':'bg-secondary';return '<span class="badge '+c+'">'+escapeHtml(s)+'</span>';}
var blockedDt = null;
function refreshDash(){
  $.getJSON('php/fetch/maintenance_active.php', function(res){
    if (res.status !== 'success') return;
    $('#stat_blocked').text(res.summary.blocked);
    $('#stat_high').text(res.summary.high);
    $('#stat_overdue').text(res.summary.overdue);
    $('#stat_cost').text('PHP ' + parseFloat(res.summary.cost || 0).toFixed(2));
    var rows = res.rows || [];
    var html = rows.map(function(r){
      return '<tr>'
        + '<td><strong>' + escapeHtml(r.unit_code) + '</strong></td>'
        + '<td><span class="badge bg-secondary">' + escapeHtml(r.unit_kind) + '</span></td>'
        + '<td>' + escapeHtml(r.category) + '</td>'
        + '<td>' + sevBadge(r.severity) + '</td>'
        + '<td>' + escapeHtml(r.reason) + '</td>'
        + '<td>' + escapeHtml(r.blocked_at) + '</td>'
        + '<td>' + escapeHtml(r.expected_return || '—') + '</td>'
        + '</tr>';
    }).join('');
    if (!blockedDt) {
      $('#blockedBody').html(html);
      blockedDt = $('#blockedTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        order: [[5, 'desc']],                     // latest "Blocked at" first
        language: { emptyTable: 'No units blocked.', search: 'Search:' }
      });
    } else {
      // Refresh rows in place so the 30s auto-refresh keeps the user's current
      // page / sort / search instead of resetting the table.
      blockedDt.clear();
      if (html) blockedDt.rows.add($(html));
      blockedDt.draw(false);
    }
  });
}
$(refreshDash);
setInterval(refreshDash, 30000);
</script>
<?php include 'maintenance/_layout_bottom.php'; ?>
