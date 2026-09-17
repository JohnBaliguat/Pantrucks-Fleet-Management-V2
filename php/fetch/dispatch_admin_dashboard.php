<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$manilaTz = new DateTimeZone('Asia/Manila');
$nowManila = new DateTimeImmutable('now', $manilaTz);

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatch Admin', 'Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

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
$today = $nowManila->format('Y-m-d');
$todayEscaped = pt_pg_escape($conn, $today);

$customerCounts = [];
$totalActiveBookings = 0;
$totalPendingBookings = 0;
$sql = "
    SELECT
        costumer,
        COUNT(*) AS booking_count,
        SUM(GREATEST(quantity - quantity_use, 0)) AS remaining_slots,
        SUM(CASE WHEN quantity_use = 0 THEN 1 ELSE 0 END) AS pending_count
    FROM booking
    WHERE status = 'Active'
      AND (quantity - quantity_use) > 0
      AND TRIM(costumer) <> ''
    GROUP BY costumer
    ORDER BY booking_count DESC, costumer ASC
";
$res = $conn->query($sql);
while ($row = $res->fetch()) {
    $bookingCount = (int)($row['booking_count'] ?? 0);
    $remainingSlots = (int)($row['remaining_slots'] ?? 0);
    $pendingCount = (int)($row['pending_count'] ?? 0);
    $customerCounts[] = [
        'customer' => (string)$row['costumer'],
        'booking_count' => $bookingCount,
        'remaining_slots' => $remainingSlots,
        'pending_count' => $pendingCount,
    ];
    $totalActiveBookings += $bookingCount;
    $totalPendingBookings += $pendingCount;
}

$totalTrucks = 0;
$activeTrucks = 0;
$totalTrailers = 0;
$activeTrailers = 0;
$truckRes = $conn->query("SELECT COUNT(*) AS total FROM units WHERE unit_type = 'truck'");
if ($truckRes && ($row = $truckRes->fetch())) {
    $totalTrucks = (int)($row['total'] ?? 0);
}
$truckActiveRes = $conn->query("
    SELECT COUNT(DISTINCT d_truck) AS active_count
    FROM dispatch
    WHERE workflow_stage IN ($quotedLiveStages)
      AND TRIM(COALESCE(d_truck, '')) <> ''
");
if ($truckActiveRes && ($row = $truckActiveRes->fetch())) {
    $activeTrucks = (int)($row['active_count'] ?? 0);
}
$truckUtilizationPct = $totalTrucks > 0 ? round(($activeTrucks / $totalTrucks) * 100, 1) : 0;

$trailerRes = $conn->query("SELECT COUNT(*) AS total FROM trailer");
if ($trailerRes && ($row = $trailerRes->fetch())) {
    $totalTrailers = (int)($row['total'] ?? 0);
}
$trailerActiveRes = $conn->query("
    SELECT COUNT(DISTINCT d_trailer) AS active_count
    FROM dispatch
    WHERE workflow_stage IN ($quotedLiveStages)
      AND TRIM(COALESCE(d_trailer, '')) <> ''
");
if ($trailerActiveRes && ($row = $trailerActiveRes->fetch())) {
    $activeTrailers = (int)($row['active_count'] ?? 0);
}
$trailerUtilizationPct = $totalTrailers > 0 ? round(($activeTrailers / $totalTrailers) * 100, 1) : 0;

$drivers = [];
$availableDrivers = 0;
$driverSql = "
    SELECT
        d.driver_id,
        CONCAT(d.driver_lname, ', ', d.driver_fname) AS driver_name,
        d.shift_truck,
        d.driver_assignSegment AS segment,
        d.last_lat,
        d.last_lng,
        d.last_seen_at,
        EXISTS(
            SELECT 1
            FROM violation_record v
            WHERE v.driver_id = d.driver_id
              AND v.vr_status = 'Active'
        ) AS violation_blocked,
        ad.d_id AS active_dispatch_id,
        ad.booking_no AS active_booking_no,
        ad.workflow_stage AS active_workflow_stage,
        COALESCE(at.trip_type, CONCAT('Dispatch #', ad.d_id)) AS current_trip_name,
        COALESCE(at.trip_from, '-') AS current_trip_from,
        COALESCE(at.trip_to, '-') AS current_trip_to
    FROM drivers d
    LEFT JOIN dispatch ad
        ON ad.d_id = (
            SELECT dd.d_id
            FROM dispatch dd
            WHERE dd.driver_id = d.driver_id
              AND dd.workflow_stage IN ($quotedLiveStages)
            ORDER BY dd.d_id DESC
            LIMIT 1
        )
    LEFT JOIN trips at
        ON at.trip_id = (
            SELECT tt.trip_id
            FROM trips tt
            WHERE tt.d_id = ad.d_id
            ORDER BY tt.trip_id DESC
            LIMIT 1
        )
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
    ORDER BY d.driver_lname ASC, d.driver_fname ASC
";
$driverRes = $conn->query($driverSql);
while ($row = $driverRes->fetch()) {
    $hasActiveDispatch = (int)($row['active_dispatch_id'] ?? 0) > 0;
    $blocked = (int)($row['violation_blocked'] ?? 0) === 1;
    $isAvailable = !$hasActiveDispatch && !$blocked;
    if ($isAvailable) {
        $availableDrivers++;
    }

    $lastLocation = 'No live GPS';
    if (is_numeric($row['last_lat']) && is_numeric($row['last_lng'])) {
        $lastLocation = number_format((float)$row['last_lat'], 5) . ', ' . number_format((float)$row['last_lng'], 5);
    }

    $currentTrip = 'Available';
    if ($blocked) {
        $currentTrip = 'Blocked by active violation';
    } elseif ($hasActiveDispatch) {
        $route = trim((string)$row['current_trip_from']) . ' - ' . trim((string)$row['current_trip_to']);
        $currentTrip = trim((string)$row['active_booking_no']) . ' | ' . trim((string)$row['current_trip_name']) . ' | ' . $route;
    }

    $drivers[] = [
        'driver_id' => (int)$row['driver_id'],
        'driver_name' => (string)$row['driver_name'],
        'shift_truck' => (string)$row['shift_truck'],
        'segment' => (string)($row['segment'] ?? ''),
        'last_location' => $lastLocation,
        'last_lat' => is_numeric($row['last_lat']) ? (float)$row['last_lat'] : null,
        'last_lng' => is_numeric($row['last_lng']) ? (float)$row['last_lng'] : null,
        'last_seen_at' => (string)($row['last_seen_at'] ?? ''),
        'available' => $isAvailable,
        'blocked' => $blocked,
        'workflow_stage' => (string)($row['active_workflow_stage'] ?? ''),
        'current_trip' => $currentTrip,
    ];
}

echo json_encode([
    'status' => 'success',
    'counts' => [
        'active_bookings' => $totalActiveBookings,
        'pending_bookings' => $totalPendingBookings,
        'customers_with_bookings' => count($customerCounts),
        'total_trucks' => $totalTrucks,
        'active_trucks' => $activeTrucks,
        'truck_utilization_pct' => $truckUtilizationPct,
        'total_trailers' => $totalTrailers,
        'active_trailers' => $activeTrailers,
        'trailer_utilization_pct' => $trailerUtilizationPct,
        'on_shift_drivers' => count($drivers),
        'available_drivers' => $availableDrivers,
    ],
    'customer_counts' => $customerCounts,
    'drivers' => $drivers,
    'fetched_at' => $nowManila->format('Y-m-d H:i:s'),
]);
