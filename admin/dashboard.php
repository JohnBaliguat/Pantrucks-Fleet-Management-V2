<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {


?>
  <!doctype html>
  <html lang="en">

    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Dashboard</title>
      <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
      <link rel="stylesheet" href="assets/css/styles.min.css" />
      <link rel="stylesheet" href="assets/css/enhancements.css" />
      <style>
        .admin-dashboard {
          --dash-navy: #10233f;
          --dash-blue: #1f5eff;
          --dash-sky: #eaf2ff;
          --dash-ink: #10233f;
          --dash-muted: #6b7a90;
          --dash-line: rgba(16, 35, 63, 0.08);
        }
        .admin-dashboard .dashboard-hero {
          background:
            radial-gradient(circle at top right, rgba(31, 94, 255, 0.16), transparent 30%),
            linear-gradient(135deg, #ffffff 0%, #f4f8ff 55%, #eef4ff 100%);
          border: 1px solid var(--dash-line);
          border-radius: 24px;
          padding: 28px;
          margin-bottom: 24px;
          box-shadow: 0 18px 44px rgba(16, 35, 63, 0.08);
        }
        .admin-dashboard .dashboard-kicker {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          padding: 6px 12px;
          border-radius: 999px;
          background: rgba(31, 94, 255, 0.10);
          color: var(--dash-blue);
          font-size: 12px;
          font-weight: 700;
          letter-spacing: .08em;
          text-transform: uppercase;
        }
        .admin-dashboard .dashboard-title {
          margin: 14px 0 8px;
          color: var(--dash-ink);
          font-size: 34px;
          line-height: 1.05;
          font-weight: 800;
        }
        .admin-dashboard .dashboard-subtitle {
          margin: 0;
          color: var(--dash-muted);
          font-size: 15px;
          max-width: 640px;
        }
        .admin-dashboard .filter-card {
          background: rgba(255,255,255,0.9);
          border: 1px solid rgba(16,35,63,0.08);
          border-radius: 18px;
          padding: 18px;
          box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
        }
        .admin-dashboard .filter-card .form-label {
          font-size: 12px;
          font-weight: 700;
          letter-spacing: .04em;
          text-transform: uppercase;
          color: var(--dash-muted);
          margin-bottom: 6px;
        }
        .admin-dashboard .filter-actions {
          display: flex;
          justify-content: flex-end;
          align-items: end;
          height: 100%;
        }
        .admin-dashboard .metric-card {
          position: relative;
          overflow: hidden;
          border: 1px solid var(--dash-line);
          border-radius: 22px;
          background: linear-gradient(180deg, #fff 0%, #f9fbff 100%);
          box-shadow: 0 14px 36px rgba(16, 35, 63, 0.08);
        }
        .admin-dashboard .metric-card::after {
          content: '';
          position: absolute;
          inset: auto 0 0 0;
          height: 4px;
          background: var(--metric-accent, #1f5eff);
        }
        .admin-dashboard .metric-card .card-body {
          padding: 20px 22px !important;
        }
        .admin-dashboard .metric-top {
          display: flex;
          align-items: center;
          gap: 14px;
        }
        .admin-dashboard .metric-icon {
          width: 54px;
          height: 54px;
          border-radius: 18px;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          background: var(--metric-bg, rgba(31, 94, 255, 0.12));
          color: var(--metric-accent, #1f5eff);
          font-size: 24px;
          flex: 0 0 auto;
        }
        .admin-dashboard .metric-label {
          font-size: 12px;
          font-weight: 800;
          letter-spacing: .08em;
          text-transform: uppercase;
          color: var(--dash-muted);
          margin-bottom: 4px;
        }
        .admin-dashboard .metric-title {
          margin: 0;
          color: var(--dash-ink);
          font-size: 18px;
          font-weight: 700;
        }
        .admin-dashboard .metric-value {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 84px;
          padding: 10px 14px;
          border-radius: 16px;
          background: var(--metric-bg, rgba(31, 94, 255, 0.12));
          color: var(--metric-accent, #1f5eff);
          font-size: 28px;
          font-weight: 800;
          line-height: 1;
          margin-left: auto;
        }
        .admin-dashboard .chart-card {
          border: 1px solid var(--dash-line);
          border-radius: 24px;
          background: linear-gradient(180deg, #fff 0%, #f9fbff 100%);
          box-shadow: 0 16px 40px rgba(16, 35, 63, 0.08);
          height: 100%;
        }
        .admin-dashboard .chart-card .card-body {
          padding: 22px 24px !important;
        }
        .admin-dashboard .row {
          --bs-gutter-y: 1.5rem;
        }
        .admin-dashboard .metrics-row {
          margin-top: 6px;
          margin-bottom: 12px;
        }
        .admin-dashboard .charts-row {
          margin-top: 10px;
          margin-bottom: 10px;
        }
        .admin-dashboard .chart-heading {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 12px;
          margin-bottom: 18px;
        }
        .admin-dashboard .chart-title {
          margin: 0;
          color: var(--dash-ink);
          font-size: 20px;
          font-weight: 800;
        }
        .admin-dashboard .chart-note {
          margin: 6px 0 0;
          color: var(--dash-muted);
          font-size: 13px;
        }
        .admin-dashboard .chart-chip {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          padding: 8px 12px;
          border-radius: 999px;
          background: var(--dash-sky);
          color: var(--dash-blue);
          font-size: 12px;
          font-weight: 700;
        }
        @media (max-width: 991px) {
          .admin-dashboard .dashboard-title { font-size: 28px; }
          .admin-dashboard .dashboard-hero { padding: 22px; }
          .admin-dashboard .metric-value {
            min-width: 72px;
            font-size: 24px;
          }
        }
        @media (max-width: 767px) {
          .admin-dashboard {
            padding-top: 92px !important;
          }
          .admin-dashboard .dashboard-hero {
            padding: 18px;
            margin-top: 0;
          }
          .admin-dashboard .dashboard-title {
            font-size: 22px;
            line-height: 1.12;
            margin-top: 10px;
          }
          .admin-dashboard .dashboard-subtitle {
            font-size: 14px;
          }
        }
      </style>
    </head>

  <body>
    <!--  Body Wrapper -->
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
      data-sidebar-position="fixed" data-header-position="fixed">

      <!--  App Topstrip -->
      <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
          <a class="d-flex justify-content-center" href="#">
            <img src="assets/images/logos/pantrucks1.png" alt="" width="122">
          </a>
        </div>

        <div class="d-lg-flex align-items-center gap-2">
          <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3>
          <div class="d-flex align-items-center justify-content-center gap-2">
          </div>
        </div>

      </div>
      <!-- Sidebar Start -->
      <?php include 'sidebar.php'; ?>
      <!--  Sidebar End -->
      <!--  Main wrapper -->
      <div class="body-wrapper">
        <!--  Header Start -->
        <?php include 'navbar.php'; ?>
        <!--  Header End -->
        <div class="body-wrapper-inner">
          <div class="container-fluid admin-dashboard">
            <div class="dashboard-hero">
              <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                  <span class="dashboard-kicker"><i class="ti ti-activity-heartbeat"></i> Operations Overview</span>
                  <h1 class="dashboard-title">Fleet performance, activity, and utilization in one place.</h1>
                  <p class="dashboard-subtitle">
                    Monitor daily volume, equipment usage, and overall fleet movement without jumping between separate reports.
                  </p>
                </div>
                <div class="col-lg-5">
                  <div class="filter-card">
                    <form id="filterForm">
                      <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                          <label for="fromDate" class="form-label">From</label>
                          <input type="date" id="fromDate" name="fromDate" class="form-control">
                        </div>
                        <div class="col-md-5">
                          <label for="toDate" class="form-label">To</label>
                          <input type="date" id="toDate" name="toDate" class="form-control">
                        </div>
                        <div class="col-md-2">
                          <div class="filter-actions">
                            <button type="button" id="submit" class="btn btn-primary w-100">Apply</button>
                          </div>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <div class="row metrics-row">
              <div class="col-md-4">
                <div class="metric-card" style="--metric-accent:#2563eb; --metric-bg:rgba(37,99,235,.12);">
                  <div class="card-body">
                    <div class="metric-top">
                      <span class="metric-icon"><i class="ti ti-clock-hour-4"></i></span>
                      <div>
                        <div class="metric-label">Transactions</div>
                        <h5 class="metric-title">Daily Transaction</h5>
                      </div>
                      <span id="dailyCount" class="metric-value">0</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="metric-card" style="--metric-accent:#7c3aed; --metric-bg:rgba(124,58,237,.12);">
                  <div class="card-body">
                    <div class="metric-top">
                      <span class="metric-icon"><i class="ti ti-chart-line"></i></span>
                      <div>
                        <div class="metric-label">Performance</div>
                        <h5 class="metric-title">Average Transaction</h5>
                      </div>
                      <span id="avgCount" class="metric-value">0</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="metric-card" style="--metric-accent:#0f766e; --metric-bg:rgba(15,118,110,.12);">
                  <div class="card-body">
                    <div class="metric-top">
                      <span class="metric-icon"><i class="ti ti-database"></i></span>
                      <div>
                        <div class="metric-label">Overall</div>
                        <h5 class="metric-title">Total Transaction</h5>
                      </div>
                      <span id="totalCount" class="metric-value">0</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="row metrics-row">
              <div class="col-md-4">
                <div class="metric-card" style="--metric-accent:#ea580c; --metric-bg:rgba(234,88,12,.12);">
                  <div class="card-body">
                    <div class="metric-top">
                      <span class="metric-icon"><i class="ti ti-truck"></i></span>
                      <div>
                        <div class="metric-label">Equipment</div>
                        <h5 class="metric-title">Truck</h5>
                      </div>
                      <span id="truckCount" class="metric-value">0</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="metric-card" style="--metric-accent:#db2777; --metric-bg:rgba(219,39,119,.12);">
                  <div class="card-body">
                    <div class="metric-top">
                      <span class="metric-icon"><i class="ti ti-box"></i></span>
                      <div>
                        <div class="metric-label">Equipment</div>
                        <h5 class="metric-title">Trailer</h5>
                      </div>
                      <span id="trailerCount" class="metric-value">0</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="metric-card" style="--metric-accent:#0891b2; --metric-bg:rgba(8,145,178,.12);">
                  <div class="card-body">
                    <div class="metric-top">
                      <span class="metric-icon"><i class="ti ti-battery-automotive"></i></span>
                      <div>
                        <div class="metric-label">Equipment</div>
                        <h5 class="metric-title">Genset</h5>
                      </div>
                      <span id="gensetCount" class="metric-value">0</span>
                    </div>
                  </div>
                </div>
              </div>

              
            </div>
            <div class="row charts-row">
              <div class="col-lg-3">
                <div class="chart-card">
                  <div class="card-body">
                    <div class="chart-heading">
                      <div>
                        <h4 class="chart-title">Fleet Mix</h4>
                        <p class="chart-note">Composition of trucks, trailers, and gensets.</p>
                      </div>
                      <span class="chart-chip"><i class="ti ti-chart-pie"></i> Snapshot</span>
                    </div>
                    <div id="chart"></div>
                  </div>
                </div>
              </div>
              <div class="col-lg-9">
                <div class="chart-card">
                  <div class="card-body">
                    <div class="chart-heading">
                      <div>
                        <h4 class="chart-title">Trips vs Used Trucks</h4>
                        <p class="chart-note">Compare trip volume against actively used truck count.</p>
                      </div>
                      <span class="chart-chip"><i class="ti ti-chart-bar"></i> Trend</span>
                    </div>
                    <div id="chart1"></div>
                  </div>
                </div>
              </div>
              <div class="col-lg-3">
                <div class="row">
                  <div class="col-md-12">
                    <div class="chart-card">
                      <div class="card-body">
                        <div class="chart-heading">
                          <div>
                            <h4 class="chart-title">Truck Utilization</h4>
                            <p class="chart-note">Availability and usage split.</p>
                          </div>
                        </div>
                        <div id="chart2"></div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-12">
                    <div class="chart-card">
                      <div class="card-body">
                        <div class="chart-heading">
                          <div>
                            <h4 class="chart-title">Trailer Utilization</h4>
                            <p class="chart-note">Current trailer deployment.</p>
                          </div>
                        </div>
                        <div id="chart3"></div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
              <div class="col-lg-9">
                <div class="chart-card" style="height: 480px;">
                  <div class="card-body">
                    <div class="chart-heading">
                      <div>
                        <h4 class="chart-title">Equipment Activity</h4>
                        <p class="chart-note">Deeper utilization and operational movement view.</p>
                      </div>
                      <span class="chart-chip"><i class="ti ti-activity"></i> Live data</span>
                    </div>
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


    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  </body>
  <script>
    function loadUnitCounts() {
    $.ajax({
        url: 'php/fetch/getUnitCounts.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            $('#truckCount').text(res.truck);
            $('#gensetCount').text(res.genset);
            $('#trailerCount').text(res.trailer);
        }
    });
}

// auto load
loadUnitCounts();

    $.ajax({
      url: 'chartjs/getUnitsSummary.php',
      method: 'GET',
      dataType: 'json',
      success: function(data) {

        var options1 = {
          series: [
            data.truck,
            data.trailer,
            data.genset
          ],
          chart: {
            width: 380,
            type: 'pie'
          },
          labels: ['Truck', 'Trailer', 'Genset'],
          responsive: [{
            breakpoint: 480,
            options: {
              chart: {
                width: 200
              },
              legend: {
                position: 'bottom'
              }
            }
          }]
        };

        var chart1 = new ApexCharts(
          document.querySelector("#chart"),
          options1
        );

        chart1.render();
      },
      error: function() {
        alert('Failed to load unit data');
      }
    });

    // TRIP VS USED TRUCKS

    let chart;
    let isFirstLoad = true;

    function loadChart(fromDate = '', toDate = '') {
      $.ajax({
        url: 'chartjs/getTripsVsTruck.php',
        type: 'GET',
        dataType: 'json',
        data: {
          fromDate,
          toDate
        },
        success: function(res) {

          const series = [{
              name: 'Trips',
              type: 'column',
              data: res.dates.map((d, i) => ({
                x: new Date(d).getTime(),
                y: res.trips[i]
              }))
            },
            {
              name: 'Used Truck',
              type: 'line',
              data: res.dates.map((d, i) => ({
                x: new Date(d).getTime(),
                y: res.trucks[i]
              }))
            }
          ];

          if (!chart) {
            const now = new Date();
            const monthStart = new Date(now.getFullYear(), now.getMonth(), 1).getTime();
            const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0).getTime();

            const options = {
              series: series,
              chart: {
                height: 280,
                type: 'line',
                zoom: {
                  enabled: true,
                  autoScaleYaxis: true
                }
              },
              stroke: {
                width: [0, 4]
              },
              title: {
                text: 'Total Trips & Used Truck'
              },
              dataLabels: {
                enabled: true,
                enabledOnSeries: [1]
              },
              xaxis: {
                type: 'datetime',
                min: monthStart,
                max: monthEnd
              }
            };

            chart = new ApexCharts(document.querySelector("#chart1"), options);
            chart.render();
          } else {
            chart.updateSeries(series);

            // 🔥 remove forced zoom after first load
            if (isFirstLoad) {
              chart.updateOptions({
                xaxis: {
                  min: undefined,
                  max: undefined
                }
              });
              isFirstLoad = false;
            }
          }
        }
      });
    }

    // initial load
    loadChart();

    // 🔹 Apply filter
    $('#submit').on('click', function() {
      const fromDate = $('#fromDate').val();
      const toDate = $('#toDate').val();
      loadChart(fromDate, toDate);
    });

    // Semi Circle Chart
    let chart2;

    function loadTruckUtilization(fromDate = '', toDate = '') {
      $.ajax({
        url: 'chartjs/getTruckUtilization.php',
        type: 'GET',
        dataType: 'json',
        data: {
          fromDate,
          toDate
        },
        success: function (res) {

          if (!chart2) {
            const options2 = {
              series: [res.utilization],
              chart: {
                type: 'radialBar',
                height: 280,
                offsetY: -20,
                sparkline: { enabled: true }
              },
              plotOptions: {
                radialBar: {
                  startAngle: -90,
                  endAngle: 90,
                  track: {
                    background: "#e7e7e7",
                    strokeWidth: '97%',
                    margin: 5,
                    dropShadow: {
                      enabled: true,
                      top: 2,
                      left: 0,
                      color: '#444',
                      opacity: 0.3,
                      blur: 2
                    }
                  },
                  dataLabels: {
                    name: { show: false },
                    value: {
                      formatter: function (val) {
                        return val + "%";
                      },
                      offsetY: -2,
                      fontSize: '22px'
                    }
                  }
                }
              },
              fill: {
                type: 'gradient',
                gradient: {
                  shade: 'light',
                  shadeIntensity: 0.4,
                  opacityFrom: 1,
                  opacityTo: 1,
                  stops: [0, 50, 53, 91]
                }
              },
              labels: ['Truck Utilization']
            };

            chart2 = new ApexCharts(
              document.querySelector("#chart2"),
              options2
            );
            chart2.render();
          } else {
            chart2.updateSeries([res.utilization]);
          }
        },
        error: function () {
          alert('Failed to load utilization data');
        }
      });
    }

    // 🔹 initial load
    loadTruckUtilization();

    // 🔹 Apply same filter button
    $('#submit').on('click', function () {
      const fromDate = $('#fromDate').val();
      const toDate = $('#toDate').val();
      loadTruckUtilization(fromDate, toDate);
    });

    let chart3;

    function loadTrailerUtilization(fromDate = '', toDate = '') {
      $.ajax({
        url: 'chartjs/getTrailerUtilization.php',
        type: 'GET',
        dataType: 'json',
        data: {
          fromDate,
          toDate
        },
        success: function (res) {

          if (!chart3) {
            const options3 = {
              series: [res.utilization],
              chart: {
                type: 'radialBar',
                height: 280,
                offsetY: -20,
                sparkline: { enabled: true }
              },
              plotOptions: {
                radialBar: {
                  startAngle: -90,
                  endAngle: 90,
                  track: {
                    background: "#e7e7e7",
                    strokeWidth: '97%',
                    margin: 5,
                    dropShadow: {
                      enabled: true,
                      top: 2,
                      left: 0,
                      color: '#444',
                      opacity: 0.3,
                      blur: 2
                    }
                  },
                  dataLabels: {
                    name: { show: false },
                    value: {
                      formatter: function (val) {
                        return val + "%";
                      },
                      offsetY: -2,
                      fontSize: '22px'
                    }
                  }
                }
              },
              fill: {
                type: 'gradient',
                gradient: {
                  shade: 'light',
                  shadeIntensity: 0.4,
                  opacityFrom: 1,
                  opacityTo: 1,
                  stops: [0, 50, 53, 91]
                }
              },
              labels: ['Truck Utilization']
            };

            chart3 = new ApexCharts(
              document.querySelector("#chart3"),
              options3
            );
            chart3.render();
          } else {
            chart3.updateSeries([res.utilization]);
          }
        },
        error: function () {
          alert('Failed to load utilization data');
        }
      });
    }

    // 🔹 initial load
    loadTrailerUtilization();

    // 🔹 Apply same filter button
    $('#submit').on('click', function () {
      const fromDate = $('#fromDate').val();
      const toDate = $('#toDate').val();
      loadTrailerUtilization(fromDate, toDate);
    });

   
    let chart4;

    function loadShopRescueChart(fromDate = '', toDate = '') {
      $.ajax({
        url: 'chartjs/getShopRescueCount.php',
        type: 'GET',
        dataType: 'json',
        data: {
          fromDate,
          toDate
        },
        success: function(res) {

          const series = [
            {
              name: 'Shop Unit',
              data: res.shop
            },
            {
              name: 'Rescue Unit',
              data: res.rescue
            }
          ];

          if (!chart4) {
            const options4 = {
              series: series,
              chart: {
                height: 350,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: true }
              },
              stroke: {
                curve: 'smooth',
                width: 3
              },
              dataLabels: {
                enabled: true
              },
              title: {
                text: 'Shop vs Rescue Units by Date',
                align: 'left'
              },
              xaxis: {
                categories: res.dates,
                title: { text: 'Date' }
              },
              yaxis: {
                title: { text: 'Number of Units' },
                min: 0
              },
              legend: {
                position: 'top'
              }
            };

            chart4 = new ApexCharts(document.querySelector("#chart4"), options4);
            chart4.render();
          } else {
            chart4.updateOptions({
              xaxis: { categories: res.dates }
            });

            chart4.updateSeries(series);
          }
        }
      });
    }

    // 🔹 initial load
    loadShopRescueChart();

    // 🔹 Apply filter
    $('#submit').on('click', function () {
      const fromDate = $('#fromDate').val();
      const toDate = $('#toDate').val();
      loadShopRescueChart(fromDate, toDate);
    });
    
     // ✅ Load trip counts with same filter
          function loadTripCounts(fromDate = '', toDate = '') {
            fetch(`php/fetch/get_trip_counts1.php?fromDate=${fromDate}&toDate=${toDate}`)
              .then(res => res.json())
              .then(data => {
                document.getElementById("dailyCount").innerText = data.daily.toLocaleString();
                document.getElementById("avgCount").innerText = data.average.toLocaleString();
                document.getElementById("totalCount").innerText = data.total.toLocaleString();
              })
              .catch(err => console.error('Error loading trip counts:', err));
          }

          // ✅ Form submission (filter both chart + trip counts)
          document.getElementById('filterForm').addEventListener('submit', e => {
            e.preventDefault();
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            loadTripCounts(fromDate, toDate);
          });

          loadTripCounts();

          // ✅ Optional auto-refresh every 30s
          setInterval(() => {
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            loadTripCounts(fromDate, toDate);
          }, 30000);
  </script>

  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
} ?>
