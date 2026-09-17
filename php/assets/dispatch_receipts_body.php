<?php
// Shared body for the Dispatcher / Admin "attach receipts to a dispatch" page.
// Required vars: $role ('admin'|'dispatcher'), $baseRoute (string).
include __DIR__ . '/../config/config.php';

$dId = (int)($_GET['d_id'] ?? 0);
$dispatch = null;
if ($dId > 0) {
    $stmt = $conn->prepare(
        "SELECT d.d_id, d.booking_no, d.costumer, d.d_drivername, d.d_truck, d.workflow_stage,
                b.booking_type, b.customs_cleared
         FROM dispatch d
         LEFT JOIN booking b ON b.booking_no = d.booking_no
         WHERE d.d_id = ? LIMIT 1"
    );
    $stmt->execute([$dId]);
    $dispatch = $stmt->fetch();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trip Receipts</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Trip Receipts</h3>
    </div>

    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <?php if (!$dispatch): ?>
            <div class="alert alert-warning mt-3">No dispatch found. Add `?d_id=NN` to the URL.</div>
          <?php else: ?>
          <div class="card mt-3"><div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
              <h4 class="card-title mb-0"><?php echo htmlspecialchars($dispatch['booking_no']); ?></h4>
              <span class="badge bg-primary"><?php echo htmlspecialchars($dispatch['booking_type'] ?? 'Local'); ?></span>
              <span class="badge bg-light text-dark"><?php echo htmlspecialchars($dispatch['workflow_stage']); ?></span>
              <span class="text-muted small ms-auto"><?php echo htmlspecialchars($dispatch['d_drivername']); ?> &mdash; <?php echo htmlspecialchars($dispatch['d_truck']); ?></span>
            </div>
            <p class="card-subtitle">Attach cargo manifests, gate passes, customs docs, etc. The driver sees them in the PWA. Tick "Requires Ack" to require the driver to tap Acknowledge before going en-route.</p>

            <input type="hidden" id="d_id" value="<?php echo (int)$dispatch['d_id']; ?>">

            <form id="uploadForm" enctype="multipart/form-data" class="row g-3 mt-2">
              <div class="col-md-3">
                <label class="form-label">Type</label>
                <select class="form-control" name="receipt_type" required>
                  <option value="manifest">Cargo Manifest</option>
                  <option value="gate_pass">Gate Pass</option>
                  <option value="customs">Customs Doc</option>
                  <option value="delivery_note">Delivery Note</option>
                  <option value="other" selected>Other</option>
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label">Title (optional)</label>
                <input type="text" class="form-control" name="title" placeholder="e.g. CY exit slip">
              </div>
              <div class="col-md-2">
                <label class="form-label d-block">Requires Ack</label>
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" id="reqAck" name="requires_ack" value="1" checked>
                  <label class="form-check-label" for="reqAck">Yes</label>
                </div>
              </div>
              <div class="col-md-2">
                <label class="form-label">File</label>
                <input type="file" class="form-control" name="file" accept=".pdf,image/*" required>
              </div>
              <div class="col-12">
                <button class="btn btn-primary"><i class="ti ti-upload"></i> Attach Receipt</button>
              </div>
            </form>

            <hr>
            <h6 class="mt-3">Attached</h6>
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead class="table-light"><tr>
                  <th>#</th><th>Type</th><th>Title</th><th>File</th><th>Requires Ack</th><th>Driver Ack</th><th>Attached</th>
                </tr></thead>
                <tbody id="receiptsTable"><tr><td colspan="7" class="text-center text-muted">Loading…</td></tr></tbody>
              </table>
            </div>
          </div></div>
          <?php endif; ?>
          <div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>
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
  <script>
  function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function loadReceipts(){
    const dId = $('#d_id').val();
    if (!dId) return;
    $.getJSON('php/fetch/dispatch_receipts.php', { d_id: dId }, function (res) {
      if (res.status !== 'success') return;
      if (!res.rows.length) {
        $('#receiptsTable').html('<tr><td colspan="7" class="text-center text-muted">No receipts yet.</td></tr>'); return;
      }
      const html = res.rows.map(function (r, i) {
        return '<tr>'
          + '<td>' + (i + 1) + '</td>'
          + '<td>' + escapeHtml(r.receipt_type) + '</td>'
          + '<td>' + escapeHtml(r.title || '-') + '</td>'
          + '<td><a href="' + escapeHtml(r.file_path) + '" target="_blank">Open</a></td>'
          + '<td>' + (parseInt(r.requires_ack, 10) === 1 ? '<span class="badge bg-warning text-dark">Yes</span>' : '<span class="badge bg-secondary">No</span>') + '</td>'
          + '<td>' + (r.acknowledged_at ? '<span class="badge bg-success">' + escapeHtml(r.acknowledged_at) + '</span>' : '<span class="text-muted">—</span>') + '</td>'
          + '<td>' + escapeHtml(r.attached_at) + '</td>'
          + '</tr>';
      }).join('');
      $('#receiptsTable').html(html);
    });
  }
  $('#uploadForm').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('d_id', $('#d_id').val());
    $.ajax({
      url: 'php/operations/save_dispatch_receipt.php',
      method: 'POST', data: fd, contentType: false, processData: false, dataType: 'json',
      success: function (res) {
        Swal.fire({ icon: res.status === 'success' ? 'success' : 'error', text: res.message, timer: 1200, showConfirmButton: false });
        if (res.status === 'success') { document.getElementById('uploadForm').reset(); loadReceipts(); }
      }
    });
  });
  $(loadReceipts);
  </script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
