<?php
// Phase 13 — Tile-based dispatch dashboard.
// Included by `dispatcher/dispatch-tiles.php` and `admin/dispatch-tiles.php`.
// Required vars from caller: $role ('admin'|'dispatcher'), $baseRoute (string).

$pageTitle = 'Dispatch Board';
$haulingOptions = [];
$locationOptions = [];
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
// Admin toggle — when off, off-shift drivers are view-only (no inline shift start).
$allowOffshiftAssign = pt_setting_bool($conn, 'allow_offshift_assign', true);
// Admin toggle — when on, in-use equipment can be picked (with a confirm prompt).
$allowInUseEquip = pt_setting_bool($conn, 'allow_inuse_equipment', false);
$haulingRes = $conn->query("SELECT hauling_segment FROM hauling ORDER BY hauling_segment ASC");
while ($haulingRes && ($row = ($haulingRes)->fetch())) { $haulingOptions[] = $row['hauling_segment']; }
$locRes = $conn->query("SELECT location_name FROM location ORDER BY location_name ASC");
while ($locRes && ($row = ($locRes)->fetch())) { $locationOptions[] = $row['location_name']; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle); ?> &mdash; Pantrucks Dispatch</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <!-- SortableJS — drag-reorder for the trip-ticket legs in the assign modal. -->
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
  <style>
    /* Tile palette — matches the colour spec from the requirements doc. */
    .tile-bk { border: 2px solid transparent; border-radius: 12px; padding: 12px 14px; margin: 8px 0;
               cursor: grab; transition: transform .1s, box-shadow .1s; user-select: none; }
    .tile-bk:active { cursor: grabbing; }
    .tile-bk:hover  { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0,0,0,.08); }
    .tile-bk.dragging { opacity: .45; }
    .tile-bk .bk-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .tile-bk .bk-no { font-family: monospace; font-size: 12px; color:#334155; display:block; margin-bottom:4px; }
    .tile-bk .bk-customer { font-size: 12px; font-weight: 700; letter-spacing: .02em; color:#0f172a; text-transform: uppercase; }
    .tile-bk .bk-route { font-weight: 700; font-size: 20px; line-height:1.15; color:#0f172a; margin-bottom:4px; }
    .tile-bk .bk-container { font-size: 13px; font-weight: 700; color:#334155; margin-bottom:10px; }
    .tile-bk .bk-meta-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; }
    .tile-bk .bk-meta-card { background:rgba(255,255,255,.52); border:1px solid rgba(255,255,255,.7); border-radius:10px; padding:8px 10px; }
    .tile-bk .bk-meta-label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; margin-bottom:2px; }
    .tile-bk .bk-meta-value { display:block; font-size:13px; color:#0f172a; }
    .tile-bk .bk-meta-value.is-strong { font-weight:700; }
    .tile-bk .bk-segment-pill { display:inline-block; padding:4px 8px; border-radius:999px; background:rgba(15,23,42,.1); color:#0f172a; font-size:11px; font-weight:700; text-transform:uppercase; }
    .recommend-btn { font-size: 11px; padding: 5px 8px; border-radius:999px; border:1px solid rgba(15,23,42,.12); background:rgba(255,255,255,.92); white-space:nowrap; }

    /* Empty lifecycle */
    .tile-empty           { background: #bfdbfe; border-color: #93c5fd; }
    .tile-empty-pickup    { background: #fef9c3; border-color: #fde047; }
    .tile-empty-ontrip    { background: #fed7aa; border-color: #fdba74; }
    .tile-empty-delivered { background: #bbf7d0; border-color: #86efac; }
    /* Loaded lifecycle */
    .tile-loaded           { background: #bfdbfe; border-color: #93c5fd; }
    .tile-loaded-pickup    { background: #fef9c3; border-color: #fde047; }
    .tile-loaded-ontrip    { background: #fed7aa; border-color: #fdba74; }
    .tile-loaded-delivered { background: #bbf7d0; border-color: #86efac; }

    /* Driver tile */
    .tile-driver { border: 2px solid #cbd5e1; border-radius: 8px; padding: 12px;
                   margin: 6px 0; background: #f8fafc; transition: background .15s, border-color .15s;
                   cursor: pointer; }
    .tile-driver:hover { border-color:#94a3b8; background:#f1f5f9; }
    .tile-driver.is-over { background: #ecfdf5; border-color: #10b981; box-shadow: 0 0 0 3px #6ee7b766 inset; }
    .tile-driver.is-hr-blocked { background:#fff1f2; border-color:#fda4af; }
    .tile-driver .drv-name  { font-weight: 600; font-size: 15px; }
    .tile-driver .drv-meta  { font-size: 12px; color: #4b5563; }
    /* Equipment column on the right side of the driver tile — truck (largest,
       dark), trailer (amber), genset (emerald) stacked top-down for at-a-glance
       reading. Distinct colours so dispatchers don't confuse them. */
    .tile-driver .drv-equip { display:flex; flex-direction:column; align-items:flex-end;
                              gap:4px; flex-shrink:0; }
    .tile-driver .drv-equip span { font-family: monospace; font-weight: 700;
                                   padding: 3px 9px; border-radius: 5px; letter-spacing:.02em;
                                   line-height: 1.1; white-space: nowrap; }
    .tile-driver .drv-truck   { background: #1f2937; color: #fff; font-size: 14px; }
    .tile-driver .drv-trailer { background: #fef3c7; color: #92400e;
                                border: 1px solid #fcd34d; font-size: 11px; }
    .tile-driver .drv-genset  { background: #d1fae5; color: #065f46;
                                border: 1px solid #6ee7b7; font-size: 11px; }
    /* When a trailer is currently jacked up on a job, dim it so the dispatcher
       sees it isn't available right now. */
    .tile-driver .drv-trailer.is-jackedup { background:#fee2e2; color:#991b1b;
                                            border-color:#fca5a5; text-decoration: line-through; }

    /* Truck tile (read-only, just an availability list) */
    .tile-truck { display:inline-block; padding: 6px 10px; margin: 4px;
                  border-radius: 6px; background: #1f2937; color: #fff; font-family: monospace; font-size: 12px; }

    .panel-head { display:flex; align-items:center; gap:8px; }
    .panel-head .count-badge { background: #1f2937; color: #fff; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
    .gps-stale { color: #b91c1c; font-style: italic; }

    .legend-chip   { display:inline-block; width:14px; height:14px; border-radius:3px; margin-right:4px; vertical-align:middle; }
    .customer-stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px; }
    .customer-stat-card { border:1px solid #dbe4ee; border-radius:12px; background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); padding:14px 16px; cursor:pointer; transition:transform .1s, box-shadow .1s, border-color .1s; }
    .customer-stat-card:hover { transform:translateY(-1px); box-shadow:0 4px 10px rgba(0,0,0,.06); border-color:#93c5fd; }
    .customer-stat-card.is-active { border-color:#1d4ed8; box-shadow:0 0 0 3px rgba(59,130,246,.25); background:linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%); }
    .customer-stat-name { font-weight:700; font-size:14px; color:#0f172a; margin-bottom:10px; }
    .customer-stat-row { display:flex; align-items:center; justify-content:space-between; font-size:12px; margin-bottom:6px; color:#475569; }
    .customer-stat-row:last-child { margin-bottom:0; }
    .customer-stat-pill { min-width:36px; text-align:center; padding:2px 8px; border-radius:999px; font-weight:700; }
    .pill-available { background:#dbeafe; color:#1d4ed8; }
    .pill-pending { background:#fef3c7; color:#92400e; }
    .pill-complete { background:#dcfce7; color:#166534; }

    /* Drill-down: lane cards (Empty / Loaded) */
    .drill-lane-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
    /* Customer-tile grid: dense responsive grid so 20+ customers still fit. */
    .drill-customer-grid { grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); }
    .drill-customer-tile { background:#f8fafc; border-color:#cbd5e1; }
    .drill-customer-tile:hover { background:#fff; border-color:#94a3b8; }
    .drill-customer-tile .dl-title { display:flex; align-items:baseline;
                                     justify-content:space-between; gap:8px;
                                     margin-bottom:10px !important; }
    .drill-customer-tile .dl-title small { font-size:11px; font-weight:500; }
    .drill-customer-tile .dl-stat-value { font-size:20px !important; }
    .dl-lane-mini { display:flex; gap:6px; margin-top:10px; flex-wrap:wrap; }
    .dl-lane-pill { font-size:10px; font-weight:700; padding:3px 8px; border-radius:999px;
                    text-transform:uppercase; letter-spacing:.04em; }
    .dl-lane-pill.lane-empty  { background:#dbeafe; color:#1e3a8a; }
    .dl-lane-pill.lane-loaded { background:#fed7aa; color:#7c2d12; }
    .drill-lane-card { border:2px solid; border-radius:12px; padding:18px; cursor:pointer;
                       transition:transform .1s, box-shadow .1s; }
    .drill-lane-card:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.08); }
    .drill-lane-card.lane-empty  { background:#dbeafe; border-color:#93c5fd; }
    .drill-lane-card.lane-loaded { background:#fed7aa; border-color:#fdba74; }
    .drill-lane-card.is-disabled { opacity:.45; cursor:not-allowed; pointer-events:none; }
    .drill-lane-card .dl-title { font-size:20px; font-weight:800; color:#0f172a; margin-bottom:14px;
                                 text-transform:uppercase; letter-spacing:.04em; display:flex;
                                 align-items:center; justify-content:space-between; gap:8px; }
    .drill-lane-card .dl-title-badge { font-size:12px; font-weight:700; padding:3px 10px; border-radius:999px;
                                       background:rgba(15,23,42,.12); color:#0f172a; }
    .drill-lane-card .dl-stats { display:flex; gap:10px; }
    .drill-lane-card .dl-stat { flex:1; background:rgba(255,255,255,.65); border-radius:10px;
                                padding:10px 12px; text-align:center; }
    .drill-lane-card .dl-stat-label { display:block; font-size:11px; font-weight:700; color:#475569;
                                      text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
    .drill-lane-card .dl-stat-value { display:block; font-size:24px; font-weight:800; color:#0f172a; }

    /* Drill-down: customer rows */
    .drill-customer-list { display:flex; flex-direction:column; gap:8px; }
    .drill-customer-card { border:1px solid #cbd5e1; border-radius:10px; padding:12px 14px; cursor:pointer;
                           background:#fff; display:flex; align-items:center; justify-content:space-between;
                           gap:12px; transition:border-color .1s, box-shadow .1s; }
    .drill-customer-card:hover { border-color:#1d4ed8; box-shadow:0 2px 6px rgba(0,0,0,.06); }
    .drill-customer-card .dc-name { font-weight:700; color:#0f172a; font-size:14px; }
    .drill-customer-card .dc-stats { display:flex; gap:8px; flex-wrap:wrap; }
    .drill-customer-card .dc-pill { padding:3px 10px; border-radius:999px; font-weight:700;
                                    font-size:12px; min-width:90px; text-align:center; white-space:nowrap; }
    .dc-pill-serve   { background:#dcfce7; color:#166534; }
    .dc-pill-balance { background:#fee2e2; color:#991b1b; }

    /* Drill-down: breadcrumb */
    .drill-breadcrumb { display:flex; align-items:center; gap:6px; font-size:13px; margin-bottom:10px;
                        flex-wrap:wrap; }
    .drill-crumb { color:#1d4ed8; cursor:pointer; text-decoration:underline; background:none; border:0;
                   padding:0; font:inherit; }
    .drill-crumb.is-current { color:#0f172a; cursor:default; text-decoration:none; font-weight:700; }
    .drill-crumb-sep { color:#94a3b8; }

    /* Drill-down: compact booking tile (View 3) */
    .tile-bk-compact { border:2px solid transparent; border-radius:10px; padding:10px 12px; margin:6px 0;
                       cursor:grab; user-select:none; transition:transform .1s, box-shadow .1s; }
    .tile-bk-compact:active { cursor:grabbing; }
    .tile-bk-compact:hover  { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.08); }
    .tile-bk-compact.dragging { opacity:.45; }
    .tile-bk-compact.lane-empty  { background:#dbeafe; border-color:#93c5fd; }
    .tile-bk-compact.lane-loaded { background:#fed7aa; border-color:#fdba74; }
    .tile-bk-compact .bkc-row1 { display:flex; align-items:center; justify-content:space-between;
                                 gap:8px; margin-bottom:6px; }
    .tile-bk-compact .bkc-ref  { font-family:monospace; font-weight:700; font-size:13px; color:#0f172a; }
    .tile-bk-compact .bkc-date { font-size:11px; color:#475569; }
    .tile-bk-compact .bkc-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(78px, 1fr));
                                 gap:6px; }
    .tile-bk-compact .bkc-cell  { background:rgba(255,255,255,.6); border-radius:6px; padding:5px 7px; }
    .tile-bk-compact .bkc-label { display:block; font-size:10px; color:#475569; font-weight:700;
                                  text-transform:uppercase; letter-spacing:.04em; }
    .tile-bk-compact .bkc-value { display:block; font-size:12px; color:#0f172a; font-weight:600;
                                  word-break:break-word; }

    /* Lane section header inside the booking-list view (used when no lane is pre-selected). */
    .bk-lane-section-header { font-size:12px; font-weight:800; text-transform:uppercase;
                              letter-spacing:.04em; color:#475569; margin:10px 4px 4px; }
    /* Stuck Assignments strip — assignments not accepted after threshold. */
    .stuck-strip { background:#fffbeb; border:1px solid #fde68a; border-radius:10px;
                   padding:10px 14px; margin-bottom:14px; display:none; }
    .stuck-strip.is-visible { display:block; }
    .stuck-strip-header { display:flex; align-items:center; gap:8px; font-size:13px;
                          font-weight:700; color:#92400e; margin-bottom:8px; }
    .stuck-strip-header .ti { font-size:18px; }
    .stuck-strip-header .btn-close { margin-left:auto; font-size:11px; }
    .stuck-strip-list { display:flex; gap:8px; flex-wrap:wrap; }
    .stuck-chip { background:#fff; border:1px solid #fcd34d; border-radius:8px;
                  padding:8px 12px; cursor:pointer; transition:transform .1s, box-shadow .1s;
                  min-width:220px; }
    .stuck-chip:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.08); }
    .stuck-chip.sev-warn  { border-color:#fcd34d; background:#fffbeb; }
    .stuck-chip.sev-high  { border-color:#fb923c; background:#fff7ed; }
    .stuck-chip.sev-crit  { border-color:#dc2626; background:#fef2f2;
                            animation: stuckPulse 2s ease-in-out infinite; }
    @keyframes stuckPulse { 0%,100% { box-shadow:0 0 0 0 rgba(220,38,38,.0); }
                            50%      { box-shadow:0 0 0 6px rgba(220,38,38,.12); } }
    .stuck-chip-row1 { display:flex; align-items:center; justify-content:space-between;
                       gap:8px; margin-bottom:4px; }
    .stuck-chip-ref  { font-family:monospace; font-weight:700; font-size:12px; color:#0f172a; }
    .stuck-chip-time { font-size:11px; font-weight:700; color:#92400e; }
    .stuck-chip.sev-high .stuck-chip-time { color:#9a3412; }
    .stuck-chip.sev-crit .stuck-chip-time { color:#991b1b; }
    .stuck-chip-driver { font-size:12px; color:#334155; }
    .stuck-chip-route  { font-size:11px; color:#64748b; margin-top:2px; }

    /* Driver tile gets a small red dot when they're holding a stuck assignment. */
    .tile-driver.has-stuck::after { content:''; position:absolute; top:6px; right:6px;
                                    width:10px; height:10px; border-radius:50%;
                                    background:#dc2626; box-shadow:0 0 0 2px #fff;
                                    animation: stuckPulse 2s ease-in-out infinite; }
    .tile-driver { position:relative; }

    /* Sub-header inside the booking list for CTH shipments — tiles sharing the
       same booking_sn are visually grouped under one Shipment chip. */
    .bk-shipment-section-header {
        font-size:11px; font-weight:700; color:#1e3a8a;
        background:#dbeafe; border:1px solid #bfdbfe; border-radius:6px;
        padding:4px 10px; margin:8px 4px 4px; display:inline-flex;
        align-items:center; gap:6px;
    }
    .bk-shipment-section-header i { font-size:14px; }
    /* Legacy-style searchable dropdowns for Truck / Trailer / Genset. */
    /* Trip-ticket legs table in the Confirm Assignment modal. */
    .am-legs th { font-size:.72rem; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; }
    .am-legs td { padding:.25rem .3rem; }
    .am-legs .form-control-sm, .am-legs .form-select-sm { font-size:.82rem; }
    .am-legs .am-drag { cursor:grab; color:#98a2b3; }
    .am-legs tr.am-booking-leg > td { background-color:#fff8e1; }
    .am-legs .sortable-ghost { opacity:.4; }
    .am-ac { box-shadow:0 6px 18px rgba(16,24,40,.14); border-radius:8px; }
    .am-ac .list-group-item { cursor:pointer; padding:8px 12px; border-left:0; border-right:0; }
    .am-ac .list-group-item:hover,
    .am-ac .list-group-item.active-suggestion { background-color:#007bff; color:#fff; }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">

    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Pantrucks Dispatch Board</h3>
    </div>

    <?php include $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">

          <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
            <h3 class="mb-0">Dispatch Board</h3>
            <span class="text-muted small">Drag a booking onto a driver to assign.</span>
            <div class="ms-auto d-flex align-items-center gap-2 small">
              <span><span class="legend-chip" style="background:#bfdbfe;"></span>New</span>
              <span><span class="legend-chip" style="background:#fef9c3;"></span>Pickup</span>
              <span><span class="legend-chip" style="background:#fed7aa;"></span>On&nbsp;Trip</span>
              <span><span class="legend-chip" style="background:#bbf7d0;"></span>Delivered</span>
              <button class="btn btn-sm btn-outline-secondary" id="btnRefresh"><i class="ti ti-refresh"></i> Refresh</button>
              <button class="btn btn-sm btn-warning" id="btnServiceTrip"><i class="ti ti-tool"></i> Service Trip</button>
              <button class="btn btn-sm btn-outline-warning d-none" id="btnStuckToggle" type="button">
                <i class="ti ti-alert-triangle"></i> Stuck Assignments
                <span class="badge bg-danger ms-1" id="stuckBtnCount">0</span>
              </button>
            </div>
          </div>

          <!-- Stuck assignments strip — assignments not accepted in >= 5 min.
               Hidden by default; user toggles visibility via the Stuck Assignments
               button in the header. Click a chip to recall or reassign. -->
          <div class="stuck-strip" id="stuckStrip">
            <div class="stuck-strip-header">
              <i class="ti ti-alert-triangle"></i>
              Stuck Assignments · <span id="stuckCount">0</span>
              <small class="text-muted ms-2" style="font-weight:500;">click a chip to recall or reassign</small>
              <button type="button" class="btn-close ms-auto" id="btnStuckClose" aria-label="Hide"></button>
            </div>
            <div class="stuck-strip-list" id="stuckList"></div>
          </div>


          <div class="card mb-3">
            <div class="card-body">
              <div class="panel-head mb-2">
                <h5 class="mb-0">Customer Booking Summary</h5>
                <span class="count-badge" id="cntCustomers">0</span>
              </div>
              <div id="customerStats" class="customer-stats-grid">
                <div class="text-muted text-center py-4">Loading…</div>
              </div>
            </div>
          </div>

          <div class="row">
            <!-- BOOKINGS -->
            <div class="col-lg-5">
              <div class="card">
                <div class="card-body">
                  <div class="panel-head mb-2">
                    <h5 class="mb-0">Available Bookings</h5>
                    <span class="count-badge" id="cntBookings">0</span>
                  </div>
                  <div id="bkBreadcrumb" class="drill-breadcrumb"></div>
                  <input type="search" class="form-control form-control-sm mb-2 d-none" id="bkFilter" placeholder="Filter by booking #, SN, customer, route…">
                  <div id="bkList" style="max-height: 65vh; overflow-y: auto;">
                    <div class="text-muted text-center py-4">Loading…</div>
                  </div>
                </div>
              </div>
              
            </div>

            <!-- DRIVERS -->
            <div class="col-lg-5">
              <div class="card">
                <div class="card-body">
                  <div class="panel-head mb-2">
                    <h5 class="mb-0" id="drvPanelTitle">Drivers on Active Shift</h5>
                    <span class="count-badge" id="cntDrivers">0</span>
                    <div class="ms-auto btn-group btn-group-sm" role="group" aria-label="Driver trip filter">
                      <button type="button" class="btn btn-primary" data-drv-filter="idle">
                        <i class="ti ti-zzz"></i> Idle
                      </button>
                      <button type="button" class="btn btn-outline-primary" data-drv-filter="active">
                        <i class="ti ti-truck-delivery"></i> On Trip
                      </button>
                      <button type="button" class="btn btn-outline-primary" data-drv-filter="offshift">
                        <i class="ti ti-user-off"></i> Off Shift
                      </button>
                    </div>
                  </div>
                  <input type="search" class="form-control form-control-sm mb-2" id="drvFilter" placeholder="Filter by name, truck, segment…">
                  <div id="drvList" style="max-height: 65vh; overflow-y: auto;">
                    <div class="text-muted text-center py-4">Loading…</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- TRUCKS -->
            <div class="col-lg-2">
              <div class="row">
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-body">
                      <div class="panel-head mb-2">
                        <h5 class="mb-0">Available Trucks</h5>
                        <span class="count-badge" id="cntTrucks">0</span>
                      </div>
                      <small class="text-muted d-block mb-2">Not yet picked up by a driver at shift-start.</small>
                      <div id="truckList"><div class="text-muted small">Loading…</div></div>
                    </div>
                  </div>
                </div>
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-body">
                      <div class="panel-head mb-2">
                        <h5 class="mb-0">Total Drivers</h5>
                        <span class="count-badge" id="cntAllDrivers">0</span>
                      </div>
                      <small class="text-muted d-block mb-2">All registered drivers in the system.</small>
                      <div class="text-muted small">Useful as a quick manpower baseline beside active-shift assignments.</div>
                    </div>
                  </div>
                </div>
              </div>
              
            </div>
          </div>

          <p class="text-muted small mt-3 mb-0">
            Auto-refreshing every 30 s. Last refresh:
            <span id="fetchedAt">—</span>.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Assignment modal — collected after drop or Recommend → Assign. -->
  <div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Confirm Assignment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3 p-2 bg-light rounded small">
            <div><b>Booking:</b> <span id="amBooking">—</span></div>
            <div><b>Driver:</b> <span id="amDriver">—</span></div>
            <div><b>Truck:</b> <code id="amTruck">—</code></div>
          </div>

          <!-- Assignment-level fields (apply to the whole trip ticket). -->
          <div class="row g-3 mb-3">
            <!-- Truck. For an OFF-SHIFT driver, entering a truck starts their
                 shift on confirm. For an ON-SHIFT driver it shows their current
                 unit and lets the dispatcher re-select a different one. -->
            <div class="col-md-4 position-relative" id="amShiftTruckWrap">
              <label class="form-label">Truck <span class="text-danger">*</span></label>
              <input type="text" class="form-control text-uppercase" id="amShiftTruck" autocomplete="off" placeholder="e.g. PM650">
              <ul id="amShiftTruckList" class="list-group position-absolute w-100 am-ac" style="z-index:1085; display:none; max-height:220px; overflow-y:auto;"></ul>
              <small class="text-muted" id="amShiftTruckHelp">This driver is off shift — confirming will start their shift on this truck.</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Trip Receipt <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="amTripReceipt" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="off" placeholder="6-digit trip receipt">
              <small class="text-muted">Use exactly 6 numbers.</small>
            </div>
            <div class="col-md-4 d-none" id="amEirWrap">
              <label class="form-label"><span id="amEirLabel">EIR Out</span> <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="amEir" autocomplete="off" placeholder="EIR reference">
              <small class="text-muted" id="amEirHelp">Required for CTH dispatch.</small>
            </div>
          </div>

          <!-- Trip legs — trip-ticket style. Drag the handle to reorder run
               order; enter each leg's location / trailer / genset inline. The
               highlighted row is the booking leg (drives the booking + container). -->
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0"><b>Destination / Segment / Equipment</b> <span class="text-danger">*</span></label>
            <button type="button" class="btn btn-sm btn-outline-primary" id="amAddLeg"><iconify-icon icon="mdi:plus"></iconify-icon> Add leg</button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle am-legs mb-1">
              <thead class="table-light">
                <tr>
                  <th style="width:26px;"></th>
                  <th>From</th><th>To</th><th>Hauling Seg</th><th>Hauling Job</th>
                  <th>Trailer</th><th>Genset</th><th>Van no</th><th style="width:110px;">Empty/Loaded</th>
                  <th style="width:92px;"></th>
                </tr>
              </thead>
              <tbody id="amLegsBody"></tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap justify-content-between align-items-center">
            <small class="text-muted">Add repositioning or return legs and drag to set the run order.</small>
            <div>
              <div class="form-check form-switch form-check-inline">
                <input class="form-check-input" type="checkbox" id="amNoTrailer">
                <label class="form-check-label small" for="amNoTrailer">Booking leg bobtail (no trailer)</label>
              </div>
              <div class="form-check form-switch form-check-inline">
                <input class="form-check-input" type="checkbox" id="amNoGenset">
                <label class="form-check-label small" for="amNoGenset">Booking leg no genset</label>
              </div>
            </div>
          </div>

          <!-- Datalists backing the inline leg inputs. -->
          <datalist id="amHaulingSegments">
            <?php foreach ($haulingOptions as $seg): ?>
              <option value="<?php echo htmlspecialchars($seg); ?>">
            <?php endforeach; ?>
          </datalist>
          <datalist id="amTrailerOptions"></datalist>
          <datalist id="amGensetOptions"></datalist>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="amSubmit"><i class="ti ti-check"></i> Assign</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Service Trip modal — assigns a non-booked route (repositioning,
       fuel, shop visit, etc.) to a driver on active shift. -->
  <div class="modal fade" id="serviceTripModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-tool"></i> Assign Service Trip</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">A service trip has no booking — use this for repositioning, fuel runs, shop visits, trailer pickups, etc.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Driver <span class="text-danger">*</span></label>
              <select class="form-select" id="stDriver">
                <option value="" selected>-- Select Driver --</option>
              </select>
              <small class="text-muted">Only drivers on active shift with a chosen truck.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Truck <span class="text-danger">*</span></label>
              <input type="text" class="form-control text-uppercase" id="stTruck" list="stTruckList" autocomplete="off" placeholder="Auto-filled from driver's shift">
              <datalist id="stTruckList"></datalist>
              <small class="text-muted">Auto-fills from the driver's shift — change it to use a different truck.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Service Reason <span class="text-danger">*</span></label>
              <select class="form-select" id="stReason">
                <option value="" selected>-- Select Reason --</option>
                <option value="Repositioning">Repositioning</option>
                <option value="Fuel Run">Fuel Run</option>
                <option value="Shop Visit">Shop Visit</option>
                <option value="Trailer Pickup">Trailer Pickup</option>
                <option value="Trailer Return">Trailer Return</option>
                <option value="Genset Pickup">Genset Pickup</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Trip Receipt</label>
              <input type="text" class="form-control" id="stTripReceipt" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="off" placeholder="Optional — 6 digits if used">
              <small class="text-muted">Optional for service trips.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">From <span class="text-danger">*</span></label>
              <input class="form-control" list="stLocations" id="stFrom" autocomplete="off" placeholder="Select origin">
            </div>
            <div class="col-md-6">
              <label class="form-label">To <span class="text-danger">*</span></label>
              <input class="form-control" list="stLocations" id="stTo" autocomplete="off" placeholder="Select destination">
            </div>
            <datalist id="stLocations">
              <?php foreach ($locationOptions as $loc): ?>
                <option value="<?php echo htmlspecialchars($loc); ?>">
              <?php endforeach; ?>
            </datalist>
            <div class="col-md-6">
              <label class="form-label">Trailer</label>
              <select class="form-select" id="stTrailer">
                <option value="" selected>-- None --</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Genset</label>
              <select class="form-select" id="stGenset">
                <option value="" selected>-- None --</option>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label">Container No</label>
              <input type="text" class="form-control text-uppercase" id="stContainer" maxlength="20" autocomplete="off" placeholder="Optional — container number">
            </div>
            <div class="col-md-12">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" id="stRemarks" rows="2" maxlength="255" placeholder="Optional notes (e.g. pickup empty trailer from Sasa yard)"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-warning" id="stSubmit"><i class="ti ti-check"></i> Assign Service Trip</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Recommendation modal -->
  <div class="modal fade" id="recommendModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Recommended drivers for <span id="recBookingNo"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-2" id="recPickup"></p>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>#</th><th>Driver</th><th>Truck</th><th>Distance</th><th>GPS</th>
                  <th>Active trips</th><th></th>
                </tr>
              </thead>
              <tbody id="recBody"><tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stuck Assignment action modal — opened from the Stuck strip chip. -->
  <div class="modal fade" id="stuckActionModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Stuck assignment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">
            <span class="text-muted small">Booking</span><br>
            <b id="staBooking">—</b>
          </p>
          <p class="mb-2">
            <span class="text-muted small">Assigned to</span><br>
            <b id="staDriver">—</b> <small class="text-muted" id="staTruck"></small>
          </p>
          <p class="mb-3">
            <span class="text-muted small">Pending for</span><br>
            <b id="staMinutes" class="text-danger">—</b>
          </p>
          <div class="mb-2">
            <label class="form-label small mb-1">Reason (optional, kept for audit)</label>
            <input type="text" class="form-control form-control-sm" id="staReason"
                   placeholder="e.g. driver unreachable, route changed">
          </div>
          <input type="hidden" id="staDId">
          <input type="hidden" id="staBookingNo">
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-danger" id="staRecall">
            <i class="ti ti-arrow-back-up"></i> Recall to pool
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" id="staReassign">
              <i class="ti ti-user-check"></i> Reassign to another driver
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Driver Active Transactions modal — opened by clicking a driver tile. -->
  <div class="modal fade" id="driverTxModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            Active transactions — <span id="drvTxName">—</span>
            <small class="text-muted" id="drvTxTruck"></small>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Booking</th>
                  <th>Customer</th>
                  <th>Container</th>
                  <th>Route</th>
                  <th>Status</th>
                  <th>Updated</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="drvTxBody">
                <tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          <small class="text-muted" id="drvTxMeta">—</small>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Role-aware routes for the legacy print pages (Trip Ticket).
    // Admin feature toggle — gates inline shift-start for off-shift drivers.
    var ALLOW_OFFSHIFT_ASSIGN = <?php echo $allowOffshiftAssign ? 'true' : 'false'; ?>;
    // Admin feature toggle — allows picking in-use equipment (with a confirm).
    var ALLOW_INUSE_EQUIP = <?php echo $allowInUseEquip ? 'true' : 'false'; ?>;
    var tripTicketHref    = <?php echo json_encode($role === 'dispatcher' ? 'dispatch-print'    : 'print');     ?>;
    var tripTicketCthHref = <?php echo json_encode($role === 'dispatcher' ? 'dispatch-printcth' : 'printcth'); ?>;

    // ----- Color mapping for booking tiles -------------------------------
    function statusClass(s) {
      var n = String(s || '').trim().toLowerCase();
      if (n === '' || n === 'n/a') n = 'empty';
      switch (n) {
        case 'empty':                       return 'tile-empty';
        case 'empty container pickup':      return 'tile-empty-pickup';
        case 'empty container on trip':     return 'tile-empty-ontrip';
        case 'empty container delivered':   return 'tile-empty-delivered';
        case 'loaded':                      return 'tile-loaded';
        case 'loaded container pickup':     return 'tile-loaded-pickup';
        case 'loaded container on trip':    return 'tile-loaded-ontrip';
        case 'loaded container delivered':  return 'tile-loaded-delivered';
        default:                            return 'tile-empty';
      }
    }
    function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function normalizeTripReceipt(value) { return String(value || '').replace(/\D/g, '').slice(0, 6); }

    // drillView/drillLane drive the new Empty/Loaded → Customer → Bookings drill-down
    // that lives in the Available Bookings panel. selectedCustomer is shared with the
    // top Customer Booking Summary cards so clicking one of those jumps straight to
    // the booking list for that customer (both lanes shown, with section headers).
    var state = {
      bookings: [], drivers: [], offShiftDrivers: [], trucks: [], customerStats: [],
      selectedCustomer: null,
      drillView: 'lane',   // 'lane' | 'customer' | 'booking'
      drillLane: null,     // 'Empty' | 'Loaded' | null
      driverFilter: 'idle' // 'idle' (active_trips === 0) | 'active' (active_trips > 0) | 'offshift' (no open shift)
    };

    function laneClass(lane) {
      return String(lane || '').toLowerCase() === 'loaded' ? 'lane-loaded' : 'lane-empty';
    }
    function bookingLane(b) {
      // dispatch_tiles.php returns container_status as exactly 'Empty' or 'Loaded'
      // for assignable bookings (remaining > 0). Fall back via cl_normalise_lane logic.
      var s = String(b.container_status || '').trim().toLowerCase();
      return s.indexOf('loaded') === 0 ? 'Loaded' : 'Empty';
    }

    function renderCustomerStats() {
      var list = state.customerStats || [];
      $('#cntCustomers').text(list.length);
      if (!list.length) {
        $('#customerStats').html('<div class="text-muted text-center py-4">No customer stats available.</div>');
        return;
      }
      var html = list.map(function(c) {
        var activeClass = (state.selectedCustomer && state.selectedCustomer === c.customer) ? ' is-active' : '';
        return '<div class="customer-stat-card' + activeClass + '" data-customer="' + escapeHtml(c.customer) + '" title="Click to filter bookings by this customer">' +
          '<div class="customer-stat-name">' + escapeHtml(c.customer) + '</div>' +
          '<div class="customer-stat-row"><span>Available</span><span class="customer-stat-pill pill-available">' + c.available + '</span></div>' +
          '<div class="customer-stat-row"><span>Pending</span><span class="customer-stat-pill pill-pending">' + c.pending + '</span></div>' +
          '<div class="customer-stat-row"><span>Complete</span><span class="customer-stat-pill pill-complete">' + c.complete + '</span></div>' +
        '</div>';
      }).join('');
      $('#customerStats').html(html);
    }

    // -- Drill-down rendering ---------------------------------------------
    // View 1 (lane): two cards summing Serve / Not Serve across all bookings per lane.
    // View 2 (customer): customer rows for the chosen lane.
    // View 3 (booking): draggable booking tiles, optionally grouped by lane.
    function renderBookings() {
      // Show the search filter only when we're actually browsing booking tiles.
      $('#bkFilter').toggleClass('d-none', state.drillView !== 'booking');
      renderDrillBreadcrumb();
      if (state.drillView === 'lane')      renderDrillLanes();
      else if (state.drillView === 'customer') renderDrillCustomers();
      else                                  renderDrillBookings();
    }

    function renderDrillBreadcrumb() {
      var parts = [];
      // "All Bookings" — always the root, clickable unless we're already at root.
      parts.push(state.drillView === 'lane'
        ? '<span class="drill-crumb is-current">All Bookings</span>'
        : '<button type="button" class="drill-crumb" data-drill="root">All Bookings</button>');

      // Drill order: Root > Customer > Lane.
      if (state.selectedCustomer) {
        parts.push('<span class="drill-crumb-sep">›</span>');
        parts.push(state.drillView === 'customer'
          ? '<span class="drill-crumb is-current">' + escapeHtml(state.selectedCustomer) + '</span>'
          : '<button type="button" class="drill-crumb" data-drill="customer">' + escapeHtml(state.selectedCustomer) + '</button>');
      }

      if (state.drillView === 'booking' && state.drillLane) {
        parts.push('<span class="drill-crumb-sep">›</span>');
        parts.push('<span class="drill-crumb is-current">' + escapeHtml(state.drillLane) + '</span>');
      }
      $('#bkBreadcrumb').html(parts.join(' '));
    }

    function renderDrillLanes() {
      // Root view: one card per customer, with totals (Serve / Not Serve)
      // summed across all their available bookings AND a mini-row per lane
      // so dispatchers see "12 empty / 6 loaded" at a glance before drilling
      // in. Click a card → jump straight to that customer's booking tiles.
      // Server-provided serve / not-serve totals (count served bookings even
      // after they leave the available list). Keyed by customer.
      var csMap = {};
      (state.customerStats || []).forEach(function(cs) { csMap[cs.customer] = cs; });

      var perCust = {};
      state.bookings.forEach(function(b) {
        var c = String(b.costumer || '').trim() || '—';
        if (!perCust[c]) perCust[c] = {
          customer: c,
          serve: 0, balance: 0,
          emptyBalance: 0, loadedBalance: 0,
          count: 0
        };
        var p = perCust[c];
        var lane = bookingLane(b);
        var rem    = (+b.remaining    || 0);
        p.balance += rem;
        if (lane === 'Loaded') p.loadedBalance += rem;
        else                   p.emptyBalance  += rem;
        p.count++;
      });
      // Apply the server serve-count (falls back to the available balance).
      Object.keys(perCust).forEach(function(c) {
        var cs = csMap[c];
        if (cs) {
          perCust[c].serve   = +cs.served || 0;
          perCust[c].balance = +cs.not_served || perCust[c].balance;
        }
      });
      var list = Object.keys(perCust).sort().map(function(k) { return perCust[k]; });
      $('#cntBookings').text(state.bookings.length);

      if (!list.length) {
        $('#bkList').html('<div class="text-muted text-center py-4">No available bookings.</div>');
        return;
      }

      function customerCard(c) {
        var laneLine = '';
        if (c.emptyBalance > 0 || c.loadedBalance > 0) {
          laneLine =
            '<div class="dl-lane-mini">' +
              (c.emptyBalance  > 0 ? '<span class="dl-lane-pill lane-empty">'  + c.emptyBalance  + ' Empty</span>'  : '') +
              (c.loadedBalance > 0 ? '<span class="dl-lane-pill lane-loaded">' + c.loadedBalance + ' Loaded</span>' : '') +
            '</div>';
        }
        return '<div class="drill-lane-card drill-customer-tile" data-drill-customer="' + escapeHtml(c.customer) + '" role="button" tabindex="0">' +
                 '<div class="dl-title"><span>' + escapeHtml(c.customer) + '</span>' +
                   '<small class="text-muted">' + c.count + ' booking' + (c.count === 1 ? '' : 's') + '</small>' +
                 '</div>' +
                 '<div class="dl-stats">' +
                   '<div class="dl-stat"><span class="dl-stat-label">Serve</span>' +
                     '<span class="dl-stat-value">' + c.serve + '</span></div>' +
                   '<div class="dl-stat"><span class="dl-stat-label">Not Serve</span>' +
                     '<span class="dl-stat-value">' + c.balance + '</span></div>' +
                 '</div>' +
                 laneLine +
               '</div>';
      }
      $('#bkList').html(
        '<div class="drill-lane-grid drill-customer-grid">' + list.map(customerCard).join('') + '</div>' +
        '<p class="text-muted small mt-3 mb-0">Click a customer to see their bookings, then drag a booking onto a driver.</p>'
      );
    }

    // View 2 — lane picker scoped to the selected customer. Shows the same
    // Empty / Loaded big cards as the old root view, but counts are filtered
    // to state.selectedCustomer's bookings only. Click a lane → bookings view.
    function renderDrillCustomers() {
      var cust = state.selectedCustomer;
      if (!cust) { // safety: no customer chosen, fall back to root
        state.drillView = 'lane';
        renderDrillLanes();
        return;
      }
      var totals = { Empty: { serve: 0, balance: 0, count: 0 },
                     Loaded: { serve: 0, balance: 0, count: 0 } };
      state.bookings.forEach(function(b) {
        if (String(b.costumer || '').trim() !== cust) return;
        var lane = bookingLane(b);
        totals[lane].serve   += (+b.quantity_use || 0);
        totals[lane].balance += (+b.remaining   || 0);
        totals[lane].count++;
      });
      $('#cntBookings').text(totals.Empty.count + totals.Loaded.count);

      function laneCard(lane) {
        var t = totals[lane];
        var disabled = (t.count === 0);
        return '<div class="drill-lane-card ' + laneClass(lane) +
                 (disabled ? ' is-disabled' : '') + '"' +
                 (disabled ? '' : ' data-drill-lane="' + lane + '" role="button" tabindex="0"') + '>' +
                 '<div class="dl-title"><span>' + lane + '</span>' +
                   '<small class="text-muted">' + t.count + ' booking' + (t.count === 1 ? '' : 's') + '</small>' +
                 '</div>' +
                 '<div class="dl-stats">' +
                   '<div class="dl-stat"><span class="dl-stat-label">Serve</span>' +
                     '<span class="dl-stat-value">' + t.serve + '</span></div>' +
                   '<div class="dl-stat"><span class="dl-stat-label">Not Serve</span>' +
                     '<span class="dl-stat-value">' + t.balance + '</span></div>' +
                 '</div>' +
               '</div>';
      }
      $('#bkList').html(
        '<div class="drill-lane-grid">' + laneCard('Empty') + laneCard('Loaded') + '</div>' +
        '<p class="text-muted small mt-3 mb-0">Click a lane to see ' + escapeHtml(cust) + '\'s bookings.</p>'
      );
    }

    function renderDrillBookings() {
      var q = $('#bkFilter').val().trim().toLowerCase();
      var lane = state.drillLane;
      var sel = state.selectedCustomer;
      var list = state.bookings.filter(function(b) {
        if (lane && bookingLane(b) !== lane) return false;
        if (sel && String(b.costumer || '').trim() !== sel) return false;
        if (!q) return true;
        return [b.booking_no, b.booking_sn, b.costumer, b.customer_segment, b.container, b.trip_from, b.trip_to, b.hauling_segment]
          .map(function(x) { return String(x || '').toLowerCase(); })
          .some(function(x) { return x.indexOf(q) !== -1; });
      });
      $('#cntBookings').text(list.length);
      if (!list.length) {
        $('#bkList').html('<div class="text-muted text-center py-4">No available bookings.</div>');
        return;
      }
      // Group by lane when no lane filter is active (e.g. came in via top customer card).
      var groups;
      if (lane) {
        groups = [{ lane: lane, items: list }];
      } else {
        var emptyItems  = list.filter(function(b) { return bookingLane(b) === 'Empty'; });
        var loadedItems = list.filter(function(b) { return bookingLane(b) === 'Loaded'; });
        groups = [];
        if (emptyItems.length)  groups.push({ lane: 'Empty',  items: emptyItems });
        if (loadedItems.length) groups.push({ lane: 'Loaded', items: loadedItems });
      }

      var html = groups.map(function(g) {
        var header = (groups.length > 1)
          ? '<div class="bk-lane-section-header">' + g.lane + ' · ' + g.items.length + '</div>'
          : '';
        return header + renderShipmentSubgroups(g.items);
      }).join('');
      $('#bkList').html(html);
    }

    // CTH bookings carry a booking_sn (Shipment Number) — multiple booking rows
    // that share the same SN belong to one physical shipment. Group those tiles
    // under a small subheader so dispatchers can see related work at a glance.
    // Non-CTH bookings have no SN → renders flat exactly as before.
    function renderShipmentSubgroups(items) {
      var byKey = Object.create(null);
      var keyOrder = [];
      items.forEach(function(b) {
        var sn = String(b.booking_sn || '').trim();
        var bucket = sn || '__nosn__';
        if (!byKey[bucket]) { byKey[bucket] = []; keyOrder.push(bucket); }
        byKey[bucket].push(b);
      });
      // Fast path: nothing has an SN (the typical non-CTH case) — render flat.
      if (keyOrder.length === 1 && keyOrder[0] === '__nosn__') {
        return items.map(renderBookingTile).join('');
      }
      return keyOrder.map(function(bucket) {
        var arr = byKey[bucket];
        if (bucket === '__nosn__') {
          return arr.map(renderBookingTile).join('');
        }
        return '<div class="bk-shipment-section-header">' +
                 '<i class="ti ti-ship"></i> Shipment ' + escapeHtml(bucket) + ' · ' + arr.length +
               '</div>' + arr.map(renderBookingTile).join('');
      }).join('');
    }

    // Format a 'YYYY-MM-DD HH:MM:SS' timestamp as e.g. "Jun 27, 2026 7:50 AM".
    function fmtBookingDateTime(ts) {
      if (!ts) return '—';
      var d = new Date(String(ts).replace(' ', 'T'));
      if (isNaN(d.getTime())) return String(ts);
      var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      var h = d.getHours(), m = d.getMinutes(), ap = h >= 12 ? 'PM' : 'AM';
      var h12 = h % 12; if (h12 === 0) h12 = 12;
      return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() +
             ' ' + h12 + ':' + (m < 10 ? '0' + m : m) + ' ' + ap;
    }

    function renderBookingTile(b) {
      var lane = bookingLane(b);
      var isLoaded = lane === 'Loaded';
      var hasContainer = String(b.container || '').trim() !== '';
      var cells = [
        { label: 'Date & Time', value: fmtBookingDateTime(b.created_at) },
        { label: 'Req. Date', value: b.booking_daterequired || '—' },
        { label: 'Reference', value: b.booking_no || '—' },
        { label: 'Qty',       value: b.quantity }
      ];
      // Container shows whenever one is set on the booking — Empty-lane
      // bookings can still have a container (e.g. empty repositioning).
      if (hasContainer) {
        cells.push({ label: 'Container', value: b.container });
      }
      if (isLoaded) {
        cells.push({ label: 'Location',
                     value: ((b.trip_from || '—') + ' → ' + (b.trip_to || '—')) });
      }
      cells.push({ label: 'Serve',   value: b.quantity_use });
      cells.push({ label: 'Balance', value: b.remaining });

      var grid = cells.map(function(c) {
        return '<div class="bkc-cell">' +
                 '<span class="bkc-label">' + escapeHtml(c.label) + '</span>' +
                 '<span class="bkc-value">' + escapeHtml(c.value) + '</span>' +
               '</div>';
      }).join('');

      return '<div class="tile-bk-compact ' + laneClass(lane) + '" draggable="true" data-booking="' +
               escapeHtml(b.booking_no) + '">' +
               '<div class="bkc-row1">' +
                 '<div>' +
                   '<div class="bkc-ref">' + escapeHtml(b.booking_no) + '</div>' +
                   '<div class="bkc-date">' + escapeHtml(b.costumer || '—') +
                     (b.customer_segment ? ' <span class="bk-segment-pill">' + escapeHtml(b.customer_segment) + '</span>' : '') +
                   '</div>' +
                 '</div>' +
                 '<button class="btn btn-light recommend-btn" type="button" data-recommend="' +
                   escapeHtml(b.booking_no) + '"><i class="ti ti-wand"></i> Suggest</button>' +
               '</div>' +
               '<div class="bkc-grid">' + grid + '</div>' +
             '</div>';
    }

    // Does a driver row match the filter text?
    function drvMatchesQuery(d, q) {
      return [d.driver_name, d.shift_truck, d.segment, d.driver_status]
        .some(function(x) { return String(x || '').toLowerCase().indexOf(q) !== -1; });
    }
    // How many drivers in a given tab match the query (for auto-tab-switch).
    function drvTabMatchCount(tab, q) {
      if (tab === 'offshift') {
        return (state.offShiftDrivers || []).filter(function(d) { return drvMatchesQuery(d, q); }).length;
      }
      return (state.drivers || []).filter(function(d) {
        var t = +d.active_trips || 0;
        if (tab === 'idle' && t !== 0) return false;
        if (tab === 'active' && t === 0) return false;
        return drvMatchesQuery(d, q);
      }).length;
    }

    function renderDrivers() {
      var q = $('#drvFilter').val().trim().toLowerCase();
      // Highlight the active toggle button.
      $('[data-drv-filter]').each(function() {
        var on = $(this).data('drv-filter') === state.driverFilter;
        $(this).toggleClass('btn-primary', on).toggleClass('btn-outline-primary', !on);
      });
      $('#drvPanelTitle').text(state.driverFilter === 'offshift'
        ? 'Drivers Off Shift' : 'Drivers on Active Shift');

      // Off-shift view — drivers without an open shift. View-only (no drag /
      // assign), so it gets its own simpler tile layout.
      if (state.driverFilter === 'offshift') {
        renderOffShiftDrivers(q);
        return;
      }

      var list = state.drivers.filter(function(d) {
        var trips = +d.active_trips || 0;
        if (state.driverFilter === 'idle'   && trips !== 0) return false;
        if (state.driverFilter === 'active' && trips === 0) return false;
        if (!q) return true;
        return [d.driver_name, d.shift_truck, d.segment]
          .map(function(x) { return String(x || '').toLowerCase(); })
          .some(function(x) { return x.indexOf(q) !== -1; });
      });
      // "On Trip" — show the most recently assigned driver first (latest trip
      // = highest active dispatch id). Other views stay alphabetical.
      if (state.driverFilter === 'active') {
        list.sort(function(a, b) {
          return (+b.latest_active_dispatch_id || 0) - (+a.latest_active_dispatch_id || 0);
        });
      }
      $('#cntDrivers').text(list.length);
      if (!list.length) {
        var msg = state.driverFilter === 'idle'
          ? 'No idle drivers on active shift.'
          : 'No drivers currently on a trip.';
        $('#drvList').html('<div class="text-muted text-center py-4">' + msg + '</div>');
        return;
      }
      var html = list.map(function(d) {
        var gpsBadge = d.has_gps
          ? '<span class="badge bg-success">GPS</span>'
          : '<span class="badge bg-secondary">No GPS</span>';
        var trips = d.active_trips > 0
          ? '<span class="badge bg-warning text-dark">' + d.active_trips + ' active</span>'
          : '<span class="badge bg-light text-muted">idle</span>';
        // Red pulsing dot on the driver tile if this driver is currently
        // holding a stuck (>= threshold) assignment.
        var stuckClass = state.stuckDriverIds[d.driver_id] ? ' has-stuck' : '';
        // Equipment column: truck (big, dark), trailer (amber), genset (green).
        var trailerClass = 'drv-trailer' + (d.assigned_trailer_jacked_up ? ' is-jackedup' : '');
        var equipHtml =
              '<div class="drv-equip">' +
                '<span class="drv-truck" title="Truck">' + escapeHtml(d.shift_truck || '—') + '</span>' +
                (d.assigned_trailer
                  ? '<span class="' + trailerClass + '" title="Trailer' +
                    (d.assigned_trailer_jacked_up ? ' (jacked up)' : '') + '">' +
                    escapeHtml(d.assigned_trailer) + '</span>'
                  : '') +
                (d.assigned_genset
                  ? '<span class="drv-genset" title="Genset">' +
                    escapeHtml(d.assigned_genset) + '</span>'
                  : '') +
              '</div>';
        return '<div class="tile-driver' + stuckClass + '" data-driver-id="' + d.driver_id + '" data-truck="' + escapeHtml(d.shift_truck) + '"' +
                 (stuckClass ? ' title="This driver has a stuck assignment"' : '') + '>' +
                 '<div class="d-flex align-items-start justify-content-between gap-2">' +
                   '<div class="flex-grow-1 min-w-0">' +
                     '<div class="drv-name">' + escapeHtml(d.driver_name) + '</div>' +
                     '<div class="drv-meta mt-1">' +
                       (d.segment ? escapeHtml(d.segment) + ' · ' : '') +
                       gpsBadge + ' ' + trips +
                       (d.shift_open_since ? ' · since ' + escapeHtml(d.shift_open_since) : '') +
                     '</div>' +
                   '</div>' +
                   equipHtml +
                 '</div>' +
               '</div>';
      }).join('');
      $('#drvList').html(html);
    }

    // Off-shift drivers — informational only. No GPS-assign, no equipment,
    // not draggable; the tile carries no data-driver-id so the assign modal
    // and drag-drop handlers never fire for these rows.
    function renderOffShiftDrivers(q) {
      var list = (state.offShiftDrivers || []).filter(function(d) {
        if (!q) return true;
        return [d.driver_name, d.shift_truck, d.segment, d.driver_status]
          .map(function(x) { return String(x || '').toLowerCase(); })
          .some(function(x) { return x.indexOf(q) !== -1; });
      });
      $('#cntDrivers').text(list.length);
      if (!list.length) {
        $('#drvList').html('<div class="text-muted text-center py-4">No off-shift drivers.</div>');
        return;
      }
      var html = list.map(function(d) {
        var statusBadge = d.driver_status
          ? '<span class="badge bg-light text-muted">' + escapeHtml(d.driver_status) + '</span>'
          : '';
        // When the admin feature is ON, the tile is a drop target:
        //   data-offshift flags it so the click handler skips the active-
        //   transactions modal; data-driver-id makes a dragged booking land
        //   here (which then asks for a truck and starts the shift).
        // When OFF, the tile is purely informational (no data-driver-id).
        if (ALLOW_OFFSHIFT_ASSIGN) {
          return '<div class="tile-driver" data-driver-id="' + d.driver_id + '" data-offshift="1" data-truck="" ' +
                   'title="Drop a booking here to assign — you\'ll enter a truck to start the shift.">' +
                   '<div class="d-flex align-items-start justify-content-between gap-2">' +
                     '<div class="flex-grow-1 min-w-0">' +
                       '<div class="drv-name">' + escapeHtml(d.driver_name) + '</div>' +
                       '<div class="drv-meta mt-1">' +
                         (d.segment ? escapeHtml(d.segment) + ' · ' : '') +
                         '<span class="badge bg-secondary">Off shift</span> ' + statusBadge +
                       '</div>' +
                     '</div>' +
                     '<i class="ti ti-plus text-muted"></i>' +
                   '</div>' +
                 '</div>';
        }
        return '<div class="tile-driver" style="opacity:.85;cursor:default;">' +
                 '<div class="d-flex align-items-start justify-content-between gap-2">' +
                   '<div class="flex-grow-1 min-w-0">' +
                     '<div class="drv-name">' + escapeHtml(d.driver_name) + '</div>' +
                     '<div class="drv-meta mt-1">' +
                       (d.segment ? escapeHtml(d.segment) + ' · ' : '') +
                       '<span class="badge bg-secondary">Off shift</span> ' + statusBadge +
                     '</div>' +
                   '</div>' +
                 '</div>' +
               '</div>';
      }).join('');
      $('#drvList').html(html);
    }

    function renderTrucks() {
      $('#cntTrucks').text(state.trucks.length);
      if (!state.trucks.length) {
        $('#truckList').html('<div class="text-muted small">All trucks are with drivers or out.</div>');
        return;
      }
      $('#truckList').html(state.trucks.map(function(t) {
        return '<span class="tile-truck">' + escapeHtml(t.unit_name) + '</span>';
      }).join(''));
    }

    // Stuck assignments — driver_ids currently holding one (for the red dot
     // on the driver tile) + the raw rows used by the strip and modal.
    state.stuckRows       = [];
    state.stuckDriverIds  = {};
    function refreshStuck() {
      return $.getJSON('php/fetch/stuck_assignments.php').done(function(res) {
        if (!res || res.status !== 'success') return;
        state.stuckRows      = res.rows || [];
        state.stuckDriverIds = {};
        state.stuckRows.forEach(function (r) { state.stuckDriverIds[r.driver_id] = true; });
        renderStuckStrip();
        // Re-render drivers so the red dot updates without waiting for the
        // next big refresh tick.
        renderDrivers();
      });
    }

    // < 1h → "8m", < 1d → "2hrs" / "2hrs 30m", ≥ 1d → "1d 16hrs".
    function formatPendingTime(mins) {
      mins = Math.max(0, Math.floor(mins || 0));
      if (mins < 60) return mins + 'm';
      if (mins < 1440) {
        var h = Math.floor(mins / 60);
        var m = mins % 60;
        return m === 0 ? h + 'hrs' : h + 'hrs ' + m + 'm';
      }
      var d = Math.floor(mins / 1440);
      var hh = Math.floor((mins % 1440) / 60);
      return hh === 0 ? d + 'd' : d + 'd ' + hh + 'hrs';
    }

    function renderStuckStrip() {
      var rows = state.stuckRows || [];
      var $strip = $('#stuckStrip');
      var $btn   = $('#btnStuckToggle');
      $('#stuckCount').text(rows.length);
      $('#stuckBtnCount').text(rows.length);
      // Toggle button is only visible when there are stuck assignments.
      if (!rows.length) {
        $btn.addClass('d-none');
        $strip.removeClass('is-visible');
        $('#stuckList').empty();
        return;
      }
      $btn.removeClass('d-none');
      // Strip stays hidden by default — only the user can open it via the button.
      var html = rows.map(function (r) {
        var sev = r.minutes_pending >= 20 ? 'sev-crit'
                : r.minutes_pending >= 10 ? 'sev-high' : 'sev-warn';
        return '<div class="stuck-chip ' + sev + '" data-stuck-d-id="' + escapeHtml(r.d_id) + '">' +
                 '<div class="stuck-chip-row1">' +
                   '<span class="stuck-chip-ref">' + escapeHtml(r.booking_no || '#' + r.d_id) + '</span>' +
                   '<span class="stuck-chip-time">' + formatPendingTime(r.minutes_pending) + '</span>' +
                 '</div>' +
                 '<div class="stuck-chip-driver">→ ' + escapeHtml(r.driver_name || '—') +
                   (r.truck ? ' <small class="text-muted">(' + escapeHtml(r.truck) + ')</small>' : '') +
                 '</div>' +
                 '<div class="stuck-chip-route">' + escapeHtml(r.customer || '—') + ' · ' +
                   escapeHtml(r.trip_from || '—') + ' → ' + escapeHtml(r.trip_to || '—') +
                 '</div>' +
               '</div>';
      }).join('');
      $('#stuckList').html(html);
    }

    function applyBookingDeepLink() {
      var params = new URLSearchParams(window.location.search);
      var target = (params.get('booking') || '').trim();
      if (!target) return false;
      var booking = (state.bookings || []).find(function(b) {
        return String(b.booking_no || '').toLowerCase() === target.toLowerCase();
      });
      if (!booking) return false;
      state.selectedCustomer = String(booking.costumer || '').trim() || null;
      state.drillLane = bookingLane(booking);
      state.drillView = 'booking';
      $('#bkFilter').val(booking.booking_no);
      renderBookings();
      return true;
    }

    function refresh() {
      return $.getJSON('php/fetch/dispatch_tiles.php').done(function(res) {
        if (res.status !== 'success') return;
        state.bookings = res.bookings;
        state.drivers  = res.drivers;
        state.offShiftDrivers = res.off_shift_drivers || [];
        state.trucks   = res.trucks;
        state.customerStats = res.customer_stats || [];
        $('#cntAllDrivers').text((res.counts && res.counts.total_drivers) || 0);
        renderCustomerStats(); renderBookings(); renderDrivers(); renderTrucks();
        applyBookingDeepLink();
        $('#fetchedAt').text(res.fetched_at);
      }).always(function () {
        // Always pair the main refresh with a stuck-strip refresh so they
        // stay in lockstep at the same 30s cadence.
        refreshStuck();
      });
    }

    // ----- Drag and drop -------------------------------------------------
    var draggingBooking = null;

    $(document).on('dragstart', '.tile-bk, .tile-bk-compact', function(e) {
      draggingBooking = $(this).data('booking');
      $(this).addClass('dragging');
      e.originalEvent.dataTransfer.effectAllowed = 'move';
      // Required for Firefox.
      try { e.originalEvent.dataTransfer.setData('text/plain', String(draggingBooking)); } catch (_) {}
    });
    $(document).on('dragend', '.tile-bk, .tile-bk-compact', function() {
      $(this).removeClass('dragging');
      $('.tile-driver').removeClass('is-over');
      draggingBooking = null;
    });
    $(document).on('dragover', '.tile-driver', function(e) {
      if (!draggingBooking) return;
      e.preventDefault();
      e.originalEvent.dataTransfer.dropEffect = 'move';
      $(this).addClass('is-over');
    });
    $(document).on('dragleave', '.tile-driver', function() {
      $(this).removeClass('is-over');
    });
    $(document).on('drop', '.tile-driver', function(e) {
      e.preventDefault();
      var $drv = $(this);
      $drv.removeClass('is-over');
      var bookingNo = draggingBooking;
      var driverId  = $drv.data('driver-id');
      var truck     = $drv.data('truck');
      if (!bookingNo || !driverId) return;
      openAssignModal(bookingNo, driverId, truck, $drv.find('.drv-name').text());
    });

    // ----- Driver active-transactions modal ------------------------------
    // Click on a driver tile (no drag in progress) → fetch and show their
    // in-flight dispatches.
    function workflowPillClass(stage) {
      var s = String(stage || '').toLowerCase();
      if (s === 'dispatcher_assigned' || s === 'reassigned')  return 'bg-warning text-dark';
      if (s === 'driver_accepted')                            return 'bg-info text-dark';
      if (s === 'gate_cleared')                               return 'bg-primary';
      if (s === 'en_route')                                   return 'bg-warning text-dark';
      if (s === 'delivered' || s === 'pending_verification')  return 'bg-success';
      return 'bg-secondary';
    }
    function workflowLabel(stage) {
      var map = {
        dispatcher_assigned: 'To Be Accepted',
        reassigned:          'Reassigned',
        driver_accepted:     'Accepted',
        gate_cleared:        'Gate Cleared',
        en_route:            'On Trip',
        delivered:           'Awaiting POD',
        pending_verification:'To be Verified'
      };
      return map[String(stage || '').toLowerCase()] || (stage || '—');
    }

    var trackingHref = <?php echo json_encode($role === 'dispatcher' ? 'dispatch-tracking' : 'containerTracking'); ?>;

    $(document).on('click', '.tile-driver', function(e) {
      // Don't fire if a drag-drop just happened (browser suppresses click after
      // drop anyway, but this guards against synthetic clicks).
      if (draggingBooking) return;
      var $drv = $(this);
      // Off-shift tiles have no active dispatches — skip the transactions modal.
      if ($drv.data('offshift')) return;
      var driverId = $drv.data('driver-id');
      var truck    = $drv.data('truck') || '';
      var name     = $drv.find('.drv-name').text().trim();
      if (!driverId) return;

      currentDrvTxId = driverId;
      $('#drvTxName').text(name || 'Driver');
      $('#drvTxTruck').text(truck ? ' · ' + truck : '');
      $('#driverTxModal').modal('show');
      loadDriverTx();
    });

    // Loads (and reloads) the active-transactions table for the open driver.
    var currentDrvTxId = null;
    function loadDriverTx() {
      if (!currentDrvTxId) return;
      $('#drvTxMeta').text('Loading…');
      $('#drvTxBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>');
      $.getJSON('php/fetch/driver_active_dispatches.php', { driver_id: currentDrvTxId })
        .done(function(res) {
          if (!res || res.status !== 'success') {
            $('#drvTxBody').html('<tr><td colspan="7" class="text-center text-danger py-4">' +
              escapeHtml((res && res.message) || 'Failed to load.') + '</td></tr>');
            $('#drvTxMeta').text('');
            return;
          }
          var rows = res.rows || [];
          $('#drvTxMeta').text(rows.length + ' active transaction' + (rows.length === 1 ? '' : 's') +
            ' · refreshed ' + (res.fetched_at || ''));
          if (!rows.length) {
            $('#drvTxBody').html('<tr><td colspan="7" class="text-center text-muted py-4">' +
              'No active transactions for this driver.</td></tr>');
            return;
          }
          var html = rows.map(function(r) {
            var stagePill = '<span class="badge ' + workflowPillClass(r.workflow_stage) + '">' +
                            escapeHtml(workflowLabel(r.workflow_stage)) + '</span>';
            var route = escapeHtml(r.trip_from || '—') + ' → ' + escapeHtml(r.trip_to || '—');
            // Pre-filter Container Tracking to this exact trip (trip receipt is
            // unique per dispatch; fall back to booking number).
            var trackQ = r.trip_receipt || r.booking_no || '';
            var trackUrl = trackingHref + (trackQ ? ('?q=' + encodeURIComponent(trackQ)) : '');
            // Action deep-links: open Container Tracking filtered to this trip and
            // auto-open the Update / Manual Complete modal there (reuse, no dup).
            var qPart = trackQ ? ('q=' + encodeURIComponent(trackQ) + '&') : '';
            var editUrl   = trackingHref + '?' + qPart + 'act=edit&did='   + r.d_id;
            var manualUrl = trackingHref + '?' + qPart + 'act=manual&did=' + r.d_id;
            // Trip ticket (CTH bookings use the CTH layout).
            var isCthTx    = String(r.customer || '').trim().toUpperCase() === 'CTH';
            var ticketHref = isCthTx ? tripTicketCthHref : tripTicketHref;
            // TEMP: remote-accept for trips still awaiting the driver's acceptance.
            var acceptBtn = '';
            if (r.workflow_stage === 'dispatcher_assigned' || r.workflow_stage === 'reassigned') {
              acceptBtn = '<button class="btn btn-sm btn-success me-1 drv-remote-accept" data-d-id="' +
                          r.d_id + '" title="Remotely accept this trip for the driver">' +
                          '<i class="ti ti-check"></i> Accept</button>';
            }
            // Print / Update / Manual Complete only appear once the trip is
            // accepted (driver_accepted or later) — not while "To Be Accepted".
            var isAcceptedTx = ['driver_accepted','gate_cleared','en_route','delivered','pending_verification'].indexOf(r.workflow_stage) !== -1;
            var actionBtns = !isAcceptedTx ? '' : (
                  '<a class="btn btn-sm btn-outline-primary me-1" href="' + ticketHref + '?id=' + r.d_id + '" target="_blank" rel="noopener" title="Print Trip Ticket"><i class="ti ti-file-text"></i></a>' +
                  '<a class="btn btn-sm btn-outline-secondary me-1" href="' + editUrl + '" target="_blank" rel="noopener" title="Update"><i class="ti ti-edit"></i></a>' +
                  '<a class="btn btn-sm btn-outline-danger me-1" href="' + manualUrl + '" target="_blank" rel="noopener" title="Manual Complete"><i class="ti ti-checks"></i></a>'
                );
            return '<tr>' +
              '<td><span class="font-monospace small">' + escapeHtml(r.booking_no || '—') + '</span>' +
                  (r.trip_receipt ? '<br><small class="text-muted">TR ' + escapeHtml(r.trip_receipt) + '</small>' : '') + '</td>' +
              '<td>' + escapeHtml(r.customer || '—') +
                  (r.customer_segment ? '<br><small class="text-muted">' + escapeHtml(r.customer_segment) + '</small>' : '') + '</td>' +
              '<td>' + escapeHtml(r.container || '—') +
                  (r.container_status ? '<br><small class="text-muted">' + escapeHtml(r.container_status) + '</small>' : '') + '</td>' +
              '<td>' + route + '</td>' +
              '<td>' + stagePill + '</td>' +
              '<td><small>' + escapeHtml(r.workflow_updated_at || '—') + '</small></td>' +
              '<td class="text-nowrap">' + acceptBtn + actionBtns +
                  '<a class="btn btn-sm btn-outline-secondary" href="' + trackUrl + '" target="_blank" rel="noopener" title="Open in Container Tracking"><i class="ti ti-external-link"></i></a></td>' +
            '</tr>';
          }).join('');
          $('#drvTxBody').html(html);
        })
        .fail(function(xhr) {
          var msg = (xhr && xhr.responseJSON && xhr.responseJSON.message)
                 || (xhr && xhr.responseText)
                 || 'Network error.';
          $('#drvTxBody').html('<tr><td colspan="7" class="text-center text-danger py-4">' +
            escapeHtml(msg) + '</td></tr>');
          $('#drvTxMeta').text('');
        });
    }

    // TEMP: remote-accept — accept a "To Be Accepted" trip on the driver's behalf.
    $(document).on('click', '.drv-remote-accept', function() {
      var dId = $(this).data('d-id');
      var $btn = $(this);
      Swal.fire({
        title: 'Accept this trip for the driver?',
        text: 'This remotely marks the trip as accepted (temporary feature).',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, accept'
      }).then(function(r) {
        if (!r.isConfirmed) return;
        $btn.prop('disabled', true);
        $.post('php/operations/remote_accept_tracking.php', { d_id: dId }, null, 'json')
          .done(function(res) {
            if (res && res.status === 'success') {
              Swal.fire({ icon: 'success', text: res.message || 'Accepted.', timer: 1400, showConfirmButton: false });
              loadDriverTx();
              if (typeof refresh === 'function') refresh();
            } else {
              $btn.prop('disabled', false);
              Swal.fire({ icon: 'error', text: (res && res.message) || 'Accept failed.' });
            }
          })
          .fail(function(xhr) {
            $btn.prop('disabled', false);
            var msg = 'Network error.';
            try { msg = (JSON.parse(xhr.responseText) || {}).message || msg; } catch (e) {}
            Swal.fire({ icon: 'error', text: msg });
          });
      });
    });

    // ----- Assignment modal ---------------------------------------------
    // Opened after a drop or from the Recommend modal's Assign button.
    // Always collects Trip Receipt + Trailer + Genset before posting to
    // assign_booking_dnd.php — the lean DnD path now requires all three.
    var amCtx = null;
    // Lists backing the searchable Trailer / Genset dropdowns (Truck reads state.trucks).
    var amTrailers = [], amGensets = [];

    // Legacy-style searchable dropdown: filters `getData()` as the user types,
    // highlights the first match, supports arrow keys + Enter, click to select.
    function amWireAutocomplete(inputId, listId, getData) {
      var $inp = $('#' + inputId), $list = $('#' + listId);
      function choose(val) { $inp.val(val); $list.hide(); $inp.trigger('change'); }
      function render() {
        var q = String($inp.val() || '').toLowerCase().trim();
        var data = getData() || [];
        $list.empty();
        if (!q) { $list.hide(); return; }
        var filtered = data.filter(function (x) { return String(x).toLowerCase().indexOf(q) !== -1; }).slice(0, 60);
        if (!filtered.length) { $list.hide(); return; }
        filtered.forEach(function (item, i) {
          var $li = $('<li class="list-group-item"></li>').text(item);
          if (i === 0) $li.addClass('active-suggestion');
          // mousedown (not click) so the input's blur doesn't hide the list first.
          $li.on('mousedown', function (e) { e.preventDefault(); choose(item); });
          $list.append($li);
        });
        $list.show();
      }
      $inp.on('input focus', render);
      $inp.on('keydown', function (e) {
        var items = $list.children('li');
        if (!items.length) return;
        var idx = items.index($list.children('.active-suggestion'));
        if (e.key === 'ArrowDown') { e.preventDefault(); idx = (idx + 1) % items.length; items.removeClass('active-suggestion'); $(items[idx]).addClass('active-suggestion'); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); idx = (idx - 1 + items.length) % items.length; items.removeClass('active-suggestion'); $(items[idx]).addClass('active-suggestion'); }
        else if (e.key === 'Enter') { var $a = $list.children('.active-suggestion'); if ($a.length) { e.preventDefault(); choose($a.text()); } }
        else if (e.key === 'Escape') { $list.hide(); }
      });
      $inp.on('blur', function () { setTimeout(function () { $list.hide(); }, 150); });
    }
    amWireAutocomplete('amShiftTruck', 'amShiftTruckList', function () { return (state.trucks || []).map(function (t) { return t.unit_name; }); });

    // ----- Trip-ticket legs (Confirm Assignment modal) ------------------
    // Each leg is a <tr> in #amLegsBody. The booking leg is highlighted and
    // cannot be removed; extra legs (repositioning / return / manual) are added
    // with "Add leg" and dragged to set the run order. Locations / trailer /
    // genset use native datalists populated in openAssignModal.
    function amLegRowHtml(isBk) {
      return '' +
        '<tr class="am-leg' + (isBk ? ' am-booking-leg' : '') + '" data-booking-leg="' + (isBk ? '1' : '0') + '">' +
          '<td class="text-center am-drag" title="Drag to reorder"><iconify-icon icon="mdi:drag-vertical"></iconify-icon></td>' +
          '<td><input type="text" class="form-control form-control-sm am-from" list="stLocations" autocomplete="off"></td>' +
          '<td><input type="text" class="form-control form-control-sm am-to" list="stLocations" autocomplete="off"></td>' +
          '<td><input type="text" class="form-control form-control-sm am-hseg" list="amHaulingSegments" autocomplete="off"></td>' +
          '<td><input type="text" class="form-control form-control-sm am-hjob" autocomplete="off" placeholder="e.g. DRYVAN"></td>' +
          '<td><input type="text" class="form-control form-control-sm text-uppercase am-trailer" list="amTrailerOptions" autocomplete="off"></td>' +
          '<td><input type="text" class="form-control form-control-sm text-uppercase am-genset" list="amGensetOptions" autocomplete="off"></td>' +
          '<td><input type="text" class="form-control form-control-sm text-uppercase am-van" maxlength="11" autocomplete="off" placeholder="ABCD1234567"></td>' +
          '<td><select class="form-select form-select-sm am-stat">' +
            '<option value="">-</option><option value="Empty">Empty</option><option value="Loaded">Loaded</option>' +
          '</select></td>' +
          '<td class="text-nowrap text-center">' +
            '<button type="button" class="btn btn-sm btn-link p-0 me-1 am-up" title="Move up"><iconify-icon icon="mdi:arrow-up"></iconify-icon></button>' +
            '<button type="button" class="btn btn-sm btn-link p-0 me-1 am-down" title="Move down"><iconify-icon icon="mdi:arrow-down"></iconify-icon></button>' +
            (isBk
              ? '<span class="badge bg-warning text-dark">booking</span>'
              : '<button type="button" class="btn btn-sm btn-link text-danger p-0 am-del" title="Remove leg"><iconify-icon icon="mdi:trash-can-outline"></iconify-icon></button>') +
          '</td>' +
        '</tr>';
    }
    // Van no (container) — ISO format: 4 letters then 7 digits (e.g. MSCU1234567).
    // Mirrors normalizeContainerNo() used on the container-tracking screen.
    function normalizeVanNo(value) {
      var upper = String(value || '').toUpperCase(), letters = '', digits = '';
      for (var i = 0; i < upper.length; i++) {
        var ch = upper[i];
        if (letters.length < 4 && /[A-Z]/.test(ch)) { letters += ch; continue; }
        if (letters.length === 4 && digits.length < 7 && /[0-9]/.test(ch)) { digits += ch; }
      }
      return letters + digits;
    }
    function isValidVanNo(value) { return /^[A-Z]{4}[0-9]{7}$/.test(String(value || '')); }
    // Enforce the format as the dispatcher types in any leg's Van no cell.
    $(document).on('input', '#amLegsBody .am-van', function () { this.value = normalizeVanNo(this.value); });

    // Append a leg row, optionally pre-filled. Returns the jQuery row.
    function amAddLeg(leg) {
      leg = leg || {};
      var $tr = $(amLegRowHtml(!!leg.is_booking_leg));
      $tr.find('.am-from').val(leg.from || '');
      $tr.find('.am-to').val(leg.to || '');
      $tr.find('.am-hseg').val(leg.hauling_seg || '');
      $tr.find('.am-hjob').val(leg.hauling_job || '');
      $tr.find('.am-trailer').val(leg.trailer || '');
      $tr.find('.am-genset').val(leg.genset || '');
      $tr.find('.am-van').val(normalizeVanNo(leg.container || ''));
      $tr.find('.am-stat').val(leg.container_stat || '');
      $('#amLegsBody').append($tr);
      return $tr;
    }
    // Row controls (delegated — rows are created dynamically).
    $('#amAddLeg').on('click', function () { amAddLeg({}).find('.am-from').trigger('focus'); });
    $(document).on('click', '#amLegsBody .am-up', function () {
      var $tr = $(this).closest('tr'); var $p = $tr.prev('tr.am-leg'); if ($p.length) $p.before($tr);
    });
    $(document).on('click', '#amLegsBody .am-down', function () {
      var $tr = $(this).closest('tr'); var $n = $tr.next('tr.am-leg'); if ($n.length) $n.after($tr);
    });
    $(document).on('click', '#amLegsBody .am-del', function () { $(this).closest('tr').remove(); });
    // Drag-reorder via the grip handle.
    if (window.Sortable) {
      Sortable.create(document.getElementById('amLegsBody'), { handle: '.am-drag', animation: 150, ghostClass: 'sortable-ghost' });
    }

    function openAssignModal(bookingNo, driverId, truck, driverName) {
      var booking = state.bookings.find(function(b) { return b.booking_no === bookingNo; }) || {};
      var driver = state.drivers.find(function(d) { return String(d.driver_id) === String(driverId); }) || {};
      // Off-shift drivers live in a separate list and carry no shift truck —
      // the dispatcher will enter one in the modal to start their shift.
      var isOffShift = false;
      if (!driver.driver_id) {
        var off = (state.offShiftDrivers || []).find(function(d) { return String(d.driver_id) === String(driverId); });
        if (off) { driver = off; isOffShift = true; }
      }
      var haulingSegment = String(booking.hauling_segment || '').trim();
      amCtx = {
        bookingNo: bookingNo,
        driverId: driverId,
        truck: truck,
        isOffShift: isOffShift,
        customer: String(booking.costumer || '').trim().toUpperCase(),
        containerStatus: String(booking.raw_container_status || booking.container_status || '').trim(),
        haulingSegment: haulingSegment,
        haulingType: String(booking.hauling_type || '').trim(),
        bookingSn: String(booking.booking_sn || '').trim(),
        bookingDo: String(booking.booking_do || '').trim(),
        assignedTrailer: String(driver.assigned_trailer || '').trim(),
        assignedGenset: String(driver.assigned_genset || '').trim(),
        assignedTrailerJackedUp: !!driver.assigned_trailer_jacked_up
      };
      var isCth = amCtx.customer === 'CTH';
      var isLoadedLane = String(amCtx.containerStatus || '').toLowerCase().indexOf('loaded') === 0;
      // Truck input — always shown. Off-shift: blank (entering starts a shift).
      // On-shift: pre-filled with the current unit so the dispatcher can
      // re-select a different one. The searchable dropdown reads state.trucks.
      $('#amShiftTruckList').hide();
      $('#amShiftTruck').val(isOffShift ? '' : String(truck || '').trim());
      $('#amShiftTruckHelp').text(isOffShift
        ? 'This driver is off shift — confirming will start their shift on this truck.'
        : 'Current unit shown. Change it to re-assign this driver to a different truck.');
      $('#amBooking').text(bookingNo);
      $('#amDriver').text(driverName || '—');
      $('#amTruck').text(isOffShift ? '(off shift — enter truck below)' : (truck || '—'));
      $('#amTripReceipt').val('');
      amCtx.tripFrom = String(booking.trip_from || '').trim();
      $('#amEir').val('');
      $('#amEirLabel').text(isLoadedLane ? 'EIR Out' : 'EIR In');
      $('#amEirHelp').text(isLoadedLane ? 'Required for CTH loaded dispatch.' : 'Required for CTH empty-return dispatch.');
      $('#amEirWrap').toggleClass('d-none', !isCth);
      // Reset the booking-leg equipment overrides.
      $('#amNoTrailer').prop('checked', false);
      $('#amNoGenset').prop('checked', false);

      // Seed the legs table with the booking leg, pre-filled from the booking.
      // Trailer/genset default to the driver's currently-held rig (unless the
      // trailer is jacked up); Empty/Loaded follows the booking's lane.
      var seedTrailer = (amCtx.assignedTrailer && !amCtx.assignedTrailerJackedUp) ? amCtx.assignedTrailer : '';
      var seedStat = isLoadedLane ? 'Loaded'
                   : (String(amCtx.containerStatus || '').toLowerCase().indexOf('empty') === 0 ? 'Empty' : '');
      $('#amLegsBody').empty();
      amAddLeg({
        is_booking_leg: true,
        from: amCtx.tripFrom,
        to: String(booking.trip_to || '').trim(),
        hauling_seg: haulingSegment,
        hauling_job: amCtx.haulingType,
        trailer: seedTrailer,
        genset: amCtx.assignedGenset || '',
        container: String(booking.container || booking.container_no || '').trim(),
        container_stat: seedStat
      });

      // Load trailer / genset lists into the datalists shared by every leg.
      $('#amTrailerOptions,#amGensetOptions').empty();
      $.when(
        $.getJSON('php/fetch/get_trailers.php'),
        $.getJSON('php/fetch/get_gensets.php', { driver_id: driverId })
      ).done(function(tRes, gRes) {
        amTrailers = tRes[0] || [];
        amGensets  = gRes[0] || [];
        var opt = function (x) { return '<option value="' + escapeHtml(String(x)) + '">'; };
        $('#amTrailerOptions').html(amTrailers.map(opt).join(''));
        $('#amGensetOptions').html(amGensets.map(opt).join(''));
      }).fail(function() {
        Swal.fire({ icon: 'error', text: 'Failed to load trailer / genset list.' });
      });

      $('#assignModal').modal('show');
      setTimeout(function() {
        $(amCtx && amCtx.isOffShift ? '#amShiftTruck' : '#amTripReceipt').trigger('focus');
      }, 250);
    }

    $('#amTripReceipt').on('input', function() {
      this.value = normalizeTripReceipt(this.value);
    });

    // Booking-leg bobtail / no-genset overrides — clear + disable that row's
    // trailer / genset cell so it prints blank and the backend skips the guard.
    function amBookingLegRow() { return $('#amLegsBody tr.am-booking-leg').first(); }
    $(document).on('change', '#amNoTrailer', function () {
      var off = this.checked, $c = amBookingLegRow().find('.am-trailer');
      $c.prop('disabled', off); if (off) $c.val('');
    });
    $(document).on('change', '#amNoGenset', function () {
      var off = this.checked, $c = amBookingLegRow().find('.am-genset');
      $c.prop('disabled', off); if (off) $c.val('');
    });

    // Truck field — keep amCtx.truck in sync as the dispatcher edits the truck.
    $('#amShiftTruck').on('input change', function () {
      if (!amCtx) return;
      amCtx.truck = this.value.trim();
    });

    $('#amSubmit').on('click', function() {
      if (!amCtx) return;
      var tripReceipt = normalizeTripReceipt($('#amTripReceipt').val());
      var eirReference = $('#amEir').val().trim();
      // Truck is always taken from the field. Off-shift: starts the shift.
      // On-shift: the current unit is pre-filled; changing it re-assigns the
      // driver to a different truck.
      var shiftTruck = String($('#amShiftTruck').val() || '').trim().toUpperCase();
      if (shiftTruck === '') {
        Swal.fire({ icon: 'warning', text: amCtx.isOffShift ? 'Enter a truck — this driver is off shift.' : 'Truck is required.' });
        return;
      }
      amCtx.truck = shiftTruck;
      if (tripReceipt === '') { Swal.fire({ icon: 'warning', text: 'Trip Receipt is required.' }); return; }
      if (!/^\d{6}$/.test(tripReceipt)) { Swal.fire({ icon: 'warning', text: 'Trip Receipt must be exactly 6 numbers.' }); return; }
      if (amCtx.customer === 'CTH' && eirReference === '') { Swal.fire({ icon: 'warning', text: 'CTH dispatch requires ' + ($('#amEirLabel').text() || 'EIR') + '.' }); return; }

      // Collect legs top-to-bottom — the visual order is the run order.
      var rows = [], bookingLeg = null, missingFromTo = false, badVan = false;
      $('#amLegsBody tr.am-leg').each(function () {
        var $r = $(this);
        var leg = {
          is_booking_leg: $r.attr('data-booking-leg') === '1',
          from:           String($r.find('.am-from').val() || '').trim(),
          to:             String($r.find('.am-to').val() || '').trim(),
          hauling_seg:    String($r.find('.am-hseg').val() || '').trim(),
          hauling_job:    String($r.find('.am-hjob').val() || '').trim(),
          trailer:        String($r.find('.am-trailer').val() || '').trim().toUpperCase(),
          genset:         String($r.find('.am-genset').val() || '').trim().toUpperCase(),
          container:      normalizeVanNo($r.find('.am-van').val()),
          container_stat: String($r.find('.am-stat').val() || '').trim()
        };
        if (leg.from === '' || leg.to === '') missingFromTo = true;
        // Van no is optional, but when filled it must be the full 4-letter + 7-digit format.
        if (leg.container !== '' && !isValidVanNo(leg.container)) badVan = true;
        if (leg.is_booking_leg) bookingLeg = leg;
        rows.push(leg);
      });
      if (!bookingLeg) { Swal.fire({ icon: 'warning', text: 'The booking leg is missing.' }); return; }
      if (missingFromTo) { Swal.fire({ icon: 'warning', text: 'Each leg needs both a From and a To.' }); return; }
      if (badVan) { Swal.fire({ icon: 'warning', text: 'Van no must be 4 letters followed by 7 numbers (e.g. MSCU1234567).' }); return; }
      if (!amCtx.haulingSegment && bookingLeg.hauling_seg === '') {
        Swal.fire({ icon: 'warning', text: 'Hauling Segment is required on the booking leg.' }); return;
      }

      // Trailer + genset required only for PM (Prime Mover) trucks; the rule is
      // enforced on the booking leg. Genset only required for loaded containers.
      var amIsPm = /^pm/i.test(String(amCtx.truck || '').trim());
      var amIsLoaded = String(amCtx.containerStatus || '').toLowerCase().indexOf('loaded') === 0;
      var noTrailer = $('#amNoTrailer').is(':checked');
      var noGenset  = $('#amNoGenset').is(':checked');
      if (noTrailer) bookingLeg.trailer = '';
      if (noGenset)  bookingLeg.genset  = '';
      if (amIsPm && !noTrailer && bookingLeg.trailer === '') {
        Swal.fire({ icon: 'warning', text: 'Trailer is required on the booking leg (or switch on “Booking leg bobtail”).' }); return;
      }
      if (amIsPm && amIsLoaded && !noGenset && bookingLeg.genset === '') {
        Swal.fire({ icon: 'warning', text: 'Genset is required for loaded containers (or switch on “Booking leg no genset”).' }); return;
      }

      // Primary rig (dispatch-level d_trailer / d_genset) = the booking leg's.
      var trailer = bookingLeg.trailer, genset = bookingLeg.genset;

      var payload = {
        booking_no:      amCtx.bookingNo,
        driver_id:       amCtx.driverId,
        trip_receipt:    tripReceipt,
        hauling_segment: bookingLeg.hauling_seg,
        cth_eir:         eirReference,
        trailer:         trailer,
        genset:          genset,
        // Truck for an off-shift driver — backend auto-starts their shift.
        shift_truck:     shiftTruck,
        // Booking-leg Van no (container) — overrides the booking's container.
        container_no:    bookingLeg.container,
        // Booking-leg pickup — overrides the booking's pickup location.
        trip_from:       bookingLeg.from,
        no_trailer:      noTrailer ? '1' : '0',
        no_genset:       noGenset ? '1' : '0',
        // Ordered trip-ticket legs. Backend persists Trip 1..N + per-leg trip_seq.
        rows:            JSON.stringify(rows)
      };

      function submitNow() {
        $('#amSubmit').prop('disabled', true);
        $.post('php/operations/assign_booking_dnd.php', payload, null, 'json')
       .done(function(res) {
          $('#amSubmit').prop('disabled', false);
          if (res && res.status === 'success') {
            $('#assignModal').modal('hide');
            var ref = res.dispatch_ref || amCtx.bookingNo;
            // CTH bookings print on the CTH trip-ticket layout.
            var isCthBooking = amCtx.customer === 'CTH';
            var ticketHref = isCthBooking ? tripTicketCthHref : tripTicketHref;
            Swal.fire({
              icon: 'success',
              title: 'Assigned',
              html: '<div class="text-muted small mb-1">Reference</div>' +
                    '<div class="fw-bold" style="font-family:monospace;word-break:break-all;">' + escapeHtml(ref) + '</div>' +
                    '<div class="text-muted small mt-2">' + escapeHtml(amCtx.bookingNo) + ' → ' + escapeHtml(amCtx.truck) + '</div>' +
                    '<div class="alert alert-info small mt-3 mb-0 py-2">' +
                      '<i class="ti ti-info-circle"></i> Print the trip ticket now, or reprint it anytime from Container Tracking.' +
                    '</div>',
              showConfirmButton: true,
              showDenyButton: true,
              showCancelButton: true,
              confirmButtonText: '<i class="ti ti-file-text"></i> Print Trip Ticket',
              denyButtonText:    '<i class="ti ti-receipt"></i> Print Receipt',
              cancelButtonText:  'Close',
              confirmButtonColor: '#0d6efd',
              denyButtonColor:    '#6c757d'
            }).then(function (result) {
              if (!res.d_id) return;
              if (result.isConfirmed) {
                // Full trip ticket (role-aware route). Printable immediately,
                // no need to wait for the driver to accept.
                window.open(ticketHref + '?id=' + encodeURIComponent(res.d_id), '_blank');
              } else if (result.isDenied) {
                // Existing thermal receipt.
                window.open('print_dispatch_receipt?d_id=' + encodeURIComponent(res.d_id), '_blank', 'width=420,height=720');
              }
            });
            refresh();
          } else {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Assignment failed.' });
          }
        })
       .fail(function() {
          $('#amSubmit').prop('disabled', false);
          Swal.fire({ icon: 'error', text: 'Network error.' });
        });
      } // submitNow

      // When in-use equipment is allowed, warn the dispatcher (with who holds
      // it) before committing. Otherwise submit straight away.
      if (!ALLOW_INUSE_EQUIP) { submitNow(); return; }
      $('#amSubmit').prop('disabled', true);
      $.getJSON('php/fetch/equipment_inuse_check.php', {
        driver_id: amCtx.driverId, truck: shiftTruck, trailer: trailer, genset: genset
      }).done(function (chk) {
        $('#amSubmit').prop('disabled', false);
        var list = (chk && chk.inuse) || [];
        if (!list.length) { submitNow(); return; }
        var rows = list.map(function (u) {
          return '<li><b>' + escapeHtml(u.type) + ' ' + escapeHtml(u.name) + '</b> — in use by ' + escapeHtml(u.holder) + '</li>';
        }).join('');
        Swal.fire({
          icon: 'warning',
          title: 'Equipment already in use',
          html: '<div class="text-start small">The following are currently assigned to another driver:</div>' +
                '<ul class="text-start small mt-2 mb-2">' + rows + '</ul>' +
                '<div class="text-start small">Assign anyway?</div>',
          showCancelButton: true,
          confirmButtonText: 'Assign anyway',
          confirmButtonColor: '#d97706',
          cancelButtonText: 'Cancel'
        }).then(function (r) { if (r.isConfirmed) submitNow(); });
      }).fail(function () {
        // If the check itself fails, don't block — fall back to a direct submit.
        $('#amSubmit').prop('disabled', false);
        submitNow();
      });
    });

    // ----- Stuck Assignment actions -------------------------------------
    // Click a chip in the Stuck Assignments strip → opens an action modal
    // offering Recall (return to pool) or Reassign (open Recommend modal).
    $(document).on('click', '[data-stuck-d-id]', function (e) {
      e.preventDefault();
      var dId = $(this).data('stuck-d-id');
      var row = (state.stuckRows || []).find(function (r) { return r.d_id == dId; });
      if (!row) return;
      $('#staDId').val(row.d_id);
      $('#staBookingNo').val(row.booking_no || '');
      $('#staBooking').text(row.booking_no || '—');
      $('#staDriver').text(row.driver_name || '—');
      $('#staTruck').text(row.truck ? ' · ' + row.truck : '');
      $('#staMinutes').text(formatPendingTime(row.minutes_pending));
      $('#staReason').val('');
      $('#stuckActionModal').modal('show');
    });

    function doRecall(dId, reason, thenCb) {
      return $.post('php/operations/recall_assignment.php', {
        d_id:   dId,
        reason: reason || ''
      }, null, 'json')
        .done(function (res) {
          if (!res || res.status !== 'success') {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Recall failed.' });
            return;
          }
          if (typeof thenCb === 'function') thenCb(res);
          refresh();
        })
        .fail(function (xhr) {
          var msg = (xhr.responseJSON && xhr.responseJSON.message) || xhr.responseText || 'Network error';
          Swal.fire({ icon: 'error', text: msg });
        });
    }

    $('#staRecall').on('click', function () {
      var dId    = $('#staDId').val();
      var reason = $('#staReason').val();
      if (!dId) return;
      doRecall(dId, reason, function (res) {
        $('#stuckActionModal').modal('hide');
        Swal.fire({ icon: 'success', text: res.message || 'Assignment recalled.', timer: 1800, showConfirmButton: false });
      });
    });

    $('#staReassign').on('click', function () {
      var dId        = $('#staDId').val();
      var bookingNo  = $('#staBookingNo').val();
      var reason     = $('#staReason').val() || 'Reassigning to another driver';
      if (!dId || !bookingNo) return;
      // Recall first so the booking is back in the pool, then immediately
      // open the Recommend modal targeted at the same booking_no — the
      // dispatcher picks a new driver and Assign uses the existing flow.
      doRecall(dId, reason, function () {
        $('#stuckActionModal').modal('hide');
        // Trigger the existing Recommend modal by faking the same path
        // the Suggest button uses.
        $('<button type="button" data-recommend="' + escapeHtml(bookingNo) + '">')
          .appendTo('body').trigger('click').remove();
      });
    });

    // ----- Recommendation modal -----------------------------------------
    $(document).on('click', '[data-recommend]', function(e) {
      e.preventDefault(); e.stopPropagation();
      var bk = $(this).data('recommend');
      $('#recBookingNo').text(bk);
      $('#recPickup').text('');
      $('#recBody').html('<tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>');
      $('#recommendModal').modal('show');

      $.getJSON('php/fetch/recommend_driver.php', { booking_no: bk, limit: 10 })
        .done(function(res) {
          if (res.status !== 'success') {
            $('#recBody').html('<tr><td colspan="7" class="text-center text-danger py-3">' + escapeHtml(res.message || 'Failed') + '</td></tr>');
            return;
          }
          if (res.pickup && !res.pickup.known) {
            $('#recPickup').html('<span class="gps-stale">Pickup location <b>' + escapeHtml(res.pickup.name) + '</b> has no coordinates — ranking by active-trips load only.</span>');
          } else if (res.pickup) {
            $('#recPickup').text('Pickup: ' + res.pickup.name + '  ·  ' + (res.pickup.lat || '') + ', ' + (res.pickup.lng || ''));
          }
          if (!res.recommended.length) {
            $('#recBody').html('<tr><td colspan="7" class="text-center text-muted py-3">No drivers on shift.</td></tr>');
            return;
          }
          var html = res.recommended.map(function(d, i) {
            var dist = (d.distance_km == null)
              ? '<span class="text-muted">—</span>'
              : (d.distance_km.toFixed(1) + ' km');
            var gps = d.has_gps
              ? (d.gps_stale ? '<span class="gps-stale">stale</span>' : '<span class="text-success">live</span>')
              : '<span class="text-muted">none</span>';
            return '<tr>' +
              '<td>' + (i + 1) + '</td>' +
              '<td>' + escapeHtml(d.driver_name) + '</td>' +
              '<td><code>' + escapeHtml(d.shift_truck) + '</code></td>' +
              '<td>' + dist + '</td>' +
              '<td>' + gps + '</td>' +
              '<td>' + d.active_trips + '</td>' +
              '<td><button class="btn btn-sm btn-primary" data-assign-from-rec="' + d.driver_id + '">Assign</button></td>' +
            '</tr>';
          }).join('');
          $('#recBody').html(html);
        })
        .fail(function() {
          $('#recBody').html('<tr><td colspan="7" class="text-center text-danger py-3">Network error.</td></tr>');
        });
    });

    // Assign from the recommend modal — hand off to the assignment
    // modal so Trip Receipt / Trailer / Genset are collected.
    $(document).on('click', '[data-assign-from-rec]', function() {
      var $row = $(this).closest('tr');
      var driverId   = $(this).data('assign-from-rec');
      var driverName = $row.find('td').eq(1).text();
      var truck      = $row.find('td').eq(2).text();
      var bookingNo  = $('#recBookingNo').text();
      $('#recommendModal').modal('hide');
      openAssignModal(bookingNo, driverId, truck, driverName);
    });

    $('#bkFilter').on('input',  function() { renderBookings(); });
    // Filtering auto-switches to the tab that holds the matching driver, so the
    // dispatcher doesn't have to guess Idle / On Trip / Off Shift first.
    $('#drvFilter').on('input', function() {
      var q = String($(this).val() || '').trim().toLowerCase();
      if (q && drvTabMatchCount(state.driverFilter, q) === 0) {
        var order = ['idle', 'active', 'offshift'];
        for (var i = 0; i < order.length; i++) {
          if (drvTabMatchCount(order[i], q) > 0) { state.driverFilter = order[i]; break; }
        }
      }
      renderDrivers();
    });
    $('#btnRefresh').on('click', refresh);

    // Stuck Assignments strip — hidden by default; toggled by the header button
    // and dismissed via the strip's close (×) button.
    $('#btnStuckToggle').on('click', function () {
      $('#stuckStrip').toggleClass('is-visible');
    });
    $('#btnStuckClose').on('click', function () {
      $('#stuckStrip').removeClass('is-visible');
    });

    // Idle / On-Trip toggle for the driver panel.
    $(document).on('click', '[data-drv-filter]', function() {
      state.driverFilter = $(this).data('drv-filter');
      renderDrivers();
    });

    // Click a top customer summary card → jump straight to that customer's
    // booking list (both lanes shown, with section headers).
    $(document).on('click', '.customer-stat-card', function() {
      var cust = $(this).data('customer');
      if (state.selectedCustomer === cust && state.drillView === 'booking') {
        // Toggle off: back to lane view.
        state.selectedCustomer = null;
        state.drillView = 'lane';
        state.drillLane  = null;
      } else {
        state.selectedCustomer = cust;
        state.drillView = 'booking';
        state.drillLane = null;
      }
      renderCustomerStats();
      renderBookings();
    });

    // Drill into a lane card.
    // Click a lane card (Empty/Loaded) on the customer-scoped view 2 →
    // drill straight into that customer's bookings for that lane.
    $(document).on('click', '[data-drill-lane]', function() {
      state.drillLane = $(this).data('drill-lane');
      state.drillView = 'booking';
      renderCustomerStats();
      renderBookings();
    });

    // Click a customer card on the root view → show the lane picker
    // (Empty/Loaded big cards) scoped to that customer.
    $(document).on('click', '[data-drill-customer]', function() {
      state.selectedCustomer = $(this).data('drill-customer');
      state.drillView = 'customer';
      state.drillLane = null;
      renderCustomerStats();
      renderBookings();
    });

    // Breadcrumb navigation: back to root, or back to the lane-picker for
    // the currently selected customer.
    $(document).on('click', '[data-drill]', function() {
      var target = $(this).data('drill');
      if (target === 'root') {
        state.drillView = 'lane';
        state.drillLane = null;
        state.selectedCustomer = null;
      } else if (target === 'customer') {
        state.drillView = 'customer';
        state.drillLane = null;
      }
      renderCustomerStats();
      renderBookings();
    });

    // ----- Service Trip modal -------------------------------------------
    // Opens an "Assign Service Trip" form — non-booked routes (repositioning,
    // fuel, shop visit, etc.). Posts to assign_service_trip.php.
    function openServiceTripModal() {
      // Populate driver dropdown from ALL drivers — on-shift first, then
      // off-shift (which carry no truck; the dispatcher enters one to start
      // their shift on confirm).
      var onShiftOpts = (state.drivers || []).map(function(d) {
        return '<option value="' + d.driver_id + '" data-truck="' + escapeHtml(d.shift_truck) + '">' +
               escapeHtml(d.driver_name) + ' (' + escapeHtml(d.shift_truck) + ')</option>';
      }).join('');
      var offShiftOpts = (state.offShiftDrivers || []).map(function(d) {
        return '<option value="' + d.driver_id + '" data-truck="" data-offshift="1">' +
               escapeHtml(d.driver_name) + ' (off shift)</option>';
      }).join('');
      $('#stDriver').html('<option value="" selected>-- Select Driver --</option>' + onShiftOpts + offShiftOpts);
      $('#stTruck').val('');
      // Available-truck datalist so the dispatcher can re-assign the truck.
      $('#stTruckList').html((state.trucks || [])
        .map(function(t) { return '<option value="' + escapeHtml(t.unit_name) + '">'; }).join(''));
      $('#stReason').val('');
      $('#stTripReceipt').val('');
      $('#stFrom').val('');
      $('#stTo').val('');
      $('#stContainer').val('');
      $('#stRemarks').val('');
      $('#stTrailer').html('<option value="" selected>Loading…</option>');
      $('#stGenset').html('<option value="" selected>Loading…</option>');

      $.when(
        $.getJSON('php/fetch/get_trailers.php'),
        $.getJSON('php/fetch/get_gensets.php')
      ).done(function(tRes, gRes) {
        var trailers = tRes[0] || [];
        var gensets  = gRes[0] || [];
        $('#stTrailer').html('<option value="" selected>-- None --</option>' +
          trailers.map(function(n) { return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>'; }).join(''));
        $('#stGenset').html('<option value="" selected>-- None --</option>' +
          gensets.map(function(n) { return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>'; }).join(''));
      }).fail(function() {
        $('#stTrailer').html('<option value="" selected>-- None --</option>');
        $('#stGenset').html('<option value="" selected>-- None --</option>');
      });

      $('#serviceTripModal').modal('show');
    }

    $('#btnServiceTrip').on('click', openServiceTripModal);

    // Auto-fill truck from selected driver's shift_truck.
    $('#stDriver').on('change', function() {
      var truck = $(this).find(':selected').data('truck') || '';
      $('#stTruck').val(truck);
    });

    $('#stTripReceipt').on('input', function() {
      this.value = normalizeTripReceipt(this.value);
    });

    $('#stSubmit').on('click', function() {
      var driverId    = $('#stDriver').val();
      var truck       = String($('#stTruck').val() || '').trim().toUpperCase();
      var reason      = $('#stReason').val();
      var tripReceipt = normalizeTripReceipt($('#stTripReceipt').val());
      var fromLoc     = $('#stFrom').val().trim();
      var toLoc       = $('#stTo').val().trim();
      var trailer     = $('#stTrailer').val() || '';
      var genset      = $('#stGenset').val() || '';
      var container   = String($('#stContainer').val() || '').trim().toUpperCase();
      var remarks     = $('#stRemarks').val().trim();

      if (!driverId) { Swal.fire({ icon: 'warning', text: 'Driver is required.' }); return; }
      if (!truck)    { Swal.fire({ icon: 'warning', text: 'Truck is required.' }); return; }
      if (!reason)   { Swal.fire({ icon: 'warning', text: 'Service Reason is required.' }); return; }
      if (!fromLoc)  { Swal.fire({ icon: 'warning', text: 'From is required.' }); return; }
      if (!toLoc)    { Swal.fire({ icon: 'warning', text: 'To is required.' }); return; }
      if (tripReceipt !== '' && !/^\d{6}$/.test(tripReceipt)) {
        Swal.fire({ icon: 'warning', text: 'Trip Receipt must be exactly 6 numbers (or leave blank).' });
        return;
      }

      $('#stSubmit').prop('disabled', true);
      // Reserve the print tab now (inside the click gesture) so the auto-print
      // isn't blocked by the popup blocker; we point it at the ticket on success.
      var stPrintWin = window.open('', '_blank');
      $.post('php/operations/assign_service_trip.php', {
        driver_id:       driverId,
        truck:           truck,
        service_reason:  reason,
        service_remarks: remarks,
        trip_receipt:    tripReceipt,
        trip_from:       fromLoc,
        trip_to:         toLoc,
        trailer:         trailer,
        genset:          genset,
        container:       container
      }, null, 'json')
       .done(function(res) {
          $('#stSubmit').prop('disabled', false);
          if (res && res.status === 'success') {
            $('#serviceTripModal').modal('hide');
            // Auto-print the trip ticket for the new service trip.
            if (res.d_id && stPrintWin) {
              stPrintWin.location.href = tripTicketHref + '?id=' + encodeURIComponent(res.d_id);
            } else if (stPrintWin) {
              stPrintWin.close();
            }
            Swal.fire({ icon: 'success', title: 'Service Trip Assigned',
                        text: 'Driver assigned to ' + fromLoc + ' → ' + toLoc,
                        timer: 1800, showConfirmButton: false });
            refresh();
          } else {
            if (stPrintWin) stPrintWin.close();
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Assignment failed.' });
          }
        })
       .fail(function() {
          $('#stSubmit').prop('disabled', false);
          if (stPrintWin) stPrintWin.close();
          Swal.fire({ icon: 'error', text: 'Network error.' });
        });
    });

    refresh();
    // Poll only while the tab is visible — a backgrounded tab left open all day
    // is pure Supabase egress waste. Refresh once on return so it's up to date.
    setInterval(function () { if (!document.hidden) refresh(); }, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
  </script>

  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
</body>
</html>
