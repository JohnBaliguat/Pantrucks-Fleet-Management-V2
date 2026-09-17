const salesOverviewEl = document.querySelector("#sales-overview");

if (salesOverviewEl) {
  fetch('get_trips_done.php')
    .then(response => response.json())
    .then(data => {
      var options_sales_overview = {
        series: [{
          name: "Completed Trips",
          data: data.counts
        }],
        chart: {
          type: "bar",
          height: 275,
          toolbar: { show: false },
          foreColor: "#adb0bb",
          fontFamily: "inherit",
          sparkline: { enabled: false },
        },
        grid: {
          show: false,
          borderColor: "transparent",
          padding: { left: 0, right: 0, bottom: 0 },
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: "35%",
            endingShape: "rounded",
            borderRadius: 5,
          },
        },
        colors: ["var(--bs-primary)"],
        dataLabels: { enabled: false },
        yaxis: {
          show: true,
          labels: { formatter: (val) => val },
        },
        stroke: {
          show: true,
          width: 3,
          lineCap: "butt",
          colors: ["transparent"],
        },
        xaxis: {
          type: "category",
          categories: data.customers,
          axisBorder: { show: false },
        },
        fill: { opacity: 1 },
        tooltip: {
          theme: "dark",
          y: { formatter: (val) => val + " trips" }
        },
        legend: { show: false },
      };

      var chart_column_basic = new ApexCharts(
        salesOverviewEl,
        options_sales_overview
      );
      chart_column_basic.render();
    });
}
