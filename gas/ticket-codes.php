<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Gastender') {
    header("Location: login.php?error=Unauthorized");
    exit();
}
$pageTitle = 'Ticket Codes';
include 'gas/_layout_top.php';
?>

<div class="row">
    <div class="col-md-6"><h4 class="mb-2">Trip Ticket Codes</h4>
      <p class="text-muted mb-0">Generate a 6-character code for each driver/unit trip plan. The Gastender enters this code to validate before refueling.</p>
    </div>
    <div class="col-md-6 text-end">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCodeModal"><i class="ti ti-plus"></i> New Ticket Code</button>
    </div>
  </div>

  <div class="my-3 p-3 bg-body rounded shadow-sm">
    <table class="table table-hover nowrap" id="codes-table" style="width:100%;">
      <thead class="table-light">
        <tr style="font-size:12px;">
          <th>Code</th><th>Driver ID</th><th>Unit</th><th>Date Issued</th><th>Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody style="font-size:12px;"></tbody>
    </table>
  </div>

<!-- New Ticket Code Modal -->
<div class="modal fade" id="newCodeModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Generate New Ticket Code</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-md-4"><label>Driver ID Number</label><input type="number" class="form-control" id="driverIdInput" placeholder="e.g. 2147467"></div>
          <div class="col-md-4"><label>Unit</label><input type="text" class="form-control" id="unitInput" placeholder="e.g. CT106"></div>
        </div>
        <hr>
        <h6>Trips</h6>
        <button type="button" class="btn btn-sm btn-secondary mb-2" id="addTripRow">+ Add Trip</button>
        <table class="table table-bordered" id="tripTable">
          <thead><tr><th>TR No</th><th>From</th><th>To</th><th>Total KM</th><th>Map KM</th><th></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" id="generateCodeBtn">Generate &amp; Print</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script>
  function addTripRow(t = {}) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" class="form-control form-control-sm trNumber" value="${t.tr || ''}"></td>
      <td><input type="text" class="form-control form-control-sm tripFrom" value="${t.from || ''}"></td>
      <td><input type="text" class="form-control form-control-sm tripTo" value="${t.to || ''}"></td>
      <td><input type="number" step="0.01" class="form-control form-control-sm tripKm" value="${t.km || ''}"></td>
      <td><input type="number" step="0.01" class="form-control form-control-sm tripMapKm" value="${t.km1 || ''}"></td>
      <td><button class="btn btn-sm btn-danger removeTrip">&times;</button></td>`;
    tr.querySelector('.removeTrip').addEventListener('click', () => tr.remove());
    document.querySelector('#tripTable tbody').appendChild(tr);
  }
  document.getElementById('addTripRow').addEventListener('click', () => addTripRow());
  addTripRow();

  function collectTrips() {
    return Array.from(document.querySelectorAll('#tripTable tbody tr')).map(tr => ({
      tr:   tr.querySelector('.trNumber').value.trim(),
      from: tr.querySelector('.tripFrom').value.trim(),
      to:   tr.querySelector('.tripTo').value.trim(),
      km:   tr.querySelector('.tripKm').value.trim() || '0',
      km1:  tr.querySelector('.tripMapKm').value.trim() || '0'
    })).filter(t => t.tr && t.from && t.to);
  }

  // Generate code button — POSTs to the operations endpoint and reloads the table.
  document.getElementById('generateCodeBtn').addEventListener('click', function () {
    const driverId = document.getElementById('driverIdInput').value.trim();
    const unit     = document.getElementById('unitInput').value.trim();
    const tripData = collectTrips();
    if (!driverId || !unit || tripData.length === 0) {
      return Swal.fire({ icon: 'warning', title: 'Missing data', text: 'Driver ID, unit and at least one trip row are required.' });
    }
    const fd = new FormData();
    fd.append('driverId', driverId);
    fd.append('unit', unit);
    fd.append('tripData', JSON.stringify(tripData));

    fetch('php/operations/generate_ticket_code.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (!data.success) return Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        Swal.fire({
          icon: 'success',
          title: 'Code Generated',
          html: `<h2 style="letter-spacing:5px;">${data.tc_code}</h2>`,
          confirmButtonText: 'Print'
        }).then(() => {
          window.open(data.print_url, '_blank');
          // Refresh the DataTable without a full page reload.
          if (window.codesTable) window.codesTable.ajax.reload(null, false);
          // Close + reset modal.
          bootstrap.Modal.getInstance(document.getElementById('newCodeModal'))?.hide();
          document.getElementById('driverIdInput').value = '';
          document.getElementById('unitInput').value = '';
          document.querySelector('#tripTable tbody').innerHTML = '';
          addTripRow();
        });
      })
      .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: String(err) }));
  });

  // Wait for jQuery + DataTables from gas/_layout_bottom.php before initialising.
  window.addEventListener('load', function () {
    window.codesTable = $('#codes-table').DataTable({
      processing: true,
      serverSide: true,
      autoWidth: false,
      scrollX: false,
      responsive: { details: { type: 'inline' } },
      order: [[3, 'desc']],
      ajax: { url: 'table-fetch/fuel-ticket-codes-table.php', type: 'POST' },
      columns: [
        { title: 'Code',        responsivePriority: 1 },
        { title: 'Driver ID',   responsivePriority: 3 },
        { title: 'Unit',        responsivePriority: 4 },
        { title: 'Date Issued', responsivePriority: 5 },
        { title: 'Status',      responsivePriority: 2 },
        { title: 'Action', orderable: false, searchable: false, responsivePriority: 2 }
      ],
      language: { emptyTable: 'No ticket codes yet.' }
    });
  });
</script>

<?php include 'gas/_layout_bottom.php'; ?>
