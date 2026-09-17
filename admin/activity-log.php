<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Admin') { header("Location: index.php?route=login"); exit(); }
$role = 'admin';
include __DIR__ . '/../php/assets/activity_log_body.php';
