<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Dispatch Admin") {
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dispatch Admin Dashboard</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <style>
    .dispatch-admin-page {
      overflow-x: hidden;
    }

    .dispatch-admin-hero {
      position: relative;
      isolation: isolate;
      background: linear-gradient(135deg, #10223e 0%, #17345f 58%, #1d5eff 100%);
      color: #11233f;
      border: 0;
      overflow: hidden;
    }

    .dispatch-admin-hero::before {
      content: "";
      position: absolute;
      inset: auto -8% -20% auto;
      width: 420px;
      height: 420px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.26) 0%, rgba(255, 255, 255, 0.08) 38%, transparent 72%);
      z-index: -1;
      pointer-events: none;
    }

    .dispatch-admin-hero::after {
      content: "";
      position: absolute;
      inset: 14% 34% 14% auto;
      width: 1px;
      background: linear-gradient(180deg, transparent 0%, rgba(255, 255, 255, 0.28) 18%, rgba(255, 255, 255, 0.12) 82%, transparent 100%);
      z-index: -1;
      pointer-events: none;
    }

    .dispatch-admin-hero .hero-kicker {
      color: #d7e6ff;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .dispatch-admin-hero .hero-title {
      font-size: clamp(1.8rem, 3vw, 2.65rem);
      font-weight: 800;
      color: #fff;
      line-height: 1.08;
      margin: 0.35rem 0 0.75rem;
      max-width: 760px;
    }

    .dispatch-admin-hero .hero-copy {
      color: rgba(255, 255, 255, 0.84);
      max-width: 640px;
      margin-bottom: 0;
    }

    .hero-aside {
      background: rgba(255, 255, 255, 0.9);
      border-radius: 22px;
      padding: 1.25rem;
      box-shadow: 0 22px 48px rgba(17, 35, 63, 0.16);
      position: relative;
      z-index: 1;
    }

    .hero-aside-label {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #5e7291;
      font-weight: 700;
    }

    .hero-aside-value {
      font-size: 2.1rem;
      font-weight: 800;
      color: #16396c;
      line-height: 1;
      margin-top: 0.4rem;
    }

    .kpi-card {
      border: 0;
      background: linear-gradient(180deg, #ffffff 0%, #f6f9ff 100%);
    }

    .kpi-icon {
      width: 52px;
      height: 52px;
      border-radius: 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #e9f1ff;
      color: #18407b;
      font-size: 1.35rem;
    }

    .kpi-label {
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #6e819e;
      font-weight: 700;
    }

    .kpi-value {
      font-size: 1.9rem;
      font-weight: 800;
      color: #15345f;
      line-height: 1.05;
    }

    .customer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 1rem;
    }

    .customer-card {
      border-radius: 18px;
      padding: 1rem 1.1rem;
      background: linear-gradient(180deg, #f8fbff 0%, #edf4ff 100%);
      border: 1px solid rgba(25, 58, 108, 0.08);
    }

    .customer-name {
      font-weight: 800;
      color: #16396c;
      margin-bottom: 0.35rem;
      font-size: 1rem;
    }

    .customer-count {
      font-size: 1.85rem;
      font-weight: 800;
      color: #2558b7;
      line-height: 1;
    }

    .customer-meta {
      color: #677c9c;
      font-size: 0.88rem;
      margin-top: 0.4rem;
    }

    .util-bar {
      height: 12px;
      border-radius: 999px;
      background: #e7edf7;
      overflow: hidden;
    }

    .util-bar > span {
      display: block;
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, #1d5eff 0%, #27b2ff 100%);
    }

    .live-table th {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #7184a3;
      border-bottom-width: 1px;
    }

    .driver-name {
      font-weight: 700;
      color: #183965;
    }

    .driver-sub {
      color: #6f82a1;
      font-size: 0.86rem;
    }

    .trip-cell {
      min-width: 280px;
      color: #20395f;
      font-weight: 600;
    }

    .location-cell {
      min-width: 170px;
      color: #4d6487;
      font-size: 0.9rem;
    }

    .location-link {
      color: #2558b7;
      font-weight: 700;
      text-decoration: none;
    }

    .location-link:hover {
      text-decoration: underline;
    }

    .refresh-note {
      color: #6b7e9e;
      font-size: 0.9rem;
    }

    @media (max-width: 767px) {
      .dispatch-admin-hero::after {
        display: none;
      }

      .hero-aside {
        margin-top: 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">

    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
        <a class="d-flex justify-content-center" href="#">
          <img src="assets/images/logos/pantrucks.png" alt="" width="122">
        </a>
      </div>
      <div class="d-lg-flex align-items-center gap-2">
        <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3>
      </div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>
      <div class="body-wrapper-inner dispatch-admin-page">
        <div class="container-fluid">
          <div class="card dispatch-admin-hero mb-4">
            <div class="card-body p-4 p-lg-5">
              <div class="row align-items-center g-4">
                <div class="col-lg-8">
                  <div class="hero-kicker">Dispatch Admin</div>
                  <h1 class="hero-title">Booking demand, truck usage, and live driver activity in one dashboard.</h1>
                  <p class="hero-copy">This view is focused on booking volume by customer, truck utilization, who is available right now, and what each driver is currently working on.</p>
                </div>
                <div class="col-lg-4">
                  <div class="hero-aside">
                    <div class="hero-aside-label">Last refresh</div>
                    <div class="hero-aside-value" id="daLastRefresh">--:--:--</div>
                    <div class="refresh-note mt-2">Auto-refresh every 15 seconds.</div>
                    <div class="d-flex gap-2 mt-3">
                      <a href="dispatch-addbook" class="btn btn-primary btn-sm">Add Booking</a>
                      <a href="dispatch-tracking" class="btn btn-outline-primary btn-sm">Tracking</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-6">
              <div class="card kpi-card h-100">
                <div class="card-body">
                  <div class="d-flex align-items-start">
                    <span class="kpi-icon"><i class="ti ti-truck"></i></span>
                    <div class="ms-3">
                      <div class="kpi-label">Truck Utilization</div>
                      <div class="kpi-value"><span id="daTruckUtil">0</span>%</div>
                      <div class="driver-sub"><span id="daActiveTrucks">0</span> active of <span id="daTotalTrucks">0</span> trucks</div>
                    </div>
                  </div>
                  <div class="util-bar mt-3"><span id="daTruckUtilBar" style="width:0%"></span></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-6">
              <div class="card kpi-card h-100">
                <div class="card-body">
                  <div class="d-flex align-items-start">
                    <span class="kpi-icon"><i class="ti ti-box-multiple"></i></span>
                    <div class="ms-3">
                      <div class="kpi-label">Trailer Utilization</div>
                      <div class="kpi-value"><span id="daTrailerUtil">0</span>%</div>
                      <div class="driver-sub"><span id="daActiveTrailers">0</span> active of <span id="daTotalTrailers">0</span> trailers</div>
                    </div>
                  </div>
                  <div class="util-bar mt-3"><span id="daTrailerUtilBar" style="width:0%"></span></div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
              <div class="card kpi-card h-100">
                <div class="card-body">
                  <div class="d-flex align-items-start">
                    <span class="kpi-icon"><i class="ti ti-book-2"></i></span>
                    <div class="ms-3">
                      <div class="kpi-label">Active Bookings</div>
                      <div class="kpi-value" id="daActiveBookings">0</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="card kpi-card h-100">
                <div class="card-body">
                  <div class="d-flex align-items-start">
                    <span class="kpi-icon"><i class="ti ti-users"></i></span>
                    <div class="ms-3">
                      <div class="kpi-label">Pending Bookings</div>
                      <div class="kpi-value" id="daPendingBookings">0</div>
                      <div class="driver-sub">Active bookings with no dispatch yet</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-6 col-xl-3">
              <div class="card kpi-card h-100">
                <div class="card-body">
                  <div class="d-flex align-items-start">
                    <span class="kpi-icon"><i class="ti ti-user-check"></i></span>
                    <div class="ms-3">
                      <div class="kpi-label">Available Drivers</div>
                      <div class="kpi-value" id="daAvailableDrivers">0</div>
                      <div class="driver-sub"><span id="daOnShiftDrivers">0</span> drivers on shift</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="card kpi-card h-100">
                <div class="card-body">
                  <div class="d-flex align-items-start">
                    <span class="kpi-icon"><i class="ti ti-building-store"></i></span>
                    <div class="ms-3">
                      <div class="kpi-label">Customers With Bookings</div>
                      <div class="kpi-value" id="daCustomers">0</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-4">
            <div class="col-xl-4">
              <div class="card h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                      <h4 class="card-title mb-1">Booking Count by Customer</h4>
                      <p class="card-subtitle mb-0">Active and pending bookings with remaining quantity.</p>
                    </div>
                  </div>
                  <div id="daCustomerGrid" class="customer-grid"></div>
                </div>
              </div>
            </div>

            <div class="col-xl-8">
              <div class="card h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                      <h4 class="card-title mb-1">Driver Live Board</h4>
                      <p class="card-subtitle mb-0">Availability, last reported GPS location, and each driver's current trip.</p>
                    </div>
                  </div>
                  <div class="table-responsive">
                    <table class="table align-middle live-table mb-0">
                      <thead>
                        <tr>
                          <th>Driver</th>
                          <th>Status</th>
                          <th>Current Trip</th>
                          <th>Last Location</th>
                          <th>Last Seen</th>
                        </tr>
                      </thead>
                      <tbody id="daDriverRows">
                        <tr><td colspan="5" class="text-center text-muted py-4">Loading dashboard data...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script>
    function escHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function statusBadge(driver) {
      if (driver.blocked) {
        return '<span class="badge bg-danger">Blocked</span>';
      }
      if (driver.available) {
        return '<span class="badge bg-success">Available</span>';
      }
      return '<span class="badge bg-primary">' + escHtml(driver.workflow_stage || 'On Trip') + '</span>';
    }

    function formatSeen(value) {
      if (!value) return 'No timestamp';
      return value.replace('T', ' ');
    }

    function renderCustomers(rows) {
      const host = document.getElementById('daCustomerGrid');
      if (!rows.length) {
        host.innerHTML = '<div class="text-muted">No active bookings found.</div>';
        return;
      }
      host.innerHTML = rows.map(function (row) {
        return '' +
          '<div class="customer-card">' +
            '<div class="customer-name">' + escHtml(row.customer) + '</div>' +
            '<div class="customer-count">' + escHtml(row.booking_count) + '</div>' +
            '<div class="customer-meta">Pending bookings: <strong>' + escHtml(row.pending_count || 0) + '</strong></div>' +
            '<div class="customer-meta">Remaining quantity: <strong>' + escHtml(row.remaining_slots) + '</strong></div>' +
          '</div>';
      }).join('');
    }

    function renderDrivers(rows) {
      const host = document.getElementById('daDriverRows');
      if (!rows.length) {
        host.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No active drivers found.</td></tr>';
        return;
      }
      host.innerHTML = rows.map(function (row) {
        var locationHtml = escHtml(row.last_location);
        if (row.last_lat != null && row.last_lng != null) {
          var mapsUrl = 'https://www.google.com/maps?q=' + encodeURIComponent(row.last_lat + ',' + row.last_lng);
          locationHtml = '<a class="location-link" href="' + mapsUrl + '" target="_blank" rel="noopener noreferrer">' + escHtml(row.last_location) + '</a>';
        }
        return '' +
          '<tr>' +
            '<td>' +
              '<div class="driver-name">' + escHtml(row.driver_name) + '</div>' +
              '<div class="driver-sub">Truck: ' + escHtml(row.shift_truck || '-') + (row.segment ? ' | Segment: ' + escHtml(row.segment) : '') + '</div>' +
            '</td>' +
            '<td>' + statusBadge(row) + '</td>' +
            '<td class="trip-cell">' + escHtml(row.current_trip) + '</td>' +
            '<td class="location-cell">' + locationHtml + '</td>' +
            '<td class="driver-sub">' + escHtml(formatSeen(row.last_seen_at)) + '</td>' +
          '</tr>';
      }).join('');
    }

    function loadDispatchAdminDashboard() {
      fetch('php/fetch/dispatch_admin_dashboard.php')
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.status !== 'success') {
            throw new Error(data.message || 'Failed to load dashboard');
          }

          const counts = data.counts || {};
          document.getElementById('daActiveBookings').textContent = counts.active_bookings || 0;
          document.getElementById('daPendingBookings').textContent = counts.pending_bookings || 0;
          document.getElementById('daTruckUtil').textContent = counts.truck_utilization_pct || 0;
          document.getElementById('daActiveTrucks').textContent = counts.active_trucks || 0;
          document.getElementById('daTotalTrucks').textContent = counts.total_trucks || 0;
          document.getElementById('daTrailerUtil').textContent = counts.trailer_utilization_pct || 0;
          document.getElementById('daActiveTrailers').textContent = counts.active_trailers || 0;
          document.getElementById('daTotalTrailers').textContent = counts.total_trailers || 0;
          document.getElementById('daAvailableDrivers').textContent = counts.available_drivers || 0;
          document.getElementById('daOnShiftDrivers').textContent = counts.on_shift_drivers || 0;
          document.getElementById('daCustomers').textContent = counts.customers_with_bookings || 0;
          document.getElementById('daTruckUtilBar').style.width = (counts.truck_utilization_pct || 0) + '%';
          document.getElementById('daTrailerUtilBar').style.width = (counts.trailer_utilization_pct || 0) + '%';
          document.getElementById('daLastRefresh').textContent = escHtml((data.fetched_at || '').split(' ')[1] || '--:--:--');

          renderCustomers(data.customer_counts || []);
          renderDrivers(data.drivers || []);
        })
        .catch(function (err) {
          document.getElementById('daCustomerGrid').innerHTML = '<div class="text-danger">Failed to load booking counts.</div>';
          document.getElementById('daDriverRows').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escHtml(err.message) + '</td></tr>';
        });
    }

    loadDispatchAdminDashboard();
    setInterval(loadDispatchAdminDashboard, 15000);
  </script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
</body>
</html>
<?php
} else {
  header("Location: dispatcher-index.php?route=login");
  exit();
}
?>
