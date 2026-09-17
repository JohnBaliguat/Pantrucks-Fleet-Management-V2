<?php
// Toggle the driver's opt-in for the earnings leaderboard.
// Opt-in shows the driver on the Top Earners board (first name + rank only);
// opting out removes them from everyone else's board (they still see their own).
session_start();
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Driver') {
    http_response_code(403);
    exit('Forbidden');
}
$driverId = (int)($_SESSION['user_id'] ?? 0);

if ($driverId > 0) {
    try {
        pt_ensure_column($conn, 'drivers', 'leaderboard_optin', 'BOOLEAN NOT NULL DEFAULT FALSE');
        $conn->prepare(
            "UPDATE drivers SET leaderboard_optin = NOT COALESCE(leaderboard_optin, FALSE) WHERE driver_id = ?"
        )->execute([$driverId]);
    } catch (Throwable $e) {
        // Never block the redirect on a DB hiccup.
    }
}

// Return to the dashboard. Prefer the referring page; fall back to the
// web-root-relative route (this file lives two levels below the app root).
$back = $_SERVER['HTTP_REFERER'] ?? '';
if ($back === '') {
    $back = '../../driver-dashboard';
}
header('Location: ' . $back);
exit;
