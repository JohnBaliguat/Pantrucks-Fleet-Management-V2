<?php
session_start();

// Best-effort activity log before the session is torn down.
if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/php/config/config.php';
        require_once __DIR__ . '/php/helpers/activity_log_helper.php';
        $uid  = (int)$_SESSION['user_id'];
        $role = (string)($_SESSION['user_type'] ?? '');
        $name = '';
        if ($role === 'Driver') {
            $st = $conn->prepare("SELECT CONCAT(driver_lname, ', ', driver_fname) AS n FROM drivers WHERE driver_id = ? LIMIT 1");
            $st->execute([$uid]);
            $name = (string)($st->fetchColumn() ?: '');
        } else {
            $st = $conn->prepare("SELECT CONCAT_WS(' ', user_fname, user_lname) AS n FROM \"user\" WHERE user_id = ? LIMIT 1");
            $st->execute([$uid]);
            $name = (string)($st->fetchColumn() ?: '');
        }
        pt_log_activity($conn, $uid, $role, $name, 'Logout', 'Signed out');
    } catch (Throwable $e) {
        // Ignore — logout must always proceed.
    }
}

session_destroy();

header("Location: login.php");
exit;
?>
