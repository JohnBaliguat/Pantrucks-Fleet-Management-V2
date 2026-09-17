<?php

// Routes for the Gate-Guard role.
$routes = [
    ''           => 'gate/dashboard.php',
    'dashboard'  => 'gate/dashboard.php',
    'checkin'    => 'gate/checkin.php',
    'incident'   => 'gate/incident.php',
    'profile'    => 'gate/profile.php',
    'logout'     => 'logout.php',
    'login'      => 'login.php',
];

$route = $_GET['route'] ?? 'dashboard';

if (array_key_exists($route, $routes)) {
    require $routes[$route];
} else {
    require 'gate/404.php';
}
