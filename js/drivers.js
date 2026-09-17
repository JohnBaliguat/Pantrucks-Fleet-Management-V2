$(document).ready(function() {
        $("#Mnav").attr({
          "class": "nav-link dropdown-toggle active"
        });
        $('#table-data').DataTable();
        $("#AddModal").click(function() {
          $("#addmodal").modal("show");
        });

        

        $('#addDriver').click(function (e) {
            e.preventDefault();

            let driver_IdNumber = $("#driver_IdNumber").val().trim();
            let driver_fname = $("#driver_fname").val().trim();
            let driver_mname = $("#driver_mname").val().trim();
            let driver_lname = $("#driver_lname").val().trim();
            let driver_assignUnit = $("#driver_assignUnit").val().trim();
            let driver_assignSegment = $("#driver_assignSegment").val();
            let driver_assignBase = $("#driver_assignBase").val();

            if (
                driver_IdNumber === "" ||
                driver_fname === "" ||
                driver_lname === "" ||
                driver_assignUnit === "" ||
                driver_assignSegment === "" ||
                driver_assignBase === ""
            ) {
                Swal.fire({
                    text: 'Please fill in all required fields',
                    icon: 'info'
                });
                return;
            }

            Swal.fire({
                title: 'Confirm Add Driver',
                text: 'Do you want to save this driver?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {

                    let formData = new FormData($('#addForm')[0]);

                    $.ajax({
                        url: 'php/crud/add/add-driver.php',
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: function (response) {
                            Swal.fire({
                                text: response,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            });

                            $('#addForm')[0].reset();
                            $('#addmodal').modal('hide');

                            setTimeout(() => {
                                location.reload();
                            }, 1600);
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

      function openUpdateDriver(driver_id, driver_IdNumber, driver_rfid, driver_fname, driver_mname, driver_lname, driver_assignUnit, driver_assignSegment, driver_uname, driver_email, driver_account_status, driver_assignBase) {
        $('#driver_rfid1').val(driver_rfid);
        $('#driver_id1').val(driver_id);
        $('#driver_IdNumber1').val(driver_IdNumber);
        $('#driver_fname1').val(driver_fname);
        $('#driver_mname1').val(driver_mname);
        $('#driver_lname1').val(driver_lname);
        $('#driver_username1').val(driver_uname);
        $('#driver_email1').val(driver_email || '');
        $('#driver_pass1').val('');                    // always blank — only updated if user types one
        $('#status').val(driver_account_status);
        $('#driver_assignUnit1').val(driver_assignUnit || '');
        $('#driver_assignSegment1').val(driver_assignSegment || '');
        $('#driver_assignBase1').val(driver_assignBase || '');

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
