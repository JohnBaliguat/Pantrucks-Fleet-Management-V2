<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Admin') { header("Location: index.php?route=login"); exit(); }
$role = 'admin';
include __DIR__ . '/../php/assets/dispatch_receipts_body.php';
