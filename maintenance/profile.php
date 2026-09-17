<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Maintenance') { header("Location: login"); exit(); }
$pageTitle = 'My Profile';
include 'maintenance/_layout_top.php';

include 'php/config/config.php';
$id = $_SESSION['user_id'] ?? 0;
$row = ['user_fname'=>'','user_lname'=>'','user_email'=>'','user_assignlocation'=>''];
if ($id) {
    $stmt = $conn->prepare("SELECT user_fname, user_lname, user_email, user_assignlocation FROM \"user\" WHERE user_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
if ($r) $row = $r;
}
?>
<div class="row">
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h4 class="card-title">Maintenance User</h4>
      <p><b>Name:</b> <?php echo htmlspecialchars($row['user_fname'] . ' ' . $row['user_lname']); ?></p>
      <p><b>Email:</b> <?php echo htmlspecialchars($row['user_email']); ?></p>
      <p><b>Assigned location:</b> <?php echo htmlspecialchars($row['user_assignlocation']); ?></p>
      <a href="maintenance-logout" class="btn btn-outline-primary">Logout</a>
    </div></div>
  </div>
</div>
<?php include 'maintenance/_layout_bottom.php'; ?>
