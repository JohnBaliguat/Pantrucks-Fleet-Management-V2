<?php
require_once __DIR__ . '/_session.php';

if (strcasecmp($clientCustomerCode, 'CTH') !== 0) {
    header('Location: client-book');
    exit;
}

$pageTitle = 'CTH Multi Booking';

$locationRows = [];
$res = $conn->query("SELECT location_name FROM location ORDER BY location_name ASC");
while ($r = ($res)->fetch()) { $locationRows[] = $r['location_name']; }

include 'client/_layout_top.php';
?>
<?php if (!$clientHasCustomerLink): ?>
  <div class="alert alert-warning">
    Your client account isn't linked to a customer yet. Please contact your dispatcher
    to set your <b>Customer Code</b> before you can create a CTH booking.
  </div>
<?php else: ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-md-flex align-items-center mb-3">
          <div>
            <h4 class="card-title mb-1">CTH Multi-Container Booking</h4>
            <p class="card-subtitle mb-0">
              One shipment number, multiple containers. Shared trip details are entered once, then add one row per container.
            </p>
          </div>
        </div>

        <form id="clientCthForm" method="POST" action="php/operations/client_book_cth.php">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Client Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($clientCustomerName ?: $clientCustomerCode); ?>" readonly>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Container Status</label>
                <input type="text" class="form-control" value="Loaded" readonly>
                <small class="text-muted">CTH client bookings start as <b>Loaded</b>.</small>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="booking_sn" class="form-label">Shipment Number <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="booking_sn" name="booking_sn" required maxlength="100">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="booking_do" class="form-label">DO <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="booking_do" name="booking_do" required maxlength="100">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-3">
              <div class="mb-3">
                <label for="booking_date" class="form-label">Date Requested <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label for="haulingStart" class="form-label">Hauling Start <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="haulingStart" name="haulingStart" required min="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label for="lastDayStorage" class="form-label">Last Day of Storage <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="lastDayStorage" name="lastDayStorage" required min="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label for="lastDayDemurrage" class="form-label">Last Day of Demurrage <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="lastDayDemurrage" name="lastDayDemurrage" required min="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label for="lastDayDetention" class="form-label">Last Day of Detention <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="lastDayDetention" name="lastDayDetention" required min="<?php echo date('Y-m-d'); ?>">
                <small class="text-muted">This will also be used as the booking's required date.</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label for="trip_from" class="form-label">Pickup Location <span class="text-danger">*</span></label>
                <input type="text" class="form-control" list="cthPickupOptions" id="trip_from" name="trip_from" required>
                <datalist id="cthPickupOptions">
                  <?php foreach ($locationRows as $ln): ?>
                    <option value="<?php echo htmlspecialchars($ln); ?>">
                  <?php endforeach; ?>
                </datalist>
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label for="trip_to" class="form-label">Delivery Location <span class="text-danger">*</span></label>
                <input type="text" class="form-control" list="cthDeliveryOptions" id="trip_to" name="trip_to" required>
                <datalist id="cthDeliveryOptions">
                  <?php foreach ($locationRows as $ln): ?>
                    <option value="<?php echo htmlspecialchars($ln); ?>">
                  <?php endforeach; ?>
                </datalist>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="return_location" class="form-label">Return Location <span class="text-danger">*</span></label>
                <input type="text" class="form-control" list="cthReturnOptions" id="return_location" name="return_location" required>
                <datalist id="cthReturnOptions">
                  <?php foreach ($locationRows as $ln): ?>
                    <option value="<?php echo htmlspecialchars($ln); ?>">
                  <?php endforeach; ?>
                </datalist>
                <small class="text-muted">Dispatcher will use this later when creating the empty-return booking.</small>
              </div>
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
            <div>
              <h5 class="mb-1">Containers</h5>
              <p class="text-muted mb-0 small">Add one row for each container under this shipment number.</p>
            </div>
            <button type="button" class="btn btn-sm btn-primary" id="cthAddRow"><i class="ti ti-plus"></i> Add Container</button>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered align-middle" id="cthContainerTable">
              <thead class="table-light">
                <tr>
                  <th style="min-width: 220px;">Container No</th>
                  <th style="min-width: 180px;">Container Seal</th>
                  <th style="width: 90px;">Qty</th>
                  <th style="width: 70px;"></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><input type="text" name="container[]" class="form-control cth-container-input" maxlength="11" placeholder="ABCD1234567" required></td>
                  <td><input type="text" name="container_seal[]" class="form-control" maxlength="100" required></td>
                  <td><input type="number" name="quantity[]" class="form-control" value="1" min="1" readonly></td>
                  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger cthRemoveRow" title="Remove row"><i class="ti ti-x"></i></button></td>
                </tr>
              </tbody>
            </table>
          </div>

          <button type="submit" class="btn btn-primary"><i class="ti ti-send"></i> Submit CTH Booking</button>
          <a href="client-mybookings" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function normalizeCthContainer(value) {
  var upper = String(value || '').toUpperCase();
  var letters = '';
  var digits = '';
  for (const ch of upper) {
    if (letters.length < 4 && /[A-Z]/.test(ch)) { letters += ch; continue; }
    if (letters.length === 4 && digits.length < 7 && /[0-9]/.test(ch)) { digits += ch; }
  }
  return letters + digits;
}

function cthRowTemplate() {
  return '<tr>' +
    '<td><input type="text" name="container[]" class="form-control cth-container-input" maxlength="11" placeholder="ABCD1234567" required></td>' +
    '<td><input type="text" name="container_seal[]" class="form-control" maxlength="100" required></td>' +
    '<td><input type="number" name="quantity[]" class="form-control" value="1" min="1" readonly></td>' +
    '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger cthRemoveRow" title="Remove row"><i class="ti ti-x"></i></button></td>' +
  '</tr>';
}

$(function() {
  $('#cthAddRow').on('click', function() {
    $('#cthContainerTable tbody').append(cthRowTemplate());
  });

  $(document).on('click', '.cthRemoveRow', function() {
    if ($('#cthContainerTable tbody tr').length === 1) {
      $(this).closest('tr').find('input').val('');
      return;
    }
    $(this).closest('tr').remove();
  });

  $(document).on('input', '.cth-container-input', function() {
    this.value = normalizeCthContainer(this.value);
  });

  $('#clientCthForm').on('submit', function(e) {
    e.preventDefault();
    var $form = $(this);
    var pickup = $('#trip_from').val().trim();
    var dropoff = $('#trip_to').val().trim();
    var returnLoc = $('#return_location').val().trim();
    if (pickup && dropoff && pickup.toLowerCase() === dropoff.toLowerCase()) {
      Swal.fire({ icon: 'warning', text: 'Pickup and delivery cannot be the same location.' });
      return;
    }
    if (dropoff && returnLoc && dropoff.toLowerCase() === returnLoc.toLowerCase()) {
      Swal.fire({ icon: 'warning', text: 'Delivery and return location cannot be the same.' });
      return;
    }
    var badContainer = false;
    $('.cth-container-input').each(function() {
      var value = normalizeCthContainer($(this).val());
      $(this).val(value);
      if (!/^[A-Z]{4}[0-9]{7}$/.test(value)) {
        badContainer = true;
        return false;
      }
    });
    if (badContainer) {
      Swal.fire({ icon: 'warning', text: 'Each container number must be exactly 4 capital letters followed by 7 numbers.' });
      return;
    }

    Swal.fire({
      title: 'Submit this CTH booking?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, submit'
    }).then(function(result) {
      if (!result.isConfirmed) return;
      $.ajax({
        url: $form.attr('action'),
        method: 'POST',
        dataType: 'json',
        data: $form.serialize()
      }).done(function(res) {
        if (res && res.status === 'success') {
          var bookingList = Array.isArray(res.booking_nos) ? res.booking_nos.join('<br>') : '';
          Swal.fire({
            icon: 'success',
            title: 'CTH booking submitted',
            html: bookingList ? ('Created bookings:<br><b>' + bookingList + '</b>') : (res.message || 'Submitted.')
          }).then(function() { window.location.href = 'client-mybookings'; });
        } else {
          Swal.fire({ icon: 'error', text: (res && res.message) || 'Could not submit booking.' });
        }
      }).fail(function() {
        Swal.fire({ icon: 'error', text: 'Network error. Please try again.' });
      });
    });
  });
});
</script>
<?php endif; ?>
<?php include 'client/_layout_bottom.php'; ?>
