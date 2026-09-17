function loadTripCounts() {
        const dailyEl = document.getElementById("dailyCount");
        const avgEl = document.getElementById("avgCount");
        const totalEl = document.getElementById("totalCount");
        if (!dailyEl || !avgEl || !totalEl) return;
        fetch('php/fetch/get_trip_counts.php')
          .then(res => res.json())
          .then(data => {
            dailyEl.innerText = data.daily.toLocaleString();
            avgEl.innerText = data.average.toLocaleString();
            totalEl.innerText = data.total.toLocaleString();
          });
      }

      if (document.getElementById("dailyCount") && document.getElementById("avgCount") && document.getElementById("totalCount")) {
        loadTripCounts();
        setInterval(loadTripCounts, 30000);
      }


// CHART

let chart; // keep chart instance outside

function loadChart(fromDate = '', toDate = '') {
  const chartEl = document.querySelector("#trip-overview");
  if (!chartEl) return;
  // Fetch data via AJAX with optional date params
  fetch(`chartjs/get_trips_done.php?fromDate=${fromDate}&toDate=${toDate}`)
    .then(response => response.json())
    .then(data => {
      const seriesData = Object.keys(data).map(customer => ({
        name: customer,
        data: data[customer]
      }));

      const options = {
        series: seriesData,
        chart: {
          type: 'area',
          height: 350,
          stacked: true,
          toolbar: { show: true }
        },
        colors: ['#008FFB', '#00E396', '#CED4DC', '#FEB019', '#FF4560'],
        dataLabels: { enabled: false },
        stroke: { curve: 'monotoneCubic' },
        fill: {
          type: 'gradient',
          gradient: { opacityFrom: 0.6, opacityTo: 0.8 }
        },
        legend: {
          position: 'top',
          horizontalAlign: 'left'
        },
        xaxis: { type: 'datetime' },
        tooltip: {
          shared: true,
          y: { formatter: val => val + " trips" }
        }
      };

      if (chart) {
        chart.updateOptions(options);
      } else {
        chart = new ApexCharts(chartEl, options);
        chart.render();
      }
    });
}

if (document.querySelector("#trip-overview")) {
  loadChart();
}

let truckChart; // keep chart instance

function loadTruckChart(fromDate = '', toDate = '') {
  const chartEl = document.querySelector("#Truckchart");
  if (!chartEl) return;
  fetch(`chartjs/get_units_done_trips.php?fromDate=${fromDate}&toDate=${toDate}`)
    .then(res => res.json())
    .then(data => {
      let categories = data.map(item => item.unit_name);
      let values = data.map(item => item.done_trips);

      // Dynamic height: 40px per bar (minimum 350px)
      let chartHeight = Math.max(350, categories.length * 40);

      let options = {
        series: [{
          data: values
        }],
        chart: {
          type: 'bar',
          height: chartHeight
        },
        plotOptions: {
          bar: {
            borderRadius: 4,
            borderRadiusApplication: 'end',
            horizontal: true,
            dataLabels: {
              position: 'center'
            }
          }
        },
        dataLabels: {
          enabled: true,
          formatter: function (val) {
            return val;
          },
          style: {
            colors: ['#fff'],
            fontSize: '14px',
            fontWeight: 'bold'
          }
        },
        xaxis: {
          categories: categories
        }
      };

      if (truckChart) {
        truckChart.updateOptions(options);
      } else {
        truckChart = new ApexCharts(chartEl, options);
        truckChart.render();
      }
    });
}

if (document.querySelector("#Truckchart")) {
  loadTruckChart();
}


let trailerChart; // keep chart instance

function loadTrailerChart(fromDate = '', toDate = '') {
  const chartEl = document.querySelector("#Trailerchart");
  if (!chartEl) return;
  fetch(`chartjs/get_trailers_done_trips.php?fromDate=${fromDate}&toDate=${toDate}`)
    .then(res => res.json())
    .then(data => {
      let categories = data.map(item => item.trailer_name);
      let values = data.map(item => item.done_trips);

      let chartHeight = Math.max(350, categories.length * 40);

      let trailerOptions = {
        series: [{ data: values }],
        chart: { type: 'bar', height: chartHeight },
        plotOptions: {
          bar: {
            borderRadius: 4,
            borderRadiusApplication: 'end',
            horizontal: true,
            dataLabels: { position: 'center' }
          }
        },
        dataLabels: {
          enabled: true,
          formatter: val => val,
          style: {
            colors: ['#fff'],
            fontSize: '14px',
            fontWeight: 'bold'
          }
        },
        xaxis: { categories: categories }
      };

      if (trailerChart) {
        trailerChart.updateOptions(trailerOptions);
      } else {
        trailerChart = new ApexCharts(chartEl, trailerOptions);
        trailerChart.render();
      }
    });
}

if (document.querySelector("#Trailerchart")) {
  loadTrailerChart();
}

let driverChart; // keep chart instance

function loadDriverChart(fromDate = '', toDate = '') {
  const chartEl = document.querySelector("#Driverchart");
  if (!chartEl) return;
  fetch(`chartjs/get_drivers_done_trips.php?fromDate=${fromDate}&toDate=${toDate}`)
    .then(res => res.json())
    .then(data => {
      let categories = data.map(item => item.driver_name);
      let values = data.map(item => item.done_trips);

      let chartHeight = Math.max(350, categories.length * 40);

      let driverOptions = {
        series: [{ data: values }],
        chart: { type: 'bar', height: chartHeight },
        plotOptions: {
          bar: {
            borderRadius: 4,
            borderRadiusApplication: 'end',
            horizontal: true,
            dataLabels: { position: 'center' }
          }
        },
        dataLabels: {
          enabled: true,
          formatter: val => val,
          style: {
            colors: ['#fff'],
            fontSize: '14px',
            fontWeight: 'bold'
          }
        },
        xaxis: { categories: categories }
      };

      if (driverChart) {
        driverChart.updateOptions(driverOptions);
      } else {
        driverChart = new ApexCharts(chartEl, driverOptions);
        driverChart.render();
      }
    });
}

if (document.querySelector("#Driverchart")) {
  loadDriverChart();
}

const dateFilterForm = document.getElementById('dateFilterForm');
if (dateFilterForm) {
  dateFilterForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const fromDate = (document.getElementById('fromDate') || {}).value || '';
    const toDate = (document.getElementById('toDate') || {}).value || '';
    loadChart(fromDate, toDate);
    loadTruckChart(fromDate, toDate);
    loadTrailerChart(fromDate, toDate);
    loadDriverChart(fromDate, toDate);
  });
}

