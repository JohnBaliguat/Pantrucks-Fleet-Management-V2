<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin', 'Gate-Guard'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

$type = strtolower(trim($_GET['type'] ?? ''));
$code = strtoupper(trim($_GET['code'] ?? ''));
if (!in_array($type, ['truck', 'genset', 'trailer'], true) || $code === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'type and code required']); exit;
}

function pt_actor_name(PDO $conn, string $role, int $actorId): string
{
    if ($actorId <= 0) return '';

    if (strtolower($role) === 'driver') {
        $stmt = $conn->prepare("SELECT TRIM(CONCAT(driver_lname, ', ', driver_fname)) AS n FROM drivers WHERE driver_id = ? LIMIT 1");
        $stmt->execute([$actorId]);
        $row = $stmt->fetch();
return trim((string)($row['n'] ?? ''));
    }

    $stmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(CONCAT(user_lname, ', ', user_fname)), ','), NULLIF(user_name, '')) AS n FROM \"user\" WHERE user_id = ? LIMIT 1");
    $stmt->execute([$actorId]);
    $row = $stmt->fetch();
return trim((string)($row['n'] ?? ''));
}

function pt_format_dispatch_detail(array $row, string $type): string
{
    $parts = [];
    if (!empty($row['booking_no'])) $parts[] = 'Booking ' . $row['booking_no'];
    if (!empty($row['d_drivername'])) $parts[] = 'Driver ' . $row['d_drivername'];
    if (!empty($row['workflow_stage'])) $parts[] = 'Stage ' . str_replace('_', ' ', $row['workflow_stage']);
    if ($type !== 'truck' && !empty($row['d_truck'])) $parts[] = 'Truck ' . $row['d_truck'];
    if ($type !== 'trailer' && !empty($row['d_trailer'])) $parts[] = 'Trailer ' . $row['d_trailer'];
    if ($type !== 'genset' && !empty($row['d_genset'])) $parts[] = 'Genset ' . $row['d_genset'];
    return implode(' • ', $parts);
}

$snapshot = [
    'code' => $code,
    'type' => $type,
    'location' => 'Unknown',
    'updated_at' => null,
    'status' => '',
];

if ($type === 'trailer') {
    $stmt = $conn->prepare("SELECT current_base, current_base_updated_at, trailer_status FROM trailer WHERE trailer_name = ? LIMIT 1");
    $stmt->execute([$code]);
    $row = $stmt->fetch();
if ($row) {
        $snapshot['location'] = trim((string)($row['current_base'] ?? '')) ?: 'Unknown';
        $snapshot['updated_at'] = $row['current_base_updated_at'] ?? null;
        $snapshot['status'] = $row['trailer_status'] ?? '';
    }
} else {
    $stmt = $conn->prepare("SELECT current_location, current_location_updated_at, unit_status FROM units WHERE unit_name = ? AND unit_type = ? LIMIT 1");
    $stmt->execute([$code, $type]);
    $row = $stmt->fetch();
if ($row) {
        $snapshot['location'] = trim((string)($row['current_location'] ?? '')) ?: 'Unknown';
        $snapshot['updated_at'] = $row['current_location_updated_at'] ?? null;
        $snapshot['status'] = $row['unit_status'] ?? '';
    }
}

$events = [];
$dispatchColumn = $type === 'truck' ? 'd_truck' : ($type === 'genset' ? 'd_genset' : 'd_trailer');
$gateColumn = $type === 'truck' ? 'truck_plate' : ($type === 'genset' ? 'genset_code' : 'trailer_code');

$stmt = $conn->prepare(
    "SELECT d_id, booking_no, d_driverName, d_truck, d_trailer, d_genset, workflow_stage, d_datetime, workflow_updated_at
     FROM dispatch
     WHERE {$dispatchColumn} = ?
     ORDER BY d_id DESC
     LIMIT 25"
);
$stmt->execute([$code]);
$res = $stmt;
while ($row = $res->fetch()) {
    $events[] = [
        'happened_at' => $row['workflow_updated_at'] ?: $row['d_datetime'],
        'source' => 'dispatch',
        'title' => 'Dispatch #' . $row['d_id'],
        'detail' => pt_format_dispatch_detail($row, $type),
        'tone' => in_array((string)$row['workflow_stage'], ['driver_declined', 'reassigned'], true) ? 'danger' : 'info',
    ];
}
$stmt = $conn->prepare(
    "SELECT we.we_id, we.d_id, we.stage, we.actor_role, we.actor_id, we.notes, we.event_at, d.booking_no, d.d_driverName
     FROM workflow_event we
     INNER JOIN dispatch d ON d.d_id = we.d_id
     WHERE d.{$dispatchColumn} = ?
     ORDER BY we.event_at DESC, we.we_id DESC
     LIMIT 50"
);
$stmt->execute([$code]);
$res = $stmt;
while ($row = $res->fetch()) {
    $actorName = pt_actor_name($conn, (string)$row['actor_role'], (int)$row['actor_id']);
    $actorLabel = trim((string)$row['actor_role']);
    if ($actorName !== '') {
        $actorLabel .= ' • ' . $actorName;
    }
    $detail = [];
    if (!empty($row['booking_no'])) $detail[] = 'Booking ' . $row['booking_no'];
    if (!empty($row['d_id'])) $detail[] = 'Dispatch #' . $row['d_id'];
    if ($actorLabel !== '') $detail[] = $actorLabel;
    if (!empty($row['notes'])) $detail[] = preg_replace('/\s+/', ' ', trim((string)$row['notes']));
    $stage = (string)$row['stage'];
    $events[] = [
        'happened_at' => $row['event_at'],
        'source' => 'workflow',
        'title' => ucwords(str_replace('_', ' ', $stage)),
        'detail' => implode(' • ', $detail),
        'tone' => in_array($stage, ['pod_rejected', 'driver_declined', 'reassigned_from'], true) ? 'danger' : (in_array($stage, ['pending_verification', 'billing_closed'], true) ? 'warn' : 'info'),
    ];
}
$stmt = $conn->prepare(
    "SELECT gl_id, d_id, direction, verified, mismatch_reason, authorised, logged_at
     FROM gate_log
     WHERE {$gateColumn} = ?
     ORDER BY logged_at DESC, gl_id DESC
     LIMIT 25"
);
$stmt->execute([$code]);
$res = $stmt;
while ($row = $res->fetch()) {
    $parts = [];
    if (!empty($row['d_id'])) $parts[] = 'Dispatch #' . $row['d_id'];
    $parts[] = 'Direction ' . strtoupper((string)$row['direction']);
    $parts[] = ((int)$row['authorised'] === 1 ? 'Authorised' : 'Not authorised');
    if (!empty($row['mismatch_reason'])) $parts[] = $row['mismatch_reason'];
    $events[] = [
        'happened_at' => $row['logged_at'],
        'source' => 'gate',
        'title' => 'Gate Scan',
        'detail' => implode(' • ', $parts),
        'tone' => ((int)$row['verified'] === 1 && (int)$row['authorised'] === 1) ? 'info' : 'warn',
    ];
}
if ($type === 'trailer') {
    $stmt = $conn->prepare(
        "SELECT tj_id, d_id, driver_id, lat, lng, photo_path, detached_at
         FROM trailer_jackup
         WHERE trailer_code = ?
         ORDER BY detached_at DESC NULLS LAST, tj_id DESC
         LIMIT 25"
    );
    $stmt->execute([$code]);
    $res = $stmt;
    while ($row = $res->fetch()) {
        $parts = [];
        if (!empty($row['d_id'])) $parts[] = 'Dispatch #' . $row['d_id'];
        $driverName = pt_actor_name($conn, 'driver', (int)$row['driver_id']);
        if ($driverName !== '') $parts[] = 'Driver ' . $driverName;
        if ($row['lat'] !== null && $row['lng'] !== null) $parts[] = 'GPS ' . $row['lat'] . ', ' . $row['lng'];
        if (!empty($row['photo_path'])) $parts[] = 'Photo saved';
        $events[] = [
            'happened_at' => $row['detached_at'],
            'source' => 'jackup',
            'title' => 'Trailer Jack-up',
            'detail' => implode(' • ', $parts),
            'tone' => 'info',
        ];
    }
}

usort($events, static function (array $a, array $b): int {
    return strcmp((string)($b['happened_at'] ?? ''), (string)($a['happened_at'] ?? ''));
});

echo json_encode([
    'status' => 'success',
    'snapshot' => $snapshot,
    'events' => array_slice($events, 0, 60),
]);
