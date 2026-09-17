<?php
// Phase 12 — shared session guard + client-context loader.
//
// Every client page includes this immediately after session_start().
// It enforces user_type = 'Client' and resolves the linked customer
// row so pages can render Client Name / Customer Segment without
// re-querying. The booking endpoint uses the same helper so a Client
// login can't book on another customer's behalf even with a tampered
// POST.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (($_SESSION['user_type'] ?? '') !== 'Client') {
    header('Location: login');
    exit();
}

require_once __DIR__ . '/../php/config/config.php';

$clientUserId = (int)($_SESSION['user_id'] ?? 0);
$clientCustomerCode    = '';
$clientCustomerName    = '';
$clientCustomerSegment = '';
$clientCustomerId      = 0;

if ($clientUserId > 0) {
    $stmt = $conn->prepare(
        'SELECT u.customer_code, c.customer_id, c.customer_name, c.customer_segment
         FROM "user" u
         LEFT JOIN customer c ON c.customer_code = u.customer_code
         WHERE u.user_id = ? LIMIT 1'
    );
    $stmt->execute([$clientUserId]);
    $row = $stmt->fetch();
    if ($row) {
        $clientCustomerCode    = $row['customer_code']    ?? '';
        $clientCustomerName    = $row['customer_name']    ?? '';
        $clientCustomerSegment = $row['customer_segment'] ?? '';
        $clientCustomerId      = (int)($row['customer_id'] ?? 0);
    }
}

// A client account without a linked customer can't book. Surface this
// in the UI rather than failing silently when they hit the form.
$clientHasCustomerLink = ($clientCustomerCode !== '');
