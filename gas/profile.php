<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Gastender') {
    header("Location: login.php?error=Unauthorized");
    exit();
}
include 'php/config/config.php';

$id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_fname, user_mname, user_lname, user_name, user_type FROM \"user\" WHERE user_id = ? LIMIT 1");
$stmt->execute([$id]);
$u = $stmt->fetch() ?: [];
$pageTitle = 'My Profile';
include 'gas/_layout_top.php';
?>

<div class="row"><div class="col-md-12"><h4 class="mb-3">My Profile</h4></div></div>
  <div class="row">
    <div class="col-md-6">
      <div class="my-3 p-3 bg-body rounded shadow-sm">
        <table class="table table-borderless">
          <tr><th>Username</th><td><?php echo htmlspecialchars($u['user_name'] ?? ''); ?></td></tr>
          <tr><th>First Name</th><td><?php echo htmlspecialchars($u['user_fname'] ?? ''); ?></td></tr>
          <tr><th>Middle Name</th><td><?php echo htmlspecialchars($u['user_mname'] ?? ''); ?></td></tr>
          <tr><th>Last Name</th><td><?php echo htmlspecialchars($u['user_lname'] ?? ''); ?></td></tr>
          <tr><th>Role</th><td><span class="badge bg-info">Gas Tender</span></td></tr>
        </table>
      </div>
    </div>
  </div>

<?php include 'gas/_layout_bottom.php'; ?>
