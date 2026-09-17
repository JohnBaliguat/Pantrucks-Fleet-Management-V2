<?php
// Lightweight GPS heartbeat endpoint.
// Driver app posts { lat, lng, accuracy? } every ~30s while the dashboard
// is open. Updates drivers.last_lat/lng/last_seen_at so the dispatcher
// view (and dispatch board) always show a fresh dot.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Driver' || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$driverId = (int)$_SESSION['user_id'];
$lat = isset($_POST['lat']) ? filter_var($_POST['lat'], FILTER_VALIDATE_FLOAT) : false;
$lng = isset($_POST['lng']) ? filter_var($_POST['lng'], FILTER_VALIDATE_FLOAT) : false;

if ($lat === false || $lng === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'lat/lng required (numeric)']);
    exit;
}
// Sanity clamp — anything outside this is junk / not on Earth.
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'lat/lng out of range']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "UPDATE drivers
            SET last_lat = ?, last_lng = ?, last_seen_at = NOW()
          WHERE driver_id = ?"
    );
    $stmt->execute([$lat, $lng, $driverId]);

    // Piggyback the auto end-shift worker on this heartbeat. Each driver
    // heartbeat is ~30s, so on a typical day this fires often enough to
    // keep idle shifts closed within ~1 minute of the 1-hour threshold,
    // without any external cron. Lock file rate-limits to once/minute.
    $lockPath = sys_get_temp_dir() . '/pt_auto_end_shifts.lock';
    $lastRun  = @filemtime($lockPath) ?: 0;
    if (time() - $lastRun >= 60) {
        @touch($lockPath);
        require_once __DIR__ . '/auto_end_idle_shifts.php';
        try {
            pt_auto_end_idle_shifts($conn);
        } catch (Throwable $e) {
            error_log('[ingest_driver_gps] auto end-shift failed: ' . $e->getMessage());
        }
    }

    // Tell the driver if their own shift was just closed (by this worker run
    // or a concurrent admin action) so the UI can show a clear message
    // instead of silently failing the next action.
    $check = $conn->prepare(
        "SELECT 1 FROM driver_shift WHERE driver_id = ? AND ended_at IS NULL LIMIT 1"
    );
    $check->execute([$driverId]);
    $shiftOpen = (bool)$check->fetchColumn();

    echo json_encode([
        'status'       => 'success',
        'last_seen_at' => date('Y-m-d H:i:s'),
        'shift_open'   => $shiftOpen,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
