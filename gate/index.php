<?php
$route = trim((string)($_GET['route'] ?? 'dashboard'));
$allowed = ['dashboard', 'checkin', 'incident', 'profile', 'login', 'logout'];

if (!in_array($route, $allowed, true)) {
    $route = 'dashboard';
}

header('Location: ../gate-index.php?route=' . urlencode($route));
exit;
