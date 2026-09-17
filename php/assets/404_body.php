<?php
// Shared 404 body. Auth-aware — shows the right "back to dashboard"
// link for the logged-in role, or a login link if the user isn't
// authed. The role-specific 404 wrappers (admin/404.php etc.) just
// include this file.
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
$role = $_SESSION['user_type'] ?? '';
$home = 'login';
switch ($role) {
    case 'Admin':       $home = 'dashboard';            break;
    case 'Dispatcher':  $home = 'dispatch-dashboard';   break;
    case 'Dispatch Admin': $home = 'dispatch-monitoring'; break;
    case 'Booker':      $home = 'dispatch-addbook';     break;
    case 'Driver':      $home = 'driver-dashboard';     break;
    case 'Shop':        $home = 'shop-dashboard';       break;
    case 'User':        $home = 'hr-dashboard';         break;
    case 'Rescue':      $home = 'shop-dashboard';       break;
    case 'HR-Admin':    $home = 'hra-dashboard';        break;
    case 'Visual':      $home = 'visual-dashboard';     break;
    case 'Gate-Guard':  $home = 'gate-dashboard';       break;
    case 'Maintenance': $home = 'maintenance-dashboard';break;
}
$requested = htmlspecialchars($_GET['route'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>404 — Page not found</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <style>
    body { background:#f5f7fa; }
    .err-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .err-card { max-width:520px; width:100%; background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(15,23,42,.08); padding:36px 32px; text-align:center; }
    .err-code { font-size:74px; font-weight:800; color:#0d6efd; line-height:1; margin:0 0 8px; }
    .err-h    { font-size:20px; font-weight:700; color:#0f172a; margin:0 0 6px; }
    .err-p    { color:#475569; margin:0 0 20px; }
    .err-route { display:inline-block; padding:4px 10px; background:#f1f5f9; border-radius:6px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12px; color:#334155; margin-bottom:18px; }
    .btn-row  { display:flex; gap:10px; justify-content:center; }
  </style>
</head>
<body>
  <div class="err-wrap">
    <div class="err-card">
      <img src="assets/images/logos/LogoFleet.png" alt="" style="width:64px;opacity:.85;margin-bottom:12px;">
      <div class="err-code">404</div>
      <div class="err-h">That page doesn't exist.</div>
      <p class="err-p">The route you tried isn't defined in this build, or the link is stale.</p>
      <?php if ($requested !== ''): ?>
        <div class="err-route">requested: <strong><?php echo $requested; ?></strong></div>
      <?php endif; ?>
      <div class="btn-row">
        <a href="<?php echo htmlspecialchars($home); ?>" class="btn btn-primary">
          <i class="ti ti-home"></i>
          <?php echo $role === '' ? 'Sign in' : 'Back to my dashboard'; ?>
        </a>
        <?php if ($role !== ''): ?>
          <a href="logout" class="btn btn-outline-secondary">Sign out</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
