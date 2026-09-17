$(document).ready(function() {
      $("#Mnav").attr({
					"class" : "nav-link dropdown-toggle active"
				});
      $('#table-data').DataTable();
      $("#AddModal").click(function() {
        $("#addmodal").modal("show");
      });

      $("#addTruck").click(function(e) {
        e.preventDefault();

        let unit_name = $("#unit_name").val();
        let unit_std = $("#unit_std").val();

        if (unit_name === "" || unit_std === "") {
          Swal.fire({
            text: 'Please fill in all required fields',
            icon: 'info'
          });
          return;
        }

        Swal.fire({
          title: 'Confirm Add Unit',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            let formData = new FormData($('#addForm')[0]);
            $.ajax({
              url: 'php/crud/add/addtruck.php',
              type: 'POST',
              data: formData,
              contentType: false,
              cache: false,
              processData: false,
              success: function(data) {
                Swal.fire({
                  text: data,
                  icon: 'success',
                  showConfirmButton: false,
                  timer: 1200
                });
                $('#addForm')[0].reset();
                $("#addmodal").modal("hide");
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


      // AJAX call for updating truck unit
    $('#UpdateTruck').click(function (e) {
    e.preventDefault();

    let id = $('#unit_id1').val();
    let formData = new FormData($('#editForm')[0]);

    Swal.fire({
        title: 'Confirm Update Unit',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Confirm'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'php/crud/update/updatetruck.php',
                type: 'POST',
                data: formData,
                contentType: false,
                cache: false,
                processData: false,
                success: function (data) {
                    Swal.fire({
                        text: data,
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 1200
                    });
                    $('#editModal').modal('hide');
                    setTimeout(() => {
                        location.reload();
                    }, 1300);
                },
                error: function (xhr, status, error) {
                    Swal.fire({
                        text: 'Error: ' + error,
                        icon: 'error'
                    });
                }
            });
        }
    });
});

$('#shopUnit').click(function (e) {
  e.preventDefault();
  let unit_id = $('#unit_id1').val();
  let formData = new FormData($('#editForm')[0]);

  Swal.fire({
    title: 'Confirm Shop Unit',
    text: 'Are you sure you want to move this unit to Shop?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Confirm'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: 'php/crud/update/updatetruck1.php',
        type: 'POST',
        data: formData,
        contentType: false,
        cache: false,
        processData: false,
        success: function (data) {
          Swal.fire({
            text: data,
            icon: 'success',
            showConfirmButton: false,
            timer: 1200
          });
          $('#editModal').modal('hide');
          setTimeout(() => {
            location.reload();
          }, 1300);
        },
        error: function (xhr, status, error) {
          Swal.fire({
            text: 'Error: ' + error,
            icon: 'error'
          });
        }
      });
    }
  });
});

$('#disposeUnit').click(function (e) {
  e.preventDefault();
  let unit_id = $('#unit_id1').val();
  let formData = new FormData($('#editForm')[0]);

  Swal.fire({
    title: 'Confirm Dispose Unit',
    text: 'Are you sure you want to dispose this unit?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Confirm'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: 'php/crud/update/updatetruck2.php',
        type: 'POST',
        data: formData,
        contentType: false,
        cache: false,
        processData: false,
        success: function (data) {
          Swal.fire({
            text: data,
            icon: 'success',
            showConfirmButton: false,
            timer: 1200
          });
          $('#editModal').modal('hide');
          setTimeout(() => {
            location.reload();
          }, 1300);
        },
        error: function (xhr, status, error) {
          Swal.fire({
            text: 'Error: ' + error,
            icon: 'error'
          });
        }
      });
    }
  });
});

$('#FixUnit').click(function (e) {
  e.preventDefault();
  let unit_id = $('#unit_id1').val();
  let formData = new FormData($('#editForm')[0]);

  Swal.fire({
    title: 'Confirm Fix Unit',
    text: 'Good units ready for use?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Confirm'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: 'php/crud/update/updatetruck3.php',
        type: 'POST',
        data: formData,
        contentType: false,
        cache: false,
        processData: false,
        success: function (data) {
          Swal.fire({
            text: data,
            icon: 'success',
            showConfirmButton: false,
            timer: 1200
          });
          $('#editModal').modal('hide');
          setTimeout(() => {
            location.reload();
          }, 1300);
        },
        error: function (xhr, status, error) {
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

    function openEditModal(unit_id, unit_name, unit_std, unit_plate, unit_address) {
    $("#unit_id1").val(unit_id);
    $("#unit_name_update").val(unit_name);
    $("#unit_std_update").val(unit_std);
    $("#unit_plate_update").val(unit_plate);
    $("#unit_location_update").val(unit_address);

    $("#editModal").modal("show");
}

function deleteUnit(unit_id) {
            var unit_Id = unit_id;

            var form_data = {
                unit_Id: unit_Id

            };
            Swal.fire({
                title: 'Confirm Remove Unit',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "php/crud/delete/deleteunit.php",
                        type: "POST",
                        data: form_data,
                        dataType: "json",
                        success: function (response) {
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
