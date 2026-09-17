<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Dispatcher') { header("Location: dispatcher-index.php?route=login"); exit(); }
$role = 'dispatcher';
include __DIR__ . '/../php/assets/billing_body.php';
