<?php

// routes.php - Define available routes
$routes = [
    '' => 'dispatcher/dashboard.php',
    'dashboard' => 'dispatcher/dashboard.php',
    'dashboard-trailer' => 'dispatcher/dashboard-trailer.php',
    'dashboard-truck' => 'dispatcher/dashboard-truck.php',
    'dashboard-attendance' => 'dispatcher/dashboard-attendance.php',
    'dispatch' => 'dispatcher/dispatch.php',
    'dispatchTest' => 'dispatcher/dispatch-test.php',
    'driversReport' => 'dispatcher/drivers-report.php',
    'drivers' => 'dispatcher/drivers.php',
    'print' => 'dispatcher/print_dispatch.php',
    'print1' => 'dispatcher/print_dispatch1.php',
    'printJob' => 'dispatcher/print_job_ticket.php',
    'printcth' => 'dispatcher/print_dispatchCTH.php',
    'printDriverRegistration' => 'dispatcher/print_driver_registration.php',
    'gate' => 'dispatcher/gate.php',
    'mybook' => 'dispatcher/mybooking.php',
    'allbook' => 'dispatcher/allbooking.php',
    'addbook' => 'dispatcher/addbooking.php',
    'multibook' => 'dispatcher/multibooking.php',
    'monitoring' => 'dispatcher/monitoring.php',
    'abcmonitoring' => 'dispatcher/abcmonitoring.php',
    'dolemonitoring' => 'dispatcher/dolemonitoring.php',
    'dmmonitoring' => 'dispatcher/dmmonitoring.php',
    'farmmonitoring' => 'dispatcher/farmmonitoring.php',
    'sumimonitoring' => 'dispatcher/sumimonitoring.php',
    'cthmonitoring' => 'dispatcher/cthmonitoring.php',
    'trailer' => 'dispatcher/trailer.php',
    'tripReport' => 'dispatcher/trip-report.php',
    'attendReport' => 'dispatcher/attend-report.php',
    'truck' => 'dispatcher/truck.php',
    'unitProfile' => 'dispatcher/addunit-page.php',
    'unitEditProfile' => 'dispatcher/updateunit-page.php',
    'genset' => 'dispatcher/genset.php',
    'user' => 'dispatcher/user.php',
    'segment' => 'dispatcher/segment.php',
    'profile' => 'dispatcher/profile.php',
    'logout' => 'logout.php',
    'login' => 'login.php',
];

// Get the route from URL
$route = $_GET['route'] ?? 'dashboard';

// Check if the route exists, otherwise show a 404 page
if (array_key_exists($route, $routes)) {
    require $routes[$route];
} else {
    require 'dispatcher/404.php';
}
?>
