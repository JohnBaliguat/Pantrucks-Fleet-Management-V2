<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Gastender') {
    header("Location: login.php?error=Unauthorized");
    exit();
}
$pageTitle = 'Fuel Reports';
include 'gas/_layout_top.php';
?>

<div class="row">
    <div class="col-md-12">
      <div class="my-3 p-3 bg-body rounded shadow-sm">
        <h4 class="border-bottom pb-2 mb-0">Generate Report</h4>
        <div class="text-body-secondary pt-3">
          <form method="POST" action="php/reports/fuel-report.php" target="_blank">
            <div class="row g-2">
              <div class="col-md-4">
                <label>From</label>
                <input type="date" class="form-control" name="from_date" required>
              </div>
              <div class="col-md-4">
                <label>To</label>
                <input type="date" class="form-control" name="to_date" required>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-success">Export Excel Report</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="my-3 p-3 bg-body rounded shadow-sm">
        <div class="row border-bottom"><div class="col-md-6"><h5 class="pb-2 mb-0">Transaction Data</h5></div></div>
        <div class="text-body-secondary pt-3">
          <table class="table table-hover nowrap" id="table-data" style="width:100%;">
            <thead class="table-light">
              <tr style="font-size:11px;">
                <th>Control No.</th>
                <th>Date/Time</th>
                <th>Unit</th>
                <th>Driver</th>
                <th>Odometer</th>
                <th>No of Liters</th>
              </tr>
            </thead>
            <tbody style="font-size:12px;"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<script>
  // Wait for jQuery + DataTables from gas/_layout_bottom.php
  window.addEventListener('load', function () {
    $('#table-data').DataTable({
      processing: true,
      serverSide: true,
      autoWidth: false,
      scrollX: false,
      responsive: { details: { type: 'inline' } },
      order: [[0, 'desc']],
      ajax: { url: 'table-fetch/fuel-msreport-table.php', type: 'POST' },
      columns: [
        { title: 'Control No.',  responsivePriority: 1 },
        { title: 'Date/Time',    responsivePriority: 3 },
        { title: 'Unit',         responsivePriority: 2 },
        { title: 'Driver',       responsivePriority: 4 },
        { title: 'Odometer',     responsivePriority: 6 },
        { title: 'No of Liters', responsivePriority: 5 }
      ],
      language: { emptyTable: 'No transactions in the selected range.' }
    });
  });
</script>

<?php include 'gas/_layout_bottom.php'; ?>
