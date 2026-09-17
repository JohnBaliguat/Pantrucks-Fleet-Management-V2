<?php
session_start();
if (!in_array($_SESSION['user_type'] ?? '', ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    header("Location: index.php"); exit();
}
$d_id = (int)($_GET['d_id'] ?? 0);
include __DIR__ . '/../php/assets/dispatch_receipt_body.php';
