<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatch Admin', 'Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$manilaTz = new DateTimeZone('Asia/Manila');
$nowManila = new DateTimeImmutable('now', $manilaTz);
$today = $nowManila->format('Y-m-d');

$liveStages = [
    'dispatcher_assigned',
    'reassigned',
    'driver_accepted',
    'gate_cleared',
    'en_route',
    'delivered',
    'pending_verification',
];
$quotedLiveStages = "'" . implode("','", array_map(fn($v) => pt_pg_escape($conn, $v), $liveStages)) . "'";
$todayEscaped = pt_pg_escape($conn, $today);

$counts = [
    'active_bookings' => 0,
    'pending_bookings' => 0,
    'overdue_bookings' => 0,
    'active_dispatches' => 0,
    'available_drivers' => 0,
    'on_shift_drivers' => 0,
    'pending_verifications' => 0,
    'gate_queue_pending' => 0,
    'open_incidents' => 0,
];

$customerMix = [];
$tripByCustomer = [];
$driverWorkload = [];
$recommendations = [];

$sql = "
    SELECT
        costumer,
        COUNT(*) AS booking_count,
        SUM(CASE WHEN quantity_use = 0 THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN booking_daterequired < ? THEN 1 ELSE 0 END) AS overdue_count,
        SUM(GREATEST(quantity - quantity_use, 0)) AS remaining_slots
    FROM booking
    WHERE status = 'Active'
      AND (quantity - quantity_use) > 0
      AND TRIM(costumer) <> ''
    GROUP BY costumer
    ORDER BY booking_count DESC, costumer ASC
";
$stmt = $conn->prepare($sql);
$stmt->execute([$today]);
$res = $stmt;
while ($row = $res->fetch()) {
    $entry = [
        'customer' => (string)$row['costumer'],
        'booking_count' => (int)($row['booking_count'] ?? 0),
        'pending_count' => (int)($row['pending_count'] ?? 0),
        'overdue_count' => (int)($row['overdue_count'] ?? 0),
        'remaining_slots' => (int)($row['remaining_slots'] ?? 0),
    ];
    $customerMix[] = $entry;
    $counts['active_bookings'] += $entry['booking_count'];
    $counts['pending_bookings'] += $entry['pending_count'];
    $counts['overdue_bookings'] += $entry['overdue_count'];
}
$sql = "
    SELECT
        COALESCE(NULLIF(TRIM(t.costumer), ''), NULLIF(TRIM(d.costumer), ''), 'Unknown') AS customer,
        COUNT(t.trip_id) AS total_trips,
        SUM(CASE WHEN t.trip_status = 'Done' THEN 1 ELSE 0 END) AS done_trips,
        SUM(CASE WHEN t.trip_status IS NULL OR t.trip_status <> 'Done' THEN 1 ELSE 0 END) AS pending_trips
    FROM trips t
    INNER JOIN dispatch d ON d.d_id = t.d_id
    GROUP BY COALESCE(NULLIF(TRIM(t.costumer), ''), NULLIF(TRIM(d.costumer), ''), 'Unknown')
    ORDER BY total_trips DESC, customer ASC
    LIMIT 12
";
$res = $conn->query($sql);
while ($row = $res->fetch()) {
    $tripByCustomer[] = [
        'customer' => (string)$row['customer'],
        'total_trips' => (int)($row['total_trips'] ?? 0),
        'done_trips' => (int)($row['done_trips'] ?? 0),
        'pending_trips' => (int)($row['pending_trips'] ?? 0),
    ];
}

$res = $conn->query("SELECT COUNT(*) AS c FROM dispatch WHERE workflow_stage IN ($quotedLiveStages)");
if ($res && ($row = $res->fetch())) {
    $counts['active_dispatches'] = (int)($row['c'] ?? 0);
}

$driverSql = "
    SELECT
        d.driver_id,
        CONCAT(d.driver_lname, ', ', d.driver_fname) AS driver_name,
        d.shift_truck,
        d.driver_assignSegment AS segment,
        d.last_seen_at,
        EXISTS(
            SELECT 1
            FROM violation_record v
            WHERE v.driver_id = d.driver_id
              AND v.vr_status = 'Active'
        ) AS violation_blocked,
        (
            SELECT COUNT(*)
            FROM dispatch dd
            WHERE dd.driver_id = d.driver_id
              AND dd.workflow_stage IN ($quotedLiveStages)
        ) AS active_trips
    FROM drivers d
    WHERE (
            EXISTS (
                SELECT 1
                FROM driver_shift s
                WHERE s.driver_id = d.driver_id
                  AND s.ended_at IS NULL
            )
         OR EXISTS (
                SELECT 1
                FROM drivers_attendance a
                WHERE a.driver_id = d.driver_id
                  AND a.da_date = '$todayEscaped'
                  AND a.da_status = 'Present'
            )
        )
      AND TRIM(COALESCE(d.shift_truck, '')) <> ''
    ORDER BY active_trips DESC, d.driver_lname ASC, d.driver_fname ASC
";
$res = $conn->query($driverSql);
while ($row = $res->fetch()) {
    $blocked = (int)($row['violation_blocked'] ?? 0) === 1;
    $activeTrips = (int)($row['active_trips'] ?? 0);
    $available = !$blocked && $activeTrips === 0;
    $driverWorkload[] = [
        'driver_id' => (int)$row['driver_id'],
        'driver_name' => (string)$row['driver_name'],
        'shift_truck' => (string)($row['shift_truck'] ?? ''),
        'segment' => (string)($row['segment'] ?? ''),
        'active_trips' => $activeTrips,
        'blocked' => $blocked,
        'available' => $available,
        'last_seen_at' => (string)($row['last_seen_at'] ?? ''),
    ];
    $counts['on_shift_drivers']++;
    if ($available) {
        $counts['available_drivers']++;
    }
}

$res = $conn->query("SELECT COUNT(*) AS c FROM dispatch WHERE workflow_stage = 'pending_verification'");
if ($res && ($row = $res->fetch())) {
    $counts['pending_verifications'] = (int)($row['c'] ?? 0);
}

$res = $conn->query("SELECT COUNT(*) AS c FROM gate_queue WHERE decision = 'pending'");
if ($res && ($row = $res->fetch())) {
    $counts['gate_queue_pending'] = (int)($row['c'] ?? 0);
}

$res = $conn->query("SELECT COUNT(*) AS c FROM incident WHERE status = 'open'");
if ($res && ($row = $res->fetch())) {
    $counts['open_incidents'] = (int)($row['c'] ?? 0);
}

if ($counts['pending_bookings'] > $counts['available_drivers']) {
    $recommendations[] = [
        'severity' => 'warning',
        'title' => 'Dispatch capacity is tight',
        'detail' => $counts['pending_bookings'] . ' pending bookings are waiting against only ' . $counts['available_drivers'] . ' available drivers.',
    ];
}

if ($counts['overdue_bookings'] > 0) {
    $recommendations[] = [
        'severity' => 'danger',
        'title' => 'Overdue bookings need attention',
        'detail' => $counts['overdue_bookings'] . ' active bookings are already past their required date.',
    ];
}

if ($counts['pending_verifications'] >= 3) {
    $recommendations[] = [
        'severity' => 'warning',
        'title' => 'Verification backlog is growing',
        'detail' => $counts['pending_verifications'] . ' trips are waiting for dispatch verification.',
    ];
}

if ($counts['gate_queue_pending'] >= 3) {
    $recommendations[] = [
        'severity' => 'warning',
        'title' => 'Gate queue is stacking up',
        'detail' => $counts['gate_queue_pending'] . ' gate requests are still pending.',
    ];
}

if (!empty($customerMix) && $counts['active_bookings'] > 0) {
    $top = $customerMix[0];
    $share = round(($top['booking_count'] / $counts['active_bookings']) * 100, 1);
    if ($share >= 45) {
        $recommendations[] = [
            'severity' => 'info',
            'title' => 'Demand is concentrated on ' . $top['customer'],
            'detail' => $top['customer'] . ' currently represents ' . $share . '% of active booking records.',
        ];
    }
}

if (empty($recommendations)) {
    $recommendations[] = [
        'severity' => 'success',
        'title' => 'Operations look balanced',
        'detail' => 'No immediate booking, gate, or verification pressure stands out right now.',
    ];
}

echo json_encode([
    'status' => 'success',
    'counts' => $counts,
    'customer_mix' => $customerMix,
    'trip_by_customer' => $tripByCustomer,
    'driver_workload' => $driverWorkload,
    'recommendations' => $recommendations,
    'fetched_at' => $nowManila->format('Y-m-d H:i:s'),
]);
