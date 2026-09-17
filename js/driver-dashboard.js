function fetchDriverBookings() {
          let driverID = $("#driverID").val();
          $.ajax({
              url: "php/fetch/getDriverBooking.php",
              type: "GET",
              data: { driverID: driverID },
              dataType: "json",
              success: function(data) {
                  $("#booking").text(data.active);     // Available Booking
                  $("#totalCount").text(data.done);    // Total Transaction
              }
          });
      }

      // Run on page load
      fetchDriverBookings();

      // Refresh every 5 seconds
      setInterval(fetchDriverBookings, 20000);
      let map, directionsService, directionsRenderer;

      // Init map function
      function initMap(fromLat, fromLng, toLat, toLng, tripData) {
        map = new google.maps.Map(document.getElementById("map"), {
          zoom: 6,
          center: { lat: fromLat, lng: fromLng }
        });

        directionsService = new google.maps.DirectionsService();
        directionsRenderer = new google.maps.DirectionsRenderer({
          map: map,
          polylineOptions: { strokeColor: "blue" }
        });

        // Request route
        directionsService.route(
          {
            origin: { lat: fromLat, lng: fromLng },
            destination: { lat: toLat, lng: toLng },
            travelMode: google.maps.TravelMode.DRIVING,
          },
          (result, status) => {
            if (status === "OK") {
              directionsRenderer.setDirections(result);
            } else {
              alert("Directions request failed: " + status);
            }
          }
        );

        // Add markers
        new google.maps.Marker({ position: { lat: fromLat, lng: fromLng }, map: map, label: "A" });
        new google.maps.Marker({ position: { lat: toLat, lng: toLng }, map: map, label: "B" });

        // Show current location
        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(pos => {
            let userLoc = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            new google.maps.Marker({
              position: userLoc,
              map: map,
              icon: { url: "http://maps.google.com/mapfiles/ms/icons/green-dot.png" },
              title: "Your Current Location"
            });
          });
        }

        // Trip details under map
        let detailsHtml = `
          <strong>Trip ID:</strong> ${tripData.trip_id}<br>
          <strong>Activity:</strong> ${tripData.container_activity} (${tripData.trip_containerStat})<br>
          <strong>From:</strong> ${tripData.trip_from}<br>
          <strong>To:</strong> ${tripData.trip_to}<br>
          <strong>Type:</strong> ${tripData.trip_type}<br>
          <strong>Hauling:</strong> ${tripData.trip_haulingSegment} - ${tripData.trip_haulingType}<br>
          <strong>KM Run:</strong> ${tripData.km_run}<br>
          <strong>Status:</strong> ${tripData.trip_status}<br>
        `;
        document.getElementById("tripDetails").innerHTML = detailsHtml;
      }

      // When user clicks "View Map"
      $(document).on("click", ".view-map", function() {
        let trip = JSON.parse($(this).attr("data-trip"));

        // Fetch latitude/longitude from hidden location table (AJAX call to get location coords)
        $.post("php/fetch/get_location.php", 
          { from: trip.trip_from, to: trip.trip_to }, 
          function(res) {
            let data = JSON.parse(res);
            let fromLat = parseFloat(data.from.latitude);
            let fromLng = parseFloat(data.from.longitude);
            let toLat   = parseFloat(data.to.latitude);
            let toLng   = parseFloat(data.to.longitude);

            $("#mapModal").modal("show");

            // Delay map init until modal is visible
            setTimeout(() => initMap(fromLat, fromLng, toLat, toLng, trip), 500);
          }
        );
      });
      $(document).on('click', '.view-time', function () {
        let tripId = $(this).data('id');
        $('#trip_id').val(tripId);

        // Reset buttons while loading
        $('#saveBtn').show();
        $('#doneBtn').hide();

        // Fetch trip data via AJAX
        $.getJSON('php/fetch/get_tripDateTime.php', { trip_id: tripId }, function (data) {
          if (data.status === 'success') {
            // Fill datetime fields if available
            $('#arrival_cy').val(data.trip.trip_arrivalDateTime || '');
            $('#departure').val(data.trip.trip_departureDateTime || '');
            $('#arrival_ph').val(data.trip.trip_pharrivalDateTime || '');

            // Check inputs to toggle Save/Done
            checkInputs();
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: data.message
            });
          }
        });

        $('#timeModal').modal('show');
      });

      // Function to check inputs
      function checkInputs() {
        let allFilled = $('#arrival_cy').val() && $('#departure').val() && $('#arrival_ph').val();
        
        if (allFilled) {
          $('#saveBtn').hide();
          $('#doneBtn').show();
        } else {
          $('#saveBtn').show();
          $('#doneBtn').hide();
        }
      }

      // Bind input events
      $('#arrival_cy, #departure, #arrival_ph').on('input change', checkInputs);

      // Save button click
      $('#saveBtn').on('click', function () {
        let tripData = {
          trip_id: $('#trip_id').val(),
          arrival_cy: $('#arrival_cy').val(),
          departure: $('#departure').val(),
          arrival_ph: $('#arrival_ph').val(),
          action: 'save'
        };

        $.post('php/crud/update/update_tripDateTime.php', tripData, function (response) {
          if (response.status === 'success') {
            Swal.fire({
              icon: 'success',
              title: 'Saved!',
              text: response.message,
              timer: 2000,
              showConfirmButton: false
            }).then(() => {
              $('#timeModal').modal('hide');
              location.reload();
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: response.message
            });
          }
        }, 'json');
      });

      // Done button click
      $('#doneBtn').on('click', function () {
        let tripData = {
          trip_id: $('#trip_id').val(),
          arrival_cy: $('#arrival_cy').val(),
          departure: $('#departure').val(),
          arrival_ph: $('#arrival_ph').val(),
          action: 'done'
        };

        $.post('php/crud/update/update_tripDateTime.php', tripData, function (response) {
          if (response.status === 'success') {
            Swal.fire({
              icon: 'success',
              title: 'Trip Completed!',
              text: response.message,
              timer: 2000,
              showConfirmButton: false
            }).then(() => {
              $('#timeModal').modal('hide');
              location.reload();
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: response.message
            });
          }
        }, 'json');
      });

      $(document).on("click", ".update-trip", function() {
          let trip_id  = $(this).data("id");
          let action   = $(this).data("action");
          let activity = $(this).data("activity") || "";

          // Map action to readable text
          let actionText = "";
          switch(action) {
              case "arrival_ph": actionText = "Arrival PH"; break;
              case "departure":  actionText = "Departure"; break;
              case "arrival_cy": actionText = "Arrival CY"; break;
              case "done":       actionText = "Done Trip"; break;
          }

          Swal.fire({
              title: "Are you sure?",
              text: "Do you want to update this trip as '" + actionText + "'?",
              icon: "warning",
              showCancelButton: true,
              confirmButtonColor: "#3085d6",
              cancelButtonColor: "#d33",
              confirmButtonText: "Yes, update it!"
          }).then((result) => {
              if (result.isConfirmed) {
                  $.ajax({
                      url: "php/crud/update/update_trip.php",
                      type: "POST",
                      data: { trip_id: trip_id, action: action, activity: activity },
                      success: function(res) {
                          if (res.trim() === "success") {
                              Swal.fire({
                                  icon: "success",
                                  title: "Updated!",
                                  text: "Trip updated successfully.",
                                  timer: 1500,
                                  showConfirmButton: false
                              }).then(() => {
                                  location.reload(); // refresh to update button sequence
                              });
                          } else {
                              Swal.fire("Error", res, "error");
                          }
                      }
                  });
              }
          });
      });
