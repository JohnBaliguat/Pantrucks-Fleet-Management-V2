<?php
// Save Geotab connection settings (Admin only). Writes the UI store
// php/config/geotab.config.php via pt_geotab_save_settings().
//
// A blank password keeps the existing one, so admins don't retype it.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/geotab_client.php';
require_once __DIR__ . '/../helpers/activity_log_helper.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$existing = pt_geotab_load_settings() ?? [];
$password = (string)($_POST['password'] ?? '');
if ($password === '' && !empty($existing['password'])) {
    $password = (string)$existing['password'];   // keep existing
}

$ok = pt_geotab_save_settings([
    'enabled'  => !empty($_POST['enabled']),
    'server'   => trim((string)($_POST['server'] ?? 'my.geotab.com')),
    'database' => trim((string)($_POST['database'] ?? '')),
    'username' => trim((string)($_POST['username'] ?? '')),
    'password' => $password,
]);

if ($ok) {
    // Drop any cached session so the next call authenticates with the new creds.
    pt_geotab_clear_session();
    pt_log_activity($conn, (int)($_SESSION['user_id'] ?? 0), 'Admin',
        (string)($_SESSION['user_name'] ?? 'Admin'),
        'Updated Geotab connection', 'db=' . (string)($_POST['database'] ?? ''));
    echo json_encode(['status' => 'success', 'message' => 'Geotab settings saved.']);
} else {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Could not write php/config/geotab.config.php — check the config folder is writable by PHP.',
    ]);
}
