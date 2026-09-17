<?php
// Auto-activate scheduled violations — schedulable entry point.
//
// Runs the same sweep the HR / dispatch screens run opportunistically, but on a
// timer so a scheduled violation blocks its driver at the scheduled moment even
// if nobody has an HR or dispatch page open.
//
// Wire it up (examples):
//   Windows Task Scheduler → every 5 min:
//     curl -s "http://localhost/php/operations/auto_activate_violations.php"
//   PHP CLI:
//     C:\xampp\php\php.exe C:\xampp\htdocs\"Fleet Management New"\php\operations\auto_activate_violations.php
//   Linux cron (*/5 * * * *):
//     curl -s "http://localhost/php/operations/auto_activate_violations.php" >/dev/null
//
// Access: a logged-in HR/HR Admin/Admin, OR a local/CLI caller (same host).
// The action is deterministic and idempotent.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/violation_auto_activate.php';

$isCli   = (PHP_SAPI === 'cli');
$remote  = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true);

$role = '';
if (!$isCli) {
    session_start();
    $role = $_SESSION['user_type'] ?? '';
}
$allowed = $isCli || $isLocal || in_array($role, ['HR', 'HR Admin', 'Admin'], true);

if (!$isCli) header('Content-Type: application/json');

if (!$allowed) {
    if (!$isCli) http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$activated = pt_activate_due_violations($conn);
echo json_encode([
    'status'    => 'success',
    'activated' => $activated,
    'message'   => $activated > 0
        ? "Activated $activated scheduled violation(s); driver(s) blocked."
        : 'No scheduled violations due.',
    'ran_at'    => date('Y-m-d H:i:s'),
]);
