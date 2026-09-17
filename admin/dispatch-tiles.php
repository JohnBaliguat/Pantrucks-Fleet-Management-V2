<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login");
    exit();
}
$role = 'admin';
$baseRoute = 'dispatch-tiles-admin';
include __DIR__ . '/../php/assets/dispatch_tiles_body.php';
