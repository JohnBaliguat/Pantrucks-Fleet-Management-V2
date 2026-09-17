<?php
include 'php/config/config.php';

$id = $_SESSION['user_id'] ?? 0;
$fname = '';
$firstLetter = '';
if ($id) {
    $stmt = $conn->prepare("SELECT user_fname, user_lname, user_type FROM \"user\" WHERE user_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
if ($row) {
        $fname = $row['user_fname'] ?? '';
        $firstLetter = substr((string)($row['user_lname'] ?? ''), 0, 1);
        $roleLabel = $row['user_type'] ?? 'Gate Guard';
    }
}
$roleLabel = $roleLabel ?? 'Gate Guard';
$displayName = trim(($fname ?: 'User') . ' ' . ($firstLetter ? strtoupper($firstLetter) . '.' : ''));
?>
<header class="app-header">
  <nav class="navbar navbar-expand-lg navbar-light">
    <div class="admin-navbar-shell w-100">
      <div class="admin-navbar-left">
        <a class="nav-link sidebartoggler d-flex align-items-center justify-content-center p-2 rounded" id="headerCollapse" href="javascript:void(0)" title="Menu" aria-label="Toggle sidebar">
          <i class="ti ti-menu-2 fs-5"></i>
        </a>
        <div class="admin-navbar-titleblock">
          <div class="admin-navbar-kicker">Gate Control</div>
          <div class="admin-navbar-title">Pantrucks Fleet Management System</div>
        </div>
      </div>

      <div class="admin-navbar-right">
        <div class="admin-navbar-status">
          <span class="admin-navbar-status-dot"></span>
          <span>System online</span>
        </div>

        <div class="nav-item dropdown">
          <a class="nav-link admin-profile-chip" href="javascript:void(0)" id="drop2" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="admin-profile-text">
              <span class="admin-profile-name"><?php echo htmlspecialchars($displayName); ?></span>
              <span class="admin-profile-role"><?php echo htmlspecialchars($roleLabel); ?></span>
            </div>
            <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;"><?php echo htmlspecialchars(strtoupper($firstLetter ?: 'U')); ?></span>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
            <div class="message-body">
              <div class="admin-profile-menuhead">
                <div class="admin-profile-menuavatar"><?php echo htmlspecialchars(strtoupper(substr($fname ?: 'U', 0, 1))); ?></div>
                <div>
                  <div class="admin-profile-menuname"><?php echo htmlspecialchars($displayName); ?></div>
                  <div class="admin-profile-menurole"><?php echo htmlspecialchars($roleLabel); ?></div>
                </div>
              </div>
              <a href="gate-profile" class="d-flex align-items-center gap-2 dropdown-item">
                <i class="ti ti-user fs-6"></i>
                <p class="mb-0 fs-3">My Profile</p>
              </a>
              <a href="gate-logout" class="btn btn-outline-primary mx-3 mt-2 d-block">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </nav>
</header>
