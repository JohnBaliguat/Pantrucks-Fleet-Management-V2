// Booking Segments editor — Phase 2.
// Loads segments under a chosen booking_no, lets the dispatcher edit
// per-leg fields and cancel a segment with the foul-trip rule applied.

$(function () {
  // Lazy-load the booking_no datalist on focus + on each keystroke.
  function refreshBookingList(q) {
    $.getJSON('php/fetch/list_booking_nos.php', { q: q || '' }, function (res) {
      if (res.status !== 'success') return;
      var $dl = $('#bookingNoList').empty();
      res.rows.forEach(function (r) {
        $dl.append('<option value="' + r.booking_no + '">' + (r.costumer || '') + '</option>');
      });
    });
  }
  $('#bookingSearch').on('focus', function () { refreshBookingList(''); });
  $('#bookingSearch').on('input', function () { refreshBookingList($(this).val()); });

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function renderBookingSummary(b) {
    var port = '';
    if (b.booking_type === 'Import' || b.booking_type === 'Export') {
      var bits = [];
      if (b.vessel_name)       bits.push('<strong>Vessel:</strong> ' + escapeHtml(b.vessel_name));
      if (b.voyage_no)         bits.push('<strong>Voyage:</strong> ' + escapeHtml(b.voyage_no));
      if (b.bill_of_lading)    bits.push('<strong>BL:</strong> ' + escapeHtml(b.bill_of_lading));
      if (b.container_no_port) bits.push('<strong>Container:</strong> ' + escapeHtml(b.container_no_port));
      if (b.port_location)     bits.push('<strong>Port:</strong> ' + escapeHtml(b.port_location));
      if (b.booking_type === 'Export') {
        bits.push('<strong>Customs:</strong> ' + (b.customs_cleared == 1
          ? '<span class="badge bg-success">Cleared</span>'
          : '<span class="badge bg-warning text-dark">Pending</span>'));
      }
      port = '<div class="mt-2">' + bits.join(' &middot; ') + '</div>';
    }
    return '<strong>' + escapeHtml(b.booking_no) + '</strong> &mdash; '
         + '<span class="badge bg-primary">' + escapeHtml(b.booking_type || 'Local') + '</span> '
         + escapeHtml(b.costumer) + ' &middot; '
         + escapeHtml(b.trip_from) + ' &rarr; ' + escapeHtml(b.trip_to) + ' &middot; '
         + 'qty ' + escapeHtml(b.quantity_use) + '/' + escapeHtml(b.quantity)
         + port;
  }

  function statusBadge(status, foul) {
    if (parseInt(foul, 10) === 1) return '<span class="badge bg-danger">Foul</span>';
    var cls = 'bg-secondary';
    switch ((status || '').toLowerCase()) {
      case 'pending':   cls = 'bg-warning text-dark'; break;
      case 'assigned':  cls = 'bg-info'; break;
      case 'enroute':   cls = 'bg-primary'; break;
      case 'delivered': cls = 'bg-success'; break;
      case 'cancelled': cls = 'bg-secondary'; break;
      case 'foul':      cls = 'bg-danger'; break;
    }
    return '<span class="badge ' + cls + '">' + escapeHtml(status || 'Pending') + '</span>';
  }

  function renderSegmentRow(s, idx) {
    var client = s.segment_costumer || s.dispatch_costumer || '';
    var sched = s.scheduled_at && s.scheduled_at !== '0000-00-00 00:00:00' ? s.scheduled_at : '';
    var assignment = escapeHtml(s.d_driverName || '?')
      + ' / <span class="text-primary">' + escapeHtml(s.d_truck || '-') + '</span>'
      + ' / ' + escapeHtml(s.d_trailer || '-')
      + ' / ' + escapeHtml(s.d_genset || '-');
    var canCancel = (s.segment_status || '').toLowerCase() !== 'cancelled'
                 && (s.segment_status || '').toLowerCase() !== 'foul'
                 && parseInt(s.foul_trip, 10) !== 1;
    return '<tr data-trip-id="' + s.trip_id + '" data-d-id="' + s.d_id + '">'
      + '<td>' + (idx + 1) + '</td>'
      + '<td>' + escapeHtml(s.trip_type || ('Trip ' + (idx + 1))) + '</td>'
      + '<td>' + escapeHtml(client) + '</td>'
      + '<td>' + escapeHtml(s.trip_from || '-') + ' &rarr; ' + escapeHtml(s.trip_to || '-') + '</td>'
      + '<td>' + assignment + '</td>'
      + '<td>' + escapeHtml(sched) + '</td>'
      + '<td>' + statusBadge(s.segment_status, s.foul_trip) + '</td>'
      + '<td class="text-end">'
        + '<button class="btn btn-sm btn-primary me-1 edit-seg" type="button"><i class="ti ti-edit"></i></button>'
        + (canCancel ? '<button class="btn btn-sm btn-danger cancel-seg" type="button"><i class="ti ti-x"></i></button>' : '')
      + '</td>'
      + '</tr>';
  }

  function loadSegments() {
    var bookingNo = ($('#bookingSearch').val() || '').trim();
    if (!bookingNo) {
      Swal.fire({ icon: 'info', text: 'Pick a booking number first.' });
      return;
    }
    $.getJSON('php/fetch/get_segments.php', { booking_no: bookingNo }, function (res) {
      if (res.status !== 'success') {
        $('#bookingSummary').hide();
        $('#segmentsBody').html('<tr><td colspan="8" class="text-center text-danger">' + escapeHtml(res.message) + '</td></tr>');
        return;
      }
      $('#bookingSummaryBody').html(renderBookingSummary(res.booking));
      $('#bookingSummary').show();
      window._lastBookingSegments = res.segments;
      if (!res.segments.length) {
        $('#segmentsBody').html('<tr><td colspan="8" class="text-center text-muted">No segments yet for this booking.</td></tr>');
        return;
      }
      var html = res.segments.map(renderSegmentRow).join('');
      $('#segmentsBody').html(html);
    }).fail(function () {
      Swal.fire({ icon: 'error', text: 'Failed to load segments.' });
    });
  }

  $('#loadSegmentsBtn').on('click', loadSegments);
  $('#bookingSearch').on('change', loadSegments);

  // Open edit modal pre-populated from cached row data.
  $('#segmentsBody').on('click', '.edit-seg', function () {
    var $tr = $(this).closest('tr');
    var tripId = $tr.data('trip-id');
    var seg = (window._lastBookingSegments || []).find(function (s) { return parseInt(s.trip_id, 10) === parseInt(tripId, 10); });
    if (!seg) { Swal.fire({ icon: 'error', text: 'Segment not in cache; reload.' }); return; }
    $('#seg_trip_id').val(seg.trip_id);
    $('#seg_d_id').val(seg.d_id);
    $('#seg_costumer').val(seg.segment_costumer || seg.dispatch_costumer || '');
    $('#seg_status').val(seg.segment_status || 'Pending');
    $('#seg_from').val(seg.trip_from || '');
    $('#seg_to').val(seg.trip_to || '');
    $('#seg_truck').val(seg.d_truck || '');
    $('#seg_trailer').val(seg.d_trailer || '');
    $('#seg_genset').val(seg.d_genset || '');
    $('#seg_driver').val(seg.driver_id || '');
    var sched = seg.scheduled_at && seg.scheduled_at !== '0000-00-00 00:00:00' ? seg.scheduled_at.replace(' ', 'T').substring(0, 16) : '';
    $('#seg_scheduled').val(sched);
    $('#editSegmentModal').modal('show');
  });

  // Submit segment edits.
  $('#editSegmentForm').on('submit', function (e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.post('php/crud/update/update_segment.php', formData, function (res) {
      if (res.status === 'success') {
        $('#editSegmentModal').modal('hide');
        Swal.fire({ icon: 'success', text: res.message, timer: 1200, showConfirmButton: false });
        loadSegments();
      } else {
        Swal.fire({ icon: 'error', text: res.message || 'Update failed' });
      }
    }, 'json').fail(function () {
      Swal.fire({ icon: 'error', text: 'Network error' });
    });
  });

  // Cancel a segment — with foul-trip warning when applicable.
  $('#segmentsBody').on('click', '.cancel-seg', function () {
    var $tr = $(this).closest('tr');
    var tripId = $tr.data('trip-id');
    var seg = (window._lastBookingSegments || []).find(function (s) { return parseInt(s.trip_id, 10) === parseInt(tripId, 10); });
    var alreadyAssigned = seg && (seg.segment_status || '').toLowerCase() !== 'pending';
    var warn = alreadyAssigned
      ? 'This segment is already <strong>' + escapeHtml(seg.segment_status) + '</strong>. Cancelling now will mark it as a <span class="text-danger"><strong>foul trip</strong></span>. Trailer billing rules still apply.'
      : 'This segment is still <em>Pending</em>. Cancelling now does NOT count as a foul trip.';
    Swal.fire({
      title: 'Cancel segment?',
      html: warn + '<br><br><label class="form-label mt-2">Reason (optional)</label>'
            + '<input id="cancelReason" class="form-control" placeholder="Reason for cancellation">',
      icon: alreadyAssigned ? 'warning' : 'question',
      showCancelButton: true,
      confirmButtonText: alreadyAssigned ? 'Yes, mark as Foul Trip' : 'Yes, cancel',
      confirmButtonColor: '#d33',
    }).then(function (r) {
      if (!r.isConfirmed) return;
      var reason = $('#cancelReason').val() || '';
      $.post('php/operations/cancel_segment.php', { trip_id: tripId, reason: reason }, function (res) {
        if (res.status === 'success') {
          Swal.fire({ icon: 'success', text: res.message, timer: 1500, showConfirmButton: false });
          loadSegments();
        } else {
          Swal.fire({ icon: 'error', text: res.message || 'Cancel failed' });
        }
      }, 'json').fail(function () {
        Swal.fire({ icon: 'error', text: 'Network error' });
      });
    });
  });
});
