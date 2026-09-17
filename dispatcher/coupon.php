<?php
session_start();
if (!in_array(($_SESSION['user_type'] ?? ''), ['Dispatcher', 'Dispatch Admin'], true)) {
    header("Location: dispatcher-index.php?route=login"); exit();
}
include "php/config/config.php";
include __DIR__ . '/../php/assets/coupon_body.php';
