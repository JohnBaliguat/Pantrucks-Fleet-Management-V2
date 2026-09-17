<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Visual") {
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analytics Dashboard</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <style>
    .visual-manager-card {
      border: 1px solid rgba(18, 36, 67, 0.08);
      border-radius: 1rem;
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .visual-manager-grid {
      display: grid;
      gap: 0.85rem;
    }
    .visual-recommendation {
      border-radius: 18px;
      padding: 1rem 1.1rem;
      background: #fff;
      border: 1px solid rgba(0, 0, 0, 0.06);
    }
    .visual-recommendation.warning { border-left: 5px solid #f59e0b; }
    .visual-recommendation.danger { border-left: 5px solid #ef4444; }
    .visual-recommendation.info { border-left: 5px solid #2563eb; }
    .visual-recommendation.success { border-left: 5px solid #16a34a; }
    .visual-recommendation-title {
      font-weight: 800;
      color: #17375f;
      margin-bottom: 0.25rem;
    }
    .visual-recommendation-copy {
      margin: 0;
      color: #6f82a1;
      line-height: 1.45;
    }
    .visual-signal-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.9rem;
    }
    .visual-signal {
      border-radius: 16px;
      padding: 1rem;
      background: #eef5ff;
    }
    .visual-signal-label {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #7283a2;
      font-weight: 700;
    }
    .visual-signal-value {
      font-size: 1.9rem;
      font-weight: 800;
      line-height: 1;
      color: #17375f;
      margin-top: .45rem;
    }
    .visual-signal-note {
      color: #6f82a1;
      font-size: .88rem;
      margin-top: .35rem;
    }
    @media (max-width: 575px) {
      .visual-signal-grid {
        grid-template-columns: 1fr;
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
      <div class="body-wrapper-inner">
        <div class="container-fluid visual-dashboard-page">
          <div class="visual-hero card border-0 mb-4">
            <div class="card-body">
              <div class="row g-4 align-items-center">
                <div class="col-xl-7">
                  <span class="visual-hero-kicker">Manager Analytics View</span>
                  <h1 class="visual-hero-title">Track fleet health, trip output, and operational load from one board.</h1>
                  <p class="visual-hero-copy">
                    This view is tuned for management review: equipment capacity, dispatch throughput, driver output,
                    and maintenance pressure in a single analytics workspace.
                  </p>
                  <div class="visual-hero-highlights">
                    <span class="visual-pill">Operations summary</span>
                    <span class="visual-pill">Driver and truck productivity</span>
                    <span class="visual-pill">Maintenance visibility</span>
                  </div>
                </div>
                <div class="col-xl-5">
                  <div class="visual-filter-card">
                    <div class="visual-filter-head">
                      <div>
                        <div class="visual-section-kicker">Filter Window</div>
                        <h5 class="mb-1">Refresh analytics</h5>
                        <p class="mb-0 text-muted">Apply a date window to the operational charts and summary cards.</p>
                      </div>
                    </div>
                    <form id="filterForm" class="row g-3">
                      <div class="col-md-6">
                        <label for="fromDate" class="form-label">From</label>
                        <input type="date" id="fromDate" name="fromDate" class="form-control">
                      </div>
                      <div class="col-md-6">
                        <label for="toDate" class="form-label">To</label>
                        <input type="date" id="toDate" name="toDate" class="form-control">
                      </div>
                      <div class="col-12 d-grid">
                        <button type="button" id="submit" class="btn btn-primary visual-generate-btn">Apply Analytics Range</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4 visual-metric-grid">
            <div class="col-md-6 col-xl-2">
              <div class="visual-metric-card metric-blue">
                <div class="visual-metric-icon"><i class="ti ti-truck"></i></div>
                <div class="visual-metric-body">
                  <div class="visual-metric-label">Trucks</div>
                  <div class="visual-metric-value" id="truckCount">0</div>
                  <div class="visual-metric-note">Total powered units</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-2">
              <div class="visual-metric-card metric-cyan">
                <div class="visual-metric-icon"><i class="ti ti-box"></i></div>
                <div class="visual-metric-body">
                  <div class="visual-metric-label">Trailers</div>
                  <div class="visual-metric-value" id="trailerCount">0</div>
                  <div class="visual-metric-note">Support equipment pool</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-2">
              <div class="visual-metric-card metric-amber">
                <div class="visual-metric-icon"><i class="ti ti-database"></i></div>
                <div class="visual-metric-body">
                  <div class="visual-metric-label">Gensets</div>
                  <div class="visual-metric-value" id="gensetCount">0</div>
                  <div class="visual-metric-note">Temperature-control support</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-2">
              <div class="visual-metric-card metric-violet">
                <div class="visual-metric-icon"><i class="ti ti-users"></i></div>
                <div class="visual-metric-body">
                  <div class="visual-metric-label">Drivers</div>
                  <div class="visual-metric-value" id="driverCount">0</div>
                  <div class="visual-metric-note">Registered driver headcount</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-2">
              <div class="visual-metric-card metric-green">
                <div class="visual-metric-icon"><i class="ti ti-route-2"></i></div>
                <div class="visual-metric-body">
                  <div class="visual-metric-label">Completed Trips</div>
                  <div class="visual-metric-value" id="doneTripCount">0</div>
                  <div class="visual-metric-note">Done trips in selected range</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-2">
              <div class="visual-metric-card metric-rose">
                <div class="visual-metric-icon"><i class="ti ti-alert-triangle"></i></div>
                <div class="visual-metric-body">
                  <div class="visual-metric-label">In-Flight Dispatches</div>
                  <div class="visual-metric-value" id="activeDispatchCount">0</div>
                  <div class="visual-metric-note">Still moving through workflow</div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-4 mb-4">
            <div class="col-xl-7">
              <div class="card visual-manager-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Manager Focus</div>
                  <h4 class="visual-card-title">Recommended Actions</h4>
                  <p class="visual-card-copy">A decision layer on top of your existing charts so managers can see what needs attention first.</p>
                  <div id="managerRecommendations" class="visual-manager-grid">
                    <div class="visual-recommendation info">
                      <div class="visual-recommendation-title">Loading recommendations</div>
                      <p class="visual-recommendation-copy">Checking booking pressure, verification queue, incidents, and available drivers.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xl-5">
              <div class="card visual-manager-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Decision Signals</div>
                  <h4 class="visual-card-title">Operational Pressure</h4>
                  <p class="visual-card-copy">These metrics help managers decide where dispatch attention should move next.</p>
                  <div class="visual-signal-grid">
                    <div class="visual-signal">
                      <div class="visual-signal-label">Pending Bookings</div>
                      <div class="visual-signal-value" id="mgrPendingBookings">0</div>
                      <div class="visual-signal-note">Waiting for first dispatch</div>
                    </div>
                    <div class="visual-signal">
                      <div class="visual-signal-label">Overdue Bookings</div>
                      <div class="visual-signal-value" id="mgrOverdueBookings">0</div>
                      <div class="visual-signal-note">Past required date</div>
                    </div>
                    <div class="visual-signal">
                      <div class="visual-signal-label">Available Drivers</div>
                      <div class="visual-signal-value" id="mgrAvailableDrivers">0</div>
                      <div class="visual-signal-note">Ready for dispatch</div>
                    </div>
                    <div class="visual-signal">
                      <div class="visual-signal-label">Pending Verification</div>
                      <div class="visual-signal-value" id="mgrPendingVerify">0</div>
                      <div class="visual-signal-note">Potential billing delay</div>
                    </div>
                    <div class="visual-signal">
                      <div class="visual-signal-label">Open Incidents</div>
                      <div class="visual-signal-value" id="mgrOpenIncidents">0</div>
                      <div class="visual-signal-note">Needs supervision</div>
                    </div>
                    <div class="visual-signal">
                      <div class="visual-signal-label">Gate Queue Pending</div>
                      <div class="visual-signal-value" id="mgrGatePending">0</div>
                      <div class="visual-signal-note">Outbound friction</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-4">
            <div class="col-xl-4">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Asset Mix</div>
                  <h4 class="visual-card-title">Fleet Composition</h4>
                  <p class="visual-card-copy">Quick ratio of trucks, trailers, and gensets currently in the system.</p>
                  <div id="chart"></div>
                </div>
              </div>
            </div>
            <div class="col-xl-8">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Throughput Trend</div>
                  <h4 class="visual-card-title">Trips vs Used Trucks</h4>
                  <p class="visual-card-copy">Compare trip output against actual truck usage over time to spot over- or under-utilization.</p>
                  <div id="chart1"></div>
                </div>
              </div>
            </div>

            <div class="col-xl-6">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Driver Output</div>
                  <h4 class="visual-card-title">Top Drivers by Completed Trips</h4>
                  <p class="visual-card-copy">Highlights the most productive drivers in the selected range.</p>
                  <div id="chart5"></div>
                </div>
              </div>
            </div>
            <div class="col-xl-6">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Truck Output</div>
                  <h4 class="visual-card-title">Top Trucks by Completed Trips</h4>
                  <p class="visual-card-copy">Shows which trucks carried the highest completed-trip load.</p>
                  <div id="chart6"></div>
                </div>
              </div>
            </div>

            
            <div class="col-8">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Route Demand</div>
                  <h4 class="visual-card-title">Trips by Segment</h4>
                  <p class="visual-card-copy">Shows which hauling segments are carrying the most trip volume in the selected period.</p>
                  <div id="chart7"></div>
                </div>
              </div>
            </div>
            <div class="col-xl-4">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                      <div class="visual-section-kicker">Customer Mix</div>
                      <h4 class="visual-card-title">Trips by Customer</h4>
                      <p class="visual-card-copy">Click a customer slice to focus the segment chart on that customer.</p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="resetCustomerFilter" style="display:none;">Reset Customer</button>
                  </div>
                  <div id="chart8"></div>
                  <div class="small text-muted mt-2" id="customerSelectionState">Showing all customers.</div>
                </div>
              </div>
            </div>

            <div class="col-xl-3 col-md-6">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Utilization</div>
                  <h4 class="visual-card-title">Truck Utilization</h4>
                  <p class="visual-card-copy">Percentage of available truck fleet that was actually used.</p>
                  <div id="chart2"></div>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-md-6">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Utilization</div>
                  <h4 class="visual-card-title">Trailer Utilization</h4>
                  <p class="visual-card-copy">Trailer pool usage for planning extra support or balancing allocations.</p>
                  <div id="chart3"></div>
                </div>
              </div>
            </div>
            <div class="col-xl-6">
              <div class="card visual-analytics-card h-100">
                <div class="card-body">
                  <div class="visual-section-kicker">Maintenance Load</div>
                  <h4 class="visual-card-title">Shop vs Rescue Unit Movement</h4>
                  <p class="visual-card-copy">Track how many units went through shop work compared to rescue operations per day.</p>
                  <div id="chart4"></div>
                </div>
              </div>
            </div>
          </div>

          <div class="py-6 px-6 text-center">
            <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
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
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

  <script>
    let fleetMixChart;
    let tripsVsTruckChart;
    let truckUtilChart;
    let trailerUtilChart;
    let shopRescueChart;
    let topDriversChart;
    let topTrucksChart;
    let tripsBySegmentChart;
    let tripsByCustomerChart;
    let selectedCustomer = '';

    function loadManagerRecommendations() {
      $.ajax({
        url: 'php/fetch/visual_manager_recommendations.php',
        type: 'GET',
        dataType: 'json',
        data: currentFilters(),
        success: function(res) {
          if (res.status !== 'success') return;

          var counts = res.counts || {};
          $('#mgrPendingBookings').text(counts.pending_bookings || 0);
          $('#mgrOverdueBookings').text(counts.overdue_bookings || 0);
          $('#mgrAvailableDrivers').text(counts.available_drivers || 0);
          $('#mgrPendingVerify').text(counts.pending_verifications || 0);
          $('#mgrOpenIncidents').text(counts.open_incidents || 0);
          $('#mgrGatePending').text(counts.gate_queue_pending || 0);

          var html = (res.recommendations || []).map(function(item) {
            return '' +
              '<div class="visual-recommendation ' + escHtml(item.severity || 'info') + '">' +
                '<div class="visual-recommendation-title">' + escHtml(item.title || 'Recommendation') + '</div>' +
                '<p class="visual-recommendation-copy">' + escHtml(item.detail || '') + '</p>' +
              '</div>';
          }).join('');

          $('#managerRecommendations').html(html || (
            '<div class="visual-recommendation success">' +
              '<div class="visual-recommendation-title">No critical recommendation</div>' +
              '<p class="visual-recommendation-copy">The selected window looks balanced.</p>' +
            '</div>'
          ));
        }
      });
    }
    let isTripsChartFirstLoad = true;

    function currentFilters() {
      return {
        fromDate: $('#fromDate').val() || '',
        toDate: $('#toDate').val() || '',
        costumer: selectedCustomer || ''
      };
    }

    function loadUnitCounts() {
      $.ajax({
        url: 'php/fetch/getUnitCounts.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
          $('#truckCount').text(res.truck || 0);
          $('#gensetCount').text(res.genset || 0);
          $('#trailerCount').text(res.trailer || 0);
        }
      });
    }

    function loadManagerSummary() {
      $.ajax({
        url: 'php/fetch/visual_dashboard_summary.php',
        type: 'GET',
        dataType: 'json',
        data: currentFilters(),
        success: function(res) {
          $('#driverCount').text(res.driver_count || 0);
          $('#doneTripCount').text(res.done_trip_count || 0);
          $('#activeDispatchCount').text(res.active_dispatch_count || 0);
        }
      });
    }

    function loadFleetMix() {
      $.ajax({
        url: 'chartjs/getUnitsSummary.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
          const options = {
            series: [data.truck || 0, data.trailer || 0, data.genset || 0],
            chart: {
              height: 320,
              type: 'donut'
            },
            labels: ['Truck', 'Trailer', 'Genset'],
            colors: ['#2563eb', '#06b6d4', '#f59e0b'],
            legend: {
              position: 'bottom'
            },
            dataLabels: {
              enabled: true
            },
            responsive: [{
              breakpoint: 768,
              options: {
                chart: { height: 280 }
              }
            }]
          };

          if (!fleetMixChart) {
            fleetMixChart = new ApexCharts(document.querySelector("#chart"), options);
            fleetMixChart.render();
          } else {
            fleetMixChart.updateSeries(options.series);
          }
        }
      });
    }

    function loadTripsVsTruck() {
      const filters = currentFilters();
      $.ajax({
        url: 'chartjs/getTripsVsTruck.php',
        type: 'GET',
        dataType: 'json',
        data: filters,
        success: function(res) {
          const series = [
            {
              name: 'Trips',
              type: 'column',
              data: (res.dates || []).map((d, i) => ({ x: new Date(d).getTime(), y: res.trips[i] || 0 }))
            },
            {
              name: 'Used Truck',
              type: 'line',
              data: (res.dates || []).map((d, i) => ({ x: new Date(d).getTime(), y: res.trucks[i] || 0 }))
            }
          ];

          if (!tripsVsTruckChart) {
            const now = new Date();
            const monthStart = new Date(now.getFullYear(), now.getMonth(), 1).getTime();
            const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0).getTime();

            tripsVsTruckChart = new ApexCharts(document.querySelector("#chart1"), {
              series: series,
              chart: {
                height: 340,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: true, autoScaleYaxis: true }
              },
              stroke: { width: [0, 4], curve: 'smooth' },
              dataLabels: { enabled: true, enabledOnSeries: [1] },
              xaxis: {
                type: 'datetime',
                min: monthStart,
                max: monthEnd
              },
              colors: ['#2563eb', '#16a34a'],
              legend: { position: 'top' }
            });
            tripsVsTruckChart.render();
          } else {
            tripsVsTruckChart.updateSeries(series);
            if (isTripsChartFirstLoad) {
              tripsVsTruckChart.updateOptions({ xaxis: { min: undefined, max: undefined } });
              isTripsChartFirstLoad = false;
            }
          }
        }
      });
    }

    function loadTruckUtilization() {
      $.ajax({
        url: 'chartjs/getTruckUtilization.php',
        type: 'GET',
        dataType: 'json',
        data: currentFilters(),
        success: function(res) {
          const series = [res.utilization || 0];
          if (!truckUtilChart) {
            truckUtilChart = new ApexCharts(document.querySelector("#chart2"), {
              series: series,
              chart: { type: 'radialBar', height: 300, offsetY: -10, sparkline: { enabled: true } },
              plotOptions: {
                radialBar: {
                  startAngle: -90,
                  endAngle: 90,
                  track: { background: "#edf2ff", strokeWidth: '97%', margin: 5 },
                  dataLabels: {
                    name: { show: false },
                    value: {
                      formatter: function(val) { return val + "%"; },
                      offsetY: -2,
                      fontSize: '24px',
                      fontWeight: 700
                    }
                  }
                }
              },
              fill: { colors: ['#2563eb'] },
              labels: ['Truck Utilization']
            });
            truckUtilChart.render();
          } else {
            truckUtilChart.updateSeries(series);
          }
        }
      });
    }

    function loadTrailerUtilization() {
      $.ajax({
        url: 'chartjs/getTrailerUtilization.php',
        type: 'GET',
        dataType: 'json',
        data: currentFilters(),
        success: function(res) {
          const series = [res.utilization || 0];
          if (!trailerUtilChart) {
            trailerUtilChart = new ApexCharts(document.querySelector("#chart3"), {
              series: series,
              chart: { type: 'radialBar', height: 300, offsetY: -10, sparkline: { enabled: true } },
              plotOptions: {
                radialBar: {
                  startAngle: -90,
                  endAngle: 90,
                  track: { background: "#eefdf5", strokeWidth: '97%', margin: 5 },
                  dataLabels: {
                    name: { show: false },
                    value: {
                      formatter: function(val) { return val + "%"; },
                      offsetY: -2,
                      fontSize: '24px',
                      fontWeight: 700
                    }
                  }
                }
              },
              fill: { colors: ['#16a34a'] },
              labels: ['Trailer Utilization']
            });
            trailerUtilChart.render();
          } else {
            trailerUtilChart.updateSeries(series);
          }
        }
      });
    }

    function loadShopRescueChart() {
      $.ajax({
        url: 'chartjs/getShopRescueCount.php',
        type: 'GET',
        dataType: 'json',
        data: currentFilters(),
        success: function(res) {
          const series = [
            { name: 'Shop Unit', data: res.shop || [] },
            { name: 'Rescue Unit', data: res.rescue || [] }
          ];

          if (!shopRescueChart) {
            shopRescueChart = new ApexCharts(document.querySelector("#chart4"), {
              series: series,
              chart: { height: 340, type: 'line', toolbar: { show: false }, zoom: { enabled: true } },
              stroke: { curve: 'smooth', width: 3 },
              dataLabels: { enabled: true },
              xaxis: { categories: res.dates || [], title: { text: 'Date' } },
              yaxis: { title: { text: 'Number of Units' }, min: 0 },
              colors: ['#0f766e', '#dc2626'],
              legend: { position: 'top' }
            });
            shopRescueChart.render();
          } else {
            shopRescueChart.updateOptions({ xaxis: { categories: res.dates || [] } });
            shopRescueChart.updateSeries(series);
          }
        }
      });
    }

    function loadTopDriversChart() {
      $.ajax({
        url: 'chartjs/get_drivers_done_trips.php',
        type: 'GET',
        dataType: 'json',
        data: currentFilters(),
        success: function(res) {
          const top = (res || []).slice(0, 8);
          const categories = top.map(item => item.driver_name || '-');
          const data = top.map(item => item.done_trips || 0);

          if (!topDriversChart) {
            topDriversChart = new ApexCharts(document.querySelector("#chart5"), {
              series: [{ name: 'Done Trips', data: data }],
              chart: { type: 'bar', height: 320, toolbar: { show: false } },
              plotOptions: { bar: { borderRadius: 8, horizontal: true, distributed: true } },
              colors: ['#2563eb', '#3b82f6', '#60a5fa', '#22c55e', '#f59e0b', '#a855f7', '#ec4899', '#14b8a6'],
              dataLabels: { enabled: true },
              xaxis: { categories: categories }
            });
            topDriversChart.render();
          } else {
            topDriversChart.updateOptions({ xaxis: { categories: categories } });
            topDriversChart.updateSeries([{ name: 'Done Trips', data: data }]);
          }
        }
      });
    }

    function loadTopTrucksChart() {
      $.ajax({
        url: 'chartjs/get_units_done_trips.php',
        type: 'GET',
        dataType: 'json',
        data: currentFilters(),
        success: function(res) {
          const top = (res || []).slice(0, 8);
          const categories = top.map(item => item.unit_name || '-');
          const data = top.map(item => item.done_trips || 0);

          if (!topTrucksChart) {
            topTrucksChart = new ApexCharts(document.querySelector("#chart6"), {
              series: [{ name: 'Done Trips', data: data }],
              chart: { type: 'bar', height: 320, toolbar: { show: false } },
              plotOptions: { bar: { borderRadius: 8, columnWidth: '48%' } },
              colors: ['#0f766e'],
              dataLabels: { enabled: true },
              xaxis: { categories: categories }
            });
            topTrucksChart.render();
          } else {
            topTrucksChart.updateOptions({ xaxis: { categories: categories } });
            topTrucksChart.updateSeries([{ name: 'Done Trips', data: data }]);
          }
        }
      });
    }

    function loadTripsBySegmentChart() {
      $.ajax({
        url: 'chartjs/get_trips_by_segment.php',
        type: 'GET',
        dataType: 'json',
        data: currentFilters(),
        success: function(res) {
          const categories = (res || []).map(item => item.segment || '-');
          const data = (res || []).map(item => item.trip_count || 0);

          if (!tripsBySegmentChart) {
            tripsBySegmentChart = new ApexCharts(document.querySelector("#chart7"), {
              series: [{ name: 'Trips', data: data }],
              chart: { type: 'bar', height: 320, toolbar: { show: false } },
              plotOptions: { bar: { borderRadius: 8, horizontal: false, columnWidth: '52%' } },
              colors: ['#7c3aed'],
              dataLabels: { enabled: true },
              xaxis: {
                categories: categories,
                labels: {
                  rotate: -25,
                  trim: true
                }
              },
              yaxis: {
                title: { text: 'Trip Count' },
                min: 0
              },
              noData: { text: 'No segment trip data available' }
            });
            tripsBySegmentChart.render();
          } else {
            tripsBySegmentChart.updateOptions({
              xaxis: { categories: categories }
            });
            tripsBySegmentChart.updateSeries([{ name: 'Trips', data: data }]);
          }
        }
      });
    }

    function updateCustomerSelectionUi() {
      if (selectedCustomer) {
        $('#customerSelectionState').text('Filtered to customer: ' + selectedCustomer);
        $('#resetCustomerFilter').show();
      } else {
        $('#customerSelectionState').text('Showing all customers.');
        $('#resetCustomerFilter').hide();
      }
    }

    function loadTripsByCustomerChart() {
      $.ajax({
        url: 'chartjs/get_trips_by_customer.php',
        type: 'GET',
        dataType: 'json',
        data: {
          fromDate: $('#fromDate').val() || '',
          toDate: $('#toDate').val() || ''
        },
        success: function(res) {
          const labels = (res || []).map(item => item.customer || 'Unknown');
          const series = (res || []).map(item => item.trip_count || 0);

          if (!tripsByCustomerChart) {
            tripsByCustomerChart = new ApexCharts(document.querySelector("#chart8"), {
              series: series,
              labels: labels,
              chart: {
                type: 'pie',
                height: 320,
                events: {
                  dataPointSelection: function(event, chartContext, config) {
                    const index = config.dataPointIndex;
                    if (index < 0 || !labels[index]) return;
                    selectedCustomer = labels[index];
                    updateCustomerSelectionUi();
                    loadTripsBySegmentChart();
                  }
                }
              },
              legend: {
                position: 'bottom'
              },
              dataLabels: {
                enabled: true
              },
              noData: {
                text: 'No customer trip data available'
              }
            });
            tripsByCustomerChart.render();
          } else {
            tripsByCustomerChart.updateOptions({
              labels: labels
            });
            tripsByCustomerChart.updateSeries(series);
          }
        }
      });
    }

    function reloadVisualDashboard() {
      loadUnitCounts();
      loadManagerSummary();
      loadManagerRecommendations();
      loadFleetMix();
      loadTripsVsTruck();
      loadTruckUtilization();
      loadTrailerUtilization();
      loadShopRescueChart();
      loadTopDriversChart();
      loadTopTrucksChart();
      loadTripsByCustomerChart();
      loadTripsBySegmentChart();
      updateCustomerSelectionUi();
    }

    reloadVisualDashboard();

    $('#submit').on('click', function() {
      selectedCustomer = '';
      reloadVisualDashboard();
    });

    $('#resetCustomerFilter').on('click', function() {
      selectedCustomer = '';
      updateCustomerSelectionUi();
      loadTripsBySegmentChart();
    });
  </script>
</body>
</html>
<?php
} else {
  header("Location: visual-index.php?route=login");
  exit();
}
?>
