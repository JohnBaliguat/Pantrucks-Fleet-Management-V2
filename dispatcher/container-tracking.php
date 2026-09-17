<?php
session_start();
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['Dispatcher', 'Dispatch Admin', 'Booker'], true)) {
    header("Location: dispatcher-index.php?route=login");
    exit();
}
$role = 'dispatcher';
include __DIR__ . '/../php/assets/container_tracking_body.php';
