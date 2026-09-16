<?php
// Phase 14 — container tracking page.
// Included by `dispatcher/container-tracking.php` and `admin/container-tracking.php`.
// Required vars: $role ('admin'|'dispatcher').
$pageTitle = 'Container Tracking';

// Locations for the "Mark Loaded" modal's Delivery Location picker.
require_once __DIR__ . '/../config/config.php';
// Maps API key — used for reverse-geocoding the "Last GPS" column into a
// human-readable address (Plus Code + locality).
require_once __DIR__ . '/../config/maps.php';
// Admin photo toggles — the manual-complete modal mirrors them so the
// dispatcher isn't forced to supply a photo the org made optional.
require_once __DIR__ . '/../helpers/settings_helper.php';
$mcPickupPhotoRequired = pt_setting_bool($conn, 'require_pickup_photo', true);
$mcPodPhotosRequired   = pt_setting_bool($conn, 'require_pod_photos', true);
$mcMovementRequired    = pt_setting_bool($conn, 'require_movement_timestamps', true);
// Optional deep-link prefilter (e.g. from the dispatch board's active-trip link).
$prefillQuery = trim($_GET['q'] ?? '');
// Optional deep-link action: auto-open Update ('edit') or Manual Complete
// ('manual') for the given dispatch id, from the dispatch board's tx modal.
$prefillAct = in_array(($_GET['act'] ?? ''), ['edit', 'manual'], true) ? $_GET['act'] : '';
$prefillDid = (int)($_GET['did'] ?? 0);
$dispatchBoardHref = ($role === 'admin') ? 'dispatchTiles' : 'dispatch-tiles';
$locationNames = [];
$lres = $conn->query("SELECT location_name FROM location ORDER BY location_name ASC");
while ($lr = ($lres)->fetch()) { $locationNames[] = $lr['location_name']; }
$trailerNames = [];
$tres = $conn->query("SELECT trailer_name FROM trailer ORDER BY trailer_name ASC");
while ($tres && ($tr = ($tres)->fetch())) { $trailerNames[] = $tr['trailer_name']; }
$gensetNames = [];
$gres = $conn->query("SELECT unit_name FROM units WHERE unit_type = 'genset' ORDER BY unit_name ASC");
while ($gres && ($gr = ($gres)->fetch())) { $gensetNames[] = $gr['unit_name']; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle); ?> &mdash; Pantrucks</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
  <!-- Google Maps JS API — used for reverse-geocoding the Last GPS column. -->
  <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(MAPS_API_KEY); ?>" async defer></script>
  <style>
    /* Status pills match the dispatch-board palette. */
    .pill { padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; display:inline-block; white-space: nowrap; }
    .pill-pickup    { background: #fef9c3; color: #713f12; }
    .pill-ontrip    { background: #fed7aa; color: #7c2d12; }
    .pill-delivered { background: #bbf7d0; color: #064e3b; }
    .pill-declined  { background: #fee2e2; color: #b91c1c; }
    .pill-loaded-base{ background: #bfdbfe; color: #1e3a8a; }
    .gps-fresh { color: #047857; }
    .gps-stale { color: #b91c1c; font-style: italic; }
    .gps-none  { color: #6b7280; }
    /* Position source: hardware Geotab fix vs driver-phone heartbeat fallback. */
    .gps-src { padding: 1px 6px; border-radius: 999px; font-size: 10px; font-weight: 700;
               text-transform: uppercase; letter-spacing: .3px; margin-right: 6px; vertical-align: middle; }
    .gps-src-live  { background: #cffafe; color: #155e75; }
    .gps-src-phone { background: #e5e7eb; color: #374151; }
    .gps-addr  { display:block; font-size:12px; color:#0f172a; font-weight:500;
                 line-height:1.25; margin-bottom:2px; max-width:260px; }
    .gps-addr.gps-addr-pending { color:#6b7280; font-style:italic; font-weight:400; }
    .gps-coords { display:block; font-size:11px; color:#6b7280; }
    .legend-chip { display:inline-block; width:14px; height:14px; border-radius:3px; margin-right:4px; vertical-align:middle; }
    code.truck { background: #1f2937; color: #fff; padding: 1px 6px; border-radius: 4px; font-size: 11px; }
    .action-stack .btn { margin-bottom: 4px; }
    #teDriverList .list-group-item { cursor:pointer; padding:8px 12px; }
    #teDriverList .list-group-item:hover, #teDriverList .list-group-item.active-suggestion { background:#007bff; color:#fff; }
    /* Active/Completed tabs — strong colour so the selected view is obvious. */
    #containerViewTabs { border-bottom: 2px solid #e5e7eb; gap: 6px; }
    #containerViewTabs .nav-link {
      font-weight: 700; border: 2px solid transparent; border-bottom: none;
      padding: 10px 22px; border-radius: 10px 10px 0 0; display: inline-flex; align-items: center; gap: 6px;
    }
    /* Unselected tabs keep a coloured outline so each is easy to spot. */
    #containerViewTabs #tab-active-containers   { color: #2563eb; border-color: #93c5fd; background: #eff6ff; }
    #containerViewTabs #tab-empty-containers    { color: #c2410c; border-color: #fdba74; background: #fff7ed; }
    #containerViewTabs #tab-complete-containers { color: #16a34a; border-color: #86efac; background: #f0fdf4; }
    #containerViewTabs #tab-active-containers:hover   { background: #dbeafe; }
    #containerViewTabs #tab-empty-containers:hover    { background: #ffedd5; }
    #containerViewTabs #tab-complete-containers:hover { background: #dcfce7; }
    #containerViewTabs #tab-active-containers.active {
      color: #fff; background: #2563eb; border-color: #2563eb; box-shadow: 0 2px 6px rgba(37,99,235,.35);
    }
    #containerViewTabs #tab-empty-containers.active {
      color: #fff; background: #ea580c; border-color: #ea580c; box-shadow: 0 2px 6px rgba(234,88,12,.35);
    }
    #containerViewTabs #tab-complete-containers.active {
      color: #fff; background: #16a34a; border-color: #16a34a; box-shadow: 0 2px 6px rgba(22,163,74,.35);
    }
    /* Shipment (SN) group header — clusters CTH containers that share a
       Shipment Number under one collapsible row with a progress summary. */
    .sn-group-header {
      cursor: pointer;
      /* Override Bootstrap's table-hover variables: without these, its light
         hover background can be applied while the shipment text stays white. */
      --bs-table-bg: #0f172a;
      --bs-table-color: #fff;
      --bs-table-hover-bg: #1e293b;
      --bs-table-hover-color: #fff;
    }
    .sn-group-header td { background: #0f172a; color: #fff; padding: 8px 12px; }
    .sn-group-header:hover td { background: #1e293b; color: #fff; }
    .sn-group-header .sn-caret { transition: transform .15s; }
    .sn-group-header.collapsed .sn-caret { transform: rotate(-90deg); }
    .sn-chip { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
    .sn-chip-delivered { background: #bbf7d0; color: #064e3b; }
    .sn-chip-ontrip    { background: #fed7aa; color: #7c2d12; }
    .sn-chip-pickup    { background: #fef9c3; color: #713f12; }
    .sn-chip-toaccept  { background: #e5e7eb; color: #374151; }
    .sn-chip-other     { background: #dbeafe; color: #1e3a8a; }
    .sn-progress { width: 120px; height: 8px; background: rgba(255,255,255,.25); border-radius: 999px; overflow: hidden; }
    .sn-progress-bar { height: 100%; background: #22c55e; border-radius: 999px; transition: width .2s; }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Pantrucks Container Tracking</h3>
    </div>
    <?php include $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
            <h3 class="mb-0">Container Tracking</h3>
            <span class="text-muted small">In-flight bookings (Pickup → On Trip → Delivered), live GPS from assigned truck's driver.</span>
            <div class="ms-auto d-flex align-items-center gap-2 small">
              <span><span class="legend-chip" style="background:#fef9c3;"></span>Pickup</span>
              <span><span class="legend-chip" style="background:#fed7aa;"></span>On&nbsp;Trip</span>
              <span><span class="legend-chip" style="background:#bbf7d0;"></span>Delivered</span>
              <button class="btn btn-sm btn-outline-secondary" id="btnRefresh"><i class="ti ti-refresh"></i> Refresh</button>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-body">
              <div class="row g-2 align-items-end">
                <div class="col-md-3">
                  <label class="form-label small mb-1">Segment</label>
                  <select class="form-select form-select-sm" id="fSegment"><option value="">All</option></select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-1">Customer</label>
                  <select class="form-select form-select-sm" id="fCustomer"><option value="">All</option></select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-1">Container Status</label>
                  <select class="form-select form-select-sm" id="fStatus"><option value="">All</option></select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-1">Quick search</label>
                  <input type="search" class="form-control form-control-sm" id="fQuery" placeholder="Booking #, SN, trip receipt, container, truck, trailer, genset, driver…">
                </div>
              </div>
            </div>
          </div>

          <ul class="nav nav-tabs mb-0" id="containerViewTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-active-containers" data-view="active" type="button" role="tab">
                <i class="ti ti-truck-delivery"></i> Active Bookings
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-empty-containers" data-view="complete_empty" type="button" role="tab">
                <i class="ti ti-box-off"></i> Completed Empty
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-complete-containers" data-view="complete_loaded" type="button" role="tab">
                <i class="ti ti-checks"></i> Completed Booking
              </button>
            </li>
          </ul>
          <div class="card" style="border-top-left-radius:0;">
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Booking</th>
                      <th>Trip Receipt</th>
                      <th>Customer / Segment</th>
                      <th>Route</th>
                      <th>Container</th>
                      <th>Status</th>
                      <th>Truck / Driver</th>
                      <th>Last GPS</th>
                      <th>Updated</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody id="tbody"><tr><td colspan="10" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>

          <p class="text-muted small mt-3 mb-0">
            Showing <span id="cnt">0</span> <span id="cntLabel">active container(s)</span>. Auto-refresh every 30 s · Last refresh
            <span id="fetchedAt">—</span>.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Mark Loaded modal -->
  <div class="modal fade" id="markLoadedModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create Loaded Booking</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Empty container delivered at <b id="mlFrom">—</b>. Choose the loaded
            delivery location to spawn a new booking — the original empty-leg
            booking is preserved.
          </p>
          <div class="mb-3">
            <label class="form-label">Parent (Empty) Booking</label>
            <input type="hidden" id="mlParentDId">
            <input type="text" class="form-control" id="mlParent" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Loaded Pickup (auto)</label>
            <input type="text" class="form-control" id="mlPickup" readonly>
            <small class="text-muted">Where the container currently sits after the empty drop.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Loaded Delivery <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="mlDelivery" list="mlLocations" autocomplete="off" placeholder="Pick or type a location">
            <datalist id="mlLocations">
              <?php foreach ($locationNames as $ln): ?>
                <option value="<?php echo htmlspecialchars($ln); ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="mlSubmit"><i class="ti ti-check"></i> Create Loaded Booking</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="trackingEditModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Update Tracking Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="teDispatchId">
          <div class="mb-3">
            <label class="form-label">Booking</label>
            <input type="text" class="form-control" id="teBookingNo" readonly>
          </div>
          <!-- Reassign driver — for fixing a wrong driver selection. -->
          <div class="mb-3 position-relative">
            <label class="form-label">Driver</label>
            <input type="hidden" id="teNewDriverId">
            <input type="text" class="form-control" id="teDriver" autocomplete="off" placeholder="Type to search driver">
            <ul id="teDriverList" class="list-group position-absolute w-100" style="z-index:1090; display:none; max-height:220px; overflow-y:auto; box-shadow:0 6px 18px rgba(16,24,40,.14);"></ul>
            <small class="text-muted">Change only to correct a wrong driver. The new driver must accept the trip.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">From / Origin Location</label>
            <input type="text" class="form-control" id="teTripFrom" list="teLocations" autocomplete="off" placeholder="Update origin">
          </div>
          <div class="mb-3">
            <label class="form-label">Delivered Location <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="teTripTo" list="teLocations" autocomplete="off" placeholder="Update destination">
            <datalist id="teLocations">
              <?php foreach ($locationNames as $ln): ?>
                <option value="<?php echo htmlspecialchars($ln); ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="mb-3">
            <label class="form-label">Trailer</label>
            <input type="text" class="form-control" id="teTrailer" list="teTrailers" autocomplete="off" placeholder="Update trailer">
            <datalist id="teTrailers">
              <?php foreach ($trailerNames as $tn): ?>
                <option value="<?php echo htmlspecialchars($tn); ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="mb-3">
            <label class="form-label">Container No</label>
            <input type="text" class="form-control" id="teContainer" maxlength="11" autocomplete="off" placeholder="ABCD1234567">
            <small class="text-muted">Use exactly 4 capital letters followed by 7 numbers.</small>
          </div>
          <div class="mb-0">
            <label class="form-label">Genset</label>
            <input type="text" class="form-control" id="teGenset" list="teGensets" autocomplete="off" placeholder="Update genset">
            <datalist id="teGensets">
              <?php foreach ($gensetNames as $gn): ?>
                <option value="<?php echo htmlspecialchars($gn); ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="teSubmit"><i class="ti ti-device-floppy"></i> Save Changes</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="manualCompleteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Manual Complete Trip</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="mcDispatchId">
          <div class="mb-3">
            <label class="form-label">Booking</label>
            <input type="text" class="form-control" id="mcBookingNo" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label">Container No</label>
            <input type="text" class="form-control text-uppercase" id="mcContainer" maxlength="20" autocomplete="off" placeholder="Optional — record the container if not yet captured">
            <small class="text-muted">Optional. Leave blank for empty trips or to keep the existing container.</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea class="form-control" id="mcReason" rows="3" placeholder="Why is dispatch manually completing this trip?"></textarea>
            <small class="text-muted">Use this only when the driver cannot complete the phone-side flow.</small>
          </div>

          <hr>
          <h6 class="mb-2"><i class="ti ti-clock-hour-4"></i> Movement timestamps <?php if ($mcMovementRequired): ?><span class="text-danger">*</span><?php else: ?><span class="text-muted small">(optional)</span><?php endif; ?></h6>
          <p class="text-muted small mb-3">Back-fill when each stage actually happened. They must be in order: Picked up &le; On the way &le; Arrived &le; Delivered.<?php if (!$mcMovementRequired): ?> Blank times default to the completion time.<?php endif; ?></p>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small">1. Picked up</label>
              <input type="datetime-local" class="form-control" id="mcTsPickedUp">
            </div>
            <div class="col-md-6">
              <label class="form-label small">2. On the way</label>
              <input type="datetime-local" class="form-control" id="mcTsOnTheWay">
            </div>
            <div class="col-md-6">
              <label class="form-label small">3. Arrived at destination</label>
              <input type="datetime-local" class="form-control" id="mcTsArrived">
            </div>
            <div class="col-md-6">
              <label class="form-label small">4. Delivered</label>
              <input type="datetime-local" class="form-control" id="mcTsDelivered">
            </div>
          </div>

          <hr>
          <h6 class="mb-2"><i class="ti ti-camera"></i> Pickup photo <span class="text-danger" id="mcPickupStar">*</span></h6>
          <div id="mcPickupExisting" class="mb-2 d-none">
            <span class="badge bg-success-subtle text-success border border-success mb-1"><i class="ti ti-check"></i> Already captured by driver</span>
            <div class="d-flex gap-2" id="mcPickupExistingThumbs"></div>
          </div>
          <div class="mb-3">
            <input type="file" class="form-control" id="mcPickupPhoto" accept="image/*">
            <small class="text-muted" id="mcPickupHint">The container photo the driver would have taken at pickup.</small>
          </div>

          <hr>
          <h6 class="mb-2"><i class="ti ti-photo"></i> Proof of delivery <span class="text-danger" id="mcPodStar">*</span></h6>
          <div id="mcPodExisting" class="mb-2 d-none">
            <span class="badge bg-success-subtle text-success border border-success mb-1"><i class="ti ti-check"></i> Already captured by driver</span>
            <div class="d-flex gap-2 flex-wrap" id="mcPodExistingThumbs"></div>
          </div>
          <p class="text-muted small mb-2" id="mcPodHint">At least 2 delivery photos are required. Photo 3 is optional.</p>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label small">POD photo 1 <?php if ($mcPodPhotosRequired): ?><span class="text-danger">*</span><?php endif; ?></label>
              <input type="file" class="form-control" id="mcPodPhoto1" accept="image/*">
            </div>
            <div class="col-md-4">
              <label class="form-label small">POD photo 2 <?php if ($mcPodPhotosRequired): ?><span class="text-danger">*</span><?php endif; ?></label>
              <input type="file" class="form-control" id="mcPodPhoto2" accept="image/*">
            </div>
            <div class="col-md-4">
              <label class="form-label small">POD photo 3</label>
              <input type="file" class="form-control" id="mcPodPhoto3" accept="image/*">
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label small">Recipient name</label>
            <input type="text" class="form-control" id="mcSignedBy" placeholder="Who received the delivery? (optional)">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="mcSubmit"><i class="ti ti-check"></i> Manual Complete</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Live satellite map modal -->
  <div class="modal fade" id="ctMapModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0" id="ctMapTitle">Live Location</h5>
            <div class="text-muted small">Satellite position from Geotab (falls back to the driver app).</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="ctMap" style="height:360px;width:100%;border-radius:12px;border:1px solid #dce8f8;"></div>
          <div class="mt-2" style="font-size:13px" id="ctRouteMeta"></div>
          <div class="mt-1" style="font-size:12px;color:#5f728f" id="ctMapMeta"></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }

    // ----- Live satellite map (Esri World Imagery via Leaflet) -----------
    var ctMap = null, ctMarker = null, ctPoll = null, ctDid = null, ctMapModal = null, ctRouteLayer = null;

    function ctBuildMap() {
      if (typeof L === 'undefined') {
        $('#ctMapMeta').html('<span class="text-danger">Map library could not load (no internet or blocked).</span>');
        return false;
      }
      if (ctMap) { ctMap.invalidateSize(); return; }
      ctMap = L.map('ctMap', { zoomControl: true });
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Tiles &copy; Esri — Maxar, Earthstar Geographics'
      }).addTo(ctMap);
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, opacity: 0.9
      }).addTo(ctMap);
      ctMap.setView([12.8797, 121.7740], 6);
    }

    function ctSetMarker(lat, lng, label) {
      if (lat == null || lng == null || isNaN(lat) || isNaN(lng)) return;
      var ll = [lat, lng];
      if (!ctMarker) { ctMarker = L.marker(ll).addTo(ctMap); }
      else { ctMarker.setLatLng(ll); }
      ctMarker.bindPopup(label);
      // When a route is shown, keep the whole trip framed; otherwise centre on truck.
      if (!ctRouteLayer) { ctMap.setView(ll, Math.max(ctMap.getZoom(), 15), { animate: true }); }
    }

    function ctClearMarker() { if (ctMarker && ctMap) { ctMap.removeLayer(ctMarker); ctMarker = null; } }
    function ctClearRoute() { if (ctRouteLayer && ctMap) { ctMap.removeLayer(ctRouteLayer); ctRouteLayer = null; } }

    // Draw the trip's origin, destination, and road route once per open.
    function ctLoadRoute(did) {
      $.getJSON('php/fetch/dispatch_route.php', { d_id: did }, function (res) {
        if (did !== ctDid || res.status !== 'success' || !ctMap) return;
        ctClearRoute();
        var layer = L.layerGroup().addTo(ctMap);
        var bounds = [];
        if (res.route && res.route.length > 1) {
          L.polyline(res.route, { color: '#2563eb', weight: 4, opacity: 0.85 }).addTo(layer);
          res.route.forEach(function (p) { bounds.push(p); });
        }
        if (res.origin) {
          L.circleMarker([res.origin.lat, res.origin.lng], { radius: 7, color: '#059669', fillColor: '#10b981', fillOpacity: 1 })
            .bindPopup('Origin: ' + escapeHtml(res.origin.name)).addTo(layer);
          bounds.push([res.origin.lat, res.origin.lng]);
        }
        if (res.destination) {
          L.circleMarker([res.destination.lat, res.destination.lng], { radius: 8, color: '#b91c1c', fillColor: '#ef4444', fillOpacity: 1 })
            .bindPopup((res.arrived ? '✓ Arrived · ' : 'Destination: ') + escapeHtml(res.destination.name)).addTo(layer);
          bounds.push([res.destination.lat, res.destination.lng]);
        }
        ctRouteLayer = layer;
        if (bounds.length > 1) { try { ctMap.fitBounds(bounds, { padding: [30, 30] }); } catch (e) {} }
        // Arrival banner.
        if (res.destination) {
          var badge = res.arrived
            ? '<span style="color:#16a34a;font-weight:700">✓ Arrived at ' + escapeHtml(res.destination.name) + '</span>'
            : '<span style="color:#5f728f">En route to ' + escapeHtml(res.destination.name) + '</span>';
          $('#ctRouteMeta').html(badge);
        } else {
          $('#ctRouteMeta').html('<span class="text-muted">No mapped origin/destination for this trip.</span>');
        }
      });
    }

    function ctPollPosition() {
      if (!ctDid) return;
      var requested = ctDid;
      $.getJSON('php/fetch/dispatch_live_position.php', { d_id: requested }, function (res) {
        if (requested !== ctDid) return; // switched trips before this returned
        if (res.status !== 'success') { $('#ctMapMeta').text(res.message || 'Could not load position.'); return; }
        if (!res.has_position) { ctClearMarker(); $('#ctMapMeta').html('<span class="text-muted">No live position yet for this trip.</span>'); return; }
        var src = res.pos_source === 'geotab' ? 'Live (Geotab)' : 'Driver app';
        ctSetMarker(res.lat, res.lng,
          escapeHtml(res.truck || '') + '<br>' + escapeHtml(res.location || '') +
          (res.speed != null ? '<br>' + Number(res.speed).toFixed(0) + ' km/h' : ''));
        $('#ctMapMeta').html('<b>' + src + '</b> · ' + escapeHtml(res.location || 'On the move') +
          ' · updated ' + escapeHtml(res.position_at || '') +
          (res.pos_source === 'geotab' && !res.communicating ? ' · <span class="text-warning">device idle</span>' : ''));
      }).fail(function () { $('#ctMapMeta').text('Could not reach the position feed.'); });
    }

    function ctOpenMap(did, lat, lng, label) {
      ctDid = did;
      clearInterval(ctPoll);
      ctClearMarker();               // drop the previous trip's marker
      ctClearRoute();                // and its route
      $('#ctMapTitle').text(label || 'Live Location');
      $('#ctRouteMeta').empty();
      $('#ctMapMeta').html('<span class="text-muted">Loading position…</span>');
      if (!ctMapModal) { ctMapModal = new bootstrap.Modal(document.getElementById('ctMapModal')); }
      ctMapModal.show();
      setTimeout(function () {
        if (ctBuildMap() === false || !ctMap) { return; }
        ctMap.invalidateSize();
        if (lat != null && lng != null && !isNaN(lat) && !isNaN(lng)) { ctSetMarker(lat, lng, escapeHtml(label || '')); }
        ctLoadRoute(did);            // draw origin/destination/road route + arrival
        ctPollPosition();
        clearInterval(ctPoll); ctPoll = setInterval(ctPollPosition, 15000);
      }, 250);
    }

    $(document).on('click', '.ct-map-btn', function () {
      var lat = parseFloat($(this).data('lat')), lng = parseFloat($(this).data('lng'));
      ctOpenMap($(this).data('did'), isNaN(lat) ? null : lat, isNaN(lng) ? null : lng, $(this).data('label'));
    });
    document.addEventListener('DOMContentLoaded', function () {
      var el = document.getElementById('ctMapModal');
      el.addEventListener('shown.bs.modal', function () { if (ctMap) ctMap.invalidateSize(); });
      el.addEventListener('hidden.bs.modal', function () { clearInterval(ctPoll); ctPoll = null; ctDid = null; ctClearRoute(); });
    });
    function normalizeContainerNo(value) {
      var upper = String(value || '').toUpperCase();
      var letters = '';
      var digits = '';
      for (const ch of upper) {
        if (letters.length < 4 && /[A-Z]/.test(ch)) { letters += ch; continue; }
        if (letters.length === 4 && digits.length < 7 && /[0-9]/.test(ch)) { digits += ch; }
      }
      return letters + digits;
    }
    function isValidContainerNo(value) {
      return /^[A-Z]{4}[0-9]{7}$/.test(normalizeContainerNo(value));
    }

    function statusPill(s) {
      var n = String(s || '').toLowerCase();
      var label = escapeHtml(s);
      if (n.indexOf('verified by dispatch') > -1) return '<span class="pill pill-ontrip">' + label + '</span>';
      if (n.indexOf('awaiting pod') > -1)       return '<span class="pill pill-pickup">' + label + '</span>';
      if (n.indexOf('pickup') > -1)    return '<span class="pill pill-pickup">' + label + '</span>';
      if (n.indexOf('on trip') > -1)   return '<span class="pill pill-ontrip">' + label + '</span>';
      if (n.indexOf('delivered') > -1) return '<span class="pill pill-delivered">' + label + '</span>';
      if (n.indexOf('declined') > -1)  return '<span class="pill pill-declined">' + label + '</span>';
      return '<span class="pill pill-loaded-base">' + label + '</span>';
    }

    function gpsCell(r) {
      if (r.last_lat == null || r.last_lng == null) return '<span class="gps-none">No GPS</span>';
      var stale = r.last_seen_at && ((Date.now() - new Date(r.last_seen_at).getTime()) > 2 * 3600 * 1000);
      var seen = r.last_seen_at ? (' · ' + escapeHtml(r.last_seen_at)) : '';
      var key = gpsCacheKey(r.last_lat, r.last_lng);
      var cached = gpsAddressCache[key];
      // Address line — populated either from cache, or asynchronously by
      // resolveGpsAddresses() once Google's geocoder loads.
      var addrText = cached ? escapeHtml(cached) : 'Resolving address…';
      var addrCls  = cached ? 'gps-addr' : 'gps-addr gps-addr-pending';
      var addrLine = '<span class="' + addrCls + '" data-gps-addr="' + escapeHtml(key) + '" ' +
                       'data-gps-lat="' + r.last_lat + '" data-gps-lng="' + r.last_lng + '">' +
                       addrText +
                     '</span>';
      var coords = r.last_lat.toFixed(4) + ', ' + r.last_lng.toFixed(4);
      var link = '<a href="https://www.google.com/maps?q=' + r.last_lat + ',' + r.last_lng +
                 '" target="_blank" rel="noopener" class="text-decoration-none gps-coords">' +
                 coords + seen + '</a>';
      // Position source badge: 'geotab' = hardware GO device (Live),
      // 'phone' = driver PWA heartbeat. Absent on older payloads → no badge.
      var srcBadge = '';
      if (r.pos_source === 'geotab') {
        srcBadge = '<span class="gps-src gps-src-live" title="Hardware GPS from the truck\'s Geotab device">Live</span>';
      } else if (r.pos_source === 'phone') {
        srcBadge = '<span class="gps-src gps-src-phone" title="From the driver app on the phone">Phone</span>';
      }
      // Live satellite-map button (opens a modal with an updating position).
      var mapBtn = '<button type="button" class="btn btn-sm btn-link p-0 ms-1 ct-map-btn" ' +
                   'data-did="' + r.d_id + '" data-lat="' + r.last_lat + '" data-lng="' + r.last_lng + '" ' +
                   'data-label="' + escapeHtml((r.truck || '') + (r.container ? (' · ' + r.container) : '')) + '" ' +
                   'title="Live map"><i class="ti ti-map-2"></i> Map</button>';
      return '<span class="' + (stale ? 'gps-stale' : 'gps-fresh') + '">' + srcBadge + addrLine + link + mapBtn + '</span>';
    }

    // ----- Reverse-geocoding for the Last GPS column ----------------------
    // Coordinates are rounded to ~11 m precision so that small GPS jitter
    // doesn't bust the cache or burn through the Google Geocoding quota.
    var gpsAddressCache = (function () {
      try {
        var raw = sessionStorage.getItem('ctGpsAddrCache');
        return raw ? JSON.parse(raw) : {};
      } catch (e) { return {}; }
    })();
    function gpsCacheKey(lat, lng) {
      return Number(lat).toFixed(4) + ',' + Number(lng).toFixed(4);
    }
    function persistGpsCache() {
      try { sessionStorage.setItem('ctGpsAddrCache', JSON.stringify(gpsAddressCache)); } catch (e) {}
    }
    // In-flight + pending queue so we (a) never reverse-geocode the same key
    // twice in parallel and (b) spread requests out to stay polite under the
    // Geocoding API's per-second rate limit.
    var gpsResolveInFlight = {};
    var gpsResolveQueue    = [];
    var gpsResolveTimer    = null;
    function resolveGpsAddresses() {
      if (!(window.google && google.maps && google.maps.Geocoder)) {
        // Maps JS API not ready yet — try again shortly.
        setTimeout(resolveGpsAddresses, 500);
        return;
      }
      $('span.gps-addr-pending[data-gps-addr]').each(function () {
        var key = $(this).attr('data-gps-addr');
        if (!key) return;
        if (gpsAddressCache[key]) {
          // Cache filled by a previous resolve — paint and move on.
          $('span.gps-addr[data-gps-addr="' + key + '"]')
            .text(gpsAddressCache[key])
            .removeClass('gps-addr-pending');
          return;
        }
        if (gpsResolveInFlight[key]) return;
        if (gpsResolveQueue.indexOf(key) === -1) {
          gpsResolveQueue.push({
            key: key,
            lat: parseFloat($(this).attr('data-gps-lat')),
            lng: parseFloat($(this).attr('data-gps-lng')),
          });
        }
      });
      pumpGpsResolveQueue();
    }
    function pumpGpsResolveQueue() {
      if (gpsResolveTimer) return;
      gpsResolveTimer = setInterval(function () {
        var job = gpsResolveQueue.shift();
        if (!job) { clearInterval(gpsResolveTimer); gpsResolveTimer = null; return; }
        if (gpsAddressCache[job.key] || gpsResolveInFlight[job.key]) return;
        gpsResolveInFlight[job.key] = true;
        (new google.maps.Geocoder()).geocode(
          { location: { lat: job.lat, lng: job.lng } },
          function (results, status) {
            gpsResolveInFlight[job.key] = false;
            var addr = '';
            if (status === 'OK' && results && results.length) {
              // Prefer the most specific result that includes a locality —
              // falls back to the first formatted address otherwise.
              var pick = results.find(function (x) {
                return (x.types || []).some(function (t) {
                  return t === 'street_address' || t === 'premise' || t === 'plus_code';
                });
              }) || results[0];
              addr = pick.formatted_address || '';
            }
            if (!addr) addr = 'Address unavailable';
            gpsAddressCache[job.key] = addr;
            persistGpsCache();
            $('span.gps-addr[data-gps-addr="' + job.key + '"]')
              .text(addr)
              .removeClass('gps-addr-pending');
          }
        );
      }, 220); // ~4-5 requests / second — well under Google's 50 rps cap.
    }

    var state = { rows: [], facets: null };
    var currentView = 'active'; // active | complete_empty | complete_loaded — driven by the tabs.
    // Deep-link prefilter passed via ?q= (e.g. from the dispatch board's trip link).
    var PREFILL_Q = <?php echo json_encode($prefillQuery); ?>;
    var PREFILL_ACT = <?php echo json_encode($prefillAct); ?>;
    var PREFILL_DID = <?php echo (int)$prefillDid; ?>;
    var prefillApplied = false;
    // Only admins may delete a tracking record (see the Delete action below).
    var IS_ADMIN = <?php echo json_encode($role === 'admin'); ?>;

    function populateFacets(f) {
      if (!f || state.facets) return;
      state.facets = f;
      function fill(sel, arr) {
        var $sel = $(sel);
        var current = $sel.val();
        arr.forEach(function(v) { $sel.append('<option value="' + escapeHtml(v) + '">' + escapeHtml(v) + '</option>'); });
        if (current) $sel.val(current);
      }
      fill('#fSegment', f.segments);
      fill('#fCustomer', f.customers);
      fill('#fStatus',   f.statuses);
    }

    // Does a tracking row match the quick-search text?
    function rowMatchesQuery(r, q) {
      if (!q) return true;
      return [r.booking_no, r.booking_sn, r.trip_receipt, r.customer, r.container, r.container_seal,
              r.truck, r.trailer, r.genset, r.driver_name, r.trip_from, r.trip_to]
        .some(function(v) { return String(v || '').toLowerCase().indexOf(q) !== -1; });
    }
    // Switch the Active/Completed tab UI + state (without refetching).
    function viewCountLabel(view) {
      if (view === 'complete_empty')  return 'completed empty trip(s)';
      if (view === 'complete_loaded') return 'completed booking(s)';
      return 'active container(s)';
    }
    function setView(view) {
      currentView = view;
      $('#containerViewTabs button[data-view]').removeClass('active');
      $('#containerViewTabs button[data-view="' + view + '"]').addClass('active');
      $('#cntLabel').text(viewCountLabel(view));
    }

    // Which shipment-progress bucket a row falls into, keyed off its workflow
    // stage (more reliable than the display-status text).
    function snStageBucket(r) {
      var s = String(r.workflow_stage || '').toLowerCase();
      if (['pod_captured', 'billing_closed', 'client_notified'].indexOf(s) !== -1) return 'delivered';
      if (s === 'en_route' || s === 'delivered' || s === 'pending_verification')   return 'ontrip';
      if (s === 'driver_accepted' || s === 'gate_cleared')                         return 'pickup';
      if (s === 'dispatcher_assigned' || s === 'reassigned')                       return 'toaccept';
      return 'other';
    }
    // Full-width header row summarising one shipment (SN): container count,
    // a chip per progress bucket, and a delivered-progress bar.
    function snGroupHeader(sn, arr) {
      var c = { delivered: 0, ontrip: 0, pickup: 0, toaccept: 0, other: 0 };
      arr.forEach(function(r) { c[snStageBucket(r)]++; });
      var total = arr.length;
      var shipmentLabel = String(arr[0].booking_type || '').toLowerCase() === 'export' ? 'ATW' : 'Shipment';
      var cust = escapeHtml(arr[0].customer || '');
      var seg  = arr[0].customer_segment ? ' · ' + escapeHtml(arr[0].customer_segment) : '';
      var chips = [];
      if (c.delivered) chips.push('<span class="sn-chip sn-chip-delivered">' + c.delivered + ' delivered</span>');
      if (c.ontrip)    chips.push('<span class="sn-chip sn-chip-ontrip">' + c.ontrip + ' on trip</span>');
      if (c.pickup)    chips.push('<span class="sn-chip sn-chip-pickup">' + c.pickup + ' pickup</span>');
      if (c.toaccept)  chips.push('<span class="sn-chip sn-chip-toaccept">' + c.toaccept + ' to accept</span>');
      if (c.other)     chips.push('<span class="sn-chip sn-chip-other">' + c.other + ' other</span>');
      var pct = total ? Math.round((c.delivered / total) * 100) : 0;
      return '<tr class="sn-group-header" data-sn="' + escapeHtml(sn) + '">' +
        '<td colspan="10">' +
          '<div class="d-flex align-items-center flex-wrap gap-2">' +
            '<i class="ti ti-chevron-down sn-caret"></i>' +
            '<i class="ti ti-package"></i>' +
            '<strong>' + shipmentLabel + ' ' + escapeHtml(sn) + '</strong>' +
            (cust ? '<span class="text-white-50">' + cust + seg + '</span>' : '') +
            '<span class="badge bg-secondary">' + total + ' container' + (total === 1 ? '' : 's') + '</span>' +
            chips.join(' ') +
            '<div class="sn-progress ms-auto" title="' + c.delivered + ' of ' + total + ' delivered">' +
              '<div class="sn-progress-bar" style="width:' + pct + '%"></div>' +
            '</div>' +
            '<span class="text-white-50 small">' + c.delivered + '/' + total + '</span>' +
          '</div>' +
        '</td>' +
      '</tr>';
    }

    function render() {
      var q = $('#fQuery').val().trim().toLowerCase();
      var list = state.rows.filter(function(r) { return rowMatchesQuery(r, q); });
      $('#cnt').text(list.length);
      if (!list.length) {
        var emptyMsg = currentView === 'active'
          ? 'No active containers match.'
          : (currentView === 'complete_empty' ? 'No completed empty trips match.' : 'No completed bookings match.');
        $('#tbody').html('<tr><td colspan="10" class="text-center text-muted py-4">' + emptyMsg + '</td></tr>');
        return;
      }
      function rowHtml(r) {
        var shownStatus = r.display_status || r.container_status || '';
        // When the row belongs to a shipment (SN), tag it so the group header
        // can collapse/expand its rows.
        var snKey = String(r.booking_sn || '').trim();
        var trAttrs = snKey ? ' class="sn-row" data-sn-group="' + escapeHtml(snKey) + '"' : '';
        var canEditTracking = !!r.can_edit_tracking;
        var canMarkLoaded = !!r.can_mark_loaded;
        var canMarkCthEmptyReturn = !!r.can_mark_cth_empty_return;
        var actions = '<div class="action-stack">';
        var verificationHref = <?php echo json_encode($role === 'dispatcher' ? 'dispatch-verifications' : 'verifications'); ?>;
        var tripTicketHref   = <?php echo json_encode($role === 'dispatcher' ? 'dispatch-print'    : 'print');         ?>;
        var tripTicketCthHref = <?php echo json_encode($role === 'dispatcher' ? 'dispatch-printcth' : 'printcth');     ?>;
        // Every in-flight row has a dispatch — surface a Trip Ticket reprint,
        // but only once the driver has accepted. While it's still awaiting
        // acceptance the ticket can't be generated, so show a disabled hint.
        if (r.d_id) {
          var notAccepted = (r.workflow_stage === 'dispatcher_assigned' || r.workflow_stage === 'reassigned');
          if (notAccepted) {
            actions += '<button class="btn btn-sm btn-outline-secondary me-1" disabled ' +
              'title="Available once the driver accepts the booking">' +
              '<i class="ti ti-printer-off"></i> Trip Ticket</button>';
          } else {
            var isCth = String(r.customer || '').toUpperCase() === 'CTH';
            var ticketHref = (isCth ? tripTicketCthHref : tripTicketHref) + '?id=' + encodeURIComponent(r.d_id);
            actions += '<a class="btn btn-sm btn-outline-secondary me-1" href="' + ticketHref +
              '" target="_blank" rel="noopener" title="Print Trip Ticket">' +
              '<i class="ti ti-printer"></i> Trip Ticket</a>';
          }
        }
        if (canEditTracking) {
          actions += '<button class="btn btn-sm btn-outline-primary me-1" data-edit-track="' + escapeHtml(r.d_id) +
            '" data-booking="' + escapeHtml(r.booking_no) +
            '" data-trip-to="' + escapeHtml(r.trip_to || '') +
            '" data-trip-from="' + escapeHtml(r.trip_from || '') +
            '" data-trailer="' + escapeHtml(r.trailer || '') +
            '" data-container="' + escapeHtml(r.container || '') +
            '" data-genset="' + escapeHtml(r.genset || '') +
            '" data-driver="' + escapeHtml(r.driver_name || '') + '">' +
            '<i class="ti ti-edit"></i> Update</button>';
        }
        if (r.workflow_stage === 'pending_verification') {
          actions += '<a class="btn btn-sm btn-warning me-1" href="' + verificationHref + '?d_id=' + encodeURIComponent(r.d_id) + '">' +
            '<i class="ti ti-shield-check"></i> Will be Verified</a>';
        }
        if (r.can_manual_complete && r.workflow_stage !== 'pending_verification') {
          actions += '<button class="btn btn-sm btn-outline-danger me-1" data-manual-complete="' + escapeHtml(r.d_id) +
            '" data-booking="' + escapeHtml(r.booking_no) +
            '" data-container="' + escapeHtml(r.container || '') + '">' +
            '<i class="ti ti-checks"></i> Manual Complete</button>';
        }
        if (canMarkLoaded) {
          actions += '<button class="btn btn-sm btn-success" data-mark-loaded="' + escapeHtml(r.booking_no) +
            '" data-parent-d-id="' + escapeHtml(r.d_id) +
            '" data-trip-to="' + escapeHtml(r.trip_to || '') + '">' +
            '<i class="ti ti-arrow-right"></i> Create Loaded</button>';
        }
        if (canMarkCthEmptyReturn) {
          actions += '<button class="btn btn-sm btn-outline-success mt-1" data-mark-empty-return="' + escapeHtml(r.booking_no) +
            '" data-parent-d-id="' + escapeHtml(r.d_id) +
            '" data-trip-from="' + escapeHtml(r.trip_to || '') +
            '" data-return-location="' + escapeHtml(r.return_location || '') + '">' +
            '<i class="ti ti-repeat"></i> Create Empty Return</button>';
        }
        // Admin-only: permanently delete this tracking record.
        if (IS_ADMIN && r.d_id) {
          actions += '<button class="btn btn-sm btn-outline-dark mt-1" data-delete-track="' + escapeHtml(r.d_id) +
            '" data-booking="' + escapeHtml(r.booking_no || '') + '" title="Delete this record (admin only)">' +
            '<i class="ti ti-trash"></i> Delete</button>';
        }
        actions += '</div>';
        return '<tr' + trAttrs + '>' +
          '<td><span class="font-monospace">' + escapeHtml(r.booking_no) + '</span><br>' +
              '<small class="text-muted">req ' + escapeHtml(r.required) + '</small></td>' +
          '<td><span class="font-monospace">' + escapeHtml(r.trip_receipt || '—') + '</span></td>' +
          '<td>' + escapeHtml(r.customer) +
              (r.customer_segment ? '<br><small class="text-muted">' + escapeHtml(r.customer_segment) + '</small>' : '') + '</td>' +
          '<td>' + escapeHtml(r.trip_from || '—') + ' → ' + escapeHtml(r.trip_to || '—') + '</td>' +
          '<td>' + escapeHtml(r.container || '—') +
              (r.container_seal ? '<br><small class="text-muted">seal ' + escapeHtml(r.container_seal) + '</small>' : '') + '</td>' +
          '<td>' + statusPill(shownStatus) +
              (
                // Stuck-trip warning badge: dispatcher_assigned/reassigned for >= 5 min.
                // Time format: < 1h → "8m", < 1d → "2hrs 30m", ≥ 1d → "1d 16hrs".
                (['dispatcher_assigned','reassigned'].indexOf(r.workflow_stage) !== -1 && r.pending_minutes >= 5)
                  ? (function () {
                      var m = Math.max(0, Math.floor(r.pending_minutes || 0));
                      var label = m < 60 ? m + 'm'
                                : m < 1440 ? Math.floor(m/60) + 'hrs' + (m%60 ? ' ' + (m%60) + 'm' : '')
                                : Math.floor(m/1440) + 'd' + (Math.floor((m%1440)/60) ? ' ' + Math.floor((m%1440)/60) + 'hrs' : '');
                      var cls = r.pending_minutes >= 20 ? 'bg-danger' : 'bg-warning text-dark';
                      return ' <span class="badge ' + cls + '" title="Not accepted in ' + label + '">' +
                             '<i class="ti ti-alert-triangle"></i> Stuck</span>';
                    })()
                  : ''
              ) +
              '<br><small class="text-muted">wf: ' + escapeHtml(r.workflow_stage) + '</small></td>' +
          '<td><code class="truck">' + escapeHtml(r.truck || '—') + '</code><br>' +
              '<small>' + escapeHtml(r.driver_name || '—') + '</small>' +
              '<br><small class="text-muted">Trailer: ' + escapeHtml(r.trailer || '—') + '</small>' +
              '<br><small class="text-muted">Genset: ' + escapeHtml(r.genset || '—') + '</small></td>' +
          '<td>' + gpsCell(r) + '</td>' +
          '<td><small>' + escapeHtml(r.workflow_updated_at || '—') + '</small></td>' +
          '<td>' + actions + '</td>' +
        '</tr>';
      }

      // Cluster rows that share a Shipment Number (SN) under one collapsible
      // header with a progress summary — this is how CTH shipments are tracked.
      // Rows without an SN (non-CTH) render flat, exactly as before.
      var byKey = Object.create(null);
      var keyOrder = [];
      list.forEach(function(r) {
        var sn = String(r.booking_sn || '').trim();
        var key = sn || '__nosn__';
        if (!byKey[key]) { byKey[key] = []; keyOrder.push(key); }
        byKey[key].push(r);
      });
      var html = keyOrder.map(function(key) {
        var arr = byKey[key];
        if (key === '__nosn__') return arr.map(rowHtml).join('');
        return snGroupHeader(key, arr) + arr.map(rowHtml).join('');
      }).join('');
      $('#tbody').html(html);
      // Re-apply any groups the user had collapsed before this redraw.
      applyCollapsedState();
      // Kick off reverse-geocoding for any GPS cells still showing a placeholder.
      resolveGpsAddresses();
    }

    // Collapsed shipment groups persist across refreshes / tab switches so an
    // auto-refresh doesn't re-expand a group the dispatcher deliberately folded.
    var collapsedSns = (function () {
      try {
        var raw = sessionStorage.getItem('ctCollapsedSns');
        return raw ? JSON.parse(raw) : {};
      } catch (e) { return {}; }
    })();
    function persistCollapsedSns() {
      try { sessionStorage.setItem('ctCollapsedSns', JSON.stringify(collapsedSns)); } catch (e) {}
    }
    // Paint the remembered collapsed state onto the freshly-rendered table.
    function applyCollapsedState() {
      $('#tbody .sn-group-header').each(function () {
        var sn = String($(this).attr('data-sn') || '');
        var isCollapsed = !!collapsedSns[sn];
        $(this).toggleClass('collapsed', isCollapsed);
        $('tr.sn-row[data-sn-group="' + $.escapeSelector(sn) + '"]').toggle(!isCollapsed);
      });
    }

    // Collapse / expand a shipment group when its header is clicked.
    $(document).on('click', '.sn-group-header', function() {
      var sn = String($(this).attr('data-sn') || '');
      var collapsed = $(this).toggleClass('collapsed').hasClass('collapsed');
      $('tr.sn-row[data-sn-group="' + $.escapeSelector(sn) + '"]').toggle(!collapsed);
      if (collapsed) { collapsedSns[sn] = 1; } else { delete collapsedSns[sn]; }
      persistCollapsedSns();
    });

    function refresh() {
      var params = {
        segment:  $('#fSegment').val() || '',
        customer: $('#fCustomer').val() || '',
        status:   $('#fStatus').val() || '',
        view:     currentView,
      };
      return $.getJSON('php/fetch/active_containers.php', params).done(function(res) {
        if (res.status !== 'success') return;
        state.rows = res.rows;
        populateFacets(res.facets);
        render();
        $('#fetchedAt').text(res.fetched_at);
        // Deep-link prefilter: once the first load is in, apply the search so
        // it filters (and auto-switches tabs if the trip is completed). When an
        // action was requested (act=edit|manual + did), auto-open that modal.
        if (!prefillApplied && (PREFILL_Q || (PREFILL_ACT && PREFILL_DID))) {
          prefillApplied = true;
          if (PREFILL_Q) { $('#fQuery').val(PREFILL_Q).trigger('input'); }
          if (PREFILL_ACT && PREFILL_DID) {
            setTimeout(function () {
              var sel = PREFILL_ACT === 'manual'
                ? '[data-manual-complete="' + PREFILL_DID + '"]'
                : '[data-edit-track="' + PREFILL_DID + '"]';
              $(sel).first().trigger('click');
            }, 450);
          }
        }
      });
    }

    $(document).on('click', '[data-mark-loaded]', function() {
      var bk     = $(this).data('mark-loaded');
      var parentDId = $(this).data('parent-d-id');
      var pickup = $(this).data('trip-to') || '';
      $('#mlParent').val(bk);
      $('#mlParentDId').val(parentDId || '');
      $('#mlFrom').text(pickup || '—');
      $('#mlPickup').val(pickup);
      $('#mlDelivery').val('');
      $('#markLoadedModal').modal('show');
      setTimeout(function() { $('#mlDelivery').trigger('focus'); }, 200);
    });

    $(document).on('click', '[data-mark-empty-return]', function() {
      var bookingNo = $(this).data('mark-empty-return');
      var parentDId = $(this).data('parent-d-id');
      var tripFrom = $(this).data('trip-from') || '';
      var returnLocation = $(this).data('return-location') || '';
      Swal.fire({
        icon: 'question',
        title: 'Create empty return booking?',
        html: 'Booking: <b>' + escapeHtml(bookingNo) + '</b><br>' +
              'Route: ' + escapeHtml(tripFrom || '—') + ' → ' + escapeHtml(returnLocation || '—'),
        showCancelButton: true,
        confirmButtonText: 'Create Empty Return'
      }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post('php/operations/mark_cth_empty_return.php', {
          booking_no: bookingNo,
          parent_d_id: parentDId
        }, null, 'json').done(function(res) {
          if (res && res.status === 'success') {
            Swal.fire({
              icon: 'success',
              title: 'Empty return booking created',
              html: 'New booking: <b>' + escapeHtml(res.new_booking) + '</b><br>' +
                    escapeHtml(res.trip_from) + ' → ' + escapeHtml(res.trip_to)
            });
            refresh();
          } else {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Failed.' });
          }
        }).fail(function() {
          Swal.fire({ icon: 'error', text: 'Network error.' });
        });
      });
    });

    $('#teContainer').on('input', function() {
      this.value = normalizeContainerNo(this.value);
    });

    $(document).on('click', '[data-edit-track]', function() {
      $('#teDispatchId').val($(this).data('edit-track'));
      $('#teBookingNo').val($(this).data('booking') || '');
      $('#teTripFrom').val($(this).data('trip-from') || '');
      $('#teTripTo').val($(this).data('trip-to') || '');
      $('#teTrailer').val($(this).data('trailer') || '');
      $('#teContainer').val(normalizeContainerNo($(this).data('container') || ''));
      $('#teGenset').val($(this).data('genset') || '');
      // Driver — pre-fill current; new id stays empty unless the dispatcher picks one.
      $('#teDriver').val(String($(this).data('driver') || ''));
      $('#teNewDriverId').val('');
      $('#teDriverList').hide();
      $('#trackingEditModal').modal('show');
      setTimeout(function() { $('#teTripTo').trigger('focus'); }, 200);
    });

    // Assignable-driver list for reassignment (loaded once, refreshed on open).
    var teDrivers = [];
    function loadTeDrivers() {
      // On-shift drivers (open shift + truck). Robust list independent of the
      // attendance table, so the search always has results to filter.
      $.getJSON('php/fetch/dispatchable_drivers.php', function(res) {
        teDrivers = (((res && res.rows) || [])).map(function(d) {
          return { id: d.driver_id, name: d.driver_name, shift_truck: d.shift_truck };
        });
      });
    }
    loadTeDrivers();
    $('#trackingEditModal').on('show.bs.modal', loadTeDrivers);

    // Searchable driver dropdown — selecting sets the hidden new-driver id.
    $('#teDriver').on('input focus', function() {
      var q = String(this.value || '').toLowerCase().trim();
      var $list = $('#teDriverList').empty();
      // Typing a new search invalidates any prior pick until re-selected.
      $('#teNewDriverId').val('');
      if (!q) { $list.hide(); return; }
      var matches = teDrivers.filter(function(dr) {
        return String(dr.name || '').toLowerCase().indexOf(q) !== -1
            || String(dr.shift_truck || '').toLowerCase().indexOf(q) !== -1;
      }).slice(0, 50);
      if (!matches.length) { $list.hide(); return; }
      matches.forEach(function(dr, i) {
        var truck = dr.shift_truck ? ' · ' + dr.shift_truck : '';
        var $li = $('<li class="list-group-item"></li>').text(dr.name + truck);
        if (i === 0) $li.addClass('active-suggestion');
        $li.on('mousedown', function(e) {
          e.preventDefault();
          $('#teDriver').val(dr.name);
          $('#teNewDriverId').val(dr.id);
          $list.hide();
        });
        $list.append($li);
      });
      $list.show();
    });
    $('#teDriver').on('blur', function() { setTimeout(function() { $('#teDriverList').hide(); }, 150); });

    // Org-level photo requirements (Admin → Settings). When off, the
    // manual-complete modal won't force the dispatcher to supply that photo.
    var MC_PICKUP_PHOTO_REQUIRED = <?php echo $mcPickupPhotoRequired ? 'true' : 'false'; ?>;
    var MC_POD_PHOTOS_REQUIRED   = <?php echo $mcPodPhotosRequired ? 'true' : 'false'; ?>;
    var MC_MOVEMENT_REQUIRED     = <?php echo $mcMovementRequired ? 'true' : 'false'; ?>;

    // Tracks what the driver already captured, so the submit handler can
    // skip requiring photos that already exist.
    var mcPrefill = { pickup: false, pod: false };

    function mcToLocalInput(s) {
      if (!s) return '';
      // 'YYYY-MM-DD HH:MM:SS' (or with 'T') -> 'YYYY-MM-DDTHH:MM'
      return String(s).replace(' ', 'T').slice(0, 16);
    }
    function mcThumb(path) {
      if (!path) return '';
      return '<a href="' + escapeHtml(path) + '" target="_blank" rel="noopener">' +
             '<img src="' + escapeHtml(path) + '" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #ddd;"></a>';
    }
    function mcResetExisting() {
      mcPrefill = { pickup: false, pod: false };
      $('#mcPickupExisting').addClass('d-none');
      $('#mcPickupExistingThumbs').empty();
      $('#mcPodExisting').addClass('d-none');
      $('#mcPodExistingThumbs').empty();
      $('#mcPickupStar').toggleClass('d-none', !MC_PICKUP_PHOTO_REQUIRED);
      $('#mcPodStar').toggleClass('d-none', !MC_POD_PHOTOS_REQUIRED);
      $('#mcPickupHint').text(MC_PICKUP_PHOTO_REQUIRED
        ? 'The container photo the driver would have taken at pickup.'
        : 'Optional. Attach the pickup photo only if you have one.');
      $('#mcPodHint').text(MC_POD_PHOTOS_REQUIRED
        ? 'At least 2 delivery photos are required. Photo 3 is optional.'
        : 'Optional. Attach delivery photos only if you have them.');
    }

    // Admin-only: delete a tracking record (dispatch + its trip legs). The
    // backend re-checks the session is an Admin — the button is only a shortcut.
    $(document).on('click', '[data-delete-track]', function() {
      var dId = $(this).data('delete-track');
      var booking = String($(this).data('booking') || '');
      Swal.fire({
        icon: 'warning',
        title: 'Delete this record?',
        html: 'This permanently deletes the dispatch and its trip legs' +
              (booking ? ' for <b>' + escapeHtml(booking) + '</b>' : '') +
              ', frees its truck / trailer / genset, and returns the booking slot to the pool.' +
              '<br><span class="text-danger small">This cannot be undone.</span>',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-trash"></i> Delete',
        confirmButtonColor: '#d33',
        cancelButtonText: 'Cancel'
      }).then(function(res) {
        if (!res.isConfirmed) return;
        $.post('php/operations/delete_tracking_record.php', { d_id: dId }, null, 'json')
          .done(function(r) {
            if (r && r.status === 'success') {
              Swal.fire({ icon: 'success', title: 'Deleted', text: r.message || 'Record deleted.', timer: 1800, showConfirmButton: false });
              refresh();
            } else {
              Swal.fire({ icon: 'error', text: (r && r.message) || 'Delete failed.' });
            }
          })
          .fail(function() { Swal.fire({ icon: 'error', text: 'Network error.' }); });
      });
    });

    $(document).on('click', '[data-manual-complete]', function() {
      var dId = $(this).data('manual-complete');
      $('#mcDispatchId').val(dId);
      $('#mcBookingNo').val($(this).data('booking') || '');
      $('#mcContainer').val(String($(this).data('container') || ''));
      $('#mcReason').val('');
      $('#mcTsPickedUp,#mcTsOnTheWay,#mcTsArrived,#mcTsDelivered').val('');
      $('#mcPickupPhoto,#mcPodPhoto1,#mcPodPhoto2,#mcPodPhoto3').val('');
      $('#mcSignedBy').val('');
      mcResetExisting();
      $('#manualCompleteModal').modal('show');
      setTimeout(function() { $('#mcReason').trigger('focus'); }, 200);

      // Pull whatever the driver already captured and pre-fill the form.
      $.getJSON('php/fetch/manual_complete_prefill.php', { d_id: dId }, function(res) {
        if (!res || res.status !== 'success') return;
        // Only apply if the dispatcher hasn't switched to another trip meanwhile.
        if (String($('#mcDispatchId').val()) !== String(dId)) return;

        var ts = res.timestamps || {};
        if (ts.picked_up)  $('#mcTsPickedUp').val(mcToLocalInput(ts.picked_up));
        if (ts.on_the_way) $('#mcTsOnTheWay').val(mcToLocalInput(ts.on_the_way));
        if (ts.arrived)    $('#mcTsArrived').val(mcToLocalInput(ts.arrived));
        if (ts.delivered)  $('#mcTsDelivered').val(mcToLocalInput(ts.delivered));

        if (res.pickup && res.pickup.photo_path) {
          mcPrefill.pickup = true;
          $('#mcPickupExistingThumbs').html(mcThumb(res.pickup.photo_path));
          $('#mcPickupExisting').removeClass('d-none');
          $('#mcPickupStar').addClass('d-none');
          $('#mcPickupHint').text('Driver already provided a pickup photo. Upload only to replace it.');
        }

        if (res.pod && (res.pod.photo1_path || res.pod.photo2_path)) {
          mcPrefill.pod = true;
          var thumbs = [res.pod.photo1_path, res.pod.photo2_path, res.pod.photo3_path]
            .filter(function(p) { return !!p; }).map(mcThumb).join('');
          $('#mcPodExistingThumbs').html(thumbs);
          $('#mcPodExisting').removeClass('d-none');
          $('#mcPodStar').addClass('d-none');
          $('#mcPodHint').text('Driver already submitted POD photos. Upload only to replace them.');
          if (res.pod.signed_by) $('#mcSignedBy').val(res.pod.signed_by);
        }
      });
    });

    $('#teSubmit').on('click', function() {
      var dId = $('#teDispatchId').val();
      var tripFrom = $('#teTripFrom').val().trim();
      var tripTo = $('#teTripTo').val().trim();
      var trailer = $('#teTrailer').val().trim();
      var container = normalizeContainerNo($('#teContainer').val());
      var genset = $('#teGenset').val().trim();
      if (!tripTo) {
        Swal.fire({ icon: 'warning', text: 'Delivered location is required.' });
        return;
      }
      if (container && !isValidContainerNo(container)) {
        Swal.fire({ icon: 'warning', text: 'Container number must be exactly 4 capital letters followed by 7 numbers.' });
        return;
      }
      $.post('php/operations/update_tracking_transaction.php', {
        d_id: dId,
        trip_from: tripFrom,
        trip_to: tripTo,
        d_trailer: trailer,
        trip_container: container,
        d_genset: genset,
        // Only set when the dispatcher picked a different driver to correct it.
        new_driver_id: $('#teNewDriverId').val() || ''
      }, null, 'json').done(function(res) {
        if (res && res.status === 'success') {
          $('#trackingEditModal').modal('hide');
          Swal.fire({ icon: 'success', text: res.message || 'Transaction updated.' });
          refresh();
        } else {
          Swal.fire({ icon: 'error', text: (res && res.message) || 'Update failed.' });
        }
      }).fail(function() {
        Swal.fire({ icon: 'error', text: 'Network error.' });
      });
    });

    $('#mcSubmit').on('click', function() {
      var dId = $('#mcDispatchId').val();
      var reason = $('#mcReason').val().trim();
      if (!reason) {
        Swal.fire({ icon: 'warning', text: 'Completion reason is required.' });
        return;
      }

      var tsPickedUp = $('#mcTsPickedUp').val();
      var tsOnTheWay = $('#mcTsOnTheWay').val();
      var tsArrived  = $('#mcTsArrived').val();
      var tsDelivered = $('#mcTsDelivered').val();
      var allTs = tsPickedUp && tsOnTheWay && tsArrived && tsDelivered;
      if (MC_MOVEMENT_REQUIRED && !allTs) {
        Swal.fire({ icon: 'warning', text: 'All four movement timestamps are required.' });
        return;
      }
      // Stages must not go backwards in time — only checked when all four are
      // provided (when optional, blanks default to the completion time server-side).
      if (allTs && !(tsPickedUp <= tsOnTheWay && tsOnTheWay <= tsArrived && tsArrived <= tsDelivered)) {
        Swal.fire({ icon: 'warning', text: 'Timestamps must be in order: Picked up ≤ On the way ≤ Arrived ≤ Delivered.' });
        return;
      }

      var pickupPhoto = $('#mcPickupPhoto')[0].files[0];
      // Pickup photo only required when the org requires it AND the driver
      // didn't already capture one.
      if (MC_PICKUP_PHOTO_REQUIRED && !pickupPhoto && !mcPrefill.pickup) {
        Swal.fire({ icon: 'warning', text: 'A pickup photo is required.' });
        return;
      }
      var pod1 = $('#mcPodPhoto1')[0].files[0];
      var pod2 = $('#mcPodPhoto2')[0].files[0];
      var pod3 = $('#mcPodPhoto3')[0].files[0];
      // POD photos only required when the org requires it AND the driver
      // didn't already submit POD.
      if (MC_POD_PHOTOS_REQUIRED && (!pod1 || !pod2) && !mcPrefill.pod) {
        Swal.fire({ icon: 'warning', text: 'At least 2 POD photos are required.' });
        return;
      }

      Swal.fire({
        icon: 'warning',
        title: 'Manual complete this trip?',
        text: 'This bypasses the driver phone completion flow and marks the trip complete.',
        showCancelButton: true,
        confirmButtonText: 'Yes, complete it',
        confirmButtonColor: '#dc3545'
      }).then(function(result) {
        if (!result.isConfirmed) return;

        var fd = new FormData();
        fd.append('d_id', dId);
        fd.append('notes', reason);
        fd.append('ts_picked_up', tsPickedUp);
        fd.append('ts_on_the_way', tsOnTheWay);
        fd.append('ts_arrived', tsArrived);
        fd.append('ts_delivered', tsDelivered);
        fd.append('signed_by', $('#mcSignedBy').val().trim());
        fd.append('container_no', String($('#mcContainer').val() || '').trim().toUpperCase());
        if (pickupPhoto) fd.append('pickup_photo', pickupPhoto);
        if (pod1) fd.append('pod_photo1', pod1);
        if (pod2) fd.append('pod_photo2', pod2);
        if (pod3) fd.append('pod_photo3', pod3);

        var $btn = $('#mcSubmit').prop('disabled', true);
        $.ajax({
          url: 'php/operations/manual_complete_tracking.php',
          method: 'POST',
          data: fd,
          processData: false,
          contentType: false,
          dataType: 'json'
        }).done(function(res) {
          if (res && res.status === 'success') {
            $('#manualCompleteModal').modal('hide');
            Swal.fire({ icon: 'success', text: res.message || 'Trip manually completed.' });
            refresh();
          } else {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Manual completion failed.' });
          }
        }).fail(function(xhr) {
          var msg = 'Network error.';
          try { msg = (JSON.parse(xhr.responseText) || {}).message || msg; } catch (e) {}
          Swal.fire({ icon: 'error', text: msg });
        }).always(function() {
          $btn.prop('disabled', false);
        });
      });
    });

    $('#mlSubmit').on('click', function() {
      var parent   = $('#mlParent').val();
      var parentDId = $('#mlParentDId').val() || '';
      var pickup   = $('#mlPickup').val().trim();
      var delivery = $('#mlDelivery').val().trim();
      if (!delivery) {
        Swal.fire({ icon: 'warning', text: 'Loaded delivery location is required.' });
        return;
      }
      if (pickup && delivery.toLowerCase() === pickup.toLowerCase()) {
        Swal.fire({ icon: 'warning', text: 'Loaded delivery cannot be the same as the pickup location.' });
        return;
      }
      $.post('php/operations/mark_loaded.php', { booking_no: parent, parent_d_id: parentDId, trip_to: delivery }, null, 'json')
        .done(function(res) {
          if (res && res.status === 'success') {
            $('#markLoadedModal').modal('hide');
            var boardUrl = <?php echo json_encode($dispatchBoardHref); ?> +
              '?booking=' + encodeURIComponent(res.new_booking || '');
            Swal.fire({
              icon: 'success',
              title: 'Loaded booking created',
              html: 'New booking: <b>' + escapeHtml(res.new_booking) + '</b><br>' +
                    escapeHtml(res.trip_from) + ' → ' + escapeHtml(res.trip_to) +
                    '<br><small class="text-muted">Open the Dispatch Board → customer → <b>Loaded</b> lane to assign it.</small>',
              showCancelButton: true,
              confirmButtonText: 'Open Dispatch Board',
              cancelButtonText: 'Stay Here'
            }).then(function(result) {
              if (result.isConfirmed) {
                window.location.href = boardUrl;
              }
            });
            refresh();
          } else {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Failed.' });
          }
        })
        .fail(function(xhr) {
          var message = 'Network error.';
          if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
          } else if (xhr && xhr.responseText) {
            message = xhr.responseText;
          }
          Swal.fire({ icon: 'error', text: message });
        });
    });

    $('#fSegment, #fCustomer, #fStatus').on('change', refresh);
    // Quick search auto-switches to whichever tab holds the match — so
    // dispatchers don't have to guess Active / Completed Empty / Completed first.
    var ALL_VIEWS = ['active', 'complete_empty', 'complete_loaded'];
    function curQuery() { return String($('#fQuery').val() || '').trim().toLowerCase(); }
    // Walk the remaining views; switch to the first one that has a match.
    function tryCrossView(views) {
      if (!views.length) return;
      var v = views[0];
      $.getJSON('php/fetch/active_containers.php', {
        segment:  $('#fSegment').val() || '',
        customer: $('#fCustomer').val() || '',
        status:   $('#fStatus').val() || '',
        view:     v
      }).done(function(res) {
        var q = curQuery();
        if (!q || state.rows.some(function(r) { return rowMatchesQuery(r, q); })) return; // current tab now matches
        var rows = (res && res.rows) || [];
        if (rows.some(function(r) { return rowMatchesQuery(r, q); })) {
          setView(v); state.rows = rows; render();
        } else {
          tryCrossView(views.slice(1));
        }
      });
    }
    var crossViewTimer = null;
    $('#fQuery').on('input', function() {
      render();
      var q = curQuery();
      if (!q || state.rows.some(function(r) { return rowMatchesQuery(r, q); })) return;
      clearTimeout(crossViewTimer);
      crossViewTimer = setTimeout(function() {
        var qNow = curQuery();
        if (!qNow || state.rows.some(function(r) { return rowMatchesQuery(r, qNow); })) return;
        tryCrossView(ALL_VIEWS.filter(function(v) { return v !== currentView; }));
      }, 350);
    });
    $('#btnRefresh').on('click', refresh);

    // Tab switching between Active and Completed bookings.
    $(document).on('click', '#containerViewTabs button[data-view]', function() {
      var $btn = $(this);
      if ($btn.hasClass('active')) return;
      setView($btn.data('view'));
      $('#tbody').html('<tr><td colspan="10" class="text-center text-muted py-4">Loading…</td></tr>');
      refresh();
    });

    refresh();
    // Poll only while the tab is visible (saves Supabase egress on idle tabs);
    // refresh once when the dispatcher returns to the tab.
    setInterval(function () { if (!document.hidden) refresh(); }, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });

    // Keep Geotab truck positions fresh WITHOUT a Windows scheduled task (the
    // company's Cortex XDR flags those). While a dispatcher has this board open
    // we trigger a throttled server-side poll — normal web traffic. It's
    // fire-and-forget; the server throttles to ~once/60s across all open tabs,
    // writes positions to the DB, and the next board refresh shows them.
    function pokeGeotab() { if (document.hidden) return; $.get('php/operations/geotab_poll.php').fail(function () {}); }
    pokeGeotab();
    setInterval(pokeGeotab, 90000);
  </script>

  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
</body>
</html>
