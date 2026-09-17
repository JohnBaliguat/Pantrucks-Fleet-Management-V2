<?php

// routes.php - Define available routes
$routes = [
    '' => 'driver/dashboard.php',
    'dashboard' => 'driver/dashboard.php',
    'profile' => 'driver/profile.php',
    'bookinglist' => 'driver/bookinglist.php',
    'unit' => 'driver/unit.php',
    'tripReport' => 'driver/trip-report.php',
    'logout' => 'logout.php',
    'login' => 'login.php',
];

// Get the route from URL
$route = $_GET['route'] ?? 'dashboard';

// Check if the route exists, otherwise show a 404 page
if (array_key_exists($route, $routes)) {
    require $routes[$route];
} else {
    require 'driver/404.php';
}
?>
