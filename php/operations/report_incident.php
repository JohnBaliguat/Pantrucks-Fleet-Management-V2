<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$type     = trim($_POST['incident_type'] ?? '');
$severity = trim($_POST['severity'] ?? 'low');
$desc     = trim($_POST['description'] ?? '');
$plate    = strtoupper(trim($_POST['truck_plate'] ?? ''));
$dId      = (int)($_POST['d_id'] ?? 0);
$tripId   = (int)($_POST['trip_id'] ?? 0);
$driverId = (int)($_POST['driver_id'] ?? 0);
$lat      = $_POST['lat'] !== '' && isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng      = $_POST['lng'] !== '' && isset($_POST['lng']) ? (float)$_POST['lng'] : null;

if ($type === '' || $desc === '') {
    echo json_encode(['status' => 'error', 'message' => 'incident_type and description required']);
    exit;
}

// Optional photo upload — store under php/assets/uploads/incidents/.
$photoPath = '';
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $dir = __DIR__ . '/../assets/uploads/incidents';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) $ext = 'jpg';
    $base = 'inc_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    $abs  = $dir . '/' . $base;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $abs)) {
        $photoPath = 'php/assets/uploads/incidents/' . $base;
    }
}

// If no d_id supplied but we have a plate, attach to most recent dispatch.
if ($dId === 0 && $plate !== '') {
    $stmt = $conn->prepare("SELECT d_id, driver_id FROM dispatch WHERE d_truck = ? ORDER BY d_id DESC LIMIT 1");
    $stmt->execute([$plate]);
    $r = $stmt->fetch();
if ($r) { $dId = (int)$r['d_id']; if (!$driverId) $driverId = (int)$r['driver_id']; }
}

$stmt = $conn->prepare(
    "INSERT INTO incident (d_id, trip_id, driver_id, incident_type, severity, description, lat, lng, photo_path, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
     RETURNING inc_id"
);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . ($conn->errorInfo()[2] ?? '')]);
    exit;
}

$ok = $stmt->execute([$dId, $tripId, $driverId, $type, $severity, $desc, $lat, $lng, $photoPath]);
$incId = (int)$stmt->fetchColumn();
if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Insert failed']);
    exit;
}

// Audit on the workflow timeline.
if ($dId > 0) {
    $bn = '';
    $r = $conn->query("SELECT booking_no FROM dispatch WHERE d_id = $dId LIMIT 1");
    if ($r && $row = $r->fetch()) { $bn = $row['booking_no']; }
    $note = "Incident #$incId — $type ($severity)";
    $stmt = $conn->prepare("INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, notes) VALUES (?, ?, 'incident_flagged', ?, ?)");
    if ($stmt) {
        $actor = $_SESSION['user_type'] ?? 'system';
        $stmt->execute([$dId, $bn, $actor, $note]);
}
}

echo json_encode(['status' => 'success', 'message' => 'Incident filed.', 'inc_id' => $incId]);
