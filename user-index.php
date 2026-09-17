<?php

// routes.php - Define available routes
$routes = [
    '' => 'HR/dashboard.php',
    'dashboard' => 'HR/dashboard.php',
    'profile' => 'HR/profile.php',
    'drivers' => 'HR/drivers.php',
    'performance' => 'HR/performance.php',
    'violationReport' => 'HR/violationReport.php',
    'tripReport' => 'HR/trip-report.php',
    'attendReport' => 'HR/attend-report.php',
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
