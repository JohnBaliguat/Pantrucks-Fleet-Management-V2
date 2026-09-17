<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Visual") {


?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trailer Report</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="datatable/datatables.min.css">
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
    .truck-card {
      background: #ffffff;
      border-radius: 16px;
      padding: 20px;
      transition: 0.2s;
    }

    .truck-card:hover {
      transform: translateY(-3px);
    }


    .truck-count {
      font-size: 34px;
      font-weight: 800;
      color: #007bff;
    }

    .truck-sub {
      font-size: 20px;
      color: #666;
    }

    .used {
      color: #28a745;
      font-weight: 700;
    }

    .unused {
      color: #dc3545;
      font-weight: 700;
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
            <img src="assets/images/logos/pantrucks.png" alt="" width="122">
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
          <div class="container-fluid">
            <!--  Row 1 -->
            <div class="row">
              <div class="col-lg-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Generate Report</h4>
                      </div>
                    </div>
                    <form id="filterForm" class="row g-3 align-items-center mb-3" method="POST" action="php/reports/trailer-report.php" target="_blank" class="Excel">
                    <div class="col-auto">
                        <label for="trailer" class="col-form-label">Trailer</label>
                      </div>
                      <div class="col-auto position-relative">
                        <input type="text" id="trailer" name="trailer" class="form-control" placeholder="Select Trailer">
                        <ul id="trailerList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                      </div>  
                    <div class="col-auto">
                        <label for="customer" class="col-form-label">Customer</label>
                      </div>
                      <div class="col-auto">
                        <select class="form-select" aria-label="Customer select menu" id="customer" name="customer">
                          <option selected disabled>Select Customer</option>
                          <option value="ABC">ABC</option>
                          <option value="CTH">CTH</option>
                          <option value="DPC">DPC</option>
                          <option value="DM">DM</option>
                          <option value="DICT">DICT</option>
                          <option value="DOLE">DOLE</option>
                          <option value="FARM">FARM</option>
                          <option value="GF">GF</option>
                          <option value="SUMI">SUMI</option>
                          <option value="TDC">TDC</option>
                        </select>
                      </div>
                      <div class="col-auto">
                        <label for="fromDate" class="col-form-label">From</label>
                      </div>
                      <div class="col-auto">
                        <input type="date" id="fromDate" name="fromDate" class="form-control">
                      </div>
                      <div class="col-auto">
                        <label for="toDate" class="col-form-label">To</label>
                      </div>
                      <div class="col-auto">
                        <input type="date" id="toDate" name="toDate" class="form-control">
                      </div>
                      <div class="col-auto">
                        <button type="button" id="submit" class="btn btn-primary">Generate</button>
                      </div>
                      <div class="col-auto">
                        <button type="submit" class="btn btn-success">Export</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <div class="col-4">
                <div class="row">
                  <div class="col-md-6">
                    <div class="truck-card">

                      <div class="d-flex justify-content-between">

                        <!-- Used Trucks -->
                        <div>
                          <div class="truck-count" id="usedCount">0</div>
                          <div class="truck-sub used">Used Trailer</div>
                        </div>
                      </div>
                    </div>

                  </div>
                  <div class="col-md-6">
                    <div class="truck-card">

                      <div class="d-flex justify-content-between">

                        <!-- Unused Trucks -->
                        <div>
                          <div class="truck-count" style="color:#dc3545;" id="unusedCount">0</div>
                          <div class="truck-sub unused">Unused Trailer</div>
                        </div>

                      </div>
                    </div>

                  </div>

                </div>

                <div class="row mt-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-body">
                        <div class="d-md-flex align-items-center">
                          <div>
                            <h4 class="card-title">Unused Trailer List</h4>
                          </div>
                        </div>

                        <table id="truckTable" class="table table-bordered table-striped mt-3">
                          <thead>
                            <tr>
                              <th>Trailer Name</th>
                              <th>Status</th>
                            </tr>
                          </thead>
                          <tbody></tbody>
                        </table>

                      </div>
                    </div>
                  </div>
                </div>
                
              </div>
              <div class="col-lg-8">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex align-items-center">
                      <div>
                        <h4 class="card-title">Trailer Trasaction Report</h4>
                      </div>
                    </div>
                    <div class="table-responsive mt-4" style="overflow: hidden;">
                      <table id="table-data" class="table mb-0 text-nowrap varient-table align-middle fs-3">
                        <thead>
                          <tr>
                            <th class="px-0 text-muted">Trailer</th>
                            <th class="px-0 text-muted">Assigned Driver</th>
                            <th class="px-0 text-muted text-center">Dispatched Date</th>
                            <th class="px-0 text-muted">Last Location</th>
                            <th class="px-0 text-muted text-center">Utilization</th>
                            <th class="px-0 text-muted">Hauling Segment</th>
                            <th class="px-0 text-muted">Dispatched By</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                      </table>
                    </div>
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
    <script src="assets/js/dashboard.js"></script>
    <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script src="datatable/datatables.min.js"></script>

    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script>
      const table = new DataTable('#table-data', {
        processing: true,
        serverSide: true,
        ajax: {
          url: 'table-fetch/trailer-table1.php',
          type: 'POST',
          data: function(d) {
            d.fromDate = $('#fromDate').val();
            d.toDate   = $('#toDate').val();
            d.customer = $('#customer').val();
            d.trailer  = $('#trailer').val();
          }
        },
        responsive: true,
        columnDefs: [
          { targets: [0,1,3,5], className: 'px-0' },
          { targets: [2,5], className: 'text-center' }
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
      
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: visual-index.php?route=login");
  exit();
} ?>
