<?php
session_start();
if (!in_array(($_SESSION['user_type'] ?? ''), ['Dispatcher', 'Dispatch Admin'], true)) { header("Location: dispatcher-index.php?route=login"); exit(); }
$role = 'dispatcher';
include __DIR__ . '/../php/assets/equipment_body.php';
