<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Driver") {
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>My Units - Driver</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/driver-modern.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper">
    
    <!-- Mobile Header -->
    <div class="app-topstrip">
      <div class="d-flex align-items-center justify-content-between w-100">
        <img src="assets/images/logos/pantrucks.png" alt="Logo">
        <h3>My Assigned Units</h3>
      </div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>
      <div class="container-fluid">
        
        <!-- Units Grid -->
        <div class="units-grid" id="unitsContainer">
          
          <!-- Truck Card -->
          <div class="unit-card variant-truck">
            <div class="unit-visual">
              <img src="assets/images/profile/primemover.png" alt="Truck" id="truckImage">
            </div>
            <div class="unit-info">
              <h5 id="truckName" class="skeleton" style="width: 60%; height: 24px;">Loading...</h5>
              <p class="unit-meta" id="truckDetails">Fetching truck details...</p>
              
              <div class="unit-stats">
                <div class="unit-stat">
                  <span class="unit-stat-value" id="truckKm">--</span>
                  <span class="unit-stat-label">KM Run</span>
                </div>
                <div class="unit-stat">
                  <span class="unit-stat-value" id="truckStatus">--</span>
                  <span class="unit-stat-label">Status</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Genset Card -->
          <div class="unit-card variant-genset">
            <div class="unit-visual">
              <img src="assets/images/profile/generator.png" alt="Genset" id="gensetImage">
            </div>
            <div class="unit-info">
              <h5 id="gensetName">No Genset</h5>
              <p class="unit-meta" id="gensetDetails">Not assigned</p>
            </div>
          </div>

          <!-- Trailer Card -->
          <div class="unit-card variant-trailer">
            <div class="unit-visual">
              <img src="assets/images/profile/trailers.png" alt="Trailer" id="trailerImage">
            </div>
            <div class="unit-info">
              <h5 id="trailerName">No Trailer</h5>
              <p class="unit-meta" id="trailerDetails">Not assigned</p>
              
              <div id="trailerActions" style="display: none; margin-top: 1rem;">
                <button id="pulloutBtn" class="btn-modern btn-outline-modern">
                  <i class="ti ti-unlink"></i> Drop Trailer
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- Quick Actions -->
        <div class="card mt-4">
          <div class="card-header">Quick Actions</div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="driver-dashboard" class="btn-modern btn-primary-modern">
                <i class="ti ti-clipboard-list"></i> View My Bookings
              </a>
              <button onclick="reportIssue()" class="btn-modern btn-outline-modern">
                <i class="ti ti-alert-circle"></i> Report Issue
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="mobile-nav">
      <a href="driver-dashboard" class="nav-item">
        <i class="ti ti-smart-home"></i>
        <span>Home</span>
      </a>
      <a href="driver-unit" class="nav-item active">
        <i class="ti ti-truck"></i>
        <span>Unit</span>
      </a>
      <a href="driver-messages" class="nav-item">
        <i class="ti ti-message-circle"></i>
        <span>Chat</span>
      </a>
      <a href="driver-tripReport" class="nav-item">
        <i class="ti ti-clipboard-list"></i>
        <span>Trip Report</span>
      </a>
      <a href="driver-breakdown" class="nav-item sos-btn">
        <i class="ti ti-alert-triangle"></i>
        <span>SOS</span>
      </a>
    </div>

  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

  <script>
    const driverID = "<?php echo $_SESSION['user_id']; ?>";
    const unitsCacheKey = `driverUnits:${driverID}`;

    function renderUnits(data) {
      data = data || {};
      const truckList = Array.isArray(data.truck) ? data.truck : [];
      const trailerList = Array.isArray(data.trailer) ? data.trailer : [];

      if (truckList.length > 0) {
        const truck = truckList[0];
        $('#truckName').text(truck.unit_name || 'Assigned Truck').removeClass('skeleton');
        $('#truckDetails').html(`Plate: ${truck.unit_plate || '--'}`);
        $('#truckStatus').text(truck.unit_status || 'Active');

        if (truck.genset_name) {
          $('#gensetName').text(truck.genset_name);
          $('#gensetDetails').html(`Status: <span class="text-success">${truck.genset_status || 'Active'}</span>`);
        } else {
          $('#gensetName').text('No Genset');
          $('#gensetDetails').text('Not assigned');
        }

        fetchTruckKm(truck.unit_name || '');
      } else {
        $('#truckName').text('No Truck Assigned').removeClass('skeleton');
        $('#truckDetails').text('No active shift truck found.');
        $('#truckKm').text('--');
        $('#truckStatus').text('--');
        $('#gensetName').text('No Genset');
        $('#gensetDetails').text('Not assigned');
      }

      if (trailerList.length > 0) {
        const trailer = trailerList[0];
        $('#trailerName').text(trailer.trailer_name || 'Assigned Trailer');
        $('#trailerDetails').html(`Plate: ${trailer.trailer_plateNo || '--'}<br>Status: ${trailer.trailer_status || '--'}`);
        $('#pulloutBtn').data('id', trailer.trailer_id);
        $('#trailerActions').show();
      } else {
        $('#trailerName').text('No Trailer');
        $('#trailerDetails').text('Not assigned');
        $('#trailerActions').hide();
      }
    }

    function fetchTruckKm(truckName) {
      if (!truckName) {
        $('#truckKm').text('--');
        return;
      }

      $.ajax({
        url: "php/fetch/fetch_unit_km.php",
        method: "GET",
        data: { truck: truckName },
        dataType: "json",
        timeout: 5000,
        success: function(data) {
          const totalKm = Number(data && data.total_km ? data.total_km : 0);
          $('#truckKm').text(totalKm.toFixed(0) + ' km');
        },
        error: function() {
          $('#truckKm').text('--');
        }
      });
    }

    function fetchDriverUnits() {
      $.ajax({
        url: "php/fetch/fetch_units.php",
        method: "GET",
        data: { driver_id: driverID },
        dataType: "json",
        timeout: 5000,
        success: function(data) {
          if (data && !data.error) {
            sessionStorage.setItem(unitsCacheKey, JSON.stringify(data));
          }
          renderUnits(data);
        },
        error: function() {
          const cached = sessionStorage.getItem(unitsCacheKey);
          if (cached) {
            try {
              renderUnits(JSON.parse(cached));
              return;
            } catch (e) {}
          }
          $('#truckName').text('Unable to load truck').removeClass('skeleton');
          $('#truckDetails').text('Please try again.');
          $('#truckKm').text('--');
          $('#truckStatus').text('--');
        }
      });
    }

    // Pullout Trailer
    $(document).on("click", "#pulloutBtn", function() {
      const trailerId = $(this).data("id");
      
      Swal.fire({
        title: "Drop Trailer?",
        text: "Confirm you want to unassign this trailer",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        confirmButtonText: "Yes, Drop it"
      }).then((result) => {
        if (result.isConfirmed) {
          $.post("php/operations/pullout_trailer.php", { trailer_id: trailerId }, function(response) {
            if (response.trim() === "success") {
              Swal.fire("Dropped!", "Trailer unassigned.", "success");
              fetchDriverUnits();
            }
          });
        }
      });
    });

    function reportIssue() {
      Swal.fire({
        title: 'Report Issue',
        input: 'textarea',
        inputLabel: 'Describe the problem',
        inputPlaceholder: 'Enter details...',
        showCancelButton: true,
        confirmButtonText: 'Submit Report'
      }).then((result) => {
        if (result.isConfirmed) {
          $.post("php/report_issue.php", { 
            driver_id: driverID, 
            issue: result.value 
          }, function() {
            Swal.fire('Submitted!', 'Issue reported to maintenance.', 'success');
          });
        }
      });
    }

    function sosAlert() {
      Swal.fire({
        title: '🚨 Emergency SOS',
        text: 'Send distress signal?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Send SOS'
      }).then((result) => {
        if (result.isConfirmed) {
          $.post("php/operations/send_sos.php", { driver_id: driverID }, function() {
            Swal.fire('Sent!', 'Help is on the way.', 'success');
          });
        }
      });
    }

    // Initialize
    const cachedUnits = sessionStorage.getItem(unitsCacheKey);
    if (cachedUnits) {
      try {
        renderUnits(JSON.parse(cachedUnits));
      } catch (e) {}
    }
    fetchDriverUnits();
    setInterval(fetchDriverUnits, 30000); // Refresh every 30s
  </script>
</body>
</html>
<?php
} else {
  header("Location: driver-index.php?route=login");
  exit();
}
?>
