<?php
// Phase 14 — active container tracking feed.
//
// Returns one row per dispatch transaction in the in-flight tracking
// stages. A single booking number can have multiple dispatch rows, and
// the tracking page must show each transaction separately instead of
// collapsing them down to only the latest dispatch.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Booker', 'Admin', 'Visual'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

// Human-friendly pending-time format. < 1h → "8m", < 1d → "2hrs" or
// "2hrs 30m", ≥ 1d → "1d 16hrs". Keeps stuck-trip durations readable
// even when something has been pending for days.
if (!function_exists('pt_format_pending_minutes')) {
    function pt_format_pending_minutes(int $mins): string {
        $mins = max(0, $mins);
        if ($mins < 60) return $mins . 'm';
        if ($mins < 1440) {
            $h = intdiv($mins, 60);
            $m = $mins % 60;
            return $m === 0 ? $h . 'hrs' : $h . 'hrs ' . $m . 'm';
        }
        $d = intdiv($mins, 1440);
        $h = intdiv($mins % 1440, 60);
        return $h === 0 ? $d . 'd' : $d . 'd ' . $h . 'hrs';
    }
}

$segment = trim($_GET['segment'] ?? '');
$customer = trim($_GET['customer'] ?? '');
$status   = trim($_GET['status'] ?? '');
// Tab filter: 'active' (default) shows in-flight bookings; 'complete' shows finished ones.
$view     = strtolower(trim($_GET['view'] ?? 'active'));
// 'active' | 'complete_empty' | 'complete_loaded' (legacy 'complete' = all completed).
if (!in_array($view, ['active', 'complete', 'complete_empty', 'complete_loaded'], true)) {
    $view = 'active';
}
$isCompleteView = in_array($view, ['complete', 'complete_empty', 'complete_loaded'], true);

// Join every dispatch row for the booking. Each dispatch can still have
// multiple trips, so we resolve the latest trip inside that dispatch
// for display.
$sql = "
SELECT
    b.booking_no,
    b.booking_sn,
    b.booking_type,
    b.customer_segment,
    b.costumer,
    b.container_seal,
    b.container,
    b.container_status,
    b.status AS booking_status,
    b.return_location,
    COALESCE(t.trip_from, b.trip_from) AS trip_from,
    COALESCE(t.trip_to, b.trip_to) AS trip_to,
    b.booking_daterequired,
    d.d_id,
    d.d_tripreceipt,
    d.d_truck,
    d.d_trailer,
    d.d_genset,
    d.d_drivername,
    d.driver_id,
    d.workflow_stage,
    d.workflow_updated_at,
    t.trip_container,
    EXISTS(
        SELECT 1
        FROM workflow_event we
        WHERE we.booking_no = b.booking_no
          AND we.stage = 'loaded_ready'
          AND we.notes LIKE CONCAT('%parent_d_id=', d.d_id, '%')
        LIMIT 1
    ) AS has_loaded_child,
    EXISTS(
        SELECT 1
        FROM workflow_event we
        WHERE we.booking_no = b.booking_no
          AND we.stage = 'cth_empty_return_ready'
          AND we.notes LIKE CONCAT('%parent_d_id=', d.d_id, '%')
        LIMIT 1
    ) AS has_cth_empty_return_child,
    -- Position: prefer the truck's hardware Geotab fix, fall back to the
    -- driver-phone heartbeat when the unit has no device (or none yet).
    COALESCE(u.last_lat, drv.last_lat)              AS pos_lat,
    COALESCE(u.last_lng, drv.last_lng)              AS pos_lng,
    COALESCE(u.last_position_at, drv.last_seen_at)  AS pos_at,
    CASE WHEN u.last_position_at IS NOT NULL THEN 'geotab' ELSE 'phone' END AS pos_source
FROM booking b
JOIN dispatch d
     ON d.booking_no = b.booking_no
-- Represent each dispatch by its booking leg ('Trip 1'), whose from/to/container
-- match the booking. Multi-leg trip tickets add extra legs (Trip 2..N) with
-- higher trip_id, so a plain MAX(trip_id) would wrongly surface a
-- repositioning/return leg here. Fall back to the newest trip only when a
-- dispatch has no 'Trip 1' at all.
LEFT JOIN trips t
     ON t.trip_id = (
         SELECT t2.trip_id FROM trips t2
         WHERE t2.d_id = d.d_id
         ORDER BY (t2.trip_type = 'Trip 1') DESC, t2.trip_id DESC
         LIMIT 1
     )
LEFT JOIN drivers drv ON drv.driver_id = d.driver_id
-- The app keys trucks by unit_name; dispatch.d_truck holds that same name.
-- unit_name isn't unique (a few duplicate rows exist), so pick ONE row per
-- name — the freshest-positioned — to avoid doubling tracking rows.
LEFT JOIN (
    SELECT DISTINCT ON (unit_name) unit_name, last_lat, last_lng, last_position_at
    FROM units
    ORDER BY unit_name, last_position_at DESC NULLS LAST
) u ON u.unit_name = d.d_truck
WHERE 1=1
";

// Per-dispatch lifecycle stages that produce a "<Lane> Container Delivered"
// display status (see the display_status mapping below). The tab split is
// driven by these stages, not the booking-level status — a multi-quantity
// booking can be partially complete with one dispatch still in flight, and
// each dispatch row should land in the right tab on its own merits.
$completedStages = "'pod_captured', 'billing_closed', 'client_notified'";

if ($isCompleteView) {
    $sql .= " AND d.workflow_stage IN ($completedStages)";
} else {
    // Active tab: any dispatch that hasn't reached the delivered+POD stages.
    // Also exclude 'recalled' — dispatcher pulled it back, no longer in flight.
    $sql .= "
        AND d.workflow_stage NOT IN ($completedStages)
        AND d.workflow_stage <> 'recalled'
        AND (
            d.workflow_stage IN ('dispatcher_assigned', 'reassigned', 'driver_accepted', 'gate_cleared', 'en_route', 'delivered', 'pending_verification')
         OR b.container_status LIKE '% Pickup'
         OR b.container_status LIKE '% On Trip'
         OR b.container_status LIKE '% Delivered'
        )
    ";
}

// Age cutoff — hide records whose dispatch is more than 15 days old, so the
// board stays focused on recent transactions (this also trims the payload /
// Supabase egress). Change the interval below to widen or narrow the window.
//
// Only apply this to the COMPLETED views. An in-flight (active) booking must
// stay visible no matter how old it is — otherwise a trip stuck for 15+ days
// (e.g. never accepted, or accepted but never picked up) silently vanishes
// from tracking exactly when someone needs to find it. This matches the
// dispatch board's driver modal (driver_active_dispatches.php), which shows
// every in-flight dispatch regardless of age. Active rows are naturally
// bounded by the fleet's in-flight capacity, so payload stays small.
if ($isCompleteView) {
    $sql .= " AND d.d_datetime >= NOW() - INTERVAL '15 days'";
}

$params = [];
if ($segment !== '') {
    $sql .= " AND b.customer_segment = ?";
    $params[] = $segment;
}
if ($customer !== '') {
    $sql .= " AND b.costumer = ?";
    $params[] = $customer;
}

// No row cap — the quick-search on this screen filters CLIENT-SIDE over the rows
// this endpoint returns, so every matching transaction must be sent or it would
// be invisible/unsearchable. Egress is controlled instead via the 60s refresh +
// pause-when-hidden. (If the result set grows large, switch the quick-search to
// server-side so we can page/limit without hiding data.)
$sql .= " ORDER BY d.workflow_updated_at DESC, d.d_id DESC, b.booking_daterequired ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$res = $stmt;

$rows = [];
while ($r = $res->fetch()) {
    $workflowStage = (string)($r['workflow_stage'] ?? '');
    $bookingStatus = (string)($r['container_status'] ?? '');
    $tripContainer = trim((string)($r['trip_container'] ?? ''));
    $lane = cl_normalise_lane($bookingStatus);
    $laneLabel = $lane === 'loaded' ? 'Loaded' : 'Empty';
    $displayStatus = $bookingStatus;

    // Split the completed view into Empty vs Loaded tabs.
    if ($view === 'complete_empty'  && $lane === 'loaded') { continue; }
    if ($view === 'complete_loaded' && $lane !== 'loaded') { continue; }

    // Elapsed minutes since the last workflow change — used both for the
    // "To Be Accepted" suffix and the per-row pending_minutes field so the
    // front-end can colour stuck rows.
    $pendingMinutes = null;
    if (!empty($r['workflow_updated_at'])) {
        $ts = strtotime((string)$r['workflow_updated_at']);
        if ($ts !== false) $pendingMinutes = (int)round((time() - $ts) / 60);
    }

    if (in_array($workflowStage, ['dispatcher_assigned', 'reassigned'], true)) {
        $displayStatus = 'To Be Accepted';
        if ($pendingMinutes !== null && $pendingMinutes > 0) {
            $displayStatus .= ' · ' . pt_format_pending_minutes($pendingMinutes);
        }
    } elseif ($workflowStage === 'driver_declined') {
        $displayStatus = 'Declined';
    } elseif ($workflowStage === 'driver_accepted' && $tripContainer === '') {
        $displayStatus = 'Accepted by Driver';
    } elseif (in_array($workflowStage, ['driver_accepted', 'gate_cleared'], true)) {
        $displayStatus = $laneLabel . ' Container Pickup';
    } elseif ($workflowStage === 'en_route') {
        $displayStatus = $laneLabel . ' Container On Trip';
    } elseif ($workflowStage === 'delivered') {
        $displayStatus = 'Awaiting POD Submission';
    } elseif ($workflowStage === 'pending_verification') {
        $displayStatus = 'To be Verified by Dispatch';
    } elseif (in_array($workflowStage, ['pod_captured', 'billing_closed', 'client_notified'], true)) {
        $displayStatus = $laneLabel . ' Container Delivered';
    } elseif ($tripContainer !== '') {
        $displayStatus = $laneLabel . ' Container Pickup';
    }

    if ($status !== '' && $displayStatus !== $status && $bookingStatus !== $status) {
        continue;
    }

    $isReadOnlyTrackingRole = ($role === 'Booker');
    $canManualCompleteRole = in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true);
    $rows[] = [
        'booking_no'          => $r['booking_no'],
        'booking_sn'          => $r['booking_sn'],
        'booking_type'        => $r['booking_type'],
        'customer'            => $r['costumer'],
        'customer_segment'    => $r['customer_segment'],
        'container_seal'      => $r['container_seal'],
        'container'           => $tripContainer !== '' ? $tripContainer : $r['container'],
        'container_status'    => $bookingStatus,
        'booking_status'      => $r['booking_status'],
        'display_status'      => $displayStatus,
        'trip_from'           => $r['trip_from'],
        'trip_to'             => $r['trip_to'],
        'return_location'     => $r['return_location'],
        'required'            => $r['booking_daterequired'],
        'd_id'                => (int)$r['d_id'],
        'trip_receipt'        => $r['d_tripreceipt'],
        'truck'               => $r['d_truck'],
        'trailer'             => $r['d_trailer'],
        'genset'              => $r['d_genset'],
        'driver_name'         => $r['d_drivername'],
        'workflow_stage'      => $r['workflow_stage'],
        'workflow_updated_at' => $r['workflow_updated_at'],
        // Minutes since the last stage transition — front-end uses this to
        // colour 'dispatcher_assigned' rows red after 5 minutes.
        'pending_minutes'     => $pendingMinutes,
        'can_edit_tracking'   => (!$isReadOnlyTrackingRole) && in_array($workflowStage, ['dispatcher_assigned', 'reassigned', 'driver_accepted', 'gate_cleared', 'en_route'], true),
        'can_manual_complete' => $canManualCompleteRole && in_array($workflowStage, ['driver_accepted', 'gate_cleared', 'en_route', 'delivered', 'pending_verification'], true),
        'can_mark_loaded'     => (!$isReadOnlyTrackingRole) && (
            $lane === 'empty' &&
            in_array($workflowStage, ['pod_captured', 'billing_closed', 'client_notified'], true) &&
            (int)$r['has_loaded_child'] === 0
        ),
        'can_mark_cth_empty_return' => (!$isReadOnlyTrackingRole) && (
            pt_customer_is_import($conn, (string)$r['costumer']) &&
            $lane === 'loaded' &&
            trim((string)($r['return_location'] ?? '')) !== '' &&
            in_array($workflowStage, ['pod_captured', 'billing_closed', 'client_notified'], true) &&
            (int)$r['has_cth_empty_return_child'] === 0
        ),
        'last_lat'            => is_numeric($r['pos_lat']) ? (float)$r['pos_lat'] : null,
        'last_lng'            => is_numeric($r['pos_lng']) ? (float)$r['pos_lng'] : null,
        'last_seen_at'        => $r['pos_at'],
        // 'geotab' = hardware fix from the truck's GO device; 'phone' = driver
        // PWA heartbeat fallback. Front-end can badge the marker accordingly.
        'pos_source'          => $r['pos_source'],
    ];
}
// Pull distinct segment / customer / status for the filter dropdowns.
$facets = ['segments' => [], 'customers' => [], 'statuses' => []];
$res = $conn->query("SELECT DISTINCT customer_segment FROM booking WHERE customer_segment <> '' ORDER BY customer_segment");
while ($r = $res->fetch()) $facets['segments'][] = $r['customer_segment'];
$res = $conn->query("SELECT DISTINCT costumer FROM booking WHERE costumer <> '' ORDER BY costumer");
while ($r = $res->fetch()) $facets['customers'][] = $r['costumer'];
$facets['statuses'] = [
    'To Be Accepted', 'Accepted by Driver',
    'Empty Container Pickup', 'Empty Container On Trip', 'Empty Container Delivered',
    'Loaded Container Pickup', 'Loaded Container On Trip', 'Loaded Container Delivered',
    'Awaiting POD Submission', 'To be Verified by Dispatch',
];

echo json_encode([
    'status'     => 'success',
    'rows'       => $rows,
    'count'      => count($rows),
    'fetched_at' => date('Y-m-d H:i:s'),
    'facets'     => $facets,
]);
