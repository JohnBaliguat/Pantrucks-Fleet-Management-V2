<?php
// Saves the admin-controlled photo requirements. Admin role only.
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
    // Each checkbox posts '1' when on; absent/anything else means off.
    $keys = array_merge(
        array_keys(pt_photo_requirements()),
        array_keys(pt_other_requirements()),
        array_keys(pt_feature_toggles())
    );
    foreach ($keys as $key) {
        $on = isset($_POST[$key]) && in_array(strtolower((string)$_POST[$key]), ['1', 'true', 'on', 'yes'], true);
        pt_setting_set($conn, $key, $on ? '1' : '0', $userId);
    }
    // Activity log — capture who changed the system settings.
    $adminName = '';
    if ($userId) {
        $st = $conn->prepare("SELECT CONCAT_WS(' ', user_fname, user_lname) FROM \"user\" WHERE user_id = ? LIMIT 1");
        $st->execute([$userId]);
        $adminName = (string)($st->fetchColumn() ?: '');
    }
    pt_log_activity($conn, $userId, 'Admin', $adminName, 'Updated settings', 'Changed required-inputs / feature toggles');
    echo json_encode(['status' => 'success', 'message' => 'Settings saved.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Save failed: ' . $e->getMessage()]);
}
