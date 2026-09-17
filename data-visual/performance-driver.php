<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== "Visual") {
  header("Location: visual-index.php?route=login");
  exit();
}
$driverId = (int)($_GET['driver_id'] ?? 0);
$fromDate = trim((string)($_GET['fromDate'] ?? ''));
$toDate   = trim((string)($_GET['toDate'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Driver Performance Breakdown</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css" />
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0"><a class="d-flex justify-content-center" href="#"><img src="assets/images/logos/pantrucks.png" alt="" width="122"></a></div>
      <div class="d-lg-flex align-items-center gap-2"><h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3></div>
    </div>
    <?php include 'sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include 'navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mb-3"><div class="card-body"><div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><h4 class="card-title mb-1">Driver Performance Breakdown</h4><p class="card-subtitle mb-0">Detailed scorecard, charts, and recent trip activity for one driver.</p></div><div class="d-flex gap-2"><button type="button" class="btn btn-danger" id="exportDriverPerformancePdf">Export PDF</button><a href="index.php?route=visual-performance" class="btn btn-outline-primary">Back to Performance</a></div></div></div></div>
          <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-semibold">Driver</div><div class="fs-5 fw-bold" id="bdDriverName">Loading…</div><div class="text-muted small" id="bdDriverMeta">—</div></div></div></div>
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-semibold">Recommendation</div><div class="fs-5 fw-bold" id="bdRecommendation">—</div><div class="text-muted small" id="bdViolationMeta">—</div></div></div></div>
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-semibold">Efficiency</div><div class="fs-5 fw-bold" id="bdEfficiency">0%</div><div class="text-muted small" id="bdTripMeta">—</div></div></div></div>
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-semibold">Presence Rate</div><div class="fs-5 fw-bold" id="bdPresentRate">0%</div><div class="text-muted small" id="bdAttendanceMeta">—</div></div></div></div>
          </div>
          <div class="row g-3">
            <div class="col-lg-8"><div class="card"><div class="card-body"><h5 class="card-title mb-1">Trip Trend</h5><p class="card-subtitle mb-3">Total vs completed trips across the selected period.</p><div id="trendChart" style="min-height:320px;"></div></div></div></div>
            <div class="col-lg-4"><div class="card"><div class="card-body"><h5 class="card-title mb-1">Recommendation Basis</h5><p class="card-subtitle mb-3">Attendance, completion, and active violations.</p><div id="statusChart" style="min-height:320px;"></div></div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-body"><h5 class="card-title mb-1">Trips by Segment</h5><p class="card-subtitle mb-3">Which hauling segments this driver worked on most.</p><div id="segmentChart" style="min-height:320px;"></div></div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-body"><h5 class="card-title mb-1">Attendance Breakdown</h5><p class="card-subtitle mb-3">Present, absent, and leave distribution.</p><div id="attendanceChart" style="min-height:320px;"></div></div></div></div>
          </div>
          <div class="card mt-3"><div class="card-body"><h5 class="card-title mb-1">Recent Trips</h5><p class="card-subtitle mb-3">Full trip history for the selected driver with server-side paging and search.</p><div class="table-responsive"><table class="table align-middle mb-0" id="recentTripsTable"><thead><tr><th>Trip ID</th><th>Truck</th><th>Customer</th><th>Segment</th><th>Status</th><th>Dispatched At</th></tr></thead><tbody></tbody></table></div></div></div>
          <div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>
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
  <script src="datatable/datatables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
    const driverId = <?php echo json_encode($driverId); ?>, fromDate = <?php echo json_encode($fromDate); ?>, toDate = <?php echo json_encode($toDate); ?>;
    let trendChart, segmentChart, attendanceChart, statusChart, recentTripsTable;
    function recommendationBadgeText(v){ return v || 'No Activity'; }
    function initCharts() {
      trendChart = new ApexCharts(document.querySelector("#trendChart"), { chart:{ type:'line', height:320, toolbar:{show:false}}, series:[], xaxis:{categories:[]}, stroke:{curve:'smooth', width:3}, colors:['#2563eb','#16a34a'], noData:{text:'No trip trend available'} }); trendChart.render();
      segmentChart = new ApexCharts(document.querySelector("#segmentChart"), { chart:{ type:'bar', height:320, toolbar:{show:false}}, series:[{ name:'Trips', data:[]}], xaxis:{categories:[]}, colors:['#0ea5e9'], noData:{text:'No segment data available'} }); segmentChart.render();
      attendanceChart = new ApexCharts(document.querySelector("#attendanceChart"), { chart:{ type:'donut', height:320}, series:[], labels:['Present','Absent','Leave'], colors:['#16a34a','#dc2626','#f59e0b'], noData:{text:'No attendance data available'} }); attendanceChart.render();
      statusChart = new ApexCharts(document.querySelector("#statusChart"), { chart:{ type:'radialBar', height:320 }, series:[0,0,0], labels:['Efficiency','Presence','Active Violations'], colors:['#2563eb','#16a34a','#dc2626'], plotOptions:{ radialBar:{ dataLabels:{ total:{ show:true, label:'Recommendation', formatter:function(){ return document.getElementById('bdRecommendation').textContent || '—'; }}}}}}); statusChart.render();
    }
    function renderHeader(driver){
      $('#bdDriverName').text(driver.name || 'Unknown Driver');
      $('#bdDriverMeta').text('ID: ' + (driver.id_number || '—') + ' • Segment: ' + (driver.segment || '—') + ' • Status: ' + (driver.driver_status || '—'));
      $('#bdRecommendation').text(recommendationBadgeText(driver.recommendation));
      $('#bdViolationMeta').text((driver.active_violations || 0) + ' active violation(s)' + (driver.last_violation_date ? ' • Last: ' + driver.last_violation_date : ''));
      $('#bdEfficiency').text((driver.efficiency_percent || 0) + '%');
      $('#bdTripMeta').text((driver.done_trips || 0) + ' done of ' + (driver.total_trips || 0) + ' total • ' + (driver.pending_trips || 0) + ' pending');
      $('#bdPresentRate').text((driver.present_percent || 0) + '%');
      $('#bdAttendanceMeta').text((driver.days_present || 0) + ' present • ' + (driver.days_absent || 0) + ' absent • ' + (driver.days_leave || 0) + ' leave');
    }
    function initRecentTripsTable() {
      recentTripsTable = $('#recentTripsTable').DataTable({ processing:true, serverSide:true, searching:true, responsive:true, autoWidth:false, order:[[5,'desc']], ajax:{ url:'table-fetch/performance-driver-trips.php', type:'POST', data:function(d){ d.driver_id = driverId; d.fromDate = fromDate; d.toDate = toDate; } } });
    }
    function loadBreakdown(){
      $.getJSON('php/fetch/performance_driver_breakdown.php', { driver_id:driverId, fromDate:fromDate, toDate:toDate }).done(function(res){
        if(res.status !== 'success') return;
        renderHeader(res.driver || {});
        trendChart.updateOptions({ xaxis:{ categories:(res.trend || []).map(r => r.date) } });
        trendChart.updateSeries([{ name:'Total Trips', data:(res.trend || []).map(r => r.total) }, { name:'Done Trips', data:(res.trend || []).map(r => r.done) }]);
        segmentChart.updateOptions({ xaxis:{ categories:(res.segments || []).map(r => r.segment) } });
        segmentChart.updateSeries([{ name:'Trips', data:(res.segments || []).map(r => r.count) }]);
        attendanceChart.updateSeries([(res.attendance && res.attendance.present) || 0, (res.attendance && res.attendance.absent) || 0, (res.attendance && res.attendance.leave) || 0]);
        statusChart.updateOptions({ plotOptions:{ radialBar:{ dataLabels:{ total:{ show:true, label:'Recommendation', formatter:function(){ return recommendationBadgeText((res.driver || {}).recommendation); }}}}}});
        statusChart.updateSeries([Math.min(parseFloat((res.driver || {}).efficiency_percent || 0),100), Math.min(parseFloat((res.driver || {}).present_percent || 0),100), Math.min((((res.driver || {}).active_violations || 0) * 25),100)]);
      });
    }
    $(function(){
      initCharts(); initRecentTripsTable(); loadBreakdown();
      $('#exportDriverPerformancePdf').on('click', function(){
        var form = $('<form>', { method:'POST', action:'php/reports/performance-driver-report.php', target:'_blank' });
        form.append($('<input>', { type:'hidden', name:'driver_id', value:driverId }));
        form.append($('<input>', { type:'hidden', name:'fromDate', value:fromDate || '' }));
        form.append($('<input>', { type:'hidden', name:'toDate', value:toDate || '' }));
        $('body').append(form); form.trigger('submit'); form.remove();
      });
    });
  </script>
</body>
</html>
