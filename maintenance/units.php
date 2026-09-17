<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Maintenance') { header("Location: login"); exit(); }
$pageTitle = 'Units & Blocks';
include 'maintenance/_layout_top.php';
?>
<div class="row">
  <div class="col-12">
    <div class="card"><div class="card-body">
      <div class="d-md-flex align-items-center mb-3">
        <div>
          <h4 class="card-title mb-0">Units &amp; Blocks</h4>
          <p class="card-subtitle">Block a truck / genset / trailer to remove it from dispatch. Release when service is done.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
          <select class="form-select form-select-sm" id="filterKind">
            <option value="all" selected>All kinds</option>
            <option value="truck">Trucks</option>
            <option value="genset">Gensets</option>
            <option value="trailer">Trailers</option>
          </select>
          <select class="form-select form-select-sm" id="filterStatus">
            <option value="all" selected>All</option>
            <option value="blocked">Blocked only</option>
            <option value="scheduled">Scheduled only</option>
            <option value="available">Available only</option>
          </select>
          <button class="btn btn-sm btn-outline-secondary" id="refreshBtn"><i class="ti ti-refresh"></i></button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered align-middle" id="unitsTable" style="width:100%">
          <thead class="table-light"><tr>
            <th>Code</th><th>Kind</th><th>Status</th><th>Block reason</th><th>Scheduled start</th><th>Since</th><th>Expected return</th><th class="text-end">Action</th>
          </tr></thead>
          <tbody id="unitsBody"><tr><td colspan="7" class="text-center text-muted">Loading…</td></tr></tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>

<!-- Block modal -->
<div class="modal fade" id="blockModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="blockForm" enctype="multipart/form-data">
        <div class="modal-header"><h5 class="modal-title">Block unit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="unit_kind" id="b_kind">
          <input type="hidden" name="unit_code" id="b_code">
          <div class="alert alert-warning"><span id="b_summary"></span></div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <select class="form-control" name="category">
                <option value="scheduled">Scheduled maintenance</option>
                <option value="engine">Engine</option>
                <option value="brakes">Brakes</option>
                <option value="tyres">Tyres / wheels</option>
                <option value="electrical">Electrical</option>
                <option value="body">Body / chassis</option>
                <option value="accident">Accident damage</option>
                <option value="other" selected>Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Severity</label>
              <select class="form-control" name="severity">
                <option value="low">Low</option>
                <option value="med" selected>Medium</option>
                <option value="high">High</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Scheduled start</label>
              <input type="datetime-local" class="form-control" name="scheduled_start">
              <small class="text-muted">Required when category is Scheduled maintenance.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Expected return (optional)</label>
              <input type="datetime-local" class="form-control" name="expected_return">
              <small class="text-muted">Unit auto-returns to the fleet at this date &amp; time.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Photo (optional)</label>
              <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png,.webp,.heic">
            </div>
            <div class="col-12">
              <label class="form-label">Reason / description <span class="text-danger">*</span></label>
              <textarea class="form-control" name="reason" rows="3" required placeholder="What's wrong?"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="ti ti-lock"></i> Block from dispatch</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Release modal -->
<div class="modal fade" id="releaseModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="releaseForm">
        <div class="modal-header"><h5 class="modal-title">Release unit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="unit_kind" id="r_kind">
          <input type="hidden" name="unit_code" id="r_code">
          <div class="alert alert-info"><span id="r_summary"></span></div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Labor cost (PHP)</label>
              <input type="number" min="0" step="0.01" class="form-control" name="cost_labor" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Parts cost (PHP)</label>
              <input type="number" min="0" step="0.01" class="form-control" name="cost_parts" value="0">
            </div>
            <div class="col-12">
              <label class="form-label">Release notes</label>
              <textarea class="form-control" name="release_notes" rows="2" placeholder="What was done?"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="ti ti-lock-open"></i> Release back to fleet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

var unitsDt = null;
function loadUnits(){
  $.getJSON('php/fetch/maintenance_units.php', {
    kind:   $('#filterKind').val(),
    status: $('#filterStatus').val(),
  }, function(res){
    if (res.status !== 'success') return;
    var rows = res.rows || [];
    var html = rows.map(function(r){
      var blocked = parseInt(r.maintenance_blocked, 10) === 1;
      var ticketStatus = String(r.maintenance_ticket_status || '').toLowerCase();
      var scheduled = ticketStatus === 'scheduled';
      // A unit is out of service when it's blocked, has an active/scheduled
      // maintenance ticket, or its master status says "Under Maintenance"
      // (a scheduled ticket that has started flips to 'active' + this status but
      // may not set the maintenance_blocked flag). Any of these should offer
      // Release/Unblock — not Block.
      var underMaintenance = blocked || scheduled || ticketStatus === 'active'
        || String(r.unit_status || '').toLowerCase() === 'under maintenance';
      var statusBadge = blocked
        ? '<span class="badge bg-danger">Blocked</span>'
        : scheduled
          ? '<span class="badge bg-warning text-dark">Scheduled</span>'
        : underMaintenance
          ? '<span class="badge bg-danger">Under maintenance</span>'
        : (r.unit_status && r.unit_status.toLowerCase() !== 'good'
            ? '<span class="badge bg-secondary">' + escapeHtml(r.unit_status) + '</span>'
            : '<span class="badge bg-success">Available</span>');
      var action = underMaintenance
        ? '<button class="btn btn-sm btn-success btn-release" data-kind="' + escapeHtml(r.unit_kind) + '" data-code="' + escapeHtml(r.unit_code) + '"><i class="ti ti-lock-open"></i> Release</button>'
        : '<button class="btn btn-sm btn-danger btn-block" data-kind="' + escapeHtml(r.unit_kind) + '" data-code="' + escapeHtml(r.unit_code) + '"><i class="ti ti-lock"></i> Block</button>';
      return '<tr>'
        + '<td><strong>' + escapeHtml(r.unit_code) + '</strong></td>'
        + '<td><span class="badge bg-light text-dark">' + escapeHtml(r.unit_kind) + '</span></td>'
        + '<td>' + statusBadge + '</td>'
        + '<td>' + escapeHtml(r.maintenance_reason || '') + '</td>'
        + '<td>' + escapeHtml(r.scheduled_start_at || '') + '</td>'
        + '<td>' + escapeHtml(r.maintenance_blocked_at || '') + '</td>'
        + '<td>' + escapeHtml(r.maintenance_expected_return || '') + '</td>'
        + '<td class="text-end">' + action + '</td>'
        + '</tr>';
    }).join('');
    // Tear down the old DataTable before swapping the rows, then re-init so
    // pagination / sorting / search apply to the freshly-loaded set.
    if (unitsDt) { unitsDt.destroy(); unitsDt = null; }
    $('#unitsBody').html(html);
    unitsDt = $('#unitsTable').DataTable({
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
      order: [[0, 'asc']],
      // Action column isn't sortable/searchable.
      columnDefs: [{ targets: -1, orderable: false, searchable: false }],
      language: { emptyTable: 'No units.', search: 'Search:' }
    });
  });
}
$('#filterKind, #filterStatus').on('change', loadUnits);
$('#refreshBtn').on('click', loadUnits);

$('#unitsBody').on('click', '.btn-block', function(){
  var $b = $(this);
  $('#b_kind').val($b.data('kind'));
  $('#b_code').val($b.data('code'));
  $('#b_summary').html('Blocking <b>' + escapeHtml($b.data('code')) + '</b> (' + escapeHtml($b.data('kind')) + ') from dispatch.');
  $('#blockForm')[0].reset();
  $('#b_kind').val($b.data('kind'));  // reset() blew them away; restore
  $('#b_code').val($b.data('code'));
  $('#blockModal').modal('show');
});
$('#unitsBody').on('click', '.btn-release', function(){
  var $b = $(this);
  $('#r_kind').val($b.data('kind'));
  $('#r_code').val($b.data('code'));
  $('#r_summary').html('Releasing <b>' + escapeHtml($b.data('code')) + '</b> (' + escapeHtml($b.data('kind')) + ') back to dispatch.');
  $('#releaseForm')[0].reset();
  $('#r_kind').val($b.data('kind'));
  $('#r_code').val($b.data('code'));
  $('#releaseModal').modal('show');
});

$('#blockForm').on('submit', function(e){
  e.preventDefault();
  var fd = new FormData(this);
  if (String(fd.get('category') || '') === 'scheduled' && !String(fd.get('scheduled_start') || '')) {
    Swal.fire({ icon: 'warning', text: 'Scheduled start is required for scheduled maintenance.' });
    return;
  }
  $.ajax({
    url: 'php/operations/block_unit.php', method:'POST', data:fd, contentType:false, processData:false, dataType:'json',
    success: function(res){
      Swal.fire({ icon: res.status === 'success' ? 'success' : 'error', text: res.message, timer: 1500, showConfirmButton: false });
      if (res.status === 'success') { $('#blockModal').modal('hide'); loadUnits(); }
    }
  });
});
$('#releaseForm').on('submit', function(e){
  e.preventDefault();
  $.post('php/operations/unblock_unit.php', $(this).serialize(), function(res){
    Swal.fire({ icon: res.status === 'success' ? 'success' : 'error', text: res.message, timer: 1500, showConfirmButton: false });
    if (res.status === 'success') { $('#releaseModal').modal('hide'); loadUnits(); }
  }, 'json');
});

loadUnits();
</script>
<?php include 'maintenance/_layout_bottom.php'; ?>
