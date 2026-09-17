<?php
// Shared body for the Billing close page.
// Required vars: $role ('admin'|'dispatcher').
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Billing</title>
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
      <h3 class="text-white mb-0 fs-5">Billing</h3>
    </div>

    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3"><div class="card-body">
            <div class="d-md-flex align-items-center mb-3">
              <div>
                <h4 class="card-title mb-0">Billing</h4>
                <p class="card-subtitle">Close billing on delivered dispatches. Optionally notify the client (email + SMS attempt logged in <code>push_send_log</code>).</p>
              </div>
              <div class="ms-auto d-flex gap-2">
                <select class="form-select form-select-sm" id="statusFilter">
                  <option value="pending" selected>Ready to close</option>
                  <option value="closed">Closed</option>
                  <option value="all">All</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" id="refreshBilling"><i class="ti ti-refresh"></i></button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead class="table-light"><tr>
                  <th>#</th><th>Booking</th><th>Customer</th><th>Truck / Driver</th>
                  <th>Workflow</th><th>Amount</th><th>Closed</th><th>Notified</th><th class="text-end">Action</th>
                </tr></thead>
                <tbody id="billingBody"><tr><td colspan="9" class="text-center text-muted">Loading…</td></tr></tbody>
              </table>
            </div>
          </div></div>
          <div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Close billing modal -->
  <div class="modal fade" id="closeBillingModal">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="closeForm">
          <div class="modal-header"><h5 class="modal-title">Close Billing</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" name="d_id" id="cb_d_id">
            <div class="alert alert-info" id="cb_summary"></div>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Currency</label>
                <select class="form-control" name="billing_currency">
                  <option value="PHP" selected>PHP</option>
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label">Total Amount <span class="text-danger">*</span></label>
                <input class="form-control" type="number" min="0" step="0.01" name="billing_amount" required>
              </div>
              <div class="col-12">
                <label class="form-label">Notes (optional)</label>
                <textarea class="form-control" name="billing_notes" rows="2" placeholder="Internal note"></textarea>
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="cb_notify" name="notify_client" value="1" checked>
                  <label class="form-check-label" for="cb_notify">Also notify client (advances workflow to <code>client_notified</code>)</label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Close Billing</button>
          </div>
        </form>
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
  function loadBilling(){
    $.getJSON('php/fetch/billing_pending.php', { status: $('#statusFilter').val() }, function(res){
      if (res.status !== 'success') return;
      window._billingCache = res.rows;
      if (!res.rows.length) { $('#billingBody').html('<tr><td colspan="9" class="text-center text-muted">No rows for this filter.</td></tr>'); return; }
      const html = res.rows.map(function(r){
        const wf = r.workflow_stage;
        const stageBadge = '<span class="badge bg-light text-dark">' + escapeHtml(wf) + '</span>';
        const notifBadge = r.client_notified_at
          ? '<span class="badge bg-success">' + escapeHtml(r.client_notified_at) + '</span>'
          : (r.notify_ready ? '<span class="badge bg-warning text-dark">Ready</span>' : '<span class="badge bg-secondary">No contact</span>');
        const action = (wf === 'delivered' || wf === 'pod_captured')
          ? '<button class="btn btn-sm btn-success btn-close-billing">Close</button>'
          : (wf === 'billing_closed' ? '<button class="btn btn-sm btn-outline-success btn-close-billing">Notify Now</button>' : '<span class="text-muted small">—</span>');
        return '<tr data-d-id="' + r.d_id + '">'
          + '<td>' + r.d_id + '</td>'
          + '<td>' + escapeHtml(r.booking_no) + '<br><span class="badge bg-primary">' + escapeHtml(r.booking_type || 'Local') + '</span></td>'
          + '<td>' + escapeHtml(r.costumer) + '</td>'
          + '<td>' + escapeHtml(r.d_truck) + '<br><span class="text-muted small">' + escapeHtml(r.d_driverName || '') + '</span></td>'
          + '<td>' + stageBadge + '</td>'
          + '<td>' + (r.billing_amount && parseFloat(r.billing_amount) > 0
                      ? escapeHtml(r.billing_currency) + ' ' + parseFloat(r.billing_amount).toFixed(2)
                      : '<span class="text-muted">—</span>') + '</td>'
          + '<td>' + escapeHtml(r.billing_closed_at || '—') + '</td>'
          + '<td>' + notifBadge + '</td>'
          + '<td class="text-end">' + action + '</td>'
          + '</tr>';
      }).join('');
      $('#billingBody').html(html);
    });
  }
  $('#statusFilter, #refreshBilling').on('change click', loadBilling);
  $(loadBilling);

  $('#billingBody').on('click', '.btn-close-billing', function(){
    const dId = $(this).closest('tr').data('d-id');
    const row = (window._billingCache || []).find(x => parseInt(x.d_id, 10) === parseInt(dId, 10));
    $('#cb_d_id').val(dId);
    $('#cb_summary').html('<b>' + escapeHtml(row.booking_no) + '</b> &mdash; ' + escapeHtml(row.costumer)
      + ' &middot; ' + escapeHtml(row.d_truck)
      + (row.notify_ready ? '' : '<br><span class="text-warning">No customer contact configured (' + escapeHtml(row.costumer) + '). The notify channel will log but not deliver.</span>'));
    if (row.billing_amount && parseFloat(row.billing_amount) > 0) {
      $('input[name=billing_amount]').val(row.billing_amount);
      $('select[name=billing_currency]').val(row.billing_currency || 'PHP');
    } else {
      $('input[name=billing_amount]').val('');
    }
    $('#closeBillingModal').modal('show');
  });

  $('#closeForm').on('submit', function(e){
    e.preventDefault();
    const data = $(this).serialize();
    $.post('php/operations/close_billing.php', data, function(res){
      if (res.status === 'success') {
        $('#closeBillingModal').modal('hide');
        Swal.fire({ icon: 'success', text: res.message, timer: 1500, showConfirmButton: false });
        loadBilling();
      } else {
        Swal.fire({ icon: 'error', text: res.message || 'Close failed' });
      }
    }, 'json').fail(function(){ Swal.fire({icon:'error',text:'Network error'}); });
  });
  </script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
