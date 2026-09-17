<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    header("Location: index.php?route=login"); exit();
}
include "php/config/config.php";
include __DIR__ . '/../php/assets/coupon_body.php';
