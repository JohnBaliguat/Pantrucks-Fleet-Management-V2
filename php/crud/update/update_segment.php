<?php
header('Content-Type: application/json');
include __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$tripId   = isset($_POST['trip_id']) ? (int)$_POST['trip_id'] : 0;
$dId      = isset($_POST['d_id'])    ? (int)$_POST['d_id']    : 0;
if ($tripId <= 0 || $dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'trip_id and d_id required']);
    exit;
}

$segmentCostumer = $_POST['segment_costumer'] ?? '';
$segmentStatus   = $_POST['segment_status']   ?? 'Pending';
$tripFrom        = $_POST['trip_from']        ?? '';
$tripTo          = $_POST['trip_to']          ?? '';
$dTruck          = $_POST['d_truck']          ?? '';
$dTrailer        = $_POST['d_trailer']        ?? '';
$dGenset         = $_POST['d_genset']         ?? '';
$driverIdRaw     = $_POST['driver_id']        ?? '';
$scheduledAt     = $_POST['scheduled_at']     ?? '';

// scheduled_at comes from a datetime-local input (YYYY-MM-DDTHH:MM).
// Normalise to MySQL DATETIME.
if ($scheduledAt !== '') {
    $scheduledAt = str_replace('T', ' ', $scheduledAt);
    if (strlen($scheduledAt) === 16) { $scheduledAt .= ':00'; }
}

$validStatuses = ['Pending', 'Assigned', 'EnRoute', 'Delivered', 'Cancelled', 'Foul'];
if (!in_array($segmentStatus, $validStatuses, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit;
}

// Update the trip row (segment-level fields).
$sql = "UPDATE trips
        SET segment_costumer = ?, segment_status = ?, trip_from = ?, trip_to = ?,
            scheduled_at = " . ($scheduledAt === '' ? "NULL" : "?") . "
        WHERE trip_id = ? AND d_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . ($conn->errorInfo()[2] ?? '')]);
    exit;
}
if ($scheduledAt === '') {
    $stmt->execute([$segmentCostumer, $segmentStatus, $tripFrom, $tripTo, $tripId, $dId]);
} else {
    $stmt->execute([$segmentCostumer, $segmentStatus, $tripFrom, $tripTo, $scheduledAt, $tripId, $dId]);
}

// Optionally update assignment fields on the parent dispatch row.
$updates = [];
$params  = [];
if ($dTruck   !== '') { $updates[] = 'd_truck = ?';   $params[] = $dTruck;   }
if ($dTrailer !== '') { $updates[] = 'd_trailer = ?'; $params[] = $dTrailer; }
if ($dGenset  !== '') { $updates[] = 'd_genset = ?';  $params[] = $dGenset;  }
if ($driverIdRaw !== '' && $driverIdRaw !== null) {
    $driverId = (int)$driverIdRaw;
    $updates[] = 'driver_id = ?'; $params[] = $driverId;
    // Refresh the cached driverName for backwards compatibility.
    $stmtDn = $conn->prepare("SELECT CONCAT(driver_lname, ', ', SUBSTRING(driver_fname, 1, 1), '.') AS dn FROM drivers WHERE driver_id = ? LIMIT 1");
    $stmtDn->execute([$driverId]);
    $row = $stmtDn->fetch();
    if ($row) {
        $updates[] = 'd_driverName = ?'; $params[] = $row['dn'];
    }
}

if (!empty($updates)) {
    $sql = 'UPDATE dispatch SET ' . implode(', ', $updates) . ' WHERE d_id = ?';
    $params[] = $dId;
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute($params);
    }
}

echo json_encode(['status' => 'success', 'message' => 'Segment updated']);
