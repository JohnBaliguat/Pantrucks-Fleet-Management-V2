<?php
// Saves the WhatsApp (Meta Cloud API) notification settings. Admin only.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
require_once __DIR__ . '/../helpers/activity_log_helper.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0) ?: null;

try {
    // Enabled toggle.
    $enabled = isset($_POST['whatsapp_enabled']) && in_array(strtolower((string)$_POST['whatsapp_enabled']), ['1', 'true', 'on', 'yes'], true);
    pt_setting_set($conn, 'whatsapp_enabled', $enabled ? '1' : '0', $userId);

    // Plain text config keys.
    foreach (['whatsapp_phone_number_id', 'whatsapp_template_name', 'whatsapp_template_lang', 'whatsapp_default_country'] as $key) {
        if (isset($_POST[$key])) {
            pt_setting_set($conn, $key, trim((string)$_POST[$key]), $userId);
        }
    }

    // Token — only overwrite when a new value is supplied (blank keeps the old
    // one, so admins don't have to re-paste it every save).
    $token = trim((string)($_POST['whatsapp_token'] ?? ''));
    if ($token !== '') {
        pt_setting_set($conn, 'whatsapp_token', $token, $userId);
    }

    $adminName = '';
    if ($userId) {
        $st = $conn->prepare("SELECT CONCAT_WS(' ', user_fname, user_lname) FROM \"user\" WHERE user_id = ? LIMIT 1");
        $st->execute([$userId]);
        $adminName = (string)($st->fetchColumn() ?: '');
    }
    pt_log_activity($conn, $userId, 'Admin', $adminName, 'Updated WhatsApp settings', $enabled ? 'Enabled' : 'Disabled');

    echo json_encode(['status' => 'success', 'message' => 'WhatsApp settings saved.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Save failed: ' . $e->getMessage()]);
}
