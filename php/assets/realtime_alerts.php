<?php
// Tiny helper that emits the role-aware realtime-alerts <script>.
//
// Include from any authed page AFTER jQuery + SweetAlert are loaded:
//
//   include __DIR__ . '/../php/assets/realtime_alerts.php';
//
// Skip the include cost when the user isn't logged in.
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (empty($_SESSION['user_type'])) return;

$role = $_SESSION['user_type'];
?>
<script>
  window.PT_ROLE = <?php echo json_encode($role); ?>;
</script>
<script src="js/realtime-alerts.js"></script>
