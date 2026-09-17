<?php

// routes.php - Define available routes
$routes = [
    '' => 'HR Admin/dashboard.php',
    'dashboard' => 'HR Admin/dashboard.php',
    'profile' => 'HR Admin/profile.php',
    'drivers' => 'HR Admin/drivers.php',
    'performance' => 'HR Admin/performance.php',
    'violationReport' => 'HR Admin/violationReport.php',
    'tripReport' => 'HR Admin/trip-report.php',
    'attendReport' => 'HR Admin/attend-report.php',
    'logout' => 'logout.php',
    'login' => 'login.php',
];

// Get the route from URL
$route = $_GET['route'] ?? 'dashboard';

// Check if the route exists, otherwise show a 404 page
if (array_key_exists($route, $routes)) {
    require $routes[$route];
} else {
    require 'HR/404.php';
}
?>
