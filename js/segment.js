$(document).ready(function() {
        $("#Mnav").attr({
          "class": "nav-link dropdown-toggle active"
        });
        $('#table-data').DataTable();
        $('#table-data1').DataTable();
        $('#table-data2').DataTable();
        $("#addSegment").click(function() {
          $("#addHaulingModal").modal("show");
        });

        $("#addLocation").click(function() {
          $("#addLocationModal").modal("show");
        });

        $("#addcustomer").click(function() {
          $("#addCustomerModal").modal("show");
        });


        $("#saveHauling").click(function(e) {
          e.preventDefault();

          var segment = $("#hauling_segment").val().trim();
          var type = $("#hauling_type").val().trim();

          if (segment === "" || type === "") {
            Swal.fire({
              icon: 'info',
              text: 'Please fill in all required fields.'
            });
            return;
          }

          Swal.fire({
            title: 'Confirm Add Hauling?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, save it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'php/crud/add/addhauling.php',
                method: 'POST',
                data: {
                  hauling_segment: segment,
                  hauling_type: type
                },
                success: function(response) {
                  Swal.fire({
                    text: response,
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500
                  });
                  $('#addHaulingForm')[0].reset();
                  $('#addHaulingModal').modal('hide');
                  setTimeout(() => {
                    location.reload();
                  }, 1600);
                },
                error: function(xhr, status, error) {
                  Swal.fire({
                    icon: 'error',
                    text: 'Error: ' + error
                  });
                }
              });
            }
          });
        });
        $("#updateHauling").click(function(e) {
          e.preventDefault();

          var id = $("#hauling_id").val();
          var segment = $("#hauling_segment1").val().trim();
          var type = $("#hauling_type1").val().trim();

          if (segment === "" || type === "") {
            Swal.fire({
              icon: 'info',
              text: 'All fields are required.'
            });
            return;
          }

          Swal.fire({
            title: 'Confirm Update?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update it!',
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'php/crud/update/updatehauling.php',
                type: 'POST',
                data: {
                  hauling_id: id,
                  hauling_segment: segment,
                  hauling_type: type
                },
                success: function(response) {
                  Swal.fire({
                    icon: 'success',
                    text: response,
                    showConfirmButton: false,
                    timer: 1500
                  });
                  $('#updateHaulingForm')[0].reset();
                  $('#updateHaulingModal').modal('hide');
                  setTimeout(() => {
                    location.reload();
                  }, 1600);
                },
                error: function(xhr, status, error) {
                  Swal.fire({
                    icon: 'error',
                    text: 'Update error: ' + error
                  });
                }
              });
            }
          });
        });


        // Location Save
        $("#saveLocation").click(function(e) {
          e.preventDefault();

          var location = $("#location_name").val().trim();
          var latitude = $("#latitude").val().trim();
          var longitude = $("#longitude").val().trim();

          if (location === "") {
            Swal.fire({
              icon: 'info',
              text: 'Please fill in all required fields.'
            });
            return;
          }

          Swal.fire({
            title: 'Confirm Add Location?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, save it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'php/crud/add/addlocation.php',
                method: 'POST',
                data: {
                  location_name: location,
                  latitude: latitude,
                  longitude: longitude
                },
                success: function(response) {
                  Swal.fire({
                    text: response,
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500
                  });
                  $('#addLocationForm')[0].reset();
                  $('#addLocationModal').modal('hide');
                  setTimeout(() => {
                    location.reload();
                  }, 1500);
                },
                error: function(xhr, status, error) {
                  Swal.fire({
                    icon: 'error',
                    text: 'Error: ' + error
                  });
                }
              });
            }
          });
        });


        $("#updateLocation").click(function(e) {
          e.preventDefault();

          var id = $("#location_id").val();
          var location = $("#location_name1").val().trim();
          var latitude = $("#latitude1").val().trim();
          var longitude = $("#longitude1").val().trim();

          if (location === "") {
            Swal.fire({
              icon: 'info',
              text: 'All fields are required.'
            });
            return;
          }

          Swal.fire({
            title: 'Confirm Update?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update it!',
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'php/crud/update/updatelocation.php',
                type: 'POST',
                data: {
                  location_id: id,
                  location_name: location,
                  latitude: latitude,
                  longitude: longitude
                },
                success: function(response) {
                  Swal.fire({
                    icon: 'success',
                    text: response,
                    showConfirmButton: false,
                    timer: 1500
                  });
                  $('#updateLocationForm')[0].reset();
                  $('#updateLocationModal').modal('hide');
                  setTimeout(() => {
                    location.reload();
                  }, 1300);
                },
                error: function(xhr, status, error) {
                  Swal.fire({
                    icon: 'error',
                    text: 'Update error: ' + error
                  });
                }
              });
            }
          });
        });


        $("#saveCustomer").click(function(e) {
          e.preventDefault();

          var customer_code = $("#customer_code").val().trim();
          var customer_name = $("#customer_name").val().trim();

          if (customer_code === "" || customer_name === "") {
            Swal.fire({
              icon: 'info',
              text: 'Please fill in all required fields.'
            });
            return;
          }

          Swal.fire({
            title: 'Confirm Add Customer?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, save it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'php/crud/add/addcustomer.php',
                method: 'POST',
                data: {
                  customer_code: customer_code,
                  customer_name: customer_name,
                  customer_segment: $('#customer_segment').val().trim()
                },
                success: function(response) {
                  Swal.fire({
                    text: response,
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500
                  });
                  $('#addCustomerForm')[0].reset();
                  $('#addCustomerModal').modal('hide');
                  setTimeout(() => {
                    location.reload();
                  }, 1500);
                },
                error: function(xhr, status, error) {
                  Swal.fire({
                    icon: 'error',
                    text: 'Error: ' + error
                  });
                }
              });
            }
          });
        });

        // Update customer via Ajax
        $("#updateCustomer").click(function(e) {
          e.preventDefault();

          var id = $("#edit_customer_id").val().trim();
          var code = $("#edit_customer_code").val().trim();
          var name = $("#edit_customer_name").val().trim();

          if (id === "" || code === "" || name === "") {
            Swal.fire({
              icon: 'info',
              text: 'Please fill in all required fields.'
            });
            return;
          }

          Swal.fire({
            title: 'Confirm Update Customer?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'php/crud/update/updatecustomer.php',
                method: 'POST',
                data: {
                  customer_id: id,
                  customer_code: code,
                  customer_name: name,
                  customer_segment: $('#edit_customer_segment').val().trim()
                },
                success: function(response) {
                  Swal.fire({
                    text: response,
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500
                  });
                  $('#updateCustomerForm')[0].reset();
                  $('#updateCustomerModal').modal('hide');
                  setTimeout(() => {
                    location.reload();
                  }, 1500);
                },
                error: function(xhr, status, error) {
                  Swal.fire({
                    icon: 'error',
                    text: 'Error: ' + error
                  });
                }
              });
            }
          });
        });

      });

      function openUpdateHauling(hauling_id, hauling_segment, hauling_type) {
        $('#hauling_id').val(hauling_id);
        $('#hauling_segment1').val(hauling_segment);
        $('#hauling_type1').val(hauling_type);

        $('#updateHaulingModal').modal('show');
      }

      function openUpdateLocation(location_id, location_name, latitude, longitude) {
        $('#location_id').val(location_id);
        $('#location_name1').val(location_name);
        $('#latitude1').val(latitude);
        $('#longitude1').val(longitude);

        $('#updateLocationModal').modal('show');
      }

      function openUpdateCustomer(customer_id, customer_code, customer_name, customer_segment, trade_type) {
        $('#edit_customer_id').val(customer_id);
        $('#edit_customer_code').val(customer_code);
        $('#edit_customer_name').val(customer_name);
        $('#edit_customer_segment').val(customer_segment || '');
        $('#edit_trade_type').val(trade_type === 'Import' ? 'Import' : 'Export');

        $('#updateCustomerModal').modal('show');
      }

      function deleteHauling(hauling_id) {
        var hauling_Id = hauling_id;

        var form_data = {
          hauling_Id: hauling_Id

        };
        Swal.fire({
          title: 'Confirm Remove Hauling',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: "php/crud/delete/deletehauling.php",
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

      function deleteLocation(location_id) {
        var location_Id = location_id;

        var form_data = {
          location_Id: location_Id

        };
        Swal.fire({
          title: 'Confirm Remove Location',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Confirm'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: "php/crud/delete/deletelocation.php",
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
