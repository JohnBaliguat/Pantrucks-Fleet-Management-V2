<?php
require_once __DIR__ . '/_session.php';
$isCthClient = (strcasecmp($clientCustomerCode, 'CTH') === 0);
if ($isCthClient) {
    header('Location: client-book-cth');
    exit;
}
$pageTitle = 'Book a Trip';

// Locations for pickup / delivery pickers.
$locationRows = [];
$res = $conn->query("SELECT location_name FROM location ORDER BY location_name ASC");
while ($r = ($res)->fetch()) { $locationRows[] = $r['location_name']; }

include 'client/_layout_top.php';
?>
<?php if (!$clientHasCustomerLink): ?>
  <div class="alert alert-warning">
    Your client account isn't linked to a customer yet. Please contact your dispatcher
    to set your <b>Customer Code</b> in the user management page before you can book
    a trip.
  </div>
<?php else: ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-md-flex align-items-center mb-3">
          <div>
            <h4 class="card-title mb-1">Book a Trip</h4>
            <p class="card-subtitle mb-0">
              Submit a new booking request. Your dispatcher will assign a driver and confirm pickup.
            </p>
          </div>
        </div>

        <form id="clientBookForm" method="POST" action="php/operations/client_book.php">
          <!-- Hidden: Customer Segment auto-populates from the logged-in client. -->
          <input type="hidden" name="customer_segment" value="<?php echo htmlspecialchars($clientCustomerSegment); ?>">

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Client Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($clientCustomerName ?: $clientCustomerCode); ?>" readonly>
                <small class="text-muted">From your account &mdash; contact dispatch to change.</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="booking_required" class="form-label">Required Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="booking_required" name="booking_required" required min="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="container_seal" class="form-label">Container Seal <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="container_seal" name="container_seal" maxlength="100" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="quantity" class="form-label">Container Quantity <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="trip_from" class="form-label">Pickup Location <span class="text-danger">*</span></label>
                <input type="text" class="form-control" list="locOptionsPickup" id="trip_from" name="trip_from" required>
                <datalist id="locOptionsPickup">
                  <?php foreach ($locationRows as $ln): ?>
                    <option value="<?php echo htmlspecialchars($ln); ?>">
                  <?php endforeach; ?>
                </datalist>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="trip_to" class="form-label">Delivery Location <span class="text-danger">*</span></label>
                <input type="text" class="form-control" list="locOptionsDelivery" id="trip_to" name="trip_to" required>
                <datalist id="locOptionsDelivery">
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
                <label class="form-label">Container Status</label>
                <input type="text" class="form-control" value="Empty" readonly>
                <small class="text-muted">All new client bookings start as <b>Empty</b>. Dispatch updates this as the container moves.</small>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary"><i class="ti ti-send"></i> Submit Booking</button>
          <a href="client-mybookings" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
  $('#clientBookForm').on('submit', function(e) {
    e.preventDefault();
    var $form = $(this);
    var pickup = $('#trip_from').val().trim();
    var dropoff = $('#trip_to').val().trim();
    if (pickup && dropoff && pickup.toLowerCase() === dropoff.toLowerCase()) {
      Swal.fire({ icon: 'warning', text: 'Pickup and delivery cannot be the same location.' });
      return;
    }
    Swal.fire({
      title: 'Submit this booking?',
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
          Swal.fire({
            icon: 'success',
            title: 'Booking submitted',
            html: 'Your booking number is <b>' + res.booking_no + '</b>.'
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
