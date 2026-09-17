<?php
require_once __DIR__ . '/_session.php';
$pageTitle = 'Dashboard';
$clientBookRoute = (strcasecmp($clientCustomerCode, 'CTH') === 0) ? 'client-book-cth' : 'client-book';
$clientBookLabel = (strcasecmp($clientCustomerCode, 'CTH') === 0) ? 'CTH Booking' : 'Book a Trip';

// Counts for the tiles.
$stats = ['active' => 0, 'pickup' => 0, 'on_trip' => 0, 'delivered' => 0];
if ($clientCustomerCode !== '') {
    $stmt = $conn->prepare(
        "SELECT
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END)                        AS active_total,
            SUM(CASE WHEN container_status LIKE '%Pickup'    THEN 1 ELSE 0 END)        AS pickup_total,
            SUM(CASE WHEN container_status LIKE '%On Trip'   THEN 1 ELSE 0 END)        AS on_trip_total,
            SUM(CASE WHEN container_status LIKE '%Delivered' THEN 1 ELSE 0 END)        AS delivered_total
         FROM booking
         WHERE costumer = ?"
    );
    $stmt->execute([$clientCustomerCode]);
    $r = $stmt->fetch();
if ($r) {
        $stats['active']    = (int)($r['active_total']    ?? 0);
        $stats['pickup']    = (int)($r['pickup_total']    ?? 0);
        $stats['on_trip']   = (int)($r['on_trip_total']   ?? 0);
        $stats['delivered'] = (int)($r['delivered_total'] ?? 0);
    }
}

include 'client/_layout_top.php';
?>
<?php if (!$clientHasCustomerLink): ?>
  <div class="alert alert-warning">
    Your client account isn't linked to a customer yet. Please contact your dispatcher
    to set your <b>Customer Code</b> in the user management page before you can book
    a trip.
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-primary"><div class="card-body">
      <h6 class="card-title text-white mb-1">Active Bookings</h6>
      <h2 class="text-white mb-0"><?php echo $stats['active']; ?></h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-warning"><div class="card-body">
      <h6 class="card-title text-white mb-1">Awaiting Pickup</h6>
      <h2 class="text-white mb-0"><?php echo $stats['pickup']; ?></h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-info"><div class="card-body">
      <h6 class="card-title text-white mb-1">On Trip</h6>
      <h2 class="text-white mb-0"><?php echo $stats['on_trip']; ?></h2>
    </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card text-bg-success"><div class="card-body">
      <h6 class="card-title text-white mb-1">Delivered</h6>
      <h2 class="text-white mb-0"><?php echo $stats['delivered']; ?></h2>
    </div></div>
  </div>
</div>

<div class="row mt-3">
  <div class="col-12">
    <div class="card"><div class="card-body">
      <h4 class="card-title">Welcome, <?php echo htmlspecialchars($clientCustomerName ?: 'Client'); ?></h4>
      <p class="text-muted">Use <b><?php echo htmlspecialchars($clientBookLabel); ?></b> to request a new container delivery. Track progress under <b>My Bookings</b>.</p>
      <a href="<?php echo $clientBookRoute; ?>" class="btn btn-primary"<?php if (!$clientHasCustomerLink) echo ' disabled aria-disabled="true" style="pointer-events:none;opacity:.6;"'; ?>><i class="ti ti-plus"></i> <?php echo htmlspecialchars($clientBookLabel); ?></a>
      <a href="client-mybookings" class="btn btn-outline-primary"><i class="ti ti-list-details"></i> View My Bookings</a>
    </div></div>
  </div>
</div>
<?php include 'client/_layout_bottom.php'; ?>
