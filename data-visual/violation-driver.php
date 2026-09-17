<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== "Visual") {
  header("Location: visual-index.php?route=login");
  exit();
}
$driverId = (int)($_GET['driver_id'] ?? 0);
$fromDate = trim((string)($_GET['fromDate'] ?? ''));
$toDate = trim((string)($_GET['toDate'] ?? ''));
$violation = trim((string)($_GET['violation'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Driver Violation Breakdown</title>
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
          <div class="card mb-3"><div class="card-body"><div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><h4 class="card-title mb-1">Driver Violation Breakdown</h4><p class="card-subtitle mb-0">Violation history, trend, and status summary for one driver.</p></div><div class="d-flex gap-2"><button type="button" class="btn btn-danger" id="exportDriverViolationPdf">Export PDF</button><a href="index.php?route=visual-violationReport" class="btn btn-outline-primary">Back to Violation Report</a></div></div></div></div>
          <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-semibold">Driver</div><div class="fs-5 fw-bold" id="vdDriverName">Loading...</div><div class="text-muted small" id="vdDriverMeta">-</div></div></div></div>
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-semibold">Active Violations</div><div class="fs-5 fw-bold text-danger" id="vdActiveCount">0</div><div class="text-muted small" id="vdLastViolation">-</div></div></div></div>
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-semibold">Resolved Violations</div><div class="fs-5 fw-bold text-success" id="vdResolvedCount">0</div><div class="text-muted small" id="vdStatusMeta">-</div></div></div></div>
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-semibold">Most Common Type</div><div class="fs-5 fw-bold" id="vdTopType">-</div><div class="text-muted small" id="vdFilterMeta">All types</div></div></div></div>
          </div>
          <div class="row g-3">
            <div class="col-lg-8"><div class="card"><div class="card-body"><h5 class="card-title mb-1">Violation Trend</h5><p class="card-subtitle mb-3">Recorded violations over the selected period.</p><div id="violationTrendChart" style="min-height:320px;"></div></div></div></div>
            <div class="col-lg-4"><div class="card"><div class="card-body"><h5 class="card-title mb-1">Status Split</h5><p class="card-subtitle mb-3">Active versus resolved records.</p><div id="violationStatusChart" style="min-height:320px;"></div></div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-body"><h5 class="card-title mb-1">Violation Types</h5><p class="card-subtitle mb-3">Which issues are most frequent for this driver.</p><div id="violationTypeChart" style="min-height:320px;"></div></div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-body"><h5 class="card-title mb-1">Resolution Mix</h5><p class="card-subtitle mb-3">Open risk versus cleared history.</p><div id="violationResolutionChart" style="min-height:320px;"></div></div></div></div>
          </div>
          <div class="card mt-3"><div class="card-body"><h5 class="card-title mb-1">Violation Records</h5><p class="card-subtitle mb-3">Paged, searchable violation history for the selected driver.</p><div class="table-responsive"><table class="table align-middle mb-0" id="violationRecordsTable"><thead><tr><th>Violation Type</th><th>Description</th><th>Status</th><th>Date Recorded</th><th>Resolved Date</th><th>Recorded By</th></tr></thead><tbody></tbody></table></div></div></div>
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
    const driverId = <?php echo json_encode($driverId); ?>, fromDate = <?php echo json_encode($fromDate); ?>, toDate = <?php echo json_encode($toDate); ?>, violationType = <?php echo json_encode($violation); ?>;
    let violationTrendChart, violationStatusChart, violationTypeChart, violationResolutionChart, violationRecordsTable;
    function initCharts(){
      violationTrendChart = new ApexCharts(document.querySelector("#violationTrendChart"), { chart:{ type:'bar', height:320, toolbar:{show:false}}, series:[{ name:'Violations', data:[]}], xaxis:{categories:[]}, colors:['#f59e0b'], noData:{text:'No violation trend available'} }); violationTrendChart.render();
      violationStatusChart = new ApexCharts(document.querySelector("#violationStatusChart"), { chart:{ type:'donut', height:320 }, series:[], labels:['Active','Done'], colors:['#dc2626','#16a34a'], noData:{text:'No status data available'} }); violationStatusChart.render();
      violationTypeChart = new ApexCharts(document.querySelector("#violationTypeChart"), { chart:{ type:'bar', height:320, toolbar:{show:false}}, series:[{ name:'Count', data:[]}], xaxis:{categories:[]}, colors:['#2563eb'], noData:{text:'No violation type data available'} }); violationTypeChart.render();
      violationResolutionChart = new ApexCharts(document.querySelector("#violationResolutionChart"), { chart:{ type:'radialBar', height:320 }, series:[0,0], labels:['Active %','Resolved %'], colors:['#dc2626','#16a34a'], noData:{text:'No resolution data available'} }); violationResolutionChart.render();
    }
    function initViolationTable(){
      violationRecordsTable = $('#violationRecordsTable').DataTable({ processing:true, serverSide:true, responsive:true, autoWidth:false, order:[[3,'desc']], ajax:{ url:'table-fetch/violation-driver-records.php', type:'POST', data:function(d){ d.driver_id = driverId; d.fromDate = fromDate; d.toDate = toDate; d.violation = violationType; } } });
    }
    function renderHeader(driver){
      $('#vdDriverName').text(driver.name || 'Unknown Driver');
      $('#vdDriverMeta').text('ID: ' + (driver.id_number || '-') + ' • Unit: ' + (driver.unit || '-') + ' • Segment: ' + (driver.segment || '-'));
      $('#vdActiveCount').text(driver.active_violations || 0);
      $('#vdLastViolation').text(driver.last_violation_date ? ('Last violation: ' + driver.last_violation_date) : 'No recorded violations');
      $('#vdResolvedCount').text(driver.resolved_violations || 0);
      $('#vdStatusMeta').text((driver.total_violations || 0) + ' total records');
      $('#vdTopType').text(driver.top_violation_type || '-');
      $('#vdFilterMeta').text(violationType ? ('Filtered to: ' + violationType) : 'All types');
    }
    function loadBreakdown(){
      $.getJSON('php/fetch/violation_driver_breakdown.php', { driver_id:driverId, fromDate:fromDate, toDate:toDate, violation:violationType }).done(function(res){
        if(res.status !== 'success') return;
        renderHeader(res.driver || {});
        violationTrendChart.updateOptions({ xaxis:{ categories:(res.trend || []).map(r => r.date) } });
        violationTrendChart.updateSeries([{ name:'Violations', data:(res.trend || []).map(r => r.count) }]);
        violationStatusChart.updateSeries([(res.status_split && res.status_split.active) || 0, (res.status_split && res.status_split.done) || 0]);
        violationTypeChart.updateOptions({ xaxis:{ categories:(res.types || []).map(r => r.type) } });
        violationTypeChart.updateSeries([{ name:'Count', data:(res.types || []).map(r => r.count) }]);
        violationResolutionChart.updateSeries([Math.min(parseFloat((res.percentages && res.percentages.active_percent) || 0),100), Math.min(parseFloat((res.percentages && res.percentages.resolved_percent) || 0),100)]);
      });
    }
    $(function(){
      initCharts(); initViolationTable(); loadBreakdown();
      $('#exportDriverViolationPdf').on('click', function(){
        var form = $('<form>', { method:'POST', action:'php/reports/violation-driver-report.php', target:'_blank' });
        form.append($('<input>', { type:'hidden', name:'driver_id', value:driverId }));
        form.append($('<input>', { type:'hidden', name:'fromDate', value:fromDate || '' }));
        form.append($('<input>', { type:'hidden', name:'toDate', value:toDate || '' }));
        form.append($('<input>', { type:'hidden', name:'violation', value:violationType || '' }));
        $('body').append(form); form.trigger('submit'); form.remove();
      });
    });
  </script>
</body>
</html>
