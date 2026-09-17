const table = new DataTable('#table-data', {
  processing: true,
  serverSide: true,
  ajax: {
    url: 'table-fetch/trailer-table.php',
    type: 'POST',
    data: function(d) {
      d.fromDate = $('#fromDate').val();
      d.toDate = $('#toDate').val();
      d.customer = $('#customer').val();
      d.trailer = $('#trailer').val();
    }
  },
  responsive: true,
  columnDefs: [
    { targets: [0, 1, 3, 4, 5, 6, 9], className: 'px-0' },
    { targets: [2, 5, 6], className: 'text-center' }
  ]
});

// Refresh table on form submit
$('#submit').on('click', function(e) {
  e.preventDefault();
  table.ajax.reload();
  loadTripCounts();
});

function loadTripCounts() {
    fetch('php/fetch/get_trailerTrip_counts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            fromDate: $('#fromDate').val(),
            toDate: $('#toDate').val(),
            customer: $('#customer').val(),
            trailer: $('#trailer').val()
        })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById("totalTrips").innerText = data.trips.toLocaleString();
        document.getElementById("totalHours").innerText = data.hours;
        document.getElementById("avgHours").innerText = data.average;
    });
}

loadTripCounts();
setInterval(loadTripCounts, 30000);

// Trailer

document.addEventListener("DOMContentLoaded", function () {

    // ===== Trailer Search =====
    const trailerInput = document.getElementById("trailer");
    const trailerList = document.getElementById("trailerList");
    let allTrailers = [];

    fetch("php/fetch/get_trailers1.php")
        .then(res => res.json())
        .then(data => { allTrailers = data; });

    trailerInput.addEventListener("input", function () {
        filterDropdown(this, trailerList, allTrailers, (name) => {
            trailerInput.value = name;
        });
    });

    // ===== Shared Function =====
    function filterDropdown(inputElem, listElem, dataArr, onSelect) {
        const searchVal = inputElem.value.toLowerCase();
        listElem.innerHTML = "";

        if (!searchVal) {
            listElem.style.display = "none";
            return;
        }

        const filtered = dataArr.filter(item => item.toLowerCase().includes(searchVal));

        if (filtered.length === 0) {
            listElem.style.display = "none";
            return;
        }

        filtered.forEach((item, index) => {
            const li = document.createElement("li");
            li.className = "list-group-item";
            li.textContent = item;

            // highlight first suggestion
            if (index === 0) {
                li.classList.add("active-suggestion");
            }

            li.addEventListener("click", function () {
                onSelect(item);
                listElem.style.display = "none";
            });
            listElem.appendChild(li);
        });

        listElem.style.display = "block";
    }

    // ===== Autofill + Navigation Support =====
    function attachKeyboardNav(inputElem, listElem, onSelect) {
        let activeIndex = 0;

        inputElem.addEventListener("keydown", function (e) {
            const items = listElem.querySelectorAll("li");
            if (!items.length) return;

            if (e.key === "ArrowDown") {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                updateActive(items, activeIndex);
            } 
            else if (e.key === "ArrowUp") {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                updateActive(items, activeIndex);
            } 
            else if (e.key === "Enter" || e.key === "Tab") {
                const activeItem = items[activeIndex];
                if (activeItem) {
                    onSelect(activeItem.textContent);
                    listElem.style.display = "none";
                }
            }
        });

        function updateActive(items, index) {
            items.forEach(i => i.classList.remove("active-suggestion"));
            items[index].classList.add("active-suggestion");
        }
    }

    attachKeyboardNav(trailerInput, trailerList, (val) => {
        trailerInput.value = val;
    });

    // ===== Hide all dropdowns on click outside =====
    document.addEventListener("click", function (e) {
        [trailerList].forEach(list => {
            if (!list.contains(e.target) &&
                !trailerInput.contains(e.target)) {
                list.style.display = "none";
            }
        });
    });
});


// DROP DOWN FUNCTION
const list = document.getElementById("bookingList");
const searchInput = document.getElementById("searchInput");
let allBookings = [];

let selectedClass = "ui-state-highlight"; // highlight selected items
let clickDelay = 600; // ms
let lastClick = 0;


function loadTrailerCountsAndTable() {

    let trailer = document.getElementById('trailer').value;
    let customer = document.getElementById('customer').value;
    let fromDate = document.getElementById('fromDate').value;
    let toDate = document.getElementById('toDate').value;

    // Destroy DataTable FIRST
    if ($.fn.DataTable.isDataTable("#truckTable")) {
        $("#truckTable").DataTable().clear().destroy();
    }

    fetch("php/fetch/get_trailer_counts.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            trailer: trailer,
            customer: customer,
            fromDate: fromDate,
            toDate: toDate
        })
    })
    .then(res => res.json())
    .then(data => {

        // update counts
        document.getElementById("usedCount").innerText = data.used;
        document.getElementById("unusedCount").innerText = data.unused;

        // build table
        let tbody = document.querySelector("#truckTable tbody");
        tbody.innerHTML = "";

        data.list.forEach(row => {
            let tr = document.createElement("tr");

            let badge = row.status === "USED" ? "bg-primary" : "bg-danger";

            tr.innerHTML = `
                <td>${row.trailer_name}</td>
                <td><span class="badge ${badge}">${row.status}</span></td>
            `;

            tbody.appendChild(tr);
        });

        // Reinitialize AFTER rows are added
        $("#truckTable").DataTable();
    });
}

window.addEventListener("load", loadTrailerCountsAndTable);
document.getElementById("submit").addEventListener("click", loadTrailerCountsAndTable);


// let trailerChart; // keep chart instance

// function loadTrailerChart(fromDate = '', toDate = '') {
//   fetch(`chartjs/get_trailers_done_trips.php?fromDate=${fromDate}&toDate=${toDate}`)
//     .then(res => res.json())
//     .then(data => {
//       let categories = data.map(item => item.trailer_name);
//       let values = data.map(item => item.done_trips);

//       let chartHeight = Math.max(350, categories.length * 40);

//       let trailerOptions = {
//         series: [{ data: values }],
//         chart: { type: 'bar', height: chartHeight },
//         plotOptions: {
//           bar: {
//             borderRadius: 4,
//             borderRadiusApplication: 'end',
//             horizontal: true,
//             dataLabels: { position: 'center' }
//           }
//         },
//         dataLabels: {
//           enabled: true,
//           formatter: val => val,
//           style: {
//             colors: ['#fff'],
//             fontSize: '14px',
//             fontWeight: 'bold'
//           }
//         },
//         xaxis: { categories: categories }
//       };

//       if (trailerChart) {
//         trailerChart.updateOptions(trailerOptions);
//       } else {
//         trailerChart = new ApexCharts(document.querySelector("#Trailerchart"), trailerOptions);
//         trailerChart.render();
//       }
//     });
// }

// // Load initial Trailer chart
// loadTrailerChart();
