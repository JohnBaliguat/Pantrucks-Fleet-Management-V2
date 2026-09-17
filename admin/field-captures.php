<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login");
    exit();
}
$role = 'admin';
include __DIR__ . '/../php/assets/field_captures_body.php';
