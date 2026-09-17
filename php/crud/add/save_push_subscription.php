<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Driver') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Driver session required']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$driverId = (int)$_SESSION['user_id'];
$endpoint = trim($_POST['endpoint'] ?? '');
$p256dh   = trim($_POST['p256dh_key'] ?? '');
$auth     = trim($_POST['auth_key'] ?? '');
$ua       = substr(trim($_POST['user_agent'] ?? ''), 0, 255);
if ($endpoint === '' || $p256dh === '' || $auth === '') {
    echo json_encode(['status' => 'error', 'message' => 'endpoint + keys required']); exit;
}

// Upsert by endpoint.
$stmt = $conn->prepare(
    "INSERT INTO push_subscription (driver_id, endpoint, p256dh_key, auth_key, user_agent, last_used_at)
     VALUES (?, ?, ?, ?, ?, NOW())
     ON CONFLICT (endpoint) DO UPDATE SET
         driver_id = EXCLUDED.driver_id,
         p256dh_key = EXCLUDED.p256dh_key,
         auth_key = EXCLUDED.auth_key,
         user_agent = EXCLUDED.user_agent,
         last_used_at = NOW()"
);
$stmt->execute([$driverId, $endpoint, $p256dh, $auth, $ua]);
echo json_encode(['status' => 'success', 'message' => 'Subscription saved.']);
