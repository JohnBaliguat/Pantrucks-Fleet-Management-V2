<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header('Location: login');
    exit();
}
include __DIR__ . '/../php/assets/driver_registration_form.php';
