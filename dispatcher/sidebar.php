<?php
$dispatcherRole = $_SESSION['user_type'] ?? '';
if (in_array($dispatcherRole, ['Dispatch Admin', 'Booker'], true)) {
  $isBooker = ($dispatcherRole === 'Booker');
?>
<aside class="left-sidebar">
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
        <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
          <ul id="sidebarnav">
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Home</span>
            </li>
            <?php if (!$isBooker): ?>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-admin-dashboard" aria-expanded="false">
                <i class="ti ti-list-search"></i>
                <span class="hide-menu">Monitor Booking</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-admin-analytics" aria-expanded="false">
                <i class="ti ti-chart-bar"></i>
                <span class="hide-menu">Analytics</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-tiles" aria-expanded="false">
                <i class="ti ti-layout-board"></i>
                <span class="hide-menu">Dispatch Board</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-workflow" aria-expanded="false">
                <i class="ti ti-timeline"></i>
                <span class="hide-menu">Workflow Timeline</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-fieldCaptures" aria-expanded="false">
                <i class="ti ti-camera"></i>
                <span class="hide-menu">Field Captures</span>
              </a>
            </li>
            <?php endif; ?>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-addbook" aria-expanded="false">
                <i class="ti ti-book"></i>
                <span class="hide-menu">Add Booking</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-tracking" aria-expanded="false">
                <i class="ti ti-map-pin"></i>
                <span class="hide-menu">Container Tracking</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-cthmonitoring" aria-expanded="false">
                <i class="ti ti-package"></i>
                <span class="hide-menu">CTH Shipment Monitor</span>
              </a>
            </li>
            <?php if (!$isBooker): ?>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-serviceTrips" aria-expanded="false">
                <i class="ti ti-tool"></i>
                <span class="hide-menu">Service Trips</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-equipment" aria-expanded="false">
                <i class="ti ti-truck"></i>
                <span class="hide-menu">Monitor Equipment</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-equipmentUtil" aria-expanded="false">
                <i class="ti ti-chart-bar"></i>
                <span class="hide-menu">Equipment Utilization</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-addEquipment" aria-expanded="false">
                <i class="ti ti-plus"></i>
                <span class="hide-menu">Add Equipment</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-manageBlockedTrucks" aria-expanded="false">
                <i class="ti ti-truck-off"></i>
                <span class="hide-menu">Blocked Trucks</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-drivers" aria-expanded="false">
                <i class="ti ti-users"></i>
                <span class="hide-menu">Monitor Driver</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-verifications" aria-expanded="false">
                <i class="ti ti-clipboard-check"></i>
                <span class="hide-menu">Trip Verification</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-incidents" aria-expanded="false">
                <i class="ti ti-alert-triangle"></i>
                <span class="hide-menu">Incidents</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-chat" aria-expanded="false">
                <i class="ti ti-message-circle"></i>
                <span class="hide-menu">Driver Chat</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-bookingSegments" aria-expanded="false">
                <i class="ti ti-route-2"></i>
                <span class="hide-menu">Segment / Location</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-foulTrips" aria-expanded="false">
                <i class="ti ti-alert-triangle"></i>
                <span class="hide-menu">Foul Trips</span>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </nav>
      </div>
    </aside>
<?php
  return;
}
?>
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
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Home</span>
            </li> 
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-dashboard" aria-expanded="false">
                <i class="ti ti-atom"></i>
                <span class="hide-menu">Dashboard</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-addbook" aria-expanded="false">
                <i class="ti ti-book"></i>
                <span class="hide-menu">Add Booking</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-tiles" aria-expanded="false">
                <i class="ti ti-layout-board"></i>
                <span class="hide-menu">Dispatch Board</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-tracking" aria-expanded="false">
                <i class="ti ti-map-pin"></i>
                <span class="hide-menu">Container Tracking</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-cthmonitoring" aria-expanded="false">
                <i class="ti ti-package"></i>
                <span class="hide-menu">CTH Shipment Monitor</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-serviceTrips" aria-expanded="false">
                <i class="ti ti-tool"></i>
                <span class="hide-menu">Service Trips</span>
              </a>
            </li>
            <li class="sidebar-item" hidden>
              <a class="sidebar-link" href="dispatch-dispatch" aria-expanded="false">
                <i class="ti ti-truck-delivery"></i>
                <span class="hide-menu">Dispatch</span>
              </a>
            </li>
<?php /* Phase 14 — removed Dispatch Test from sidebar. Route still exists. */ ?>
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
                    href="dispatch-addbook">
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
                    href="dispatch-mybook">
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
                    <a class="sidebar-link justify-content-between" href="dispatch-cthmonitoring">
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
                    href="dispatch-monitoring">
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
                <li class="sidebar-item" hidden>
                  <a class="sidebar-link justify-content-between" href="dispatch-abcmonitoring">
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
                <li class="sidebar-item" hidden>
                  <a class="sidebar-link justify-content-between" href="dispatch-dolemonitoring">
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
                <li class="sidebar-item" hidden>
                  <a class="sidebar-link justify-content-between" href="dispatch-dmmonitoring">
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
                <li class="sidebar-item" hidden>
                  <a class="sidebar-link justify-content-between" href="dispatch-farmmonitoring">
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
                <li class="sidebar-item" hidden>
                  <a class="sidebar-link justify-content-between" href="dispatch-sumimonitoring">
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
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-gate" aria-expanded="false">
                <i class="ti ti-table"></i>
                <span class="hide-menu">Gate</span>
              </a>
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
                    href="dispatch-truck">
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
                    href="dispatch-trailer">
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
                    href="dispatch-genset">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Genset</span>
                    </div>
                    
                  </a>
                </li>
              </ul>
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
                    href="dispatch-drivers">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Driver</span>
                    </div>

                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="dispatch-violations">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-alert-triangle"></i>
                      </div>
                      <span class="hide-menu">Drivers With Violations</span>
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
                    href="dispatch-tripReport">
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
                    href="dispatch-attendReport">
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
                    href="dispatch-driversReport">
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

            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-bookingSegments" aria-expanded="false">
                <i class="ti ti-route"></i>
                <span class="hide-menu">Booking Segments</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-incidents" aria-expanded="false">
                <i class="ti ti-alert-triangle"></i>
                <span class="hide-menu">Incidents</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between" href="dispatch-chat" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <i class="ti ti-message-circle"></i>
                  <span class="hide-menu">Driver Chat</span>
                </div>
                <span id="chatUnreadBadge" class="badge bg-danger rounded-pill" style="display:none;">0</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-verifications" aria-expanded="false">
                <i class="ti ti-clipboard-check"></i>
                <span class="hide-menu">Trip Verification</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-equipment" aria-expanded="false">
                <i class="ti ti-truck"></i>
                <span class="hide-menu">Equipment Locations</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-equipmentUtil" aria-expanded="false">
                <i class="ti ti-chart-bar"></i>
                <span class="hide-menu">Equipment Utilization</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-addEquipment" aria-expanded="false">
                <i class="ti ti-plus"></i>
                <span class="hide-menu">Add Equipment</span>
              </a>
            </li>
            <script>
              // Phase 5+ — live unread chat count for the dispatcher.
              (function () {
                if (!window.jQuery) return;
                function poll() {
                  $.getJSON('php/fetch/messages_unread.php', function (res) {
                    if (res.status !== 'success') return;
                    var n = parseInt(res.count, 10) || 0;
                    if (n > 0) { $('#chatUnreadBadge').text(n).show(); }
                    else       { $('#chatUnreadBadge').hide(); }
                  });
                }
                $(poll);
                setInterval(poll, 15000);
              })();
            </script>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-billing" aria-expanded="false">
                <i class="ti ti-receipt"></i>
                <span class="hide-menu">Billing</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-workflow" aria-expanded="false">
                <i class="ti ti-timeline"></i>
                <span class="hide-menu">Workflow Timeline</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="dispatch-segment" aria-expanded="false">
                <i class="ti ti-list-details"></i>
                <span class="hide-menu">Segment/location</span>
              </a>
            </li>
          </ul>
        </nav>
        <!-- End Sidebar navigation -->
      </div>
      <!-- End Sidebar scroll-->
    </aside>
