$(document).ready(function() {
      


      $("#AddModal").click(function() {
        $("#addmodal").modal("show");
      });

      $("#addTrailer").click(function(e) {
        e.preventDefault();

        let trailer_name = $("#trailer_name").val();
        let trailer_plateNo = $("#trailer_plateNo").val();
        let trailer_location = $("#trailer_location").val();

        if (trailer_name === "" || trailer_plateNo === "" || trailer_location === "") {
          Swal.fire({
            text: 'Please fill in all required fields',
            icon: 'info'
          });
          return;
        }

        Swal.fire({
          title: 'Confirm Add Trailer',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            let formData = new FormData($('#addForm')[0]);
            $.ajax({
              url: 'php/crud/add/addtrailer.php',
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
                var detail = (xhr && xhr.responseText) ? xhr.responseText : (error || status || 'Unknown error');
                Swal.fire({
                  text: 'Error: ' + detail,
                  icon: 'error'
                });
              }
            });
          }
        });
      });

      $('#UpdateTrailer').click(function(e) {
        e.preventDefault();

        let formData = new FormData($('#editForm')[0]);

        Swal.fire({
          title: 'Confirm Update Trailer',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: 'php/crud/update/updatetrailer.php',
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

     

      $('#markDisposed').click(function(e) {
        e.preventDefault();

        let formData = new FormData($('#editForm')[0]);

        Swal.fire({
          title: 'Are you sure you want to mark this trailer as Disposed?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: 'php/operations/markdisposed.php',
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

      $('#markGood').click(function(e) {
        e.preventDefault();

        let formData = new FormData($('#editForm')[0]);

        Swal.fire({
          title: 'Are you sure you want to mark this trailer as Good?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: 'php/operations/markgood.php',
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

    function openEditModal(trailer_id, trailer_name, trailer_plateNo, trailer_assignTo, trailer_location) {
      $("#trailer_id1").val(trailer_id);
      $("#trailer_name_update").val(trailer_name);
      $("#trailer_plateNo_update").val(trailer_plateNo);
      $("#trailer_assignTo_update").val(trailer_assignTo);
      $("#trailer_location_update").val(trailer_location);

      $("#editModal").modal("show");
    }

    function deleteTrailer(trailer_id) {
      var trailer_Id = trailer_id;

      var form_data = {
        trailer_Id: trailer_Id
      };
      Swal.fire({
        title: 'Confirm Remove Trailer',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Confirm'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: "php/crud/delete/deletetrailer.php",
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
