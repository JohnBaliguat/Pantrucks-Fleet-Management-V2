<?php
// Executive overview — mobile-first, responsive dashboard grid. Search a truck
// to see its current trip, plus operations prediction, completed-trip trend,
// customer/driver ranking, and truck utilization. Read-only.
session_start();
$allowed = ['Admin', 'Dispatch Admin', 'Executive'];
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $allowed, true)) {
    header('Location: login.php'); exit;
}
include __DIR__ . '/../php/config/config.php';

// Role-appropriate "Dashboard" target (admin dashboard is Admin-only, so a
// non-Admin viewer clicking it was bounced to login). Executive has no separate
// dashboard, so the link is hidden for them.
$dashHref = '';
switch ($_SESSION['user_type']) {
    case 'Admin':          $dashHref = 'dashboard'; break;
    case 'Dispatch Admin': $dashHref = 'dispatch-admin-dashboard'; break;
}

$truckQuery = trim($_GET['truck'] ?? '');
$current = null; $trucks = [];
$kpiCompletedMonth = 0; $kpiActive = 0; $kpiProjected = 0;
$trendLabels = []; $trendData = []; $customers = []; $drivers = [];
$trucksRank = []; $activeTrucks = 0; $totalTrucks = 0; $utilPct = 0;

try {
    foreach ($conn->query("SELECT DISTINCT d_truck FROM dispatch WHERE d_truck <> '' ORDER BY d_truck") as $r) {
        $trucks[] = $r['d_truck'];
    }

    if ($truckQuery !== '') {
        $st = $conn->prepare(
            "SELECT d.d_id, d.d_truck, d.d_drivername, d.costumer, d.booking_no, d.workflow_stage,
                    d.d_datetime, d.trip_completed_at,
                    t.trip_from, t.trip_to, t.trip_container, t.trip_containerstat,
                    t.trip_status, t.trip_haulingsegment
             FROM dispatch d LEFT JOIN trips t ON t.d_id = d.d_id
             WHERE LOWER(TRIM(d.d_truck)) = LOWER(TRIM(?))
             ORDER BY (t.trip_status = 'Active') DESC NULLS LAST, d.d_id DESC LIMIT 1"
        );
        $st->execute([$truckQuery]);
        $current = $st->fetch();
    }

    $kpiCompletedMonth = (int)$conn->query(
        "SELECT COUNT(*) FROM dispatch WHERE trip_completed_at >= date_trunc('month', NOW())"
    )->fetchColumn();
    $kpiActive = (int)$conn->query("SELECT COUNT(*) FROM trips WHERE trip_status = 'Active'")->fetchColumn();
    $dayNow = (int)date('j'); $daysIn = (int)date('t');
    $kpiProjected = $dayNow > 0 ? (int)round($kpiCompletedMonth / $dayNow * $daysIn) : $kpiCompletedMonth;

    $rows = [];
    foreach ($conn->query(
        "SELECT DATE(trip_completed_at) d, COUNT(*) c FROM dispatch
         WHERE trip_completed_at >= (CURRENT_DATE - INTERVAL '13 days') GROUP BY 1") as $r) {
        $rows[$r['d']] = (int)$r['c'];
    }
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i day"));
        $trendLabels[] = date('M j', strtotime($day));
        $trendData[]   = $rows[$day] ?? 0;
    }

    $customers = $conn->query(
        "SELECT costumer, COUNT(*) c FROM dispatch
         WHERE trip_completed_at >= NOW() - INTERVAL '30 days' AND TRIM(costumer) <> ''
         GROUP BY costumer ORDER BY c DESC LIMIT 10")->fetchAll();

    $drivers = $conn->query(
        "SELECT d_drivername, COUNT(*) c FROM dispatch
         WHERE trip_completed_at >= NOW() - INTERVAL '30 days' AND TRIM(d_drivername) <> ''
         GROUP BY d_drivername ORDER BY c DESC LIMIT 10")->fetchAll();

    $trucksRank = $conn->query(
        "SELECT d_truck, COUNT(*) c FROM dispatch
         WHERE trip_completed_at >= NOW() - INTERVAL '30 days' AND TRIM(d_truck) <> ''
         GROUP BY d_truck ORDER BY c DESC LIMIT 10")->fetchAll();
    $activeTrucks = (int)$conn->query(
        "SELECT COUNT(DISTINCT d_truck) FROM dispatch
         WHERE trip_completed_at >= NOW() - INTERVAL '30 days' AND TRIM(d_truck) <> ''")->fetchColumn();
    $totalTrucks = (int)$conn->query("SELECT COUNT(*) FROM units WHERE unit_name NOT LIKE 'GS%'")->fetchColumn();
    $utilPct = $totalTrucks > 0 ? (int)round($activeTrucks / $totalTrucks * 100) : 0;
} catch (Throwable $e) { /* degrade quietly */ }

$maxCust = 0;  foreach ($customers as $c)  { $maxCust  = max($maxCust,  (int)$c['c']); }
$maxDrv = 0;   foreach ($drivers as $d)    { $maxDrv   = max($maxDrv,   (int)$d['c']); }
$maxTruck = 0; foreach ($trucksRank as $t) { $maxTruck = max($maxTruck, (int)$t['c']); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Executive Overview</title>
<link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  :root{
    --bg:#f4f6f9; --card:#fff; --border:#e5e9f0; --text:#141a22; --muted:#6b7686;
    --accent:#1f5eff; --accent-weak:#e9f0ff; --ok:#15803d; --warn:#b45309;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  .top{position:sticky;top:0;z-index:10;background:#0d1b2a;color:#fff;padding:14px 16px;display:flex;align-items:center;gap:10px}
  .top b{font-size:16px;font-weight:800;letter-spacing:-.01em}
  .top .sp{flex:1}
  .top a{color:#cfe0ff;text-decoration:none;font-size:13px;font-weight:600}
  .wrap{max-width:1440px;margin:0 auto;padding:18px 24px}
  .search{display:flex;gap:8px;margin-bottom:16px;max-width:560px}
  .search input{flex:1;border:1px solid var(--border);border-radius:10px;padding:12px 14px;font:inherit;font-size:15px;background:#fff}
  .search button{border:none;background:var(--accent);color:#fff;border-radius:10px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer}
  .btn-map-exec{border:none;background:var(--accent-weak);color:var(--accent);border-radius:10px;padding:9px 14px;font:inherit;font-weight:700;cursor:pointer;margin-top:10px;display:inline-flex;align-items:center;gap:6px}
  .exmap-overlay{position:fixed;inset:0;background:rgba(13,27,42,.55);z-index:2000;display:none;align-items:center;justify-content:center;padding:16px}
  .exmap-overlay.open{display:flex}
  .exmap-box{background:#fff;border-radius:16px;width:min(920px,96vw);box-shadow:0 24px 60px rgba(13,27,42,.35);overflow:hidden}
  .exmap-head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border)}
  .exmap-head h3{margin:0;font-size:15px}
  .exmap-close{border:0;background:transparent;font-size:22px;line-height:1;color:var(--muted);cursor:pointer}
  #exMap{height:min(62vh,440px);width:100%}
  .exmap-meta{padding:10px 16px;font-size:13px;color:var(--muted)}
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px}
  @media(max-width:640px){.grid{grid-template-columns:1fr}}
  .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px;box-shadow:0 1px 2px rgba(16,24,40,.05)}
  .kpi .lbl{font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)}
  .kpi .val{font-size:30px;font-weight:800;line-height:1.1;margin-top:6px}
  .kpi .sub{font-size:12px;color:var(--muted);margin-top:2px}
  .kpi.pred{border-left:4px solid var(--accent)}
  .sec-title{font-size:13px;font-weight:800;margin:0 0 12px}
  .trip .row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid #eef1f5;font-size:14px}
  .trip .row:first-of-type{border-top:none}
  .trip .k{color:var(--muted);font-weight:600}
  .trip .v{font-weight:700;text-align:right}
  .pill{display:inline-block;font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px}
  .pill.active{background:#e8effe;color:var(--accent)} .pill.done{background:#e6f4ea;color:var(--ok)}
  .empty{color:var(--muted);font-size:14px;padding:8px 0}
  .cust{display:flex;align-items:center;gap:10px;padding:7px 0}
  .cust .rk{width:20px;text-align:center;font-weight:800;color:var(--muted)}
  .cust .nm{flex:1;min-width:0}
  .cust .nm .n{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .cust .bar{height:8px;border-radius:999px;background:var(--accent-weak);margin-top:4px;overflow:hidden}
  .cust .bar span{display:block;height:100%;background:var(--accent);border-radius:999px}
  .cust .c{font-weight:800;font-size:13px;font-variant-numeric:tabular-nums}
  .mb{margin-bottom:16px}
  .ranks{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-start}
  .ranks > .card{flex:1 1 300px;min-width:0}
  .util-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
  .util-meter{min-width:180px;flex:1;max-width:280px}
  .util-meter .um-num{font-size:26px;font-weight:800;line-height:1}
  .util-meter .um-sub{font-size:11px;color:var(--muted);margin:2px 0 6px}
  .util-meter .um-bar{height:10px;border-radius:999px;background:var(--accent-weak);overflow:hidden}
  .util-meter .um-bar span{display:block;height:100%;background:var(--accent);border-radius:999px}
</style>
</head>
<body>
  <div class="top">
    <b>Executive Overview</b><span class="sp"></span>
    <?php if ($dashHref !== ''): ?><a href="<?= h($dashHref) ?>">Dashboard</a>&nbsp;·&nbsp;<?php endif; ?><a href="logout">Logout</a>
  </div>
  <div class="wrap">

    <form class="search" method="get" action="executive">
      <input name="truck" list="trucklist" value="<?= h($truckQuery) ?>" placeholder="Search a truck (e.g. PM840)…" autocomplete="off">
      <datalist id="trucklist"><?php foreach ($trucks as $t): ?><option value="<?= h($t) ?>"></option><?php endforeach; ?></datalist>
      <button type="submit">Search</button>
    </form>

    <?php if ($truckQuery !== ''): ?>
      <div class="card trip mb">
        <div class="sec-title">Truck <?= h($truckQuery) ?> — current trip</div>
        <?php if ($current): $active = ($current['trip_status'] ?? '') === 'Active'; ?>
          <div class="row"><span class="k">Status</span><span class="v"><span class="pill <?= $active?'active':'done' ?>"><?= $active?'On trip':'Completed' ?></span></span></div>
          <div class="row"><span class="k">Driver</span><span class="v"><?= h($current['d_drivername'] ?: '—') ?></span></div>
          <div class="row"><span class="k">Customer</span><span class="v"><?= h($current['costumer'] ?: '—') ?></span></div>
          <div class="row"><span class="k">Booking</span><span class="v"><?= h($current['booking_no'] ?: '—') ?></span></div>
          <div class="row"><span class="k">Route</span><span class="v"><?= h(($current['trip_from'] ?: '—').' → '.($current['trip_to'] ?: '—')) ?></span></div>
          <div class="row"><span class="k">Container</span><span class="v"><?= h($current['trip_container'] ?: '—') ?> <?= h($current['trip_containerstat']?('· '.$current['trip_containerstat']):'') ?></span></div>
          <div class="row"><span class="k">Segment</span><span class="v"><?= h($current['trip_haulingsegment'] ?: '—') ?></span></div>
          <div class="row"><span class="k">Stage</span><span class="v"><?= h($current['workflow_stage'] ?: '—') ?></span></div>
          <div class="row"><span class="k">Dispatched</span><span class="v"><?= h($current['d_datetime']) ?></span></div>
          <button class="btn-map-exec" id="exViewMap"
                  data-did="<?= (int)$current['d_id'] ?>"
                  data-truck="<?= h($current['d_truck']) ?>"
                  data-container="<?= h($current['trip_container'] ?: '') ?>">🛰️ View on map</button>
        <?php else: ?>
          <div class="empty">No trips found for this truck.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="grid">
      <div class="card kpi"><div class="lbl">Completed — this month</div><div class="val"><?= number_format($kpiCompletedMonth) ?></div><div class="sub">trips delivered</div></div>
      <div class="card kpi pred"><div class="lbl">Projected — month end</div><div class="val"><?= number_format($kpiProjected) ?></div><div class="sub">estimate from current pace</div></div>
      <div class="card kpi"><div class="lbl">Active now</div><div class="val"><?= number_format($kpiActive) ?></div><div class="sub">trips on the road</div></div>
    </div>

    <div class="card mb">
      <div class="sec-title">Completed-trip trend — last 14 days</div>
      <div id="trendChart"></div>
    </div>

    <div class="ranks mb">
      <div class="card">
        <div class="sec-title">Top customers — last 30 days (completed trips)</div>
        <?php if (!$customers): ?>
          <div class="empty">No completed trips in the last 30 days.</div>
        <?php else: foreach ($customers as $i => $c): $pct = $maxCust ? round((int)$c['c']/$maxCust*100) : 0; ?>
          <div class="cust">
            <div class="rk"><?= $i+1 ?></div>
            <div class="nm">
              <div class="n"><?= h($c['costumer']) ?></div>
              <div class="bar"><span style="width:<?= $pct ?>%"></span></div>
            </div>
            <div class="c"><?= number_format((int)$c['c']) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="card">
        <div class="sec-title">Top drivers — last 30 days (completed trips)</div>
        <?php if (!$drivers): ?>
          <div class="empty">No completed trips in the last 30 days.</div>
        <?php else: foreach ($drivers as $i => $d): $pct = $maxDrv ? round((int)$d['c']/$maxDrv*100) : 0;
              $rkc = $i===0?'#e0a419':($i===1?'#9aa4b2':($i===2?'#c2793f':'var(--muted)')); ?>
          <div class="cust">
            <div class="rk" style="color:<?= $rkc ?>"><?= $i+1 ?></div>
            <div class="nm">
              <div class="n"><?= h($d['d_drivername']) ?></div>
              <div class="bar"><span style="width:<?= $pct ?>%;background:var(--ok)"></span></div>
            </div>
            <div class="c"><?= number_format((int)$d['c']) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="card">
        <div class="util-head">
          <div class="sec-title" style="margin:0">Truck utilization — last 30 days</div>
          <div class="util-meter" title="<?= $activeTrucks ?> of <?= $totalTrucks ?> trucks ran">
            <div class="um-num"><?= $utilPct ?>%</div>
            <div class="um-sub"><?= number_format($activeTrucks) ?> of <?= number_format($totalTrucks) ?> trucks ran</div>
            <div class="um-bar"><span style="width:<?= max(0,min(100,$utilPct)) ?>%"></span></div>
          </div>
        </div>
        <div class="sec-title" style="margin:14px 0 8px;font-size:12px;color:var(--muted)">Most-utilized trucks (completed trips)</div>
        <?php if (!$trucksRank): ?>
          <div class="empty">No completed trips in the last 30 days.</div>
        <?php else: foreach ($trucksRank as $i => $t): $pct = $maxTruck ? round((int)$t['c']/$maxTruck*100) : 0; ?>
          <div class="cust">
            <div class="rk"><?= $i+1 ?></div>
            <div class="nm">
              <div class="n"><?= h($t['d_truck']) ?></div>
              <div class="bar"><span style="width:<?= $pct ?>%;background:var(--warn)"></span></div>
            </div>
            <div class="c"><?= number_format((int)$t['c']) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div>

  <!-- Live satellite map overlay -->
  <div class="exmap-overlay" id="exMapOverlay">
    <div class="exmap-box">
      <div class="exmap-head">
        <h3 id="exMapTitle">Live Location</h3>
        <button type="button" class="exmap-close" id="exMapClose" aria-label="Close">&times;</button>
      </div>
      <div id="exMap"></div>
      <div class="exmap-meta" id="exMapRoute"></div>
      <div class="exmap-meta" id="exMapLive" style="padding-top:0"></div>
    </div>
  </div>

  <script src="assets/libs/apexcharts/dist/apexcharts.min.js"></script>
  <script>
    var labels = <?= json_encode($trendLabels) ?>, data = <?= json_encode($trendData) ?>;
    if (window.ApexCharts) {
      new ApexCharts(document.querySelector('#trendChart'), {
        chart: { type: 'area', height: 240, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
        series: [{ name: 'Completed', data: data }],
        xaxis: { categories: labels, labels: { rotate: -45, style: { fontSize: '11px' } } },
        colors: ['#1f5eff'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: '#eef1f5' }
      }).render();
    }

    // ----- Live satellite map (Leaflet + Esri) for the searched truck -----
    (function () {
      var exMap = null, exMarker = null, exRoute = null, exPoll = null, exDid = null;
      function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

      function build() {
        if (typeof L === 'undefined') { document.getElementById('exMapLive').innerHTML = '<span style="color:#b91c1c">Map could not load.</span>'; return false; }
        if (exMap) { exMap.invalidateSize(); return true; }
        exMap = L.map('exMap', { zoomControl: true });
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' }).addTo(exMap);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, opacity: 0.9 }).addTo(exMap);
        exMap.setView([12.8797, 121.7740], 6);
        return true;
      }
      function clearRoute() { if (exRoute && exMap) { exMap.removeLayer(exRoute); exRoute = null; } }
      function clearMarker() { if (exMarker && exMap) { exMap.removeLayer(exMarker); exMarker = null; } }

      function setMarker(lat, lng, label) {
        if (lat == null || lng == null) return;
        var ll = [lat, lng];
        if (!exMarker) { exMarker = L.marker(ll).addTo(exMap); } else { exMarker.setLatLng(ll); }
        exMarker.bindPopup(label);
        if (!exRoute) { exMap.setView(ll, Math.max(exMap.getZoom(), 15)); }
      }

      function loadRoute(did) {
        fetch('php/fetch/dispatch_route.php?d_id=' + did).then(r => r.json()).then(res => {
          if (res.status !== 'success' || !exMap || did !== exDid) return;
          clearRoute();
          var layer = L.layerGroup().addTo(exMap); var b = [];
          if (res.route && res.route.length > 1) { L.polyline(res.route, { color: '#1f5eff', weight: 5, opacity: 0.85 }).addTo(layer); res.route.forEach(p => b.push(p)); }
          if (res.origin) { L.circleMarker([res.origin.lat, res.origin.lng], { radius: 7, color: '#059669', fillColor: '#10b981', fillOpacity: 1 }).bindPopup('Origin: ' + esc(res.origin.name)).addTo(layer); b.push([res.origin.lat, res.origin.lng]); }
          if (res.destination) { L.circleMarker([res.destination.lat, res.destination.lng], { radius: 8, color: '#b91c1c', fillColor: '#ef4444', fillOpacity: 1 }).bindPopup((res.arrived ? '✓ Arrived · ' : 'Destination: ') + esc(res.destination.name)).addTo(layer); b.push([res.destination.lat, res.destination.lng]); }
          exRoute = layer;
          if (b.length > 1) { try { exMap.fitBounds(b, { padding: [30, 30] }); } catch (e) {} }
          document.getElementById('exMapRoute').innerHTML = res.destination
            ? (res.arrived ? '<b style="color:#15803d">✓ Arrived at ' + esc(res.destination.name) + '</b>' : 'En route to <b>' + esc(res.destination.name) + '</b>')
            : '<span>No mapped origin/destination for this trip.</span>';
        }).catch(() => {});
      }

      function pollLive(did) {
        fetch('php/fetch/dispatch_live_position.php?d_id=' + did).then(r => r.json()).then(res => {
          if (res.status !== 'success' || did !== exDid) return;
          if (!res.has_position) { clearMarker(); document.getElementById('exMapLive').innerHTML = '<span>No live position yet for this trip.</span>'; return; }
          var src = res.pos_source === 'geotab' ? 'Live (Geotab)' : 'Driver app';
          setMarker(res.lat, res.lng, esc(res.truck || '') + '<br>' + esc(res.location || ''));
          document.getElementById('exMapLive').innerHTML = '<b>' + src + '</b> · ' + esc(res.location || 'On the move') + ' · updated ' + esc(res.position_at || '');
        }).catch(() => {});
      }

      function open(did, title) {
        exDid = did;
        document.getElementById('exMapTitle').textContent = title || 'Live Location';
        document.getElementById('exMapRoute').innerHTML = '';
        document.getElementById('exMapLive').innerHTML = '<span>Loading position…</span>';
        document.getElementById('exMapOverlay').classList.add('open');
        setTimeout(function () {
          if (!build()) return;
          exMap.invalidateSize(); clearRoute(); clearMarker();
          loadRoute(did); pollLive(did);
          clearInterval(exPoll); exPoll = setInterval(function () { pollLive(exDid); }, 15000);
        }, 200);
      }
      function close() { document.getElementById('exMapOverlay').classList.remove('open'); clearInterval(exPoll); exPoll = null; exDid = null; }

      var btn = document.getElementById('exViewMap');
      if (btn) { btn.addEventListener('click', function () {
        var t = this.getAttribute('data-truck') || '';
        var c = this.getAttribute('data-container') || '';
        open(parseInt(this.getAttribute('data-did'), 10), t + (c ? (' · ' + c) : ''));
      }); }
      document.getElementById('exMapClose').addEventListener('click', close);
      document.getElementById('exMapOverlay').addEventListener('click', function (e) { if (e.target.id === 'exMapOverlay') close(); });
    })();
  </script>
</body>
</html>
