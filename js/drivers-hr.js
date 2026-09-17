$(document).ready(function() {
        $("#Mnav").attr({
          "class": "nav-link dropdown-toggle active"
        });
        $('#table-data').DataTable();
        $("#AddModal").click(function() {
          $("#addmodal").modal("show");
        });

        $("#addDriver").click(function(e) {
          e.preventDefault();
          var driver_rfid = $("#driver_rfid")
          var driver_IdNumber = $("#driver_IdNumber").val();
          var driver_fname = $("#driver_fname").val();
          var driver_mname = $("#driver_mname").val();
          var driver_lname = $("#driver_lname").val();
          var driver_assignUnit = $("#driver_assignUnit").val();
          var driver_assignSegment = $("#driver_assignSegment").val();

          if (driver_IdNumber == "" || driver_fname == "" || driver_lname == "" || driver_assignUnit == "" || driver_assignSegment == "") {
            Swal.fire({
              text: 'Please fill in all required fields',
              icon: 'info'
            });
            return;
          }
          Swal.fire({
            title: 'Confirm Register Driver',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Confirm'
          }).then((result) => {
            if (result.isConfirmed) {
              var formData = new FormData($('#addForm')[0]);
              $.ajax({
                url: 'php/crud/add/add-driver.php',
                type: 'POST',
                data: formData,
                contentType: false,
                cache: false,
                processData: false,
                success: function(data) {
                  var message = (data || '').toString().trim();
                  var isError = /^error:/i.test(message) || /^failed/i.test(message) || /^please fill/i.test(message);
                  Swal.fire({
                    text: message || 'Driver added successfully!',
                    icon: isError ? 'error' : 'success',
                    showConfirmButton: false,
                    timer: isError ? 1800 : 1200
                  });
                  if (isError) {
                    return;
                  }
                  $('#addForm')[0].reset();
                  $("#addmodal").modal("hide");
                  setTimeout(() => {
                    location.reload();
                  }, 1300);
                },
                error: function(xhr, status, error) {
                  var message = (xhr.responseText || error || 'Request failed').toString().trim();
                  Swal.fire({
                    text: message,
                    icon: 'error'
                  });
                }
              });
            }
          });
        });
        $('#UpdateDriver').click(function() {
          var id = $('#driver_id1').val();
          var formData = new FormData($('#editForm')[0]);

          Swal.fire({
            title: 'Confirm Update Driver',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Confirm'
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'php/crud/update/updatedriver.php',
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

      function openUpdateDriver(driver_id, driver_IdNumber, driver_rfid, driver_fname, driver_mname, driver_lname, driver_assignUnit, driver_assignSegment, driver_uname, driver_account_status, driver_contact) {
        $('#driver_rfid1').val(driver_rfid)
        $('#driver_id1').val(driver_id);
        $('#driver_IdNumber1').val(driver_IdNumber);
        $('#driver_fname1').val(driver_fname);
        $('#driver_mname1').val(driver_mname);
        $('#driver_lname1').val(driver_lname);
        $('#driver_contact1').val(driver_contact || '');
        $('#driver_username1').val(driver_uname);
        $('#status').val(driver_account_status);
        $('#driver_assignUnit1').val(driver_assignUnit);
        $('#driver_assignSegment1').val(driver_assignSegment);


        $('#editModal').modal('show');
      }

      function deleteDriver(driver_id) {
        var driver_Id = driver_id;

        var form_data = {
          driver_Id: driver_Id

        };
        Swal.fire({
          title: 'Confirm Remove Driver',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: "php/crud/delete/deletedriver.php",
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
