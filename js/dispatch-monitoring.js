function updateCounts() {
      fetch("php/fetch/get_counts.php")
        .then(res => res.json())
        .then(data => {
          for (let cust in data) {
            let badge = document.getElementById("count-" + cust);
            if (badge) {
              badge.textContent = data[cust];
            }
          }
        })
        .catch(err => console.error(err));
    }

    // Initial load
    updateCounts();

    // Refresh periodically without hammering the server.
    setInterval(updateCounts, 10000);



    let selectedCustomer = '';

    const table = new DataTable('#table-data', {
      processing: true,
      serverSide: true,
      ajax: {
        url: 'table-fetch/monitoring-table1.php',
        type: 'POST',
        data: function(d) {
          d.customer = selectedCustomer;
        }
      },
      responsive: true,
      columnDefs: [{
          targets: [0, 2, 3, 4, 5],
          className: 'px-0'
        },
        {
          targets: [2, 3, 4, 5],
          className: 'text-center'
        }
      ]
    });

    // Handle customer click filter
    $(document).on('click', '.customer-filter', function() {
      selectedCustomer = $(this).data('customer');
      table.ajax.reload(); // refresh table with filter
    });

    // ============================================================
    // Drill-down: clicking the + on column 0 toggles a child row
    // listing every trip under that dispatch.
    // ============================================================
    function _escDM(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
    function _statusBadgeDM(s){
      var x = (s || '').toLowerCase();
      var cls = x === 'done' ? 'bg-success'
              : x === 'active' ? 'bg-primary'
              : x === 'foul' ? 'bg-danger'
              : x === 'cancelled' ? 'bg-secondary'
              : 'bg-light text-dark';
      return '<span class="badge ' + cls + '">' + _escDM(s || '—') + '</span>';
    }
    function renderTripsChild(dispatch, trips) {
      if (!trips || !trips.length) {
        return '<div class="p-3 text-muted">No trips on this dispatch yet.</div>';
      }
      var rows = trips.map(function (t) {
        var foul = parseInt(t.foul_trip, 10) === 1
          ? ' <span class="badge bg-danger">Foul</span>' : '';
        var seg = t.segment_costumer
          ? '<span class="badge bg-info">' + _escDM(t.segment_costumer) + '</span> '
          : '';
        return '<tr>'
          + '<td><strong>' + _escDM(t.trip_type || ('Trip #' + t.trip_id)) + '</strong>' + foul + '</td>'
          + '<td>' + _escDM(t.trip_from || '—') + ' &rarr; ' + _escDM(t.trip_to || '—') + '</td>'
          + '<td>' + _escDM(t.trip_haulingSegment || '—') + '<br><small class="text-muted">' + _escDM(t.trip_haulingType || '') + '</small></td>'
          + '<td>' + _escDM(t.trip_container || '—') + '<br><small class="text-muted">' + _escDM(t.trip_containerStat || '') + '</small></td>'
          + '<td>' + seg + _statusBadgeDM(t.segment_status || t.trip_status) + '</td>'
          + '<td>' + _escDM(t.scheduled_at || t.required_date || '—') + '</td>'
          + '<td>' + _escDM(t.deliver_dateTime && t.deliver_dateTime !== '0000-00-00 00:00:00' ? t.deliver_dateTime : '—') + '</td>'
          + '</tr>';
      }).join('');
      var header = ''
        + '<div class="d-flex flex-wrap gap-3 align-items-center mb-2">'
        +   '<strong>Booking ' + _escDM(dispatch.booking_no || '—') + '</strong>'
        +   '<span class="badge bg-secondary">' + _escDM(dispatch.booking_type || 'Local') + '</span>'
        +   '<span class="badge bg-light text-dark">' + _escDM(dispatch.workflow_stage || '—') + '</span>'
        + (dispatch.billing_amount && parseFloat(dispatch.billing_amount) > 0
            ? '<span class="badge bg-success">' + _escDM(dispatch.billing_currency || 'PHP') + ' ' + parseFloat(dispatch.billing_amount).toFixed(2) + '</span>' : '')
        + '</div>';
      return '<div class="p-3 bg-light">'
        + header
        + '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">'
        +   '<thead class="table-light"><tr>'
        +     '<th>Trip</th><th>Route</th><th>Hauling / Type</th><th>Container / Status</th>'
        +     '<th>Status</th><th>Scheduled / Required</th><th>Delivered</th>'
        +   '</tr></thead><tbody>' + rows + '</tbody></table></div></div>';
    }

    $('#table-data tbody').on('click', '.drill-toggle', function (e) {
      e.stopPropagation();
      var $btn = $(this);
      var dId = $btn.data('d-id');
      var $tr = $btn.closest('tr');
      var row = table.row($tr);
      if (row.child && row.child.isShown && row.child.isShown()) {
        row.child.hide();
        $tr.removeClass('shown');
        $btn.html('<i class="ti ti-plus"></i>').removeClass('btn-primary').addClass('btn-outline-primary');
        return;
      }
      $btn.html('<i class="ti ti-loader-2"></i>');
      $.getJSON('php/fetch/dispatch_trip_summary.php', { d_id: dId }, function (res) {
        if (res.status !== 'success') {
          row.child('<div class="p-3 text-danger">' + _escDM(res.message || 'Failed to load trips') + '</div>').show();
        } else {
          row.child(renderTripsChild(res.dispatch, res.trips)).show();
        }
        $tr.addClass('shown');
        $btn.html('<i class="ti ti-minus"></i>').removeClass('btn-outline-primary').addClass('btn-primary');
      }).fail(function () {
        row.child('<div class="p-3 text-danger">Network error.</div>').show();
        $btn.html('<i class="ti ti-plus"></i>');
      });
    });

    $(document).ready(function() {
      $("#addRecord").click(function(e) {
        e.preventDefault();

        let driver = $("#drivers_name").val();
        let truck = $("#truck").val();
        let trailer = $("#trailer").val();
        let haulingSegment = $("#hauling_segment").val();


        if (!driver || !truck || !trailer || !haulingSegment) {
          Swal.fire({
            text: 'Please fill in all required fields',
            icon: 'info'
          });
          return;
        }

        Swal.fire({
          title: 'Confirm Dispatch',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            let formData = new FormData($('#addForm')[0]);
            $.ajax({
              url: 'php/crud/add/addRecord.php',
              type: 'POST',
              data: formData,
              contentType: false,
              cache: false,
              processData: false,
              success: function(response) {
                try {
                  const json = JSON.parse(response);
                  if (json.status === 'success') {
                    Swal.fire({
                      text: json.message,
                      icon: 'success',
                      showConfirmButton: false,
                      timer: 1200
                    });

                    $('#addForm')[0].reset();

                    setTimeout(() => {
                      // Open print view in new tab
                      window.open("index.php?route=print&id=" + json.insert_id, "_blank");
                      location.reload();
                    }, 1300);
                  } else {
                    Swal.fire({
                      text: json.message || "Error occurred",
                      icon: 'error'
                    });
                  }
                } catch (err) {
                  Swal.fire({
                    text: 'Unexpected response: ' + response,
                    icon: 'error'
                  });
                }
              },
              error: function(xhr, status, error) {
                Swal.fire({
                  text: 'Error: ' + error,
                  icon: 'error'
                });
              }
            });
          }
        });
      });

      $('#editRecord').click(function(e) {
        e.preventDefault(); // Prevent default form submit

        var formData = new FormData($('#editForm')[0]);

        Swal.fire({
          title: 'Confirm Update Dispatch',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: 'php/crud/update/updateRecord.php',
              type: 'POST',
              data: formData,
              contentType: false,
              processData: false,
              success: function(response) {
                Swal.fire({
                  text: response,
                  icon: 'success',
                  showConfirmButton: false,
                  timer: 1200
                });
                $('#editModal').modal('hide');
                setTimeout(() => {
                  location.reload();
                }, 1300);
              },
              error: function(xhr, status, error) {
                Swal.fire({
                  text: 'Error: ' + error,
                  icon: 'error'
                });
              }
            });
          }
        });
      });
      
    });

    function editDispatch(d_id) {
      $.ajax({
        url: 'php/fetch/get_dispatch.php', type: 'POST', data: { d_id: d_id }, dataType: 'json',
        success: function(response) {
          if (!response.success) { alert('Failed to fetch dispatch data.'); return; }
          var dispatch = response.dispatch || {};
          var trips    = response.trips || [];

          $('#edit_record_id').val(dispatch.d_id);
          $('#edit_drivers_name').val(dispatch.d_driverName);
          $('#edit_truck').val(dispatch.d_truck);
          $('#edit_trailer').val(dispatch.d_trailer);
          $('#edit_genset').val(dispatch.d_genset);
          $('#edit_tr').val(dispatch.d_tripReceipt);
          $('#edit_ecs').val(dispatch.d_ecs);
          $('#shippingSN1').val(dispatch.booking_sn);

          // ----- Build a fieldset per trip into #dynamicTripsBox -----
          var $box = $('#dynamicTripsBox');
          if ($box.length === 0) {
            // Page hasn't been migrated yet — fall back to legacy
            // Trip 1 / Trip 2 inputs.
            var t1 = trips[0] || null;
            var t2 = trips[1] || null;
            if (t1) {
              $('#edit_container_no').val(t1.trip_container);
              $('#edit_container_status').val(t1.trip_containerStat);
              $('#edit_hauling_segment').val(t1.trip_haulingSegment);
              $('#edit_destination_from').val(t1.trip_from);
              $('#edit_destination_to').val(t1.trip_to);
            }
            if (t2) {
              $('#edit_container_no2').val(t2.trip_container);
              $('#edit_container_status2').val(t2.trip_containerStat);
              $('#edit_hauling_segment2').val(t2.trip_haulingSegment);
              $('#edit_destination_from1').val(t2.trip_from);
              $('#edit_destination_to1').val(t2.trip_to);
            }
            $('#editModal').modal('show');
            return;
          }

          $box.empty();
          if (!trips.length) {
            $box.html('<div class="alert alert-secondary mb-0">No trips on this dispatch yet.</div>');
          } else {
            trips.forEach(function (t, i) {
              var foulBadge = parseInt(t.foul_trip, 10) === 1
                ? ' <span class="badge bg-danger">Foul Trip</span>' : '';
              var tid = parseInt(t.trip_id, 10);
              var prefix = 'trip[' + tid + ']';
              var segmentStatusOptions = ['', 'Pending', 'Assigned', 'EnRoute', 'Delivered', 'Cancelled', 'Foul']
                .map(function (s) {
                  return '<option value="' + s + '"' + ((t.segment_status || '') === s ? ' selected' : '') + '>' + (s || '(unset)') + '</option>';
                }).join('');
              var card = ''
                + '<div class="card mb-3 border-primary-subtle"><div class="card-body">'
                +   '<div class="d-flex align-items-center mb-2">'
                +     '<h6 class="card-title mb-0">'
                +       (t.trip_type || ('Trip #' + (i + 1))) + foulBadge
                +     '</h6>'
                +     '<span class="ms-auto small text-muted">trip_id: ' + tid + '</span>'
                +   '</div>'
                +   '<div class="row g-2">'
                +     '<div class="col-md-6"><label class="form-label small">Container No</label>'
                +       '<input type="text" class="form-control form-control-sm" name="' + prefix + '[container]" value="' + (t.trip_container ? _escDM(t.trip_container) : '') + '"></div>'
                +     '<div class="col-md-3"><label class="form-label small">Container Status</label>'
                +       '<input list="containerStat" class="form-control form-control-sm" name="' + prefix + '[status]" value="' + (t.trip_containerStat ? _escDM(t.trip_containerStat) : '') + '"></div>'
                +     '<div class="col-md-3"><label class="form-label small">Per-leg client</label>'
                +       '<input type="text" class="form-control form-control-sm" name="' + prefix + '[seg_costumer]" placeholder="(blank = use dispatch customer)" value="' + (t.segment_costumer ? _escDM(t.segment_costumer) : '') + '"></div>'
                +     '<div class="col-md-12"><label class="form-label small">Hauling Segment</label>'
                +       '<input list="datalistOptions_hauling_segment" class="form-control form-control-sm" name="' + prefix + '[segment]" value="' + (t.trip_haulingSegment ? _escDM(t.trip_haulingSegment) : '') + '"></div>'
                +     '<div class="col-md-6"><label class="form-label small">From</label>'
                +       '<input list="datalistOptions_destination_from" class="form-control form-control-sm" name="' + prefix + '[from]" value="' + (t.trip_from ? _escDM(t.trip_from) : '') + '"></div>'
                +     '<div class="col-md-6"><label class="form-label small">To</label>'
                +       '<input list="datalistOptions_destination_to" class="form-control form-control-sm" name="' + prefix + '[to]" value="' + (t.trip_to ? _escDM(t.trip_to) : '') + '"></div>'
                +     '<div class="col-md-3"><label class="form-label small">Segment status</label>'
                +       '<select class="form-select form-select-sm" name="' + prefix + '[seg_status]">'
                +         segmentStatusOptions
                +       '</select></div>'
                +     '<div class="col-md-9"><div class="small text-muted mt-3">Legacy trip_status: <strong>' + _escDM(t.trip_status || '—') + '</strong></div></div>'
                +   '</div>'
                + '</div></div>';
              $box.append(card);
            });
          }
          $('#editModal').modal('show');
        },
        error: function(xhr, status, error) {
          console.error('AJAX Error:', error);
          alert('An error occurred while fetching data.');
        }
      });
    }

    function deleteDispatch(d_id) {
      var d_Id = d_id;

      var form_data = {
        d_Id: d_Id

      };
      Swal.fire({
        title: 'Confirm Remove Record',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Confirm'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: "php/crud/delete/deleteRecord.php",
            type: "POST",
            data: form_data,
            dataType: "json",
            success: function(response) {
              if (response['valid'] == false) {
                Swal.fire({
                  text: response['msg'],
                  icon: 'warning'
                });
              } else {
                Swal.fire({
                  text: response['msg'],
                  icon: 'success',
                  showConfirmButton: false,
                  timer: 1200
                });
                setTimeout(() => {
                  location.reload();
                }, 1300);
              }
            }

          });
        }
      });
    }

    function markDone(d_id) {
      Swal.fire({
        title: 'Are you sure?',
        text: "This dispatch and trips will be marked as Done.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, mark as Done'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: 'php/crud/update/update_status_booking.php',
            type: 'POST',
            data: {
              d_id: d_id
            },
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                Swal.fire(
                  'Updated!',
                  'The dispatch and trips are marked as Done.',
                  'success'
                ).then(() => {
                  $('#table-data').DataTable().ajax.reload(); // reload datatable
                  location.reload();
                });
              } else {
                Swal.fire('Error!', 'Failed to update status.', 'error');
              }
            },
            error: function() {
              Swal.fire('Error!', 'AJAX request failed.', 'error');
            }
          });
        }
      });
    }


   
