<?php
session_start();
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['Dispatcher', 'Dispatch Admin'], true)) {
    header('Location: dispatcher-index.php?route=login');
    exit();
}
include __DIR__ . '/../php/assets/driver_registration_form.php';
