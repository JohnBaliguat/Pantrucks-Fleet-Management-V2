// Phase 4 — Dispatcher / Admin incidents page.
$(function () {
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function severityBadge(s) {
    var cls = s === 'high' ? 'bg-danger' : (s === 'med' ? 'bg-warning text-dark' : 'bg-secondary');
    return '<span class="badge ' + cls + '">' + escapeHtml(s || '?') + '</span>';
  }

  function statusBadge(s) {
    var cls = s === 'open' ? 'bg-warning text-dark'
            : s === 'acknowledged' ? 'bg-info'
            : s === 'resolved' ? 'bg-success' : 'bg-secondary';
    return '<span class="badge ' + cls + '">' + escapeHtml(s || '?') + '</span>';
  }

  function loadIncidents() {
    var status = $('#filterStatus').val();
    var sev    = $('#filterSeverity').val();
    $.getJSON('php/fetch/incident_list.php', { source: 'all', limit: 100, status: status, severity: sev }, function (res) {
      if (res.status !== 'success') return;
      window._incidentsCache = res.rows;
      if (!res.rows.length) {
        $('#incBody').html('<tr><td colspan="9" class="text-center text-muted">No incidents match this filter.</td></tr>');
        return;
      }
      var html = res.rows.map(function (r) {
        var actions = '';
        if (r.status === 'open') {
          actions += '<button class="btn btn-sm btn-info me-1 inc-ack">Acknowledge</button>';
        }
        if (r.status !== 'resolved') {
          actions += '<button class="btn btn-sm btn-warning me-1 inc-reassign" title="Re-assign dispatch">Re-assign</button>';
          actions += '<button class="btn btn-sm btn-success inc-resolve">Resolve</button>';
        } else {
          actions = '<span class="text-muted small">Closed ' + escapeHtml(r.resolved_at || '') + '</span>';
        }
        var photo = r.photo_path
          ? ' <a href="' + escapeHtml(r.photo_path) + '" target="_blank" title="Open photo"><i class="ti ti-photo"></i></a>'
          : '';
        var truckBooking = escapeHtml(r.truck_plate || '-')
          + (r.booking_no ? ' <span class="text-muted">/ ' + escapeHtml(r.booking_no) + '</span>' : '');
        var reassignNote = r.reassigned_d_id
          ? '<div class="small text-muted">Re-assigned &rarr; dispatch #' + r.reassigned_d_id + '</div>'
          : '';
        return '<tr data-inc-id="' + r.inc_id + '" data-d-id="' + (r.d_id || '') + '">'
          + '<td>' + r.inc_id + '</td>'
          + '<td>' + escapeHtml(r.reported_at) + '</td>'
          + '<td>' + escapeHtml(r.incident_type) + photo + '</td>'
          + '<td>' + severityBadge(r.severity) + '</td>'
          + '<td>' + truckBooking + '</td>'
          + '<td>' + escapeHtml(r.driver_id || '-') + '</td>'
          + '<td>' + escapeHtml(r.description) + reassignNote + '</td>'
          + '<td>' + statusBadge(r.status) + '</td>'
          + '<td class="text-end">' + actions + '</td>'
          + '</tr>';
      }).join('');
      $('#incBody').html(html);
    });
  }

  $('#filterStatus, #filterSeverity').on('change', loadIncidents);
  $('#refreshIncidents').on('click', loadIncidents);

  $('#incBody').on('click', '.inc-ack', function () {
    var incId = $(this).closest('tr').data('inc-id');
    $.post('php/operations/incident_acknowledge.php', { inc_id: incId }, function (res) {
      Swal.fire({ icon: res.status === 'success' ? 'success' : 'error', text: res.message, timer: 1200, showConfirmButton: false });
      loadIncidents();
    }, 'json');
  });

  $('#incBody').on('click', '.inc-resolve', function () {
    var incId = $(this).closest('tr').data('inc-id');
    Swal.fire({
      title: 'Mark as resolved?',
      input: 'text',
      inputPlaceholder: 'Resolution note (optional)',
      showCancelButton: true,
      confirmButtonText: 'Resolve',
      confirmButtonColor: '#28a745',
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.post('php/operations/incident_resolve.php', { inc_id: incId, note: r.value || '' }, function (res) {
        Swal.fire({ icon: res.status === 'success' ? 'success' : 'error', text: res.message, timer: 1500, showConfirmButton: false });
        loadIncidents();
      }, 'json');
    });
  });

  $('#incBody').on('click', '.inc-reassign', function () {
    var $tr = $(this).closest('tr');
    var incId = $tr.data('inc-id');
    var dId = $tr.data('d-id');
    var inc = (window._incidentsCache || []).find(function (x) { return parseInt(x.inc_id, 10) === parseInt(incId, 10); });
    $('#ra_inc_id').val(incId);
    $('#ra_d_id').val(dId || '');
    $('#ra_summary').html('Incident <b>#' + incId + '</b> on dispatch <b>#' + (dId || '?') + '</b>'
      + (inc ? ' — booking <b>' + escapeHtml(inc.booking_no || '?') + '</b> (truck ' + escapeHtml(inc.truck_plate || '-') + ')' : ''));
    $('#reassignModal').modal('show');
  });

  $('#reassignForm').on('submit', function (e) {
    e.preventDefault();
    var data = $(this).serialize();
    $.post('php/operations/incident_reassign.php', data, function (res) {
      if (res.status === 'success') {
        $('#reassignModal').modal('hide');
        Swal.fire({ icon: 'success', text: res.message, timer: 1800, showConfirmButton: false });
        loadIncidents();
      } else {
        Swal.fire({ icon: 'error', text: res.message || 'Reassign failed' });
      }
    }, 'json').fail(function () {
      Swal.fire({ icon: 'error', text: 'Network error' });
    });
  });

  loadIncidents();
});
