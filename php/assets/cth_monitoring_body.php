<?php
// Focused CTH shipment monitor. Included by the admin and dispatcher routes.
require_once __DIR__ . '/../config/config.php';

$pageTitle = 'CTH Shipment Monitor';
$trackingHref = $role === 'admin' ? 'containerTracking' : 'dispatch-tracking';
$dispatchHref = $role === 'admin' ? 'dispatchTiles' : 'dispatch-tiles';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> — Pantrucks</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png">
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/enhancements.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    .cth-hero { background:linear-gradient(120deg,#0f172a,#1d4ed8); color:#fff; border-radius:14px; padding:22px 24px; }
    .cth-kpi { border:0; border-radius:12px; box-shadow:0 2px 10px rgba(15,23,42,.08); }
    .cth-kpi .value { font-size:1.7rem; font-weight:800; line-height:1; color:#0f172a; }
    .cth-kpi .label { color:#64748b; font-size:.78rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .cth-tabs { border-bottom:2px solid #e2e8f0; }
    .cth-tabs .nav-link { border:0; color:#475569; font-weight:700; border-radius:8px 8px 0 0; }
    .cth-tabs .nav-link.active { color:#1d4ed8; background:#eff6ff; border-bottom:3px solid #2563eb; }
    .shipment-card { border:1px solid #dbe4f0; border-radius:12px; overflow:hidden; margin-bottom:14px; }
    .shipment-card.attention { border-left:5px solid #f59e0b; }
    .shipment-head { background:#f8fafc; padding:14px 16px; }
    .shipment-sn { color:#0f172a; font-weight:800; font-size:1rem; }
    .shipment-meta { color:#64748b; font-size:.82rem; }
    .shipment-progress { height:8px; background:#e2e8f0; border-radius:99px; overflow:hidden; }
    .shipment-progress > span { display:block; height:100%; background:#22c55e; border-radius:inherit; }
    .container-row { padding:10px 16px; border-top:1px solid #edf2f7; }
    .container-row:hover { background:#f8fafc; }
    .stage { font-size:.74rem; font-weight:700; padding:3px 8px; border-radius:99px; white-space:nowrap; display:inline-block; }
    .stage-pending { background:#fef3c7; color:#92400e; }
    .stage-moving { background:#dbeafe; color:#1d4ed8; }
    .stage-pod { background:#ede9fe; color:#6d28d9; }
    .stage-done { background:#dcfce7; color:#166534; }
    .stage-neutral { background:#e2e8f0; color:#334155; }
    /* Position source: hardware Geotab fix (Live) vs driver-phone heartbeat (Phone). */
    .gps-src { padding:1px 6px; border-radius:99px; font-size:.62rem; font-weight:700;
               text-transform:uppercase; letter-spacing:.3px; margin-right:5px; vertical-align:middle; }
    .gps-src-live  { background:#cffafe; color:#155e75; }
    .gps-src-phone { background:#e5e7eb; color:#374151; }
    /* Live-map overlay modal (this page has no Bootstrap JS). */
    .ctm-overlay { position:fixed; inset:0; background:rgba(16,35,63,.55); z-index:2000; display:none; align-items:center; justify-content:center; padding:16px; }
    .ctm-overlay.open { display:flex; }
    .ctm-box { background:#fff; border-radius:16px; width:min(900px,96vw); box-shadow:0 24px 60px rgba(16,35,63,.35); overflow:hidden; }
    .ctm-head { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #e6edf6; }
    .ctm-head h5 { margin:0; font-size:1rem; color:#10233f; }
    .ctm-close { border:0; background:transparent; font-size:1.4rem; line-height:1; color:#5f728f; cursor:pointer; }
    #ctmMap { height:min(60vh,420px); width:100%; }
    .ctm-meta { font-size:12px; color:#5f728f; padding:10px 16px; }
    .btn-map { border:0; background:#e0f2fe; color:#075985; border-radius:8px; padding:2px 8px; font-size:11px; font-weight:700; cursor:pointer; margin-left:6px; }
    .btn-map:hover { background:#bae6fd; }
    .empty-state { color:#64748b; padding:42px 20px; text-align:center; }
    @media (max-width: 767px) { .cth-hero { padding:18px; } .container-row .text-end { text-align:left !important; margin-top:7px; } }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <img src="assets/images/logos/pantrucks.png" width="122" alt="Pantrucks">
      <h3 class="text-white mb-0 fs-5">CTH Shipment Monitor</h3>
    </div>
    <?php include $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner"><div class="container-fluid">
      <div class="cth-hero mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div><h3 class="mb-1 text-white">CTH shipment monitoring</h3><div class="opacity-75">One view for every container under a shipment number.</div></div>
        <div class="text-end"><div class="small opacity-75">Last refreshed</div><strong id="lastUpdated">Loading…</strong></div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card cth-kpi"><div class="card-body"><div class="label">Shipments</div><div class="value" id="kpiShipments">—</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card cth-kpi"><div class="card-body"><div class="label">Containers</div><div class="value" id="kpiContainers">—</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card cth-kpi"><div class="card-body"><div class="label">On the road</div><div class="value text-primary" id="kpiMoving">—</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card cth-kpi"><div class="card-body"><div class="label">Needs attention</div><div class="value text-warning" id="kpiAttention">—</div></div></div></div>
      </div>

      <div class="card"><div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <ul class="nav cth-tabs" id="monitorTabs">
            <li class="nav-item"><button class="nav-link active" data-view="active" type="button">Active</button></li>
            <li class="nav-item"><button class="nav-link" data-view="complete_empty" type="button">Completed Empty</button></li>
            <li class="nav-item"><button class="nav-link" data-view="complete_loaded" type="button">Completed Booking</button></li>
          </ul>
          <div class="d-flex gap-2"><input id="shipmentSearch" class="form-control form-control-sm" style="min-width:230px" placeholder="Search shipment, container, truck…"><button id="refreshMonitor" class="btn btn-sm btn-outline-primary"><i class="ti ti-refresh"></i> Refresh</button></div>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3" id="quickFilters">
          <button class="btn btn-sm btn-primary" data-filter="all">All</button>
          <button class="btn btn-sm btn-outline-warning" data-filter="attention">Needs attention</button>
          <button class="btn btn-sm btn-outline-primary" data-filter="moving">On the road</button>
          <button class="btn btn-sm btn-outline-secondary" data-filter="pod">Awaiting POD</button>
          <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= htmlspecialchars($dispatchHref) ?>"><i class="ti ti-layout-board"></i> Dispatch board</a>
        </div>
        <div id="shipmentList"><div class="empty-state">Loading CTH shipments…</div></div>
      </div></div>
    </div></div></div>
  </div>

  <!-- Live satellite map overlay -->
  <div class="ctm-overlay" id="ctmOverlay">
    <div class="ctm-box">
      <div class="ctm-head">
        <h5 id="ctmTitle">Live Location</h5>
        <button type="button" class="ctm-close" id="ctmClose" aria-label="Close">&times;</button>
      </div>
      <div id="ctmMap"></div>
      <div class="ctm-meta" id="ctmMeta"></div>
    </div>
  </div>
<script>
(() => {
  const feedUrl = 'php/fetch/active_containers.php';
  const trackingHref = <?= json_encode($trackingHref) ?>;
  let rows = [], view = 'active', quickFilter = 'all';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const stageInfo = row => {
    const stage = String(row.workflow_stage || '').toLowerCase();
    if (['dispatcher_assigned','reassigned','driver_declined'].includes(stage)) return ['Needs assignment / acceptance','stage-pending','attention'];
    if (stage === 'en_route') return ['On the road','stage-moving','moving'];
    if (['delivered','pending_verification'].includes(stage)) return ['Awaiting POD / verification','stage-pod','pod'];
    if (['pod_captured','billing_closed','client_notified'].includes(stage)) return ['Completed','stage-done','done'];
    if (['driver_accepted','gate_cleared'].includes(stage)) return ['Pickup in progress','stage-moving','moving'];
    return [row.display_status || 'In progress','stage-neutral','neutral'];
  };
  const isAttention = row => {
    const type = stageInfo(row)[2];
    return type === 'attention' || (Number(row.pending_minutes || 0) >= 60 && type !== 'done');
  };
  function updateKpis(list) {
    const shipments = new Set(list.map(r => String(r.booking_sn || r.booking_no))).size;
    $('#kpiShipments').text(shipments); $('#kpiContainers').text(list.length);
    $('#kpiMoving').text(list.filter(r => stageInfo(r)[2] === 'moving').length);
    $('#kpiAttention').text(list.filter(isAttention).length);
  }
  function visibleRows() {
    const query = $('#shipmentSearch').val().trim().toLowerCase();
    return rows.filter(r => {
      const type = stageInfo(r)[2];
      if (quickFilter === 'attention' && !isAttention(r)) return false;
      if (quickFilter !== 'all' && quickFilter !== 'attention' && type !== quickFilter) return false;
      return !query || [r.booking_sn,r.container,r.trip_receipt,r.truck,r.driver_name,r.trip_from,r.trip_to].join(' ').toLowerCase().includes(query);
    });
  }
  function render() {
    const list = visibleRows(); updateKpis(rows);
    if (!list.length) { $('#shipmentList').html('<div class="empty-state"><i class="ti ti-search fs-5 d-block mb-2"></i>No CTH shipments match this view.</div>'); return; }
    const grouped = {};
    list.forEach(r => { const key = String(r.booking_sn || ('Booking ' + r.booking_no)); (grouped[key] ||= []).push(r); });
    const html = Object.keys(grouped).sort().map(sn => {
      const group = grouped[sn], completed = group.filter(r => stageInfo(r)[2] === 'done').length;
      const attention = group.some(isAttention), percent = Math.round(completed / group.length * 100);
      const shipmentLabel = String(group[0].booking_type || '').toLowerCase() === 'export' ? 'ATW' : 'Shipment';
      const route = group[0].trip_from || group[0].trip_to ? esc(group[0].trip_from || '—') + ' <i class="ti ti-arrow-right"></i> ' + esc(group[0].trip_to || '—') : 'Route not yet recorded';
      const groupRows = group.map(r => { const info = stageInfo(r); const hasGps = r.last_lat != null && r.last_lng != null; let gpsSrc = ''; if (hasGps && r.pos_source === 'geotab') gpsSrc = '<span class="gps-src gps-src-live" title="Hardware GPS from the truck\'s Geotab device">Live</span>'; else if (hasGps && r.pos_source === 'phone') gpsSrc = '<span class="gps-src gps-src-phone" title="From the driver app on the phone">Phone</span>'; const mapBtn = hasGps ? ('<button type="button" class="btn-map ctm-open" data-did="' + r.d_id + '" data-lat="' + r.last_lat + '" data-lng="' + r.last_lng + '" data-label="' + esc((r.truck || '') + (r.container ? (' · ' + r.container) : '')) + '"><i class="ti ti-map-2"></i> Map</button>') : ''; const location = (hasGps ? (gpsSrc + 'GPS available') : 'No GPS') + mapBtn; return '<div class="container-row"><div class="row align-items-center g-1"><div class="col-md-3"><strong>' + esc(r.container || 'Container pending') + '</strong><div class="shipment-meta">' + esc(r.trip_receipt || 'No trip receipt') + '</div></div><div class="col-md-3"><span class="stage ' + info[1] + '">' + esc(info[0]) + '</span><div class="shipment-meta mt-1">' + esc(r.display_status || '') + '</div></div><div class="col-md-3"><strong>' + esc(r.truck || 'Truck pending') + '</strong><div class="shipment-meta">' + esc(r.driver_name || 'Driver pending') + '</div></div><div class="col-md-2"><span class="shipment-meta"><i class="ti ti-map-pin"></i> ' + location + '</span></div><div class="col-md-1 text-end"><a class="btn btn-sm btn-outline-primary" title="Open this container in tracking" href="' + trackingHref + '?q=' + encodeURIComponent(r.container || r.booking_sn || '') + '"><i class="ti ti-external-link"></i></a></div></div></div>'; }).join('');
      return '<section class="shipment-card ' + (attention ? 'attention' : '') + '"><div class="shipment-head"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><div class="shipment-sn"><i class="ti ti-package"></i> ' + shipmentLabel + ' ' + esc(sn) + '</div><div class="shipment-meta mt-1">' + route + ' · ' + group.length + ' container' + (group.length === 1 ? '' : 's') + '</div></div><div class="text-end"><a class="btn btn-sm btn-outline-secondary" href="' + trackingHref + '?q=' + encodeURIComponent(sn) + '">View shipment</a><div class="shipment-meta mt-2">' + completed + '/' + group.length + ' completed</div></div></div><div class="shipment-progress mt-3"><span style="width:' + percent + '%"></span></div></div>' + groupRows + '</section>';
    }).join('');
    $('#shipmentList').html(html);
  }
  function load() {
    $('#refreshMonitor').prop('disabled', true);
    $.getJSON(feedUrl, { customer: 'CTH', view: view }).done(data => {
      rows = data.rows || []; $('#lastUpdated').text(data.fetched_at || new Date().toLocaleString()); render();
    }).fail(() => $('#shipmentList').html('<div class="empty-state text-danger">Unable to load the CTH monitoring feed. Please refresh and try again.</div>')).always(() => $('#refreshMonitor').prop('disabled', false));
  }
  $('#monitorTabs button').on('click', function(){ view = $(this).data('view'); $('#monitorTabs button').removeClass('active'); $(this).addClass('active'); quickFilter = 'all'; $('#quickFilters button').removeClass('btn-primary').addClass('btn-outline-secondary'); $('#quickFilters button[data-filter="all"]').removeClass('btn-outline-secondary').addClass('btn-primary'); load(); });
  $('#quickFilters button').on('click', function(){ quickFilter = $(this).data('filter'); $('#quickFilters button').removeClass('btn-primary').addClass('btn-outline-secondary'); $(this).removeClass('btn-outline-secondary').addClass('btn-primary'); render(); });
  $('#shipmentSearch').on('input', render); $('#refreshMonitor').on('click', load);

  // ----- Live satellite map (Esri World Imagery via Leaflet) -----------
  let ctmMap = null, ctmMarker = null, ctmPoll = null, ctmDid = null;
  function ctmBuild() {
    if (typeof L === 'undefined') {
      $('#ctmMeta').html('<span class="text-danger">Map library could not load (no internet or blocked).</span>');
      return false;
    }
    if (ctmMap) { ctmMap.invalidateSize(); return; }
    ctmMap = L.map('ctmMap', { zoomControl: true });
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 19, attribution: 'Tiles &copy; Esri — Maxar, Earthstar Geographics'
    }).addTo(ctmMap);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 19, opacity: 0.9
    }).addTo(ctmMap);
    ctmMap.setView([12.8797, 121.7740], 6);
  }
  function ctmSet(lat, lng, label) {
    if (lat == null || lng == null || isNaN(lat) || isNaN(lng)) return;
    const ll = [lat, lng];
    if (!ctmMarker) ctmMarker = L.marker(ll).addTo(ctmMap); else ctmMarker.setLatLng(ll);
    ctmMarker.bindPopup(label);
    ctmMap.setView(ll, Math.max(ctmMap.getZoom(), 15), { animate: true });
  }
  function ctmPollPos() {
    if (!ctmDid) return;
    $.getJSON('php/fetch/dispatch_live_position.php', { d_id: ctmDid }, res => {
      if (res.status !== 'success') { $('#ctmMeta').text(res.message || 'Could not load position.'); return; }
      if (!res.has_position) { $('#ctmMeta').html('<span class="text-muted">No live position yet for this trip.</span>'); return; }
      const src = res.pos_source === 'geotab' ? 'Live (Geotab)' : 'Driver app';
      ctmSet(res.lat, res.lng, esc(res.truck || '') + '<br>' + esc(res.location || '') + (res.speed != null ? '<br>' + Number(res.speed).toFixed(0) + ' km/h' : ''));
      $('#ctmMeta').html('<b>' + src + '</b> · ' + esc(res.location || 'On the move') + ' · updated ' + esc(res.position_at || '') +
        (res.pos_source === 'geotab' && !res.communicating ? ' · <span class="text-warning">device idle</span>' : ''));
    }).fail(() => $('#ctmMeta').text('Could not reach the position feed.'));
  }
  function ctmClose() { $('#ctmOverlay').removeClass('open'); clearInterval(ctmPoll); ctmPoll = null; ctmDid = null; }
  $('#shipmentList').on('click', '.ctm-open', function () {
    ctmDid = $(this).data('did');
    const lat = parseFloat($(this).data('lat')), lng = parseFloat($(this).data('lng'));
    const label = String($(this).data('label') || '');
    $('#ctmTitle').text(label || 'Live Location');
    $('#ctmMeta').html('<span class="text-muted">Loading position…</span>');
    $('#ctmOverlay').addClass('open');
    setTimeout(() => {
      if (ctmBuild() === false || !ctmMap) { return; }
      ctmMap.invalidateSize();
      if (!isNaN(lat) && !isNaN(lng)) ctmSet(lat, lng, esc(label));
      ctmPollPos(); clearInterval(ctmPoll); ctmPoll = setInterval(ctmPollPos, 15000);
    }, 200);
  });
  $('#ctmClose').on('click', ctmClose);
  $('#ctmOverlay').on('click', e => { if (e.target.id === 'ctmOverlay') ctmClose(); });

  load(); setInterval(() => { if (!document.hidden) load(); }, 60000);
})();
</script>
<!-- Required by the shared sidebar: enables expandable menus such as Units. -->
<script src="assets/js/sidebarmenu.js"></script>
<script src="assets/js/app.min.js"></script>
<script src="assets/libs/simplebar/dist/simplebar.js"></script>
</body>
</html>
