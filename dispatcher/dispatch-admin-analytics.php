<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Dispatch Admin") {
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dispatch Admin Analytics</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <style>
    .analytics-page { overflow-x: hidden; }
    .analytics-hero {
      position: relative;
      overflow: hidden;
      border: 0;
      background:
        radial-gradient(circle at top right, rgba(255,255,255,0.22), transparent 28%),
        linear-gradient(135deg, #122443 0%, #193c73 58%, #2360df 100%);
      color: #fff;
    }
    .analytics-hero .kicker {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .12em;
      font-weight: 700;
      color: rgba(255,255,255,.78);
    }
    .analytics-hero h1 {
      color: #fff;
      font-size: clamp(1.8rem, 3vw, 2.5rem);
      font-weight: 800;
      line-height: 1.08;
      margin: .4rem 0 .75rem;
    }
    .analytics-hero p {
      color: rgba(255,255,255,.84);
      max-width: 700px;
      margin: 0;
    }
    .hero-panel {
      background: rgba(255,255,255,.92);
      border-radius: 22px;
      padding: 1.2rem;
      color: #143661;
      box-shadow: 0 20px 48px rgba(8, 21, 42, .15);
    }
    .hero-panel .label {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: #63789a;
      font-weight: 700;
    }
    .hero-panel .value {
      font-size: 2rem;
      font-weight: 800;
      line-height: 1;
      margin-top: .4rem;
    }
    .metric-card {
      border: 0;
      background: linear-gradient(180deg, #fff 0%, #f6f9ff 100%);
    }
    .metric-icon {
      width: 48px;
      height: 48px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 16px;
      background: #eaf1ff;
      color: #1b4b92;
      font-size: 1.25rem;
    }
    .metric-label {
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #7283a2;
      font-weight: 700;
    }
    .metric-value {
      font-size: 1.9rem;
      font-weight: 800;
      line-height: 1;
      color: #163b69;
    }
    .metric-sub {
      color: #7283a2;
      font-size: .88rem;
      margin-top: .35rem;
    }
    .recommendation-list {
      display: grid;
      gap: .9rem;
    }
    .recommendation-card {
      border-radius: 18px;
      padding: 1rem 1.1rem;
      border: 1px solid rgba(0,0,0,.06);
      background: #fff;
    }
    .recommendation-card.warning { border-left: 5px solid #f59e0b; }
    .recommendation-card.danger { border-left: 5px solid #ef4444; }
    .recommendation-card.info { border-left: 5px solid #2563eb; }
    .recommendation-card.success { border-left: 5px solid #16a34a; }
    .recommendation-title {
      font-weight: 800;
      color: #17375f;
      margin-bottom: .25rem;
    }
    .recommendation-detail {
      color: #6f82a1;
      margin: 0;
    }
    .mix-table th,
    .workload-table th {
      font-size: .74rem;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #7384a2;
    }
    .share-bar {
      height: 8px;
      border-radius: 999px;
      background: #e8eef7;
      overflow: hidden;
      min-width: 140px;
    }
    .share-bar > span {
      display: block;
      height: 100%;
      background: linear-gradient(90deg, #1d5eff 0%, #29b3ff 100%);
    }
    .workload-name {
      font-weight: 700;
      color: #16385f;
    }
    .analytics-refresh {
      color: #6b7e9e;
      font-size: .9rem;
    }
    #anaTripByCustomerChart {
      min-height: 320px;
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
      <div class="body-wrapper-inner analytics-page">
        <div class="container-fluid">
          <div class="card analytics-hero mb-4">
            <div class="card-body p-4 p-lg-5">
              <div class="row align-items-center g-4">
                <div class="col-lg-8">
                  <div class="kicker">Dispatch Admin Analytics</div>
                  <h1>Decision support for booking pressure, driver capacity, and operational backlog.</h1>
                  <p>This page is designed to help Dispatch Admin decide when dispatching is under pressure, where capacity is short, and which issues need escalation first.</p>
                </div>
                <div class="col-lg-4">
                  <div class="hero-panel">
                    <div class="label">Last refresh</div>
                    <div class="value" id="anaLastRefresh">--:--:--</div>
                    <div class="analytics-refresh mt-2">Auto-refresh every 30 seconds.</div>
                    <div class="d-flex gap-2 mt-3">
                      <a href="dispatch-admin-dashboard" class="btn btn-primary btn-sm">Operations</a>
                      <a href="dispatch-tracking" class="btn btn-outline-primary btn-sm">Tracking</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
              <div class="card metric-card h-100"><div class="card-body">
                <div class="d-flex align-items-start">
                  <span class="metric-icon"><i class="ti ti-clock-exclamation"></i></span>
                  <div class="ms-3">
                    <div class="metric-label">Pending Bookings</div>
                    <div class="metric-value" id="anaPendingBookings">0</div>
                    <div class="metric-sub">Waiting for first dispatch</div>
                  </div>
                </div>
              </div></div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="card metric-card h-100"><div class="card-body">
                <div class="d-flex align-items-start">
                  <span class="metric-icon"><i class="ti ti-alert-circle"></i></span>
                  <div class="ms-3">
                    <div class="metric-label">Overdue Bookings</div>
                    <div class="metric-value" id="anaOverdueBookings">0</div>
                    <div class="metric-sub">Past required date</div>
                  </div>
                </div>
              </div></div>
            </div>
            <div class="col-md-6 col-xl-2">
              <div class="card metric-card h-100"><div class="card-body">
                <div class="d-flex align-items-start">
                  <span class="metric-icon"><i class="ti ti-route-2"></i></span>
                  <div class="ms-3">
                    <div class="metric-label">Active Dispatches</div>
                    <div class="metric-value" id="anaActiveDispatches">0</div>
                  </div>
                </div>
              </div></div>
            </div>
            <div class="col-md-6 col-xl-2">
              <div class="card metric-card h-100"><div class="card-body">
                <div class="d-flex align-items-start">
                  <span class="metric-icon"><i class="ti ti-checkup-list"></i></span>
                  <div class="ms-3">
                    <div class="metric-label">Verify Queue</div>
                    <div class="metric-value" id="anaVerifyQueue">0</div>
                  </div>
                </div>
              </div></div>
            </div>
            <div class="col-md-6 col-xl-2">
              <div class="card metric-card h-100"><div class="card-body">
                <div class="d-flex align-items-start">
                  <span class="metric-icon"><i class="ti ti-barrier-block"></i></span>
                  <div class="ms-3">
                    <div class="metric-label">Gate Pending</div>
                    <div class="metric-value" id="anaGateQueue">0</div>
                  </div>
                </div>
              </div></div>
            </div>
          </div>

          <div class="row g-4">
            <div class="col-xl-4">
              <div class="card h-100">
                <div class="card-body">
                  <h4 class="card-title mb-1">Recommended Focus</h4>
                  <p class="card-subtitle mb-3">Actionable signals for dispatch-admin review.</p>
                  <div id="anaRecommendations" class="recommendation-list"></div>
                </div>
              </div>
            </div>

            <div class="col-xl-8">
              <div class="card h-100">
                <div class="card-body">
                  <h4 class="card-title mb-1">Customer Demand Mix</h4>
                  <p class="card-subtitle mb-3">Which customers are driving current booking pressure.</p>
                  <div class="table-responsive">
                    <table class="table align-middle mix-table mb-0">
                      <thead>
                        <tr>
                          <th>Customer</th>
                          <th>Active</th>
                          <th>Pending</th>
                          <th>Overdue</th>
                          <th>Remaining Qty</th>
                          <th>Demand Share</th>
                        </tr>
                      </thead>
                      <tbody id="anaCustomerMixRows">
                        <tr><td colspan="6" class="text-center text-muted py-4">Loading analytics...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-4 mt-1">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title mb-1">Trips by Customer</h4>
                  <p class="card-subtitle mb-3">Top customers by trip volume, split into done and pending trips.</p>
                  <div id="anaTripByCustomerChart"></div>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title mb-1">Driver Workload</h4>
                  <p class="card-subtitle mb-3">See who is available, overloaded, or blocked before assigning more work.</p>
                  <div class="table-responsive">
                    <table class="table align-middle workload-table mb-0">
                      <thead>
                        <tr>
                          <th>Driver</th>
                          <th>Truck</th>
                          <th>Segment</th>
                          <th>Active Trips</th>
                          <th>Status</th>
                          <th>Last Seen</th>
                        </tr>
                      </thead>
                      <tbody id="anaDriverWorkloadRows">
                        <tr><td colspan="6" class="text-center text-muted py-4">Loading driver workload...</td></tr>
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
  <script src="assets/libs/apexcharts/dist/apexcharts.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script>
    let anaTripChart = null;

    function escHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function statusChip(row) {
      if (row.blocked) return '<span class="badge bg-danger">Blocked</span>';
      if (row.available) return '<span class="badge bg-success">Available</span>';
      if ((row.active_trips || 0) >= 2) return '<span class="badge bg-warning text-dark">Heavy Load</span>';
      return '<span class="badge bg-primary">Assigned</span>';
    }

    function renderRecommendations(rows) {
      const host = document.getElementById('anaRecommendations');
      host.innerHTML = rows.map(function (row) {
        return '' +
          '<div class="recommendation-card ' + escHtml(row.severity) + '">' +
            '<div class="recommendation-title">' + escHtml(row.title) + '</div>' +
            '<p class="recommendation-detail">' + escHtml(row.detail) + '</p>' +
          '</div>';
      }).join('');
    }

    function renderCustomerMix(rows, totalActive) {
      const host = document.getElementById('anaCustomerMixRows');
      if (!rows.length) {
        host.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No booking analytics available.</td></tr>';
        return;
      }
      host.innerHTML = rows.map(function (row) {
        var share = totalActive > 0 ? ((row.booking_count / totalActive) * 100) : 0;
        return '' +
          '<tr>' +
            '<td><strong>' + escHtml(row.customer) + '</strong></td>' +
            '<td>' + escHtml(row.booking_count) + '</td>' +
            '<td>' + escHtml(row.pending_count) + '</td>' +
            '<td>' + escHtml(row.overdue_count) + '</td>' +
            '<td>' + escHtml(row.remaining_slots) + '</td>' +
            '<td>' +
              '<div class="d-flex align-items-center gap-2">' +
                '<div class="share-bar"><span style="width:' + share.toFixed(1) + '%"></span></div>' +
                '<small class="text-muted">' + share.toFixed(1) + '%</small>' +
              '</div>' +
            '</td>' +
          '</tr>';
      }).join('');
    }

    function renderDriverWorkload(rows) {
      const host = document.getElementById('anaDriverWorkloadRows');
      if (!rows.length) {
        host.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No driver workload found.</td></tr>';
        return;
      }
      host.innerHTML = rows.map(function (row) {
        return '' +
          '<tr>' +
            '<td><div class="workload-name">' + escHtml(row.driver_name) + '</div></td>' +
            '<td>' + escHtml(row.shift_truck || '-') + '</td>' +
            '<td>' + escHtml(row.segment || '-') + '</td>' +
            '<td>' + escHtml(row.active_trips) + '</td>' +
            '<td>' + statusChip(row) + '</td>' +
            '<td class="text-muted">' + escHtml(row.last_seen_at || 'No timestamp') + '</td>' +
          '</tr>';
        }).join('');
    }

    function renderTripByCustomerChart(rows) {
      const categories = rows.map(function (row) { return row.customer; });
      const doneSeries = rows.map(function (row) { return row.done_trips || 0; });
      const pendingSeries = rows.map(function (row) { return row.pending_trips || 0; });

      const options = {
        chart: {
          type: 'bar',
          height: 320,
          stacked: true,
          toolbar: { show: false },
          animations: { easing: 'easeinout', speed: 350 }
        },
        series: [
          { name: 'Done', data: doneSeries },
          { name: 'Pending', data: pendingSeries }
        ],
        colors: ['#16a34a', '#2563eb'],
        plotOptions: {
          bar: {
            horizontal: false,
            borderRadius: 6,
            columnWidth: '48%'
          }
        },
        dataLabels: { enabled: false },
        stroke: { width: 0 },
        xaxis: {
          categories: categories,
          labels: {
            rotate: -25,
            style: { colors: '#64748b', fontSize: '12px' }
          }
        },
        yaxis: {
          labels: {
            style: { colors: '#64748b', fontSize: '12px' }
          }
        },
        legend: {
          position: 'top',
          horizontalAlign: 'left'
        },
        grid: {
          borderColor: '#e6edf7'
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val + ' trips';
            }
          }
        },
        noData: {
          text: 'No trip data available'
        }
      };

      if (!anaTripChart) {
        anaTripChart = new ApexCharts(document.querySelector('#anaTripByCustomerChart'), options);
        anaTripChart.render();
      } else {
        anaTripChart.updateOptions({
          xaxis: { categories: categories }
        }, false, false);
        anaTripChart.updateSeries(options.series, true);
      }
    }

    function loadDispatchAdminAnalytics() {
      fetch('php/fetch/dispatch_admin_analytics.php')
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.status !== 'success') {
            throw new Error(data.message || 'Failed to load analytics');
          }
          const counts = data.counts || {};
          document.getElementById('anaPendingBookings').textContent = counts.pending_bookings || 0;
          document.getElementById('anaOverdueBookings').textContent = counts.overdue_bookings || 0;
          document.getElementById('anaActiveDispatches').textContent = counts.active_dispatches || 0;
          document.getElementById('anaVerifyQueue').textContent = counts.pending_verifications || 0;
          document.getElementById('anaGateQueue').textContent = counts.gate_queue_pending || 0;
          document.getElementById('anaLastRefresh').textContent = escHtml((data.fetched_at || '').split(' ')[1] || '--:--:--');

          renderRecommendations(data.recommendations || []);
          renderCustomerMix(data.customer_mix || [], counts.active_bookings || 0);
          renderTripByCustomerChart(data.trip_by_customer || []);
          renderDriverWorkload(data.driver_workload || []);
        })
        .catch(function (err) {
          document.getElementById('anaRecommendations').innerHTML = '<div class="recommendation-card danger"><div class="recommendation-title">Analytics load failed</div><p class="recommendation-detail">' + escHtml(err.message) + '</p></div>';
          document.getElementById('anaCustomerMixRows').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">' + escHtml(err.message) + '</td></tr>';
          document.getElementById('anaDriverWorkloadRows').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">' + escHtml(err.message) + '</td></tr>';
        });
    }

    loadDispatchAdminAnalytics();
    setInterval(loadDispatchAdminAnalytics, 30000);
  </script>
</body>
</html>
<?php
} else {
  header("Location: dispatcher-index.php?route=login");
  exit();
}
?>
