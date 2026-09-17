<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Driver") {
  include "php/config/config.php";
  $id = $_SESSION['user_id'];
  
  // Query dispatch & trips for this driver
  $sql = "SELECT d.d_id, d.booking_no, d.d_datetime, d.d_dispatcher, d.d_dispatchHub,
          d.d_driverName, d.driver_id, d.d_truck, d.d_trailer, d.d_genset,
          d.d_tripReceipt, d.d_ecs, d.costumer,
          t.trip_id, t.trip_type, t.trip_container, t.container_activity, 
          t.trip_containerStat, t.trip_haulingSegment, t.trip_haulingType, 
          t.trip_from, t.trip_to, t.km_run, t.trip_departureDateTime, t.trip_arrivalDateTime,
          t.trip_pharrivalDateTime, t.deliver_location, t.deliver_dateTime, 
          t.withdraw_location, t.withdraw_dateTime, t.required_date, t.trip_status
          FROM dispatch d
          LEFT JOIN trips t ON d.d_id = t.d_id
          WHERE d.driver_id = '$id'
          ORDER BY d.d_datetime DESC";
  
  $result = $conn->query($sql);
  $bookings = [];
  while ($row = $result->fetch()) {
    $bookings[$row['d_id']]['info'] = $row;
    $bookings[$row['d_id']]['trips'][] = $row;
  }
  
  // Calculate stats using the same workflow rules as driver/dashboard.php.
  $totalTrips = count($bookings);
  $completedTrips = 0;
  $activeTrips = 0;
  foreach ($bookings as $data) {
    $dispatch = $data['info'];
    $trips = $data['trips'];
    $wf = $dispatch['workflow_stage'] ?? 'dispatcher_assigned';
    $hasDoneTrip = false;
    foreach ($trips as $tripRow) {
      if (($tripRow['trip_status'] ?? '') === 'Done') {
        $hasDoneTrip = true;
        break;
      }
    }
    $isCompleted = in_array($wf, ['pod_captured', 'billing_closed', 'client_notified'], true);
    $isDeclined = $wf === 'driver_declined';
    $isActive = in_array($wf, ['dispatcher_assigned', 'reassigned', 'driver_accepted', 'gate_cleared', 'en_route', 'pending_verification', 'delivered'], true);

    if (($hasDoneTrip || $isCompleted) && !$isDeclined) {
      $completedTrips++;
    } elseif ($isActive) {
      $activeTrips++;
    }
  }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>Trip Reports - Driver</title>
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
        <h3>Trip Reports</h3>
      </div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>
      <div class="container-fluid">
        
        <!-- Report Stats -->
        <div class="stats-grid mb-4">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="ti ti-clipboard-list"></i>
            </div>
            <div class="stat-info">
              <h6>Total Trips</h6>
              <span class="stat-value"><?= $totalTrips ?></span>
            </div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon success">
              <i class="ti ti-circle-check"></i>
            </div>
            <div class="stat-info">
              <h6>Completed</h6>
              <span class="stat-value"><?= $completedTrips ?></span>
            </div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon warning">
              <i class="ti ti-clock"></i>
            </div>
            <div class="stat-info">
              <h6>In Progress</h6>
              <span class="stat-value"><?= $activeTrips ?></span>
            </div>
          </div>
        </div>

        <!-- Date Filter Card -->
        <div class="card mb-4">
          <div class="card-header">
            <i class="ti ti-filter me-2"></i>Generate Report
          </div>
          <div class="card-body">
            <form id="filterForm" method="POST" action="reports/driver-trip-report.php" target="_blank">
              <input type="hidden" name="user_Id1" value="<?= $id ?>">
              
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">From Date</label>
                  <input type="date" name="fromDate" class="form-control-modern" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">To Date</label>
                  <input type="date" name="toDate" class="form-control-modern" required>
                </div>
              </div>
              
              <div class="mt-3">
                <button type="submit" class="btn-modern btn-primary-modern w-100">
                  <i class="ti ti-file-download"></i> Generate PDF Report
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Trip History -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="ti ti-history me-2"></i>Trip History</span>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Filter
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item active" href="#" data-filter="all">All Trips</a></li>
                <li><a class="dropdown-item" href="#" data-filter="completed">Completed</a></li>
                <li><a class="dropdown-item" href="#" data-filter="active">In Progress</a></li>
              </ul>
            </div>
          </div>
          
          <div class="card-body p-0">
            <div class="trip-timeline">
              <?php 
              $counter = 0;
              foreach($bookings as $d_id => $data): 
                $dispatch = $data['info'];
                $trips = $data['trips'];
                $status = end($trips)['trip_status'];
                $wf = $dispatch['workflow_stage'] ?? 'dispatcher_assigned';
                $hasDoneTrip = false;
                foreach ($trips as $tripRow) {
                  if (($tripRow['trip_status'] ?? '') === 'Done') {
                    $hasDoneTrip = true;
                    break;
                  }
                }
                $isCompleted = in_array($wf, ['pod_captured', 'billing_closed', 'client_notified'], true);
                $isDeclined = $wf === 'driver_declined';
                $isActive = in_array($wf, ['dispatcher_assigned', 'reassigned', 'driver_accepted', 'gate_cleared', 'en_route', 'pending_verification', 'delivered'], true);
                $statusClass = $isDeclined ? "declined" : (($isCompleted || $hasDoneTrip) ? "completed" : ($isActive ? "active" : "pending"));
                $statusLabel = $isDeclined ? "Declined" : (($isCompleted || $hasDoneTrip) ? "Completed" : ($isActive ? "In Progress" : ($status ?: 'Pending')));
                $displayDateRaw = trim((string)($dispatch['required_date'] ?? ''));
                if ($displayDateRaw === '' || $displayDateRaw === '0000-00-00' || $displayDateRaw === '0000-00-00 00:00:00') {
                  $displayDateRaw = trim((string)($dispatch['d_datetime'] ?? ''));
                }
                $displayDate = '—';
                if ($displayDateRaw !== '') {
                  $ts = strtotime($displayDateRaw);
                  if ($ts !== false && $ts > 0) {
                    $displayDate = date("M d, Y", $ts);
                  }
                }
                $isLast = $counter === count($bookings) - 1;
              ?>
              <div class="timeline-item" data-status="<?= strtolower($statusClass) ?>">
                <div class="timeline-marker <?= $statusClass ?>"></div>
                <div class="timeline-content">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                      <h6 class="mb-1"><?= $dispatch['booking_no'] ?></h6>
                      <span class="customer-badge"><?= htmlspecialchars($dispatch['costumer']) ?></span>
                    </div>
                    <span class="status-badge <?= $statusClass ?>">
                      <?= htmlspecialchars($statusLabel) ?>
                    </span>
                  </div>
                  
                  <div class="trip-meta mb-2">
                    <span><i class="ti ti-calendar"></i> <?= htmlspecialchars($displayDate) ?></span>
                    <span class="mx-2">•</span>
                    <span><i class="ti ti-truck"></i> <?= $dispatch['d_truck'] ?></span>
                  </div>
                  
                  <div class="trip-route-mini mb-3">
                    <?php foreach($trips as $index => $trip): ?>
                    <div class="route-stop">
                      <span class="stop-dot"></span>
                      <div class="stop-info">
                        <strong><?= $trip['trip_from'] ?></strong>
                        <i class="ti ti-arrow-right text-muted mx-1"></i>
                        <strong><?= $trip['trip_to'] ?></strong>
                        <span class="badge bg-light text-dark ms-2"><?= $trip['container_activity'] ?></span>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  
                  <div class="trip-actions">
                    <button class="btn btn-sm btn-outline-primary view-map" 
                            data-from="<?= htmlspecialchars(end($trips)['trip_from']) ?>"
                            data-to="<?= htmlspecialchars(end($trips)['trip_to']) ?>"
                            data-trip='<?= json_encode(end($trips), JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                      <i class="ti ti-map"></i> View Route
                    </button>
                  </div>
                </div>
                <?php if (!$isLast): ?>
                <div class="timeline-connector"></div>
                <?php endif; ?>
              </div>
              <?php 
              $counter++;
              endforeach; 
              ?>
              
              <?php if (empty($bookings)): ?>
              <div class="text-center py-5 text-muted">
                <i class="ti ti-clipboard-x" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3">No trips found in your history</p>
              </div>
              <?php endif; ?>
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
      <a href="driver-unit" class="nav-item">
        <i class="ti ti-truck"></i>
        <span>Unit</span>
      </a>
      <a href="driver-messages" class="nav-item">
        <i class="ti ti-message-circle"></i>
        <span>Chat</span>
      </a>
      <a href="driver-tripReport" class="nav-item active">
        <i class="ti ti-clipboard-list"></i>
        <span>Trip Report</span>
      </a>
      <a href="driver-breakdown" class="nav-item sos-btn">
        <i class="ti ti-alert-triangle"></i>
        <span>SOS</span>
      </a>
    </div>

  </div>

  <!-- Map Modal -->
  <div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-route"></i> Trip Route</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="map" style="height: 400px; width: 100%;"></div>
          <div id="tripDetails" class="map-info mt-3"></div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDi9dpeJZM1GkdSfovy2ufBWQZFabMrSRA&libraries=places,routes,marker"></script>

  <script>
    // Filter functionality
    $('.dropdown-item').on('click', function(e) {
      e.preventDefault();
      const filter = $(this).data('filter');
      
      $('.dropdown-item').removeClass('active');
      $(this).addClass('active');
      
      $('.timeline-item').each(function() {
        const item = $(this);
        if (filter === 'all') {
          item.show();
        } else {
          item.toggle(item.data('status') === filter);
        }
      });
    });

    // SOS Function
    function sosAlert() {
      Swal.fire({
        title: '🚨 Emergency SOS',
        text: 'Send distress signal to dispatch?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Send SOS',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          $.post("php/operations/send_sos.php", { driver_id: "<?= $id ?>" }, function() {
            Swal.fire('Sent!', 'SOS alert transmitted.', 'success');
          });
        }
      });
    }

    // Map functionality using the current Routes + Advanced Marker APIs.
    let map;
    let mapPolylines = [];
    let mapMarkers = [];

    function clearRouteArtifacts() {
      mapPolylines.forEach(polyline => polyline.setMap(null));
      mapMarkers.forEach(marker => {
        marker.map = null;
      });
      mapPolylines = [];
      mapMarkers = [];
    }

    async function initMap(fromLat, fromLng, toLat, toLng, tripData) {
      const { Map } = await google.maps.importLibrary("maps");
      const { Route } = await google.maps.importLibrary("routes");
      const { AdvancedMarkerElement, PinElement } = await google.maps.importLibrary("marker");

      clearRouteArtifacts();
      map = new Map(document.getElementById("map"), {
        zoom: 6,
        center: { lat: fromLat, lng: fromLng },
        mapId: "DEMO_MAP_ID",
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false
      });

      const request = {
        origin: { lat: fromLat, lng: fromLng },
        destination: { lat: toLat, lng: toLng },
        travelMode: "DRIVING",
        fields: ["path"],
      };

      const { routes } = await Route.computeRoutes(request);
      if (routes && routes.length) {
        mapPolylines = routes[0].createPolylines({
          strokeColor: "#2563eb",
          strokeWeight: 4,
        });
        mapPolylines.forEach(polyline => polyline.setMap(map));

        const routeMarkers = await routes[0].createWaypointAdvancedMarkers();
        routeMarkers.forEach(marker => {
          marker.map = map;
          mapMarkers.push(marker);
        });
      }

      const youPin = new PinElement({
        background: "#2563eb",
        borderColor: "#1d4ed8",
        glyphColor: "#ffffff",
      });

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
          const currentMarker = new AdvancedMarkerElement({
            position: { lat: pos.coords.latitude, lng: pos.coords.longitude },
            map: map,
            title: "You are here",
            content: youPin.element,
          });
          mapMarkers.push(currentMarker);
        });
      }

      $("#tripDetails").html(`
        <div class="map-info-row"><span>Activity</span><strong>${tripData.container_activity}</strong></div>
        <div class="map-info-row"><span>From</span><strong>${tripData.trip_from}</strong></div>
        <div class="map-info-row"><span>To</span><strong>${tripData.trip_to}</strong></div>
        <div class="map-info-row"><span>Container</span><strong>${tripData.trip_container || 'N/A'}</strong></div>
      `);
    }

    $(document).on("click", ".view-map", function() {
      const trip = JSON.parse($(this).attr("data-trip"));
      $.post("php/fetch/get_location.php", { from: trip.trip_from, to: trip.trip_to }, function(res) {
        const data = JSON.parse(res);
        $("#mapModal").modal("show");
        setTimeout(() => initMap(
          parseFloat(data.from.latitude), 
          parseFloat(data.from.longitude),
          parseFloat(data.to.latitude), 
          parseFloat(data.to.longitude), 
          trip
        ), 500);
      });
    });

    // Quick complete action
    $(document).on('click', '.update-trip', function() {
      const tripId = $(this).data('id');
      
      Swal.fire({
        title: 'Complete Trip?',
        text: 'Mark this trip as finished?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Complete',
        confirmButtonColor: '#10b981'
      }).then((result) => {
        if (result.isConfirmed) {
          $.post('php/crud/update/update_tripDateTime1.php', {
            trip_id: tripId,
            action: 'done'
          }, function(response) {
            if (response.status === 'success') {
              Swal.fire('Completed!', 'Trip marked as finished.', 'success')
                .then(() => location.reload());
            }
          }, 'json');
        }
      });
    });
  </script>
</body>
</html>
<?php
} else {
  header("Location: driver-index.php?route=login");
  exit();
}
?>
