const table = new DataTable('#table-data', {
  processing: true,
  serverSide: true,
  ajax: {
    url: 'table-fetch/truck-table.php',
    type: 'POST',
    data: function(d) {
      d.fromDate = $('#fromDate').val();
      d.toDate = $('#toDate').val();
      d.customer = $('#customer').val();
      d.truck = $('#truck').val();
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
    fetch('php/fetch/get_truckTrip_counts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            fromDate: $('#fromDate').val(),
            toDate: $('#toDate').val(),
            customer: $('#customer').val(),
            truck: $('#truck').val()
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
    const truckInput = document.getElementById("truck");
    const truckList = document.getElementById("truckList");
    let allTrucks = [];

    fetch("php/fetch/get_trucks1.php")
        .then(res => res.json())
        .then(data => { allTrucks = data; });

    truckInput.addEventListener("input", function () {
        filterDropdown(this, truckList, allTrucks, (name) => {
            truckInput.value = name;
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

    attachKeyboardNav(truckInput, truckList, (val) => {
        truckInput.value = val;
    });

    // ===== Hide all dropdowns on click outside =====
    document.addEventListener("click", function (e) {
        [truckList].forEach(list => {
            if (!list.contains(e.target) &&
                !truckInput.contains(e.target)) {
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

function loadTruckCountsAndTable() {

    let truck = document.getElementById('truck').value;
    let customer = document.getElementById('customer').value;
    let fromDate = document.getElementById('fromDate').value;
    let toDate = document.getElementById('toDate').value;

    // Destroy DataTable FIRST
    if ($.fn.DataTable.isDataTable("#truckTable")) {
        $("#truckTable").DataTable().clear().destroy();
    }

    fetch("php/fetch/get_truck_counts.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            truck: truck,
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

            let badge = "bg-secondary";
            if (row.status === "USED") badge = "bg-primary";
            else if (row.status === "IN SHOP") badge = "bg-warning";
            else if (row.status === "UNUSED") badge = "bg-danger";

            tr.innerHTML = `
                <td>${row.unit_name}</td>
                <td><span class="badge ${badge}">${row.status}</span></td>
            `;

            tbody.appendChild(tr);
        });

        // Reinitialize AFTER rows are added
        $("#truckTable").DataTable();
    });
}

window.addEventListener("load", loadTruckCountsAndTable);
document.getElementById("submit").addEventListener("click", loadTruckCountsAndTable);


// let truckChart; // keep chart instance

// function loadTrucksChart(fromDate = '', toDate = '') {
//   fetch(`chartjs/get_trucks_done_trips.php?fromDate=${fromDate}&toDate=${toDate}`)
//     .then(res => res.json())
//     .then(data => {
//       let categories = data.map(item => item.unit_name); // FIXED
//       let values = data.map(item => item.done_trips);

//       let chartHeight = Math.max(350, categories.length * 40);

//       let truckOptions = {
//         series: [{ name: 'Done Trips', data: values }],
//         chart: { type: 'bar', height: chartHeight },
//         plotOptions: {
//           bar: {
//             borderRadius: 4,
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
//         xaxis: { categories: categories },
//         tooltip: {
//           y: { formatter: val => `${val} trips` }
//         }
//       };

//       if (truckChart) {
//         truckChart.updateOptions(truckOptions);
//       } else {
//         truckChart = new ApexCharts(document.querySelector("#Truckchart"), truckOptions);
//         truckChart.render();
//       }
//     })
//     .catch(err => console.error('Error loading chart:', err));
// }

// // Load initial chart
// loadTrucksChart();
