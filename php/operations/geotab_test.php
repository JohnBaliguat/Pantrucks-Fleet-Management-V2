<?php
// Test the Geotab connection (Admin only). Authenticates with the saved
// settings and does one small Get. Returns JSON {ok, message, count}.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../lib/geotab_client.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Admin only']);
    exit;
}

if (!pt_geotab_is_configured()) {
    echo json_encode(['ok' => false, 'message' => 'Fill in and enable the settings, then Save before testing.']);
    exit;
}

@set_time_limit(60);
try {
    // Force a fresh authentication with the currently-saved credentials.
    pt_geotab_clear_session();
    $session = pt_geotab_authenticate(true);
    $devices = pt_geotab_get('Device', [], 1000);
    echo json_encode([
        'ok'      => true,
        'message' => 'Connected to ' . $session['server'] . '. ' . count($devices) . ' device(s) visible to this API user.',
        'count'   => count($devices),
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
}
