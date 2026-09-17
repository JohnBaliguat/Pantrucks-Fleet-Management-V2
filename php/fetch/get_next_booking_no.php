<?php
// Phase 14.1 — booking_no preview.
//
// New format is Customer-BN-From-To, so the preview has to be computed
// against three form fields. Callers pass them as query params; if any
// are missing we just return a placeholder so the form can show
// something useful pre-fill.

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/booking_no.php';

$customer = trim($_GET['customer']  ?? $_GET['costumer'] ?? '');
$from     = trim($_GET['from']      ?? $_GET['trip_from'] ?? '');
$to       = trim($_GET['to']        ?? $_GET['trip_to']   ?? '');

if ($customer === '' || $from === '' || $to === '') {
    echo '(auto-generated on save)';
    exit;
}

echo bn_generate($conn, $customer, $from, $to);
