<?php

// routes.php - Define available routes
$routes = [
    '' => 'data-visual/dashboard.php',
    'dashboard' => 'data-visual/dashboard.php',
    'profile' => 'data-visual/profile.php',
    'trailerReport' => 'data-visual/trailer-report.php',
    'truckReport' => 'data-visual/truck-report.php',
    'logout' => 'logout.php',
    'login' => 'login.php',
];

// Get the route from URL
$route = $_GET['route'] ?? 'dashboard';

// Check if the route exists, otherwise show a 404 page
if (array_key_exists($route, $routes)) {
    require $routes[$route];
} else {
    require 'data-visual/404.php';
}
?>
