function updateCounts() {
      fetch("?ajax=1") // same file, just with ajax=1
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

    // Refresh every second
    setInterval(updateCounts, 20000);



    let selectedCustomer = '';

    const table = new DataTable('#table-data', {
      processing: true,
      serverSide: true,
      ajax: {
        url: 'table-fetch/monitoring-table.php',
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
      $('#printRecord').click(function(e) {
        e.preventDefault();
        var id = $("#edit_record_id").val();
        setTimeout(() => {
          window.open("index.php?route=print&id=" + id, "_blank");
          location.reload();
        }, 1300);

      });
    });

    function editDispatch(d_id) {
      $.ajax({
        url: 'php/fetch/get_dispatch.php',
        type: 'POST',
        data: {
          d_id: d_id
        },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            const dispatch = response.dispatch;
            const trip1 = response.trip1;
            const trip2 = response.trip2;

            $('#edit_record_id').val(dispatch.d_id);
            $('#edit_drivers_name').val(dispatch.d_driverName);
            $('#edit_truck').val(dispatch.d_truck);
            $('#edit_trailer').val(dispatch.d_trailer);
            $('#edit_genset').val(dispatch.d_genset);
            $('#edit_tr').val(dispatch.d_tripReceipt);
            $('#edit_ecs').val(dispatch.d_ecs);

            // Trip 1
            if (trip1) {
              $('#edit_container_no').val(trip1.trip_container);
              $('#edit_container_status').val(trip1.trip_containerStat);
              $('#edit_hauling_segment').val(trip1.trip_haulingSegment);
              $('#edit_destination_from').val(trip1.trip_from);
              $('#edit_destination_to').val(trip1.trip_to);
            }

            // Trip 2
            if (trip2) {
              $('#edit_container_no2').val(trip2.trip_container);
              $('#edit_container_status2').val(trip2.trip_containerStat);
              $('#edit_hauling_segment2').val(trip2.trip_haulingSegment);
              $('#edit_destination_from1').val(trip2.trip_from);
              $('#edit_destination_to1').val(trip2.trip_to);
            }

            $('#editModal').modal('show');
          } else {
            alert('Failed to fetch dispatch data.');
          }
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
