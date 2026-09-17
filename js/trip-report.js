const table = new DataTable('#table-data', {
  processing: true,
  serverSide: true,
  ajax: {
    url: 'table-fetch/trip-table.php',
    type: 'POST',
    data: function(d) {
      d.fromDate = $('#fromDate').val();
      d.toDate = $('#toDate').val();
      d.customer = $('#customer').val() ?? '';
    }
  },
  responsive: true,
});

// Reload table
$('#submit').on('click', function(e) {
  e.preventDefault();
  table.ajax.reload();
});

// -------------------------------------------------------------------------
// Edit + Delete handlers for the Trip Report Action column.
// -------------------------------------------------------------------------

function treSetTripFields(prefix, trip) {
  // Populate one trip block; if `trip` is null, clear the inputs and set id=0.
  const id = trip && trip.trip_id ? trip.trip_id : 0;
  $('#tre_' + prefix + '_id').val(id);
  $('#tre_' + prefix + '_from').val(trip ? (trip.trip_from || '') : '');
  $('#tre_' + prefix + '_to').val(trip ? (trip.trip_to || '') : '');
  $('#tre_' + prefix + '_container').val(trip ? (trip.trip_container || '') : '');
  $('#tre_' + prefix + '_containerstat').val(trip ? (trip.trip_containerstat || '') : '');
  $('#tre_' + prefix + '_segment').val(trip ? (trip.trip_haulingsegment || '') : '');
  $('#tre_' + prefix + '_type').val(trip ? (trip.trip_haulingtype || '') : '');
  $('#tre_' + prefix + '_status').val(trip ? (trip.trip_status || '') : '');

  // Disable trip 2 inputs (other than the id) when no trip 2 exists.
  if (prefix === 'trip2') {
    const disable = !trip;
    $('#tre_trip2_block input').not('#tre_trip2_id').prop('disabled', disable);
  }
}

function treToDatetimeLocal(value) {
  if (!value) return '';
  // PG timestamps come back as "YYYY-MM-DD HH:MM:SS"; datetime-local needs T.
  return String(value).replace(' ', 'T').slice(0, 16);
}

$(document).on('click', '.trip-report-edit', function () {
  const dId = $(this).data('d-id');
  $.getJSON('php/fetch/get_trip_report_row.php', { d_id: dId }, function (res) {
    if (!res || res.status !== 'success') {
      Swal.fire({ icon: 'error', text: (res && res.message) || 'Failed to load row.' });
      return;
    }
    $('#tre_d_id').val(res.dispatch.d_id);
    $('#tre_d_datetime').val(treToDatetimeLocal(res.dispatch.d_datetime));
    $('#tre_d_dispatchhub').val(res.dispatch.d_dispatchhub || '');
    treSetTripFields('trip1', res.trip1);
    treSetTripFields('trip2', res.trip2);
    $('#tripReportEditModal').modal('show');
  }).fail(function () {
    Swal.fire({ icon: 'error', text: 'Network error while loading row.' });
  });
});

$('#tre_save_btn').on('click', function () {
  const $btn = $(this);
  if ($btn.prop('disabled')) return;
  $btn.prop('disabled', true);

  const payload = {
    d_id:           $('#tre_d_id').val(),
    d_datetime:     $('#tre_d_datetime').val(),
    d_dispatchhub:  $('#tre_d_dispatchhub').val(),
    trip1_id:           $('#tre_trip1_id').val(),
    trip1_from:         $('#tre_trip1_from').val(),
    trip1_to:           $('#tre_trip1_to').val(),
    trip1_container:    $('#tre_trip1_container').val(),
    trip1_containerstat:$('#tre_trip1_containerstat').val(),
    trip1_segment:      $('#tre_trip1_segment').val(),
    trip1_type:         $('#tre_trip1_type').val(),
    trip1_status:       $('#tre_trip1_status').val(),
    trip2_id:           $('#tre_trip2_id').val(),
    trip2_from:         $('#tre_trip2_from').val(),
    trip2_to:           $('#tre_trip2_to').val(),
    trip2_container:    $('#tre_trip2_container').val(),
    trip2_containerstat:$('#tre_trip2_containerstat').val(),
    trip2_segment:      $('#tre_trip2_segment').val(),
    trip2_type:         $('#tre_trip2_type').val(),
    trip2_status:       $('#tre_trip2_status').val(),
  };

  $.post('php/operations/update_trip_report.php', payload, function (res) {
    $btn.prop('disabled', false);
    if (res && res.status === 'success') {
      $('#tripReportEditModal').modal('hide');
      Swal.fire({ icon: 'success', text: res.message, timer: 1400, showConfirmButton: false });
      table.ajax.reload(null, false);
    } else {
      Swal.fire({ icon: 'error', text: (res && res.message) || 'Update failed.' });
    }
  }, 'json').fail(function (xhr) {
    $btn.prop('disabled', false);
    let msg = 'Network error.';
    try { msg = (JSON.parse(xhr.responseText) || {}).message || msg; } catch (e) {}
    Swal.fire({ icon: 'error', text: msg });
  });
});

$(document).on('click', '.trip-report-delete', function () {
  const dId = $(this).data('d-id');
  Swal.fire({
    title: 'Delete this trip report entry?',
    text: 'For Dispatchers this submits a deletion request that needs Admin / Dispatch Admin approval. Admins delete immediately.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Yes, delete'
  }).then(function (r) {
    if (!r.isConfirmed) return;
    $.post('php/crud/delete/delete_trip_report.php', { d_id: dId }, function (res) {
      if (!res) {
        Swal.fire({ icon: 'error', text: 'Empty response.' });
        return;
      }
      if (res.status === 'success') {
        Swal.fire({ icon: 'success', text: res.message, timer: 1300, showConfirmButton: false });
        table.ajax.reload(null, false);
      } else if (res.status === 'pending') {
        // Dispatcher path — request recorded, awaiting approval.
        Swal.fire({ icon: 'info', title: 'Request submitted', text: res.message });
        table.ajax.reload(null, false);
      } else {
        Swal.fire({ icon: 'error', text: res.message || 'Delete failed.' });
      }
    }, 'json').fail(function (xhr) {
      let msg = 'Network error.';
      try { msg = (JSON.parse(xhr.responseText) || {}).message || msg; } catch (e) {}
      Swal.fire({ icon: 'error', text: msg });
    });
  });
});

// Approve a pending Dispatcher delete request — Admin / Dispatch Admin only.
// Reuses the delete endpoint, which marks the request approved as a side effect.
$(document).on('click', '.trip-report-approve-delete', function () {
  const dId = $(this).data('d-id');
  Swal.fire({
    title: 'Approve deletion?',
    text: 'This will permanently delete the dispatch and its trip rows.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#198754',
    confirmButtonText: 'Yes, approve & delete'
  }).then(function (r) {
    if (!r.isConfirmed) return;
    $.post('php/crud/delete/delete_trip_report.php', { d_id: dId }, function (res) {
      if (res && res.status === 'success') {
        Swal.fire({ icon: 'success', text: 'Deletion approved and applied.', timer: 1300, showConfirmButton: false });
        table.ajax.reload(null, false);
      } else {
        Swal.fire({ icon: 'error', text: (res && res.message) || 'Approval failed.' });
      }
    }, 'json').fail(function (xhr) {
      let msg = 'Network error.';
      try { msg = (JSON.parse(xhr.responseText) || {}).message || msg; } catch (e) {}
      Swal.fire({ icon: 'error', text: msg });
    });
  });
});

// Reject a pending Dispatcher delete request.
$(document).on('click', '.trip-report-reject-delete', function () {
  const dId = $(this).data('d-id');
  Swal.fire({
    title: 'Reject deletion request?',
    input: 'text',
    inputLabel: 'Reason (optional, shown in the audit trail)',
    inputPlaceholder: 'e.g. data still under review',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Reject request',
    confirmButtonColor: '#6c757d',
  }).then(function (r) {
    if (!r.isConfirmed) return;
    $.post('php/operations/reject_trip_delete_request.php',
      { d_id: dId, reason: r.value || '' },
      function (res) {
        if (res && res.status === 'success') {
          Swal.fire({ icon: 'success', text: res.message, timer: 1300, showConfirmButton: false });
          table.ajax.reload(null, false);
        } else {
          Swal.fire({ icon: 'error', text: (res && res.message) || 'Reject failed.' });
        }
      }, 'json').fail(function (xhr) {
        let msg = 'Network error.';
        try { msg = (JSON.parse(xhr.responseText) || {}).message || msg; } catch (e) {}
        Swal.fire({ icon: 'error', text: msg });
      });
  });
});
