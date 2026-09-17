<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Gastender') {
    header("Location: login.php?error=Unauthorized");
    exit();
}
include 'php/config/config.php';

$qty = 0;
if (pt_table_exists($conn, 'fuel_inventory')) {
    $res = $conn->query("SELECT COALESCE(SUM(fi_consumableltr), 0) AS fuel_qty FROM fuel_inventory");
    if ($res && $row = ($res)->fetch()) {
        $qty = number_format((float)$row['fuel_qty'], 2);
    }
}

$pageTitle = 'Fuel Inventory';
include 'gas/_layout_top.php';
?>

<div class="row">
    <div class="col-md-6"><h4 class="mb-3">Fuel Inventory</h4></div>
  </div>

  <div class="row">
    <div class="col-md-4">
      <div class="d-flex align-items-center p-3 my-3 text-dark bg-white rounded shadow-sm">
        <i class="ti ti-gas-station me-3" style="font-size:48px;"></i>
        <div class="lh-1">
          <h1 class="h1 mb-1 text-dark lh-1"><?php echo $qty; ?> L</h1>
          <small style="font-size:18px;">Available Fuel (Liters)</small>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="my-3 p-3 bg-body rounded shadow-sm">
        <div class="row border-bottom">
          <div class="col-md-6"><h5 class="pb-2 mb-0">Fuel Receiving</h5></div>
          <div class="col-md-6 mb-3 text-end">
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addmodal"><i class="ti ti-plus"></i> Add</button>
          </div>
        </div>
        <div class="text-body-secondary pt-3">
          <table class="table table-hover nowrap" id="table-data" style="width:100%;">
            <thead class="table-light">
              <tr style="font-size:11px;">
                <th>No.</th>
                <th>Date Delivered</th>
                <th>No. of Liters</th>
                <th>PO Number</th>
                <th>DR No.</th>
                <th>Plate No.</th>
                <th>Received By</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody style="font-size:12px;"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<!-- Add Fuel Modal -->
<div class="modal fade" id="addmodal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border border-0">
      <div class="modal-header border border-0"><h3>Save Fuel Record</h3></div>
      <div class="modal-body">
        <form id="addForm" action="php/operations/add_fuel_inventory.php" method="POST" enctype="multipart/form-data">
          <div class="mb-2"><label>Delivery Date*</label><input type="datetime-local" class="form-control" name="fi_date" required></div>
          <div class="mb-2"><label>No. of Liters*</label><input type="number" step="0.01" class="form-control" name="fi_noOfLiters" required></div>
          <div class="mb-2"><label>PO Number*</label><input type="text" class="form-control" name="fi_poNo" required></div>
          <div class="mb-2"><label>DR No.*</label><input type="text" class="form-control" name="fi_inVo" required></div>
          <div class="mb-2"><label>Plate No.*</label><input type="text" class="form-control" name="fi_plateNo" required></div>
          <div class="mb-2"><label>Received By*</label><input type="text" class="form-control" name="fi_receiveBy" required></div>
        </form>
      </div>
      <div class="modal-footer border border-0">
        <button class="btn btn-success" id="addFuel">Save</button>
        <button data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
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
      order: [[1, 'desc']],
      ajax: { url: 'table-fetch/fuel-inventory-table.php', type: 'POST' },
      columns: [
        { title: 'No.',            responsivePriority: 10 },
        { title: 'Date Delivered', responsivePriority: 2 },
        { title: 'No. of Liters',  responsivePriority: 1 },
        { title: 'PO Number',      responsivePriority: 3 },
        { title: 'DR No.',         responsivePriority: 5 },
        { title: 'Plate No.',      responsivePriority: 6 },
        { title: 'Received By',    responsivePriority: 4 },
        { title: 'Action', orderable: false, searchable: false, responsivePriority: 2 }
      ],
      language: { emptyTable: 'No fuel deliveries recorded yet.' }
    });

    $('#addFuel').on('click', function (e) {
      e.preventDefault();
      const form = document.getElementById('addForm');
      if (!form.checkValidity()) { form.reportValidity(); return; }
      fetch(form.action, { method: 'POST', body: new FormData(form) })
        .then(r => r.text().then(t => ({ ok: r.ok, text: t })))
        .then(({ ok, text }) => {
          if (!ok) return Swal.fire({ icon: 'error', title: 'Failed', text });
          Swal.fire({ icon: 'success', title: 'Saved', text, timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        });
    });
  });
</script>

<?php include 'gas/_layout_bottom.php'; ?>
