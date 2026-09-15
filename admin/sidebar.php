<aside class="left-sidebar">
      <!-- Sidebar scroll-->
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <a href="#" class="text-nowrap logo-img">
            <img src="assets/images/logos/pantrucks.png" alt="" style="width: 200px;"/>
          </a>
          <div class="d-flex align-items-center gap-1">
            <button type="button" class="sidebar-toggle-btn btn btn-link text-dark d-none d-xl-inline-flex p-2 rounded sidebartoggler" id="sidebarCollapseDesktop" title="Hide sidebar" aria-label="Hide sidebar">
              <i class="ti ti-panel-left-close fs-5"></i>
            </button>
            <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer p-2" id="sidebarCollapse" title="Close menu" aria-label="Close menu">
              <i class="ti ti-x fs-6"></i>
            </div>
          </div>
        </div>
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
          <ul id="sidebarnav">

            <!-- ===================== MAIN ===================== -->
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Main</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dashboard" aria-expanded="false">
                <i class="ti ti-layout-dashboard"></i>
                <span class="hide-menu">Dashboard</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="executive" aria-expanded="false">
                <i class="ti ti-chart-arcs"></i>
                <span class="hide-menu">Executive View</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatchTiles" aria-expanded="false">
                <i class="ti ti-layout-board"></i>
                <span class="hide-menu">Dispatch Board</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="containerTracking" aria-expanded="false">
                <i class="ti ti-map-pin"></i>
                <span class="hide-menu">Container Tracking</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="cthmonitoring" aria-expanded="false">
                <i class="ti ti-package"></i>
                <span class="hide-menu">CTH Shipment Monitor</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="workflow" aria-expanded="false">
                <i class="ti ti-timeline"></i>
                <span class="hide-menu">Workflow Timeline</span>
              </a>
            </li>
            <li class="sidebar-item" hidden>
              <a class="sidebar-link" href="dispatchDashboard" aria-expanded="false">
                <i class="ti ti-dashboard"></i>
                <span class="hide-menu">Dispatch Dashboard</span>
              </a>
            </li>
            <li class="sidebar-item" hidden>
              <a class="sidebar-link" href="dispatch" aria-expanded="false">
                <i class="ti ti-truck-delivery"></i>
                <span class="hide-menu">Dispatch</span>
              </a>
            </li>

            <!-- ===================== BOOKINGS ===================== -->
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Bookings</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="addbook" aria-expanded="false">
                <i class="ti ti-book"></i>
                <span class="hide-menu">Add Booking</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="bookingSegments" aria-expanded="false">
                <i class="ti ti-route"></i>
                <span class="hide-menu">Booking Segments</span>
              </a>
            </li>
            <li class="sidebar-item" hidden>
              <a class="sidebar-link justify-content-between has-arrow" href="javascript:void(0)" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-book"></i>
                  </span>
                  <span class="hide-menu">Booking</span>
                </div>

              </a>
              <ul aria-expanded="false" class="collapse first-level">
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="addbook">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Add Booking</span>
                    </div>
                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="mybook">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">My Booking</span>
                    </div>
                  </a>
                </li>
                <?php
                    include "php/config/config.php";

                    // Count only active trips with customer = Del Monte
                    $sql5 = "
                        SELECT COUNT(*) AS active_cth
                        FROM booking WHERE costumer = 'CTH'
                          AND status = 'Active' AND booking_sn != ''
                    ";
                    $result5 = $conn->query($sql5);
                    $row5 = $result5->fetch();
                    $activeCTH = $row5['active_cth'];
                    ?>
                  <li class="sidebar-item">
                    <a class="sidebar-link justify-content-between" href="cthmonitoring">
                      <div class="d-flex align-items-center gap-3">
                        <div class="round-16 d-flex align-items-center justify-content-center">
                          <i class="ti ti-circle"></i>
                        </div>
                        <span class="hide-menu">CTH Booking</span>
                      </div>

                      <?php if ($activeCTH > 0): ?>
                        <span class="badge bg-primary rounded-pill"><p style="margin-bottom: -3px;"><?= $activeCTH ?></p></span>
                      <?php endif; ?>
                    </a>
                  </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="monitoring">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">All Booking Monitoring</span>
                    </div>

                  </a>
                </li>
                <?php
                  include "php/config/config.php";

                  // Count only active trips with customer = ABC
                  $sql = "
                      SELECT COUNT(*) AS active_abc
                      FROM trips t
                      INNER JOIN dispatch d ON d.d_id = t.d_id
                      WHERE (d.costumer = 'ABC' OR t.costumer = 'ABC')
                        AND t.trip_status = 'Active'
                  ";
                  $result = $conn->query($sql);
                  $row = $result->fetch();
                  $activeABC = $row['active_abc'];
                  ?>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between" href="abcmonitoring">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">ABC Monitoring</span>
                    </div>

                    <?php if ($activeABC > 0): ?>
                      <span class="badge bg-primary rounded-pill"><p style="margin-bottom: -3px;"><?= $activeABC ?></p></span>
                    <?php endif; ?>
                  </a>
                </li>
                <?php
                  include "php/config/config.php";

                  // Count only active trips with customer = DOLE
                  $sql1 = "
                      SELECT COUNT(*) AS active_dole
                      FROM trips t
                      INNER JOIN dispatch d ON d.d_id = t.d_id
                      WHERE (d.costumer = 'DOLE' OR t.costumer = 'DOLE')
                        AND t.trip_status = 'Active'
                  ";
                  $result1 = $conn->query($sql1);
                  $row1 = $result1->fetch();
                  $activeDOLE = $row1['active_dole'];
                  ?>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between" href="dolemonitoring">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">DOLE Monitoring</span>
                    </div>

                    <?php if ($activeDOLE > 0): ?>
                      <span class="badge bg-primary rounded-pill"><p style="margin-bottom: -3px;"><?= $activeDOLE ?></p></span>
                    <?php endif; ?>
                  </a>
                </li>

                <?php
                  include "php/config/config.php";

                  // Count only active trips with customer = Del Monte
                  $sql2 = "
                      SELECT COUNT(*) AS active_dm
                      FROM trips t
                      INNER JOIN dispatch d ON d.d_id = t.d_id
                      WHERE (d.costumer = 'DM' OR t.costumer = 'DM')
                        AND t.trip_status = 'Active'
                  ";
                  $result2 = $conn->query($sql2);
                  $row2 = $result2->fetch();
                  $activeDM = $row2['active_dm'];
                  ?>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between" href="dmmonitoring">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">DM Monitoring</span>
                    </div>

                    <?php if ($activeDM > 0): ?>
                      <span class="badge bg-primary rounded-pill"><p style="margin-bottom: -3px;"><?= $activeDM ?></p></span>
                    <?php endif; ?>
                  </a>
                </li>

                <?php
                  include "php/config/config.php";

                  // Count only active trips with customer = Del Monte
                  $sql3 = "
                      SELECT COUNT(*) AS active_farm
                      FROM trips t
                      INNER JOIN dispatch d ON d.d_id = t.d_id
                      WHERE (d.costumer = 'FARM' OR t.costumer = 'FARM')
                        AND t.trip_status = 'Active'
                  ";
                  $result3 = $conn->query($sql3);
                  $row3 = $result3->fetch();
                  $activeFARM = $row3['active_farm'];
                  ?>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between" href="farmmonitoring">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">FARM Monitoring</span>
                    </div>

                    <?php if ($activeFARM > 0): ?>
                      <span class="badge bg-primary rounded-pill"><p style="margin-bottom: -3px;"><?= $activeFARM ?></p></span>
                    <?php endif; ?>
                  </a>
                </li>

                <?php
                  include "php/config/config.php";

                  // Count only active trips with customer = Del Monte
                  $sql4 = "
                      SELECT COUNT(*) AS active_sumi
                      FROM trips t
                      INNER JOIN dispatch d ON d.d_id = t.d_id
                      WHERE (d.costumer = 'SUMI' OR t.costumer = 'SUMI')
                        AND t.trip_status = 'Active'
                  ";
                  $result4 = $conn->query($sql4);
                  $row4 = $result4->fetch();
                  $activeSUMI = $row4['active_sumi'];
                  ?>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between" href="sumimonitoring">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">SUMI Monitoring</span>
                    </div>

                    <?php if ($activeSUMI > 0): ?>
                      <span class="badge bg-primary rounded-pill"><p style="margin-bottom: -3px;"><?= $activeSUMI ?></p></span>
                    <?php endif; ?>
                  </a>
                </li>
              </ul>
            </li>

            <!-- ===================== FLEET ===================== -->
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Fleet</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between has-arrow" href="javascript:void(0)" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-truck"></i>
                  </span>
                  <span class="hide-menu">Units</span>
                </div>

              </a>
              <ul aria-expanded="false" class="collapse first-level">
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="truck">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Truck</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="trailer">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Trailer</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="genset">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Genset</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="geotab-devices">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Geotab Devices</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="geotab-zones">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Geotab Zones</span>
                    </div>

                  </a>
                </li>
              </ul>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="equipment" aria-expanded="false">
                <i class="ti ti-truck"></i>
                <span class="hide-menu">Equipment Locations</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="equipmentUtil" aria-expanded="false">
                <i class="ti ti-chart-bar"></i>
                <span class="hide-menu">Equipment Utilization</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="index.php?route=gate" aria-expanded="false">
                <i class="ti ti-table"></i>
                <span class="hide-menu">Gate</span>
              </a>
            </li>

            <!-- ===================== OPERATIONS ===================== -->
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Operations</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="verifications" aria-expanded="false">
                <i class="ti ti-clipboard-check"></i>
                <span class="hide-menu">Trip Verification</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="fieldCaptures" aria-expanded="false">
                <i class="ti ti-camera"></i>
                <span class="hide-menu">Field Captures</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="serviceTrips" aria-expanded="false">
                <i class="ti ti-tool"></i>
                <span class="hide-menu">Service Trips</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="incidents" aria-expanded="false">
                <i class="ti ti-alert-triangle"></i>
                <span class="hide-menu">Incidents</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between" href="chat" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <i class="ti ti-message-circle"></i>
                  <span class="hide-menu">Driver Chat</span>
                </div>
                <span id="chatUnreadBadgeAdmin" class="badge bg-danger rounded-pill" style="display:none;">0</span>
              </a>
            </li>
            <script>
              (function () {
                if (!window.jQuery) return;
                function poll() {
                  $.getJSON('php/fetch/messages_unread.php', function (res) {
                    if (res.status !== 'success') return;
                    var n = parseInt(res.count, 10) || 0;
                    if (n > 0) { $('#chatUnreadBadgeAdmin').text(n).show(); }
                    else       { $('#chatUnreadBadgeAdmin').hide(); }
                  });
                }
                $(poll);
                setInterval(poll, 15000);
              })();
            </script>

            <!-- ===================== FINANCE ===================== -->
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Finance</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="billing" aria-expanded="false">
                <i class="ti ti-receipt"></i>
                <span class="hide-menu">Billing</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="tripRates" aria-expanded="false">
                <i class="ti ti-receipt-2"></i>
                <span class="hide-menu">Trip Rates</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="foulTrips" aria-expanded="false">
                <i class="ti ti-alert-triangle"></i>
                <span class="hide-menu">Foul Trips</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="payroll" aria-expanded="false">
                <i class="ti ti-cash"></i>
                <span class="hide-menu">Driver Payroll</span>
              </a>
            </li>

            <!-- ===================== PEOPLE & REPORTS ===================== -->
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">People &amp; Reports</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between has-arrow" href="javascript:void(0)" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-users"></i>
                  </span>
                  <span class="hide-menu">Account</span>
                </div>

              </a>
              <ul aria-expanded="false" class="collapse first-level">
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="user">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">User</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="drivers">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Driver</span>
                    </div>

                  </a>
                </li>
              </ul>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between has-arrow" href="javascript:void(0)" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-report"></i>
                  </span>
                  <span class="hide-menu">Report</span>
                </div>

              </a>
              <ul aria-expanded="false" class="collapse first-level">
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="tripReport">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Trip Report</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="truckReport">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Truck Movement</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="trailerReport">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Trailer Movement</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="attendReport">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Attendance</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item" hidden>
                  <a class="sidebar-link justify-content-between"
                    href="driversReport">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Driver's Report</span>
                    </div>
                  </a>
                </li>
              </ul>
            </li>

            <!-- ===================== ADMINISTRATION ===================== -->
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Administration</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="segment" aria-expanded="false">
                <i class="ti ti-list-details"></i>
                <span class="hide-menu">Segment/location</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="activityLog" aria-expanded="false">
                <i class="ti ti-history"></i>
                <span class="hide-menu">Activity Log</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="settings" aria-expanded="false">
                <i class="ti ti-settings"></i>
                <span class="hide-menu">Settings</span>
              </a>
            </li>
          </ul>
        </nav>
        <!-- End Sidebar navigation -->
      </div>
      <!-- End Sidebar scroll-->
    </aside>
