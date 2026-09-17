<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Visual', 'Admin', 'Dispatch Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$manilaTz = new DateTimeZone('Asia/Manila');
$nowManila = new DateTimeImmutable('now', $manilaTz);
$today = $nowManila->format('Y-m-d');

$fromDate = trim((string)($_GET['fromDate'] ?? ''));
$toDate = trim((string)($_GET['toDate'] ?? ''));

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

$bookingDateFilter = '';
$bookingTypes = 's';
$bookingParams = [$today];
if ($fromDate !== '' && $toDate !== '') {
    $bookingDateFilter = " AND DATE(booking_daterequired) BETWEEN ? AND ?";
    $bookingTypes .= 'ss';
    $bookingParams[] = $fromDate;
    $bookingParams[] = $toDate;
}

$dispatchDateFilter = '';
$dispatchTypes = '';
$dispatchParams = [];
if ($fromDate !== '' && $toDate !== '') {
    $dispatchDateFilter = " AND DATE(d.d_datetime) BETWEEN ? AND ?";
    $dispatchTypes = 'ss';
    $dispatchParams = [$fromDate, $toDate];
}

$incidentDateFilter = '';
$incidentTypes = '';
$incidentParams = [];
if ($fromDate !== '' && $toDate !== '') {
    $incidentDateFilter = " AND DATE(incident_date) BETWEEN ? AND ?";
    $incidentTypes = 'ss';
    $incidentParams = [$fromDate, $toDate];
}

$counts = [
    'active_bookings' => 0,
    'pending_bookings' => 0,
    'overdue_bookings' => 0,
    'available_drivers' => 0,
    'pending_verifications' => 0,
    'open_incidents' => 0,
    'gate_queue_pending' => 0,
    'active_dispatches' => 0,
];

$customerPressure = [];
$recommendations = [];

$sql = "
    SELECT
        costumer,
        COUNT(*) AS booking_count,
        SUM(CASE WHEN quantity_use = 0 THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN booking_dateRequired < ? THEN 1 ELSE 0 END) AS overdue_count
    FROM booking
    WHERE status = 'Active'
      AND (quantity - quantity_use) > 0
      AND TRIM(costumer) <> ''
      $bookingDateFilter
    GROUP BY costumer
    ORDER BY booking_count DESC, costumer ASC
";
$stmt = $conn->prepare($sql);
$stmt->execute($bookingParams);
$res = $stmt;
while ($row = $res->fetch()) {
    $entry = [
        'customer' => (string)$row['costumer'],
        'booking_count' => (int)($row['booking_count'] ?? 0),
        'pending_count' => (int)($row['pending_count'] ?? 0),
        'overdue_count' => (int)($row['overdue_count'] ?? 0),
    ];
    $customerPressure[] = $entry;
    $counts['active_bookings'] += $entry['booking_count'];
    $counts['pending_bookings'] += $entry['pending_count'];
    $counts['overdue_bookings'] += $entry['overdue_count'];
}
$sql = "
    SELECT COUNT(*) AS c
    FROM dispatch d
    WHERE d.workflow_stage IN ($quotedLiveStages)
    $dispatchDateFilter
";
$stmt = $conn->prepare($sql);
if ($dispatchTypes !== '') $stmt->execute($dispatchParams);
$counts['active_dispatches'] = (int)($stmt->fetch()['c'] ?? 0);
$sql = "
    SELECT COUNT(*) AS c
    FROM dispatch d
    WHERE d.workflow_stage = 'pending_verification'
    $dispatchDateFilter
";
$stmt = $conn->prepare($sql);
if ($dispatchTypes !== '') $stmt->execute($dispatchParams);
$counts['pending_verifications'] = (int)($stmt->fetch()['c'] ?? 0);
$sql = "
    SELECT COUNT(*) AS c
    FROM gate_queue q
    LEFT JOIN dispatch d ON d.d_id = q.d_id
    WHERE q.decision = 'pending'
    " . ($dispatchDateFilter !== '' ? " AND DATE(d.d_datetime) BETWEEN ? AND ?" : "");
$stmt = $conn->prepare($sql);
if ($dispatchTypes !== '') $stmt->execute($dispatchParams);
$counts['gate_queue_pending'] = (int)($stmt->fetch()['c'] ?? 0);
$sql = "SELECT COUNT(*) AS c FROM incident WHERE status = 'open' $incidentDateFilter";
$stmt = $conn->prepare($sql);
if ($incidentTypes !== '') $stmt->execute($incidentParams);
$counts['open_incidents'] = (int)($stmt->fetch()['c'] ?? 0);
$driverSql = "
    SELECT COUNT(*) AS available_count
    FROM drivers d
    WHERE (
            EXISTS (
                SELECT 1 FROM driver_shift s
                WHERE s.driver_id = d.driver_id
                  AND s.ended_at IS NULL
            )
         OR EXISTS (
                SELECT 1 FROM drivers_attendance a
                WHERE a.driver_id = d.driver_id
                  AND a.da_date = ?
                  AND a.da_status = 'Present'
            )
        )
      AND TRIM(COALESCE(d.shift_truck, '')) <> ''
      AND NOT EXISTS (
        SELECT 1 FROM violation_record v
        WHERE v.driver_id = d.driver_id
          AND v.vr_status = 'Active'
      )
      AND NOT EXISTS (
        SELECT 1 FROM dispatch dd
        WHERE dd.driver_id = d.driver_id
          AND dd.workflow_stage IN ($quotedLiveStages)
      )
";
$stmt = $conn->prepare($driverSql);
$stmt->execute([$today]);
$counts['available_drivers'] = (int)($stmt->fetch()['available_count'] ?? 0);
if ($counts['pending_bookings'] > $counts['available_drivers']) {
    $recommendations[] = [
        'severity' => 'warning',
        'title' => 'Pending bookings are outpacing driver capacity',
        'detail' => $counts['pending_bookings'] . ' pending bookings versus ' . $counts['available_drivers'] . ' available drivers. Consider reassignment, overtime, or staging dispatches by due date.',
    ];
}

if ($counts['overdue_bookings'] > 0) {
    $recommendations[] = [
        'severity' => 'danger',
        'title' => 'Overdue booking risk is present',
        'detail' => $counts['overdue_bookings'] . ' active bookings are already beyond required date and should be escalated before customer impact spreads.',
    ];
}

if ($counts['pending_verifications'] >= 3) {
    $recommendations[] = [
        'severity' => 'warning',
        'title' => 'Verification backlog may delay billing',
        'detail' => $counts['pending_verifications'] . ' dispatches are waiting for verification. Clearing this queue should speed up billing closure.',
    ];
}

if ($counts['open_incidents'] > 0) {
    $recommendations[] = [
        'severity' => 'info',
        'title' => 'Incident volume needs manager visibility',
        'detail' => $counts['open_incidents'] . ' open incidents are active in the selected window. Review whether they cluster by route, truck, or driver.',
    ];
}

if (!empty($customerPressure) && $counts['active_bookings'] > 0) {
    $top = $customerPressure[0];
    $share = round(($top['booking_count'] / $counts['active_bookings']) * 100, 1);
    if ($share >= 40) {
      $recommendations[] = [
          'severity' => 'info',
          'title' => 'Demand is concentrated on ' . $top['customer'],
          'detail' => $top['customer'] . ' accounts for ' . $share . '% of active booking records in the selected window. Protect capacity around this customer first.',
      ];
    }
}

if (empty($recommendations)) {
    $recommendations[] = [
        'severity' => 'success',
        'title' => 'Current operating window is balanced',
        'detail' => 'No major dispatch, booking, or verification pressure stands out in the selected range.',
    ];
}

echo json_encode([
    'status' => 'success',
    'counts' => $counts,
    'customer_pressure' => $customerPressure,
    'recommendations' => $recommendations,
    'fetched_at' => $nowManila->format('Y-m-d H:i:s'),
]);
