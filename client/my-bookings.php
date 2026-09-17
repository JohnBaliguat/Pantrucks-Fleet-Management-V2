<?php
require_once __DIR__ . '/_session.php';
$pageTitle = 'My Bookings';
$clientBookRoute = (strcasecmp($clientCustomerCode, 'CTH') === 0) ? 'client-book-cth' : 'client-book';
$clientBookLabel = (strcasecmp($clientCustomerCode, 'CTH') === 0) ? 'CTH Booking' : 'Book a Trip';

$rows = [];
if ($clientCustomerCode !== '') {
    $stmt = $conn->prepare(
        "SELECT booking_no, booking_date, booking_daterequired, container_seal, container,
                container_status, trip_from, trip_to, quantity, status
         FROM booking
         WHERE costumer = ?
         ORDER BY booking_id DESC"
    );
    $stmt->execute([$clientCustomerCode]);
    $res = $stmt;
    while ($r = $res->fetch()) { $rows[] = $r; }
}

// Same colour tokens as the upcoming dispatch dashboard tiles. We map
// every legacy/current status onto one of four buckets and surface a
// matching Bootstrap badge here so clients see progress at a glance.
function client_status_badge(string $s): array {
    $norm = strtolower(trim($s));
    if ($norm === '' || $norm === 'n/a') $norm = 'empty';
    if ($norm === 'empty')                            return ['bg-info text-dark',    'Empty'];
    if ($norm === 'empty container pickup')           return ['bg-warning text-dark', 'Empty &middot; Pickup'];
    if ($norm === 'empty container on trip')          return ['bg-orange text-white', 'Empty &middot; On Trip'];
    if ($norm === 'empty container delivered')        return ['bg-success text-white','Empty &middot; Delivered'];
    if ($norm === 'loaded')                           return ['bg-info text-dark',    'Loaded'];
    if ($norm === 'loaded container pickup')          return ['bg-warning text-dark', 'Loaded &middot; Pickup'];
    if ($norm === 'loaded container on trip')         return ['bg-orange text-white', 'Loaded &middot; On Trip'];
    if ($norm === 'loaded container delivered')       return ['bg-success text-white','Loaded &middot; Delivered'];
    return ['bg-secondary', htmlspecialchars($s)];
}

include 'client/_layout_top.php';
?>
<style>
  .bg-orange { background-color: #fb923c !important; }
</style>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-md-flex align-items-center mb-3">
          <div>
            <h4 class="card-title mb-1">My Bookings</h4>
            <p class="card-subtitle mb-0">All bookings under <b><?php echo htmlspecialchars($clientCustomerName ?: $clientCustomerCode); ?></b>.</p>
          </div>
          <a href="<?php echo $clientBookRoute; ?>" class="btn btn-primary ms-auto"<?php if (!$clientHasCustomerLink) echo ' disabled aria-disabled="true" style="pointer-events:none;opacity:.6;"'; ?>>
            <i class="ti ti-plus"></i> <?php echo htmlspecialchars($clientBookLabel); ?>
          </a>
        </div>

        <div class="table-responsive">
          <table id="myBookingsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-light">
              <tr>
                <th>Booking No</th>
                <th>Booked</th>
                <th>Required</th>
                <th>Seal</th>
                <th>Pickup</th>
                <th>Delivery</th>
                <th class="text-end">Qty</th>
                <th>Container Status</th>
                <th>Booking Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No bookings yet. Click <b>Book a Trip</b> to create one.</td></tr>
              <?php else: foreach ($rows as $b): list($cls, $label) = client_status_badge($b['container_status'] ?? ''); ?>
                <tr>
                  <td><?php echo htmlspecialchars($b['booking_no']); ?></td>
                  <td><?php echo htmlspecialchars($b['booking_date']); ?></td>
                  <td><?php echo htmlspecialchars($b['booking_daterequired']); ?></td>
                  <td><?php echo htmlspecialchars($b['container_seal']); ?></td>
                  <td><?php echo htmlspecialchars($b['trip_from']); ?></td>
                  <td><?php echo htmlspecialchars($b['trip_to']); ?></td>
                  <td class="text-end"><?php echo (int)$b['quantity']; ?></td>
                  <td><span class="badge <?php echo $cls; ?>"><?php echo $label; ?></span></td>
                  <td><?php echo htmlspecialchars($b['status']); ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  $(function() {
    if ($.fn.DataTable && $('#myBookingsTable tbody tr').length > 1) {
      $('#myBookingsTable').DataTable({ order: [[0, 'desc']], pageLength: 25 });
    }
  });
</script>
<?php include 'client/_layout_bottom.php'; ?>
