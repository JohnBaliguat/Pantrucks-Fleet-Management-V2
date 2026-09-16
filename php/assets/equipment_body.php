<?php
// Shared body for Equipment Locations.
// Required vars: $role.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Equipment Locations</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    .eq-page-shell { padding-top: 12px; }
    .eq-hero { border: 1px solid rgba(31, 94, 255, 0.08); border-radius: 22px; background: radial-gradient(circle at right top, rgba(31,94,255,.12), transparent 28%), linear-gradient(135deg, #f9fbff 0%, #f1f6ff 55%, #ebf3ff 100%); box-shadow: 0 18px 44px rgba(16,35,63,.06); overflow: hidden; }
    .eq-hero .card-body { padding: 1.6rem 1.5rem; }
    .eq-kicker { display:inline-block; color:#2d5bdf; font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; margin-bottom:10px; }
    .eq-hero h4 { font-size: clamp(1.8rem, 2.4vw, 2.6rem); line-height:1.02; color:#10233f; margin-bottom:10px; max-width: 12ch; }
    .eq-hero-copy { color:#5f728f; max-width:60ch; line-height:1.7; margin-bottom:0; }
    .eq-toolbar { display:flex; align-items:center; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
    .eq-toolbar .form-control { min-width: 220px; border-radius: 14px; border-color: rgba(16,35,63,.1); box-shadow: 0 8px 20px rgba(16,35,63,.04); }
    .eq-summary-grid { --bs-gutter-y: 1rem; margin-top: 1rem; }
    .eq-summary-card { border:1px solid rgba(16,35,63,.08); border-radius:18px; background:#fff; box-shadow:0 10px 28px rgba(16,35,63,.05); height:100%; }
    .eq-summary-card .card-body { padding:1rem 1.15rem; }
    .eq-summary-kicker { color:#6b7a90; font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .eq-summary-count { color:#10233f; font-size:1.9rem; font-weight:800; line-height:1; margin-top:6px; }
    .eq-summary-note { color:#6b7a90; font-size:12px; margin-top:6px; }
    .eq-summary-badge { display:inline-flex; align-items:center; justify-content:center; min-width:44px; height:44px; border-radius:14px; font-weight:800; font-size:16px; }
    .eq-summary-badge.truck { background:#dbeafe; color:#0d6efd; }
    .eq-summary-badge.genset { background:#fff3cd; color:#8a5b00; }
    .eq-summary-badge.trailer { background:#d1e7dd; color:#146c43; }
    .eq-summary-badge.issue { background:#fde2e1; color:#c92a2a; }
    .eq-legend { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
    .eq-legend-item { display:inline-flex; align-items:center; gap:8px; padding:7px 12px; border-radius:999px; background:#fff; border:1px solid rgba(16,35,63,.08); font-size:12px; color:#334e68; }
    .eq-legend-dot { width:10px; height:10px; border-radius:50%; }
    .eq-legend-dot.truck { background:#0d6efd; }
    .eq-legend-dot.genset { background:#d4a72c; }
    .eq-legend-dot.trailer { background:#2f855a; }
    .eq-legend-dot.issue { background:#dc3545; }
    .eq-bucket { border:1px solid rgba(16,35,63,.08); border-radius:18px; padding:16px; margin-bottom:16px; background:#fff; box-shadow:0 12px 30px rgba(16,35,63,.05); }
    .eq-bucket h6 { margin:0 0 10px; color:#10233f; font-weight:800; }
    .eq-section-label { display:block; color:#6b7a90; font-size:12px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; margin-bottom:8px; }
    .eq-chip { display:inline-flex; align-items:center; gap:4px; padding:5px 11px; border-radius:999px; font-size:12px; font-weight:600; margin:3px 5px 3px 0; background:#e9ecef; color:#212529; border:0; cursor:pointer; transition:transform .15s ease, box-shadow .15s ease, filter .15s ease; }
    .eq-chip:hover { transform:translateY(-1px); box-shadow:0 4px 10px rgba(0,0,0,.08); }
    .eq-chip.truck { background:#cfe2ff; color:#0a58ca; }
    .eq-chip.genset { background:#fff3cd; color:#664d03; }
    .eq-chip.trailer { background:#d1e7dd; color:#0f5132; }
    .eq-chip.bad { background:#f8d7da; color:#842029; }
    .eq-chip.bad::before { content:''; width:7px; height:7px; border-radius:50%; background:currentColor; opacity:.85; }
    .history-row { border:1px solid #edf2f7; border-radius:12px; padding:12px 14px; background:#fff; }
    .history-row + .history-row { margin-top:10px; }
    .history-title { font-weight:700; color:#102a43; }
    .history-meta { font-size:12px; color:#6c7a8c; margin-top:2px; }
    .history-detail { font-size:13px; color:#334e68; margin-top:6px; line-height:1.45; }
    .history-pill { display:inline-flex; align-items:center; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; }
    .history-pill.info { background:#dbeafe; color:#0d6efd; }
    .history-pill.warn { background:#fff3cd; color:#997404; }
    .history-pill.danger { background:#fde2e1; color:#c92a2a; }
    .snapshot-card { background:#f8fbff; border:1px solid #dce8f8; border-radius:14px; padding:12px 14px; }
    #equipmentMap { height: 320px; width: 100%; border-radius: 14px; border:1px solid #dce8f8; }
    .eq-map-meta { font-size:12px; color:#5f728f; margin-top:6px; }
    .eq-live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#16a34a; margin-right:5px; animation:eqpulse 1.6s infinite; }
    @keyframes eqpulse { 0%{opacity:.35} 50%{opacity:1} 100%{opacity:.35} }
    @media (max-width: 767px) {
      .eq-hero .card-body { padding: 1.2rem 1rem; }
      .eq-hero h4 { max-width:none; }
      .eq-toolbar { justify-content:stretch; }
      .eq-toolbar .form-control { min-width: 0; width:100%; }
    }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Equipment Locations</h3>
    </div>

    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner eq-page-shell">
        <div class="container-fluid">
          <div class="eq-hero card border-0 mt-3">
            <div class="card-body">
              <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                  <span class="eq-kicker">Live Equipment Visibility</span>
                  <h4 class="mb-0">Track trucks, gensets, and trailers from one place.</h4>
                  <p class="eq-hero-copy mt-3">Use this view to see where equipment is grouped right now, spot non-good units fast, and open the full movement history by clicking any equipment code.</p>
                </div>
                <div class="col-lg-5">
                  <div class="eq-toolbar">
                    <input class="form-control form-control-sm" id="filterText" placeholder="Filter codes..." style="max-width:220px;">
                    <button class="btn btn-sm btn-outline-primary" id="refreshBtn"><i class="ti ti-refresh"></i></button>
                  </div>
                  <div class="eq-legend">
                    <span class="eq-legend-item"><span class="eq-legend-dot truck"></span>Truck</span>
                    <span class="eq-legend-item"><span class="eq-legend-dot genset"></span>Genset</span>
                    <span class="eq-legend-item"><span class="eq-legend-dot trailer"></span>Trailer</span>
                    <span class="eq-legend-item"><span class="eq-legend-dot issue"></span>Needs attention</span>
                  </div>
                </div>
              </div>
              <div class="row eq-summary-grid" id="equipmentSummary">
                <div class="col-md-3 col-sm-6">
                  <div class="eq-summary-card"><div class="card-body d-flex align-items-start justify-content-between">
                    <div><div class="eq-summary-kicker">Trucks</div><div class="eq-summary-count">-</div><div class="eq-summary-note">Loading count</div></div>
                    <span class="eq-summary-badge truck">T</span>
                  </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="eq-summary-card"><div class="card-body d-flex align-items-start justify-content-between">
                    <div><div class="eq-summary-kicker">Gensets</div><div class="eq-summary-count">-</div><div class="eq-summary-note">Loading count</div></div>
                    <span class="eq-summary-badge genset">G</span>
                  </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="eq-summary-card"><div class="card-body d-flex align-items-start justify-content-between">
                    <div><div class="eq-summary-kicker">Trailers</div><div class="eq-summary-count">-</div><div class="eq-summary-note">Loading count</div></div>
                    <span class="eq-summary-badge trailer">R</span>
                  </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="eq-summary-card"><div class="card-body d-flex align-items-start justify-content-between">
                    <div><div class="eq-summary-kicker">Attention Needed</div><div class="eq-summary-count">-</div><div class="eq-summary-note">Units not in Good status</div></div>
                    <span class="eq-summary-badge issue">!</span>
                  </div></div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-body">
              <div class="d-md-flex align-items-center mb-3">
                <div>
                  <h4 class="card-title mb-0">Equipment Locations</h4>
                  <p class="card-subtitle">Grouped by current base or location. Click any code to inspect its dispatch and activity timeline.</p>
                </div>
              </div>
              <div id="bucketsBox"><div class="text-muted">Loading...</div></div>
            </div>
          </div>
          <div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="equipmentHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0" id="equipmentHistoryTitle">Equipment Activity</h5>
            <div class="text-muted small">Dispatch, workflow, gate, and jack-up history for the selected equipment.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="equipmentSnapshot"></div>
          <div id="equipmentMapWrap" class="mt-3" hidden>
            <span class="eq-section-label">Live Location (Geotab)</span>
            <div id="equipmentMap"></div>
            <div class="eq-map-meta" id="equipmentMapMeta"></div>
          </div>
          <div id="equipmentHistoryBody" class="mt-3"><div class="text-muted">Choose equipment from the list.</div></div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/sidebarmenu.js"></script>
  <script src="assets/js/app.min.js"></script>
  <script src="assets/libs/simplebar/dist/simplebar.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
  function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  let equipmentHistoryModal = null;

  // ----- Live satellite map (Geotab) in the modal ----------------------
  let eqMap = null, eqMarker = null, eqPollTimer = null, eqTarget = null;

  function eqBuildMap() {
    if (typeof L === 'undefined') {
      $('#equipmentMapMeta').html('<span class="text-danger">Map library could not load (no internet or blocked). The location text above still updates.</span>');
      return false;
    }
    if (eqMap) { eqMap.invalidateSize(); return; }
    eqMap = L.map('equipmentMap', { zoomControl: true, attributionControl: true });
    // Esri World Imagery — free satellite tiles, no API key.
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 19,
      attribution: 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics'
    }).addTo(eqMap);
    // Roads/labels overlay on top of imagery for context.
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 19, opacity: 0.9
    }).addTo(eqMap);
    eqMap.setView([12.8797, 121.7740], 6); // Philippines fallback
  }

  function eqSetMarker(lat, lng, label) {
    if (lat == null || lng == null) return;
    const ll = [lat, lng];
    if (!eqMarker) { eqMarker = L.marker(ll).addTo(eqMap); }
    else { eqMarker.setLatLng(ll); }
    eqMarker.bindPopup(label);
    const z = Math.max(eqMap.getZoom(), 15);
    eqMap.setView(ll, z, { animate: true });
  }

  function eqPollPosition() {
    if (!eqTarget) return;
    $.getJSON('php/fetch/unit_live_position.php', { code: eqTarget }, function (res) {
      if (res.status !== 'success') { $('#equipmentMapMeta').text(res.message || 'Could not load position.'); return; }
      if (!res.linked) {
        $('#equipmentMapMeta').html('<span class="text-muted">No Geotab device linked to this unit.</span>');
        return;
      }
      if (!res.has_position) {
        $('#equipmentMapMeta').html('<span class="text-muted">Waiting for the first Geotab fix…</span>');
        return;
      }
      eqSetMarker(res.lat, res.lng,
        escapeHtml(res.code) + '<br>' + escapeHtml(res.location || '') +
        (res.speed != null ? '<br>' + Number(res.speed).toFixed(0) + ' km/h' : ''));
      $('#equipmentMapMeta').html(
        '<span class="eq-live-dot"></span>Live · ' + escapeHtml(res.location || 'On the move') +
        ' · updated ' + escapeHtml(formatDateTime(res.position_at)) +
        (res.communicating ? '' : ' · <span class="text-warning">device idle</span>'));
    }).fail(function () { $('#equipmentMapMeta').text('Could not reach the position feed.'); });
  }

  function eqStartMap(code, lat, lng) {
    eqTarget = code;
    $('#equipmentMapWrap').prop('hidden', false);
    $('#equipmentMapMeta').html('<span class="text-muted">Loading position…</span>');
    // Build the map after the modal is visible so tiles size correctly.
    setTimeout(function () {
      if (eqBuildMap() === false || !eqMap) { return; }
      eqMap.invalidateSize();
      if (lat != null && lng != null) { eqSetMarker(lat, lng, escapeHtml(code)); }
      eqPollPosition();
      clearInterval(eqPollTimer);
      eqPollTimer = setInterval(eqPollPosition, 15000);
    }, 250);
  }

  function eqStopMap() {
    clearInterval(eqPollTimer); eqPollTimer = null; eqTarget = null;
  }

  function renderItem(kind, item, filter) {
    if (filter && !String(item.code).toLowerCase().includes(filter)) return '';
    const cls = (item.status && item.status.toLowerCase() !== 'good') ? ' bad' : '';
    const tip = (item.updated_at ? '\nUpdated: ' + item.updated_at : '') + (item.status ? '\nStatus: ' + item.status : '');
    return '<button type="button" class="eq-chip ' + kind + cls + '"'
         + ' data-kind="' + escapeHtml(kind) + '"'
         + ' data-code="' + escapeHtml(item.code) + '"'
         + (item.lat != null ? ' data-lat="' + item.lat + '"' : '')
         + (item.lng != null ? ' data-lng="' + item.lng + '"' : '')
         + ' title="' + escapeHtml(tip.trim()) + '">'
         + escapeHtml(item.code) + '</button>';
  }

  function renderBucket(name, group, filter) {
    const trucks   = (group.truck   || []).map(function(i){ return renderItem('truck', i, filter); }).filter(Boolean).join('');
    const gensets  = (group.genset  || []).map(function(i){ return renderItem('genset', i, filter); }).filter(Boolean).join('');
    const trailers = (group.trailer || []).map(function(i){ return renderItem('trailer', i, filter); }).filter(Boolean).join('');
    if (filter && !trucks && !gensets && !trailers) return '';
    const counts = '<span class="text-muted small">'
      + (group.truck   ? group.truck.length   + ' trucks · '   : '')
      + (group.genset  ? group.genset.length  + ' gensets · '  : '')
      + (group.trailer ? group.trailer.length + ' trailers' : '')
      + '</span>';
    return '<div class="eq-bucket">'
      +   '<div class="d-flex align-items-center mb-2"><h6 class="mb-0">' + escapeHtml(name) + '</h6><span class="ms-auto">' + counts + '</span></div>'
      +   (trucks   ? '<div><span class="eq-section-label">Trucks</span>'   + trucks   + '</div>' : '')
      +   (gensets  ? '<div class="mt-2"><span class="eq-section-label">Gensets</span>'  + gensets  + '</div>' : '')
      +   (trailers ? '<div class="mt-2"><span class="eq-section-label">Trailers</span>' + trailers + '</div>' : '')
      + '</div>';
  }

  function renderSummary(res) {
    let truck = 0, genset = 0, trailer = 0, issues = 0;
    Object.keys(res.buckets || {}).forEach(function (k) {
      const group = res.buckets[k] || {};
      (group.truck || []).forEach(function (item) { truck++; if ((item.status || '').toLowerCase() !== 'good') issues++; });
      (group.genset || []).forEach(function (item) { genset++; if ((item.status || '').toLowerCase() !== 'good') issues++; });
      (group.trailer || []).forEach(function (item) { trailer++; if ((item.status || '').toLowerCase() !== 'good') issues++; });
    });
    $('#equipmentSummary').html(
      '<div class="col-md-3 col-sm-6"><div class="eq-summary-card"><div class="card-body d-flex align-items-start justify-content-between"><div><div class="eq-summary-kicker">Trucks</div><div class="eq-summary-count">' + truck + '</div><div class="eq-summary-note">Tracked across current locations</div></div><span class="eq-summary-badge truck">T</span></div></div></div>'
      + '<div class="col-md-3 col-sm-6"><div class="eq-summary-card"><div class="card-body d-flex align-items-start justify-content-between"><div><div class="eq-summary-kicker">Gensets</div><div class="eq-summary-count">' + genset + '</div><div class="eq-summary-note">Attached and standby support units</div></div><span class="eq-summary-badge genset">G</span></div></div></div>'
      + '<div class="col-md-3 col-sm-6"><div class="eq-summary-card"><div class="card-body d-flex align-items-start justify-content-between"><div><div class="eq-summary-kicker">Trailers</div><div class="eq-summary-count">' + trailer + '</div><div class="eq-summary-note">Road and yard trailer inventory</div></div><span class="eq-summary-badge trailer">R</span></div></div></div>'
      + '<div class="col-md-3 col-sm-6"><div class="eq-summary-card"><div class="card-body d-flex align-items-start justify-content-between"><div><div class="eq-summary-kicker">Attention Needed</div><div class="eq-summary-count">' + issues + '</div><div class="eq-summary-note">Units not marked as Good</div></div><span class="eq-summary-badge issue">!</span></div></div></div>'
    );
  }

  function formatDateTime(value) {
    if (!value || value === '-') return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function renderSnapshotCard(snapshot) {
    return '<div class="snapshot-card">'
      + '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">'
      +   '<div>'
      +     '<div class="text-muted small text-uppercase fw-bold">' + escapeHtml(snapshot.type) + '</div>'
      +     '<div class="fs-5 fw-bold">' + escapeHtml(snapshot.code) + '</div>'
      +   '</div>'
      +   '<span class="history-pill info">' + escapeHtml(snapshot.status || 'Unknown') + '</span>'
      + '</div>'
      + '<div class="mt-2"><strong>Current location:</strong> ' + escapeHtml(snapshot.location || 'Unknown') + '</div>'
      + '<div class="text-muted small mt-1">Last location update: ' + escapeHtml(formatDateTime(snapshot.updated_at)) + '</div>'
      + '</div>';
  }

  function renderHistory(events) {
    if (!events.length) {
      return '<div class="text-muted">No equipment activity found yet.</div>';
    }
    return events.map(function (ev) {
      return '<div class="history-row">'
        + '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">'
        +   '<div class="history-title">' + escapeHtml(ev.title || 'Activity') + '</div>'
        +   '<span class="history-pill ' + escapeHtml(ev.tone || 'info') + '">' + escapeHtml(ev.source || 'history') + '</span>'
        + '</div>'
        + '<div class="history-meta">' + escapeHtml(formatDateTime(ev.happened_at)) + '</div>'
        + (ev.detail ? '<div class="history-detail">' + escapeHtml(ev.detail) + '</div>' : '')
        + '</div>';
    }).join('');
  }

  function loadEquipmentHistory(kind, code) {
    $('#equipmentHistoryTitle').text(code + ' Activity');
    $('#equipmentSnapshot').html('');
    $('#equipmentHistoryBody').html('<div class="text-muted">Loading activity...</div>');
    equipmentHistoryModal.show();
    $.getJSON('php/fetch/equipment_activity.php', { type: kind, code: code }, function (res) {
      if (res.status !== 'success') {
        $('#equipmentHistoryBody').html('<div class="alert alert-danger">' + escapeHtml(res.message || 'Failed to load history.') + '</div>');
        return;
      }
      $('#equipmentSnapshot').html(renderSnapshotCard(res.snapshot || { type: kind, code: code }));
      $('#equipmentHistoryBody').html(renderHistory(res.events || []));
    }).fail(function () {
      $('#equipmentHistoryBody').html('<div class="alert alert-danger">Failed to load equipment history.</div>');
    });
  }

  function load() {
    const filter = ($('#filterText').val() || '').trim().toLowerCase();
    $.getJSON('php/fetch/equipment_locations.php', function (res) {
      if (res.status !== 'success') {
        $('#bucketsBox').html('<div class="alert alert-danger">' + escapeHtml(res.message) + '</div>');
        return;
      }
      renderSummary(res);
      const html = Object.keys(res.buckets).map(function(k){ return renderBucket(k, res.buckets[k], filter); }).join('');
      $('#bucketsBox').html(html || '<div class="text-muted">Nothing matches.</div>');
    });
  }

  $('#refreshBtn').on('click', load);
  $('#filterText').on('input', load);
  $('#bucketsBox').on('click', '.eq-chip', function () {
    const kind = $(this).data('kind');
    const code = $(this).data('code');
    const lat = $(this).data('lat');
    const lng = $(this).data('lng');
    loadEquipmentHistory(kind, code);
    // Trucks/gensets carry a Geotab position; trailers don't, so hide the map.
    if (kind === 'trailer') {
      $('#equipmentMapWrap').prop('hidden', true);
      eqStopMap();
    } else {
      eqStartMap(code, lat != null ? Number(lat) : null, lng != null ? Number(lng) : null);
    }
  });

  equipmentHistoryModal = new bootstrap.Modal(document.getElementById('equipmentHistoryModal'));
  const eqModalEl = document.getElementById('equipmentHistoryModal');
  eqModalEl.addEventListener('shown.bs.modal', function () { if (eqMap) eqMap.invalidateSize(); });
  eqModalEl.addEventListener('hidden.bs.modal', function () {
    eqStopMap();
    $('#equipmentMapWrap').prop('hidden', true);
  });
  load();
  setInterval(load, 30000);
  </script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
