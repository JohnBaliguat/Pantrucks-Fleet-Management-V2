<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: index.php?route=login");
    exit();
}
$role = 'admin';
$baseRoute = 'bookingSegments';
include __DIR__ . '/../php/assets/booking_segments_body.php';
