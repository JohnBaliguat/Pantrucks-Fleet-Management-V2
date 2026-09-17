<?php
// Auto-release expired maintenance blocks — schedulable entry point.
//
// Runs the same sweep the maintenance views run opportunistically, but on a
// timer so units are returned to the fleet the moment their expected return
// date arrives even if nobody has the maintenance screen open.
//
// Wire it up (examples):
//   Windows Task Scheduler → run every 15 min:
//     curl -s "http://localhost/php/operations/auto_release_maintenance.php"
//   or via PHP CLI:
//     C:\xampp\php\php.exe C:\xampp\htdocs\"Fleet Management New"\php\operations\auto_release_maintenance.php
//   Linux cron (*/15 * * * *):
//     curl -s "http://localhost/php/operations/auto_release_maintenance.php" >/dev/null
//
// Access: a logged-in Maintenance/Admin, OR a local/CLI caller (Task Scheduler
// or cron on the same host). The action is deterministic and idempotent.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/maintenance_auto_release.php';

$isCli   = (PHP_SAPI === 'cli');
$remote  = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true);

$role = '';
if (!$isCli) {
    session_start();
    $role = $_SESSION['user_type'] ?? '';
}
$allowed = $isCli || $isLocal || in_array($role, ['Maintenance', 'Admin'], true);

if (!$isCli) header('Content-Type: application/json');

if (!$allowed) {
    if (!$isCli) http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$released = pt_auto_release_expired_maintenance($conn);
echo json_encode([
    'status'   => 'success',
    'released' => $released,
    'message'  => $released > 0
        ? "Auto-released $released unit(s) whose expected return date had arrived."
        : 'No units due for auto-release.',
    'ran_at'   => date('Y-m-d H:i:s'),
]);
