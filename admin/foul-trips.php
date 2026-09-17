<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: index.php?route=login");
    exit();
}
$role = 'admin';
include __DIR__ . '/../php/assets/foul_trips_body.php';
