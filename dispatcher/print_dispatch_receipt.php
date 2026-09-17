<?php
session_start();
if (!in_array($_SESSION['user_type'] ?? '', ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    header("Location: dispatcher-index.php?route=login"); exit();
}
$d_id = (int)($_GET['d_id'] ?? 0);
include __DIR__ . '/../php/assets/dispatch_receipt_body.php';
