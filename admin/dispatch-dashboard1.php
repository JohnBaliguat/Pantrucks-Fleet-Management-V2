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
    <link rel="stylesheet" href="datatable/datatables.min.css">
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
      /* Common style for all status cells */
      #table-data td {
        font-weight: 600;
        /* bold text */
      }

      /* Specific background + darker font */
      .status-restday {
        background-color: lightcoral !important;
        color: #222 !important;
        /* darker text */
        border-color: lightcoral !important;
      }

      .status-leave {
        background-color: lightyellow !important;
        color: #333 !important;
        border-color: lightyellow !important;
      }

      .status-dispatch {
        background-color: lightgreen !important;
        color: #111 !important;
        border-color: lightgreen !important;
      }

      .mismatch-unit {
        background-color: lightcoral !important;
        color: #222 !important;
      }

      /* Dropdown */
      .dropdown1 {
        position: relative;
        display: inline-block;
        width: 250px;
        font-family: Arial, sans-serif;
        margin: 10px;
      }

      .dropdown1-btn {
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #ccc;
        width: 100%;
        cursor: pointer;
        text-align: left;
        border-radius: 5px;
      }

      .dropdown1-content {
        display: none;
        position: absolute;
        background: #fff;
        border: 1px solid #ddd;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
      }

      .dropdown1-content label {
        display: block;
        padding: 8px;
        cursor: pointer;
      }

      .dropdown1-content input[type="checkbox"] {
        margin-right: 8px;
      }

      .dropdown1.show .dropdown1-content {
        display: block;
      }

      .selected-values {
        margin: 10px 0;
        font-size: 14px;
      }


      /* Scrollable container */
      .truck-grid-container {
        overflow-x: auto;
        padding-right: 5px;
        max-height: 820px;
      }

      /* Optional: style scrollbar */
      .truck-grid-container::-webkit-scrollbar {
        height: 6px;
      }

      .truck-grid-container::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 3px;
      }

      /* Grid layout */
      .truck-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 15px;
        white-space: nowrap;
        /* prevents wrapping in some cases */
      }

      /* Card styles (your existing ones) */
      .truck-card {
        background: #f2f2f2;
        border-radius: 20px;
        padding: 20px 10px;
        text-align: center;
        font-weight: 600;
        box-shadow: 0px 2px 6px rgba(0, 0, 0, 0.1);
      }

      .truck-card i {
        font-size: 35px;
        margin-bottom: 10px;
        display: block;
      }

      /* Status Colors */
      .available {
        background: #f5f5f5;
      }

      .dispatch {
        background: #c8ffbf;
      }

      .rescue {
        background: #fff7a8;
      }

      .shop {
        background: #ffb6b6;
      }

      /* STATUS LEGEND */
      .status-legend .status-dot {
        width: 12px;
        height: 12px;
        display: inline-block;
        border-radius: 50%;
        margin-right: 5px;
        vertical-align: middle;
      }

      .status-dot.available {
        background: #eaeaea;
      }

      .status-dot.ontrip {
        background: #b9f7ac;
      }

      .status-dot.rescue {
        background: #ffe97a;
      }

      .status-dot.shop {
        background: #ff8b8b;
      }

      .status-filter {
        cursor: pointer;
        user-select: none;
        padding: 3px 6px;
        border-radius: 5px;
        transition: 0.2s;
      }

      .status-filter.active {
        background: rgba(0, 0, 0, 0.1);
        font-weight: bold;
      }

      .booking-wrapper {
        padding: 10px;
        font-family: 'Inter', sans-serif;
      }

      /* Header */
      .booking-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
      }

      .title {
        font-weight: 700;
      }

      .title span {
        font-weight: 400;
        color: #6c757d;
      }

      /* Legend */
      .legend {
        font-size: 14px;
        color: #555;
      }

      .dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin: 0 5px 0 10px;
      }

      .assigned { background: #8fb7ff; }
      .completed { background: #9cff9c; }

      /* Search */
      .search-box {
        position: relative;
      }

      .search-box input {
        padding: 8px 35px 8px 15px;
        border-radius: 20px;
        border: 1px solid #ddd;
        outline: none;
      }

      .search-box i {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
      }

      /* Grid */
      .booking-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 16px;
      }

      /* Cards */
      .booking-card {
        background: #fff;
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: 1px solid #eee;
      }

      .booking-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.12);
      }

      .booking-card h5 {
        font-weight: 700;
        margin-bottom: 12px;
        text-align: center;
      }

      /* Stats */
      .booking-stats p {
        font-size: 14px;
        margin: 6px 0;
        display: flex;
        justify-content: space-between;
      }

      /* Status Colors */
      .assigned-bg {
        background: #eaf1ff;
      }

      .completed-bg {
        background: #eaffea;
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
            <div class="row">
              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-clock fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Today's Transaction</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="dailyCount" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-chart-line fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Average Transaction</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="avgCount" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-database fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Total Transaction</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="totalCount" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!--  -->
            <div class="row">
              <div class="col-md-2">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-info rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-users fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Driver</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="dailyCount1" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-2">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-user fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Present Driver</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="totalCount_others" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-2">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-success rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-route fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Dispatch</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="avgCount1" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-2">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-secondary rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-route-off fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">N-Dispatch</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="totalCount_ndispatch" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-2">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-warning rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-user fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">VL/SL</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="totalCount_vlsl" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-2">
                <div class="card overflow-hidden">
                  <div class="card-body pb-0">
                    <div class="pb-3 d-flex align-items-center">
                      <span class="btn btn-danger rounded-circle round-48 hstack justify-content-center">
                        <i class="ti ti-user fs-6"></i>
                      </span>
                      <div class="ms-3">
                        <h5 class="mb-0 fw-bolder fs-4">Absent</h5>
                      </div>
                      <div class="ms-auto">
                        <span id="totalCount_restday" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!--  Row 1 -->
            <div class="row">
              <div class="col-lg-3">
                <div class="card w-100">
                  <div class="card-body">
                    <h4 class="text-center mb-3">Truck List</h4>
                    <input type="text" id="truckSearch" class="form-control" placeholder="Search truck...">
                    <!-- STATUS LEGEND -->
                    <div class="status-legend mb-3 mt-3 text-center">
                      <span class="status-filter" data-status="good">
                        <span class="status-dot good"></span> Available
                      </span>

                      <span class="status-filter ms-3" data-status="dispatch">
                        <span class="status-dot ontrip"></span> On Trip
                      </span>

                      <span class="status-filter ms-3" data-status="rescue unit">
                        <span class="status-dot rescue unit"></span> Rescue
                      </span>

                      <span class="status-filter ms-3" data-status="shop">
                        <span class="status-dot shop"></span> Shop
                      </span>
                    </div>

                    <!-- TRUCK GRID -->
                    <div class="truck-grid-container">
                      <div class="truck-grid">
                        <?php
                        include 'php/config/config.php';
                        $sql = "SELECT unit_id, unit_name, unit_status 
                                  FROM units 
                                  WHERE unit_status IN ('Dispatch', 'Good', 'Rescue', 'Shop Unit') AND unit_name NOT LIKE 'GS%' AND unit_name NOT LIKE 'FL%'";

                        $result = $conn->query($sql);

                        while ($row = ($result)->fetch()) {
                          $status = strtolower($row['unit_status']); // Dispatch → dispatch
                          echo '
                                  <div class="truck-card ' . $status . '">
                                      <i class="ti ti-truck card-icon"></i>
                                      <p>' . $row['unit_name'] . '</p>
                                  </div>
                              ';
                        }
                        ?>
                      </div>
                    </div>


                  </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="row">
                  <div class="col-md-4">
                    <div class="card overflow-hidden">
                      <div class="card-body pb-0">
                        <div class="pb-3 d-flex align-items-center">
                          <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-calendar fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Total Booking</h5>
                          </div>
                          <div class="ms-auto">
                            <span id="totalBooking" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card overflow-hidden">
                      <div class="card-body pb-0">
                        <div class="pb-3 d-flex align-items-center">
                          <span class="btn btn-info rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-calendar fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Today's Booking</h5>
                          </div>
                          <div class="ms-auto">
                            <span id="todayBooking" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card overflow-hidden">
                      <div class="card-body pb-0">
                        <div class="pb-3 d-flex align-items-center">
                          <span class="btn btn-success rounded-circle round-48 hstack justify-content-center">
                            <i class="ti ti-check fs-6"></i>
                          </span>
                          <div class="ms-3">
                            <h5 class="mb-0 fw-bolder fs-4">Completed</h5>
                          </div>
                          <div class="ms-auto">
                            <span id="completedBooking" class="badge bg-secondary-subtle text-dark" style="font-size: 20px; font-weight: 600;">0</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="card w-100">
                    <div class="card-body booking-wrapper">
                      <div class="row mb-3 mt-1">
                        <div class="col-md-3"></div>
                        <div class="col-md-9 text-end">
                        <div class="btn-group" role="group" aria-label="Basic example">
                          <a class="btn btn-outline-primary" href="dashboard-attendance">Driver Attendance</a>
                          <a class="btn btn-outline-primary" href="dashboard-truck">Truck Movement</a>
                          <a href="dashboard-trailer" class="btn btn-outline-primary">Trailer Movement</a>
                        </div>
                      </div>
                      </div>

                      <!-- Header -->
                      <div class="booking-header">
                        <div>
                          <h3 class="title">Customer List <span>By Booking</span></h3>
                          <div class="legend">
                            <span class="dot assigned"></span> Assigned
                            <span class="dot completed"></span> Completed
                          </div>
                        </div>

                        <div class="search-box">
                          <i class="bi bi-search"></i>
                          <input type="text" placeholder="Search Customer..." id="searchCustomer">
                        </div>
                      </div>

                      <?php
                        include "php/config/config.php";

                        $sql = "
                            SELECT 
                              b.costumer,

                              /* TOTAL REMAINING BOOKING */
                              SUM(
                                  CASE 
                                      WHEN b.status = 'Active'
                                          OR (b.status != 'Active' AND (b.quantity - b.quantity_use) > 0)
                                      THEN GREATEST(b.quantity - b.quantity_use, 0)
                                      ELSE 0
                                  END
                              ) AS total_book,

                              /* ASSIGNED QUANTITY (BOOKING ONLY) */
                              SUM(b.quantity_use) AS total_assigned,

                              /* DONE TRIPS (REFERENCE ONLY) */
                              COUNT(DISTINCT 
                                  CASE 
                                      WHEN t.trip_status = 'Done' THEN t.trip_id
                                  END
                              ) AS done_booking

                          FROM booking b
                          LEFT JOIN dispatch d
                              ON b.booking_no = d.booking_no
                          LEFT JOIN trips t
                              ON d.d_id = t.d_id
                          WHERE b.status != 'Disable'
                          GROUP BY b.costumer
                          HAVING total_book > 0
                          ORDER BY b.costumer ASC";

                        $result = $conn->query($sql);
                        ?>
                      <!-- Cards -->
                      <div class="booking-grid">
                        <?php while($row = ($result)->fetch()):

                            $cardClass = '';
                            if ($row['done_booking'] > 0) {
                                $cardClass = 'completed-bg';
                            } elseif ($row['total_assigned'] > 0) {
                                $cardClass = 'assigned-bg';
                            }
                        ?>
                        <div class="booking-card <?= $cardClass ?>">
                            <h5><?= htmlspecialchars($row['costumer']) ?></h5>
                            <div class="booking-stats">
                                <p>Total Book: <strong><?= $row['total_book'] ?></strong></p>
                                <p>Total Assigned: <strong><?= $row['total_assigned'] ?></strong></p>
                                <p>Done Booking: <strong><?= $row['done_booking'] ?></strong></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        </div>
                    </div>

                  </div>
                </div>
                
              </div>

              <div class="col-lg-3">
                <div class="col-md-12">
                  <div class="card overflow-hidden">
                    <div class="card-body pb-0">
                      <div class="d-flex align-items-start">
                        <div>
                          <h4 class="card-title">Truck Stats</h4>
                          <p class="card-subtitle">Number of truck by Status</p>
                        </div>
                      </div>
                      <div class="mt-4 pb-3 d-flex align-items-center">
                        <span class="btn btn-primary rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-truck fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4">Total Units</h5>
                        </div>
                        <div class="ms-auto">
                          <span class="badge bg-secondary-subtle text-black" id="totalUnits">0</span>
                        </div>
                      </div>
                      <div class="mt-4 pb-3 d-flex align-items-center">
                        <span class="btn btn-info rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-truck fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4">Not Dispatch Unit</h5>
                        </div>
                        <div class="ms-auto">
                          <span class="badge bg-secondary-subtle text-black" id="goodUnits">0</span>
                        </div>
                      </div>
                      <div class="py-3 d-flex align-items-center">
                        <span class="btn btn-success rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-truck-delivery fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4">Dispatch Unit</h5>
                        </div>
                        <div class="ms-auto">
                          <span class="badge bg-secondary-subtle text-black" id="dispatchUnits">0</span>
                        </div>
                      </div>
                      <div class="py-3 d-flex align-items-center">
                        <span class="btn btn-warning rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-car-crane fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4">Rescue Units</h5>
                        </div>
                        <div class="ms-auto">
                          <span class="badge bg-secondary-subtle text-black" id="rescueUnits">0</span>
                        </div>
                      </div>
                      <div class="pt-3 mb-7 d-flex align-items-center">
                        <span class="btn btn-danger rounded-circle round-48 hstack justify-content-center">
                          <i class="ti ti-truck-off fs-6"></i>
                        </span>
                        <div class="ms-3">
                          <h5 class="mb-0 fw-bolder fs-4">Shop Units</h5>
                        </div>
                        <div class="ms-auto">
                          <span class="badge bg-secondary-subtle text-black" id="shopUnits">0</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-12">
                  <div class="card overflow-hidden shadow-sm rounded-3">
                    <div class="card-body pb-3">
                      <div class="d-flex align-items-center mb-3">
                        <div class="me-2">
                          <i class="ti ti-truck text-primary" style="font-size: 28px;"></i>
                        </div>
                        <div>
                          <h4 class="card-title mb-0">Truck Utilization</h4>
                          <small class="text-muted">Current usage overview</small>
                        </div>
                      </div>

                      <?php
                      // --- PHP LOGIC TO GET TRUCK UTILIZATION DATA BASED ON TRIPS ---

                      // Get total number of trucks
                      $totalTrucksQuery = $conn->query("SELECT COUNT(*) AS total FROM units WHERE unit_name NOT LIKE 'GS%' AND unit_status != 'Disposed'");
                      $totalTrucks = $totalTrucksQuery->fetch()['total'];

                      // Get count of trucks that are utilized (have at least one trip)
                      $utilizedQuery1 = $conn->query("
                            SELECT COUNT(DISTINCT d.d_truck) AS utilized
                            FROM dispatch d
                            INNER JOIN trips t ON d.d_id = t.d_id
                            WHERE d.d_truck IS NOT NULL 
                              AND d.d_truck != ''
                              AND t.trip_status IS NOT NULL
                        ");
                      $utilized1 = $utilizedQuery1->fetch()['utilized'] ?? 0;

                      // Compute unutilized
                      $unutilized1 = $totalTrucks - $utilized1;

                      // Compute percentages
                      $utilizedPercent1 = ($totalTrucks > 0) ? round(($utilized1 / $totalTrucks) * 100, 1) : 0;
                      $unutilizedPercent1 = ($totalTrucks > 0) ? round(($unutilized1 / $totalTrucks) * 100, 1) : 0;
                      ?>

                      <div class="row text-center">
                        <!-- Utilized -->
                        <div class="col-md-6 border-end">
                          <div class="d-flex justify-content-center align-items-center mb-2">
                            <i class="ti ti-check text-success" style="font-size: 22px;"></i>
                            <span class="ms-2 fw-bold text-success"><?= $utilizedPercent1 ?>%</span>
                          </div>
                          <p class="mb-1 fw-semibold">Utilized Trucks</p>
                          <small class="text-muted"><?= $utilized1 ?> of <?= $totalTrucks ?> units</small>
                          <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar"
                              style="width: <?= $utilizedPercent1 ?>%;"></div>
                          </div>
                        </div>

                        <!-- Unutilized -->
                        <div class="col-md-6">
                          <div class="d-flex justify-content-center align-items-center mb-2">
                            <i class="ti ti-truck-off text-danger" style="font-size: 22px;"></i>
                            <span class="ms-2 fw-bold text-danger"><?= $unutilizedPercent1 ?>%</span>
                          </div>
                          <p class="mb-1 fw-semibold">Unutilized Trucks</p>
                          <small class="text-muted"><?= $unutilized1 ?> of <?= $totalTrucks ?> units</small>
                          <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar"
                              style="width: <?= $unutilizedPercent1 ?>%;"></div>
                          </div>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>
                <div class="col-md-12">
                  <div class="card overflow-hidden shadow-sm rounded-3">
                    <div class="card-body pb-3">
                      <div class="d-flex align-items-center mb-3">
                        <div class="me-2">
                          <i class="ti ti-truck text-primary" style="font-size: 28px;"></i>
                        </div>
                        <div>
                          <h4 class="card-title mb-0">Trailer Utilization</h4>
                          <small class="text-muted">Current usage overview</small>
                        </div>
                      </div>

                      <?php
                      // --- PHP LOGIC TO GET UTILIZATION DATA ---
                      $totalTrailersQuery = $conn->query("SELECT COUNT(*) AS total FROM trailer");
                      $totalTrailers = $totalTrailersQuery->fetch()['total'];

                      $utilizedQuery = $conn->query("
                            SELECT COUNT(DISTINCT tm_trailername) AS utilized
                            FROM trailer_movement
                        ");
                      $utilized = $utilizedQuery->fetch()['utilized'];

                      $unutilized = $totalTrailers - $utilized;

                      $utilizedPercent = ($totalTrailers > 0) ? round(($utilized / $totalTrailers) * 100, 1) : 0;
                      $unutilizedPercent = ($totalTrailers > 0) ? round(($unutilized / $totalTrailers) * 100, 1) : 0;
                      ?>

                      <div class="row text-center">
                        <!-- Utilized -->
                        <div class="col-md-6 border-end">
                          <div class="d-flex justify-content-center align-items-center mb-2">
                            <i class="ti ti-check text-success" style="font-size: 22px;"></i>
                            <span class="ms-2 fw-bold text-success"><?= $utilizedPercent ?>%</span>
                          </div>
                          <p class="mb-1 fw-semibold">Utilized Trailers</p>
                          <small class="text-muted"><?= $utilized ?> of <?= $totalTrailers ?> units</small>
                          <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar"
                              style="width: <?= $utilizedPercent ?>%;"></div>
                          </div>
                        </div>

                        <!-- Unutilized -->
                        <div class="col-md-6">
                          <div class="d-flex justify-content-center align-items-center mb-2">
                            <i class="ti ti-truck-off text-danger" style="font-size: 22px;"></i>
                            <span class="ms-2 fw-bold text-danger"><?= $unutilizedPercent ?>%</span>
                          </div>
                          <p class="mb-1 fw-semibold">Unutilized Trailers</p>
                          <small class="text-muted"><?= $unutilized ?> of <?= $totalTrailers ?> units</small>
                          <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar"
                              style="width: <?= $unutilizedPercent ?>%;"></div>
                          </div>
                        </div>
                      </div>

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
    <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script src="datatable/datatables.min.js"></script>
    <script src="js/dashboard.js"></script>
    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script>
      function loadTruckStats() {
          fetch('php/operations/truck-stats-realtime.php')
              .then(response => response.json())
              .then(data => {
                  document.getElementById('totalUnits').textContent = data.total;
                  document.getElementById('goodUnits').textContent = data.good;
                  document.getElementById('dispatchUnits').textContent = data.dispatch;
                  document.getElementById('rescueUnits').textContent = data.rescue;
                  document.getElementById('shopUnits').textContent = data.shop;
              })
              .catch(err => console.error('Realtime error:', err));
      }

      // Initial load
      loadTruckStats();

      // Refresh every 5 seconds
      setInterval(loadTruckStats, 20000);


      
      document.getElementById("searchCustomer").addEventListener("keyup", function () {
          let value = this.value.toLowerCase();
          document.querySelectorAll(".booking-card").forEach(card => {
              card.style.display = card.innerText.toLowerCase().includes(value)
                  ? "block" : "none";
          });
      });

      function loadDriverCounts() {
        fetch('table-fetch/getDriverCount.php')
          .then(response => response.json())
          .then(data => {
            document.getElementById('dailyCount1').innerText = data.totalDrivers;
            document.getElementById('avgCount1').innerText = data.dispatch;
            document.getElementById('totalCount_ndispatch').innerText = data.ndispatch;
            document.getElementById('totalCount_vlsl').innerText = data.vlsl;
            document.getElementById('totalCount_others').innerText = data.others;
            document.getElementById('totalCount_restday').innerText = data.restDay;
          });
      }

      // Load first time
      loadDriverCounts();

      // Refresh every 5 seconds
      setInterval(loadDriverCounts, 20000);




      const statusFilters = document.querySelectorAll(".status-filter");
      const searchInput = document.getElementById("truckSearch");

      function applyFilters() {
        const activeStatuses = [...document.querySelectorAll(".status-filter.active")]
          .map(el => el.getAttribute("data-status"));

        const searchValue = searchInput.value.toLowerCase();

        const cards = document.querySelectorAll(".truck-card");

        cards.forEach(card => {
          const cardText = card.querySelector("p").textContent.toLowerCase();

          const statusClass = [...card.classList].find(c => ["good", "dispatch", "rescue unit", "shop"].includes(c));

          // status match
          const statusMatch =
            activeStatuses.length === 0 || activeStatuses.includes(statusClass);

          // search match
          const searchMatch = cardText.includes(searchValue);

          if (statusMatch && searchMatch) {
            card.style.display = "block";
          } else {
            card.style.display = "none";
          }
        });
      }

      /* STATUS FILTER CLICK */
      statusFilters.forEach(filter => {
        filter.addEventListener("click", function() {
          this.classList.toggle("active");
          applyFilters();
        });
      });

      /* SEARCH INPUT */
      searchInput.addEventListener("keyup", applyFilters);






      function loadBookingCounts() {
        fetch("php/fetch/get_booking_counts.php")
          .then(res => res.json())
          .then(data => {
            document.getElementById("totalBooking").textContent = data.total_booking;
            document.getElementById("todayBooking").textContent = data.today_booking;
            document.getElementById("completedBooking").textContent = data.today_completed;
          })
          .catch(err => console.error(err));
      }

      // Load on page open
      loadBookingCounts();

      // Auto refresh every 5 seconds (realtime feel)
      setInterval(loadBookingCounts, 20000);
    </script>
  </body>

  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
} ?>
