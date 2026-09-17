<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Maintenance') { header("Location: login"); exit(); }
$pageTitle = 'Service History';
include 'maintenance/_layout_top.php';
?>
<div class="row">
  <div class="col-12">
    <div class="card"><div class="card-body">
      <div class="d-md-flex align-items-center mb-3">
        <div>
          <h4 class="card-title mb-0">Service History</h4>
          <p class="card-subtitle">Every block / release event with cost, category, and duration.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
          <input class="form-control form-control-sm" id="histText" placeholder="Filter code…" style="max-width:180px;">
          <button class="btn btn-sm btn-outline-secondary" id="histRefresh"><i class="ti ti-refresh"></i></button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light"><tr>
            <th>#</th><th>Code</th><th>Kind</th><th>Category</th><th>Severity</th><th>Reason</th>
            <th>Blocked</th><th>Released</th><th>Duration</th><th>Cost (L/P)</th>
          </tr></thead>
          <tbody id="histBody"><tr><td colspan="10" class="text-center text-muted">Loading…</td></tr></tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>

<script>
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function sevBadge(s){var c=s==='high'?'bg-danger':s==='med'?'bg-warning text-dark':'bg-secondary';return '<span class="badge '+c+'">'+escapeHtml(s)+'</span>';}
function dur(a, b){
  if (!a || !b) return '<span class="text-muted">—</span>';
  var t = Math.max(0, Math.round((new Date(b) - new Date(a)) / 60000));
  if (t < 60) return t + ' min';
  var h = Math.floor(t / 60), m = t % 60;
  if (h < 24) return h + 'h ' + m + 'm';
  var d = Math.floor(h / 24), hh = h % 24;
  return d + 'd ' + hh + 'h';
}

function loadHistory(){
  $.getJSON('php/fetch/maintenance_history.php', { q: $('#histText').val() || '' }, function(res){
    if (res.status !== 'success') return;
    if (!res.rows.length) { $('#histBody').html('<tr><td colspan="10" class="text-center text-muted">No history yet.</td></tr>'); return; }
    var html = res.rows.map(function(r){
      var status = r.status === 'active'
        ? '<span class="badge bg-warning text-dark">Open</span>'
        : '<span class="badge bg-secondary">Released</span>';
      var costL = parseFloat(r.cost_labor || 0).toFixed(2);
      var costP = parseFloat(r.cost_parts || 0).toFixed(2);
      return '<tr>'
        + '<td>' + r.um_id + ' ' + status + '</td>'
        + '<td><strong>' + escapeHtml(r.unit_code) + '</strong></td>'
        + '<td><span class="badge bg-light text-dark">' + escapeHtml(r.unit_kind) + '</span></td>'
        + '<td>' + escapeHtml(r.category) + '</td>'
        + '<td>' + sevBadge(r.severity) + '</td>'
        + '<td>' + escapeHtml(r.reason) + (r.release_notes ? '<div class="text-muted small">→ ' + escapeHtml(r.release_notes) + '</div>' : '') + '</td>'
        + '<td>' + escapeHtml(r.blocked_at) + '</td>'
        + '<td>' + escapeHtml(r.released_at || '—') + '</td>'
        + '<td>' + dur(r.blocked_at, r.released_at) + '</td>'
        + '<td>' + costL + ' / ' + costP + '</td>'
        + '</tr>';
    }).join('');
    $('#histBody').html(html);
  });
}
$('#histText').on('input', loadHistory);
$('#histRefresh').on('click', loadHistory);
loadHistory();
</script>
<?php include 'maintenance/_layout_bottom.php'; ?>
