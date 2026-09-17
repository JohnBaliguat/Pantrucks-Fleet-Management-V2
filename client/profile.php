<?php
require_once __DIR__ . '/_session.php';
$pageTitle = 'My Profile';

$user = ['user_fname' => '', 'user_lname' => '', 'user_email' => '', 'user_assignlocation' => ''];
if ($clientUserId > 0) {
    $stmt = $conn->prepare("SELECT user_fname, user_lname, user_email, user_assignlocation FROM \"user\" WHERE user_id = ? LIMIT 1");
    $stmt->execute([$clientUserId]);
    $r = $stmt->fetch();
if ($r) $user = $r;
}
include 'client/_layout_top.php';
?>
<div class="row">
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h4 class="card-title">Client Account</h4>
      <p><b>Name:</b> <?php echo htmlspecialchars(trim(($user['user_fname'] ?? '') . ' ' . ($user['user_lname'] ?? ''))); ?></p>
      <p><b>Email:</b> <?php echo htmlspecialchars($user['user_email'] ?? ''); ?></p>
      <p><b>Customer Code:</b> <?php echo htmlspecialchars($clientCustomerCode ?: '— not linked —'); ?></p>
      <p><b>Customer Name:</b> <?php echo htmlspecialchars($clientCustomerName ?: '—'); ?></p>
      <p><b>Customer Segment:</b> <?php echo htmlspecialchars($clientCustomerSegment ?: '—'); ?></p>
      <p><b>Assigned location:</b> <?php echo htmlspecialchars($user['user_assignlocation'] ?? ''); ?></p>
      <a href="client-logout" class="btn btn-outline-primary">Logout</a>
    </div></div>
  </div>
</div>
<?php include 'client/_layout_bottom.php'; ?>
