<?php
// Phase 13 — dispatch dashboard tiles feed.
//
// One request returns everything the tile dashboard needs:
//   • Available bookings (status = Active, qty remaining)
//   • Active-shift drivers (truck selected + open driver_shift)
//   • Trucks that aren't blocked, not assigned out, ready to roll
//
// Used by the polling/refresh on the dashboard so the page stays
// reactive without a separate request per panel.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';

pt_ensure_trailer_jackup_table($conn);

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

// Activate any scheduled driver violations that are now due, so a driver blocked
// by a schedule drops off the dispatchable list at the scheduled moment.
require_once __DIR__ . '/../lib/violation_auto_activate.php';
pt_activate_due_violations($conn);

// -- Bookings (available to dispatch) ----------------------------------
$bookings = [];
$sql = "SELECT b.booking_id, b.booking_no, b.booking_type, b.costumer, b.customer_segment,
               b.booking_sn, b.booking_do, b.return_location,
               b.container_seal, b.container, b.container_status,
               b.hauling_segment, b.hauling_type, b.trip_from, b.trip_to,
               b.quantity, b.quantity_use, b.booking_daterequired,
               (SELECT MIN(we.event_at) FROM workflow_event we
                  WHERE we.booking_no = b.booking_no AND we.stage = 'order_created') AS created_at
        FROM booking b
        WHERE b.status = 'Active' AND (b.quantity - b.quantity_use) > 0
        ORDER BY created_at DESC NULLS LAST, b.booking_id DESC
        LIMIT 200";
$res = $conn->query($sql);
while ($r = $res->fetch()) {
    $remaining = max(0, (int)$r['quantity'] - (int)$r['quantity_use']);
    $rawStatus = (string)($r['container_status'] ?? '');
    $lane = cl_normalise_lane($rawStatus);
    $boardStatus = $rawStatus !== ''
        ? $rawStatus
        : ($lane === 'loaded' ? 'Loaded' : 'Empty');

    // The dispatch board shows assignable inventory. If quantity still
    // remains, don't paint the tile as Pickup / On Trip / Delivered
    // just because one earlier leg already advanced the booking-wide
    // lifecycle. Keep the card in its base lane colour instead.
    if ($remaining > 0) {
        $boardStatus = $lane === 'loaded' ? 'Loaded' : 'Empty';
    }

    $bookings[] = [
        'booking_id'           => (int)$r['booking_id'],
        'booking_no'           => $r['booking_no'],
        'booking_type'         => $r['booking_type'],
        'costumer'             => $r['costumer'],
        'customer_segment'     => $r['customer_segment'],
        'booking_sn'           => $r['booking_sn'],
        'booking_do'           => $r['booking_do'],
        'return_location'      => $r['return_location'],
        'container_seal'       => $r['container_seal'],
        'container'            => $r['container'],
        'container_status'     => $boardStatus,
        'raw_container_status' => $rawStatus ?: 'Empty',
        'hauling_segment'      => $r['hauling_segment'],
        // Trailer + genset are required for most hauling types, but Reefer
        // Van trucks are self-contained — the dispatch board uses this to
        // make those fields optional in the assign modal.
        'hauling_type'         => $r['hauling_type'] ?? '',
        'trip_from'            => $r['trip_from'],
        'trip_to'              => $r['trip_to'],
        'remaining'            => $remaining,
        'quantity'             => (int)$r['quantity'],
        'quantity_use'         => (int)$r['quantity_use'],
        'booking_daterequired' => $r['booking_daterequired'],
        'created_at'           => $r['created_at'],
    ];
}

// -- Customer summary cards --------------------------------------------
$customerStats = [];
$sql = "SELECT b.booking_no, b.costumer, b.quantity, b.quantity_use, b.status,
               (
                   SELECT d.workflow_stage
                   FROM dispatch d
                   WHERE d.booking_no = b.booking_no
                   ORDER BY d.d_id DESC
                   LIMIT 1
               ) AS latest_workflow
        FROM booking b
        WHERE b.costumer <> ''
        ORDER BY b.costumer ASC, b.booking_id ASC";
$res = $conn->query($sql);
while ($r = $res->fetch()) {
    $customer = trim((string)$r['costumer']);
    if ($customer === '') continue;

    if (!isset($customerStats[$customer])) {
        $customerStats[$customer] = [
            'customer'   => $customer,
            'available'  => 0,
            'pending'    => 0,
            'complete'   => 0,
            'served'     => 0,
            'not_served' => 0,
        ];
    }

    $quantity   = (int)($r['quantity'] ?? 0);
    $used       = (int)($r['quantity_use'] ?? 0);
    $remaining  = max(0, $quantity - $used);
    $status     = (string)($r['status'] ?? '');
    $workflow   = (string)($r['latest_workflow'] ?? '');

    if ($remaining > 0) {
        $customerStats[$customer]['available'] += $remaining;
    }

    $inFlight = in_array($workflow, ['dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','delivered','pending_verification'], true);
    if ($inFlight) {
        $customerStats[$customer]['pending']++;
    }

    // Serve / Not-serve totals for the booking-panel customer cards. Counted
    // over bookings still "in play" — active (has remaining) OR currently
    // in-flight (already assigned but the trip isn't done yet). Fully-served
    // bookings stay counted while their trip is on the road, instead of
    // vanishing the moment they leave the available list.
    if (strcasecmp($status, 'Active') === 0 || $inFlight) {
        $customerStats[$customer]['served']     += $used;
        $customerStats[$customer]['not_served'] += $remaining;
    }

    if (
        in_array($workflow, ['pod_captured','billing_closed','client_notified'], true) ||
        ($remaining === 0 && strcasecmp($status, 'Complete') === 0)
    ) {
        $customerStats[$customer]['complete']++;
    }
}
$customerStats = array_values($customerStats);

// -- Drivers on active shift ------------------------------------------
$drivers = [];
$sql = "SELECT d.driver_id,
               CONCAT(d.driver_lname, ', ', d.driver_fname) AS driver_name,
               d.shift_truck,
               d.driver_assignsegment AS segment,
               d.driver_status,
               EXISTS(
                   SELECT 1
                   FROM violation_record v
                   WHERE v.driver_id = d.driver_id
                     AND v.vr_status = 'Active'
               ) AS violation_blocked,
               tu.unit_assigntrailer AS assigned_trailer,
               tu.unit_assigngenset AS assigned_genset,
               (
                   SELECT dd.d_id
                   FROM dispatch dd
                   WHERE dd.driver_id = d.driver_id
                     AND dd.workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')
                   ORDER BY dd.d_id DESC
                   LIMIT 1
               ) AS latest_active_dispatch_id,
               d.last_lat, d.last_lng, d.last_seen_at,
               (SELECT s.started_at FROM driver_shift s
                  WHERE s.driver_id = d.driver_id AND s.ended_at IS NULL
                  ORDER BY s.ds_id DESC LIMIT 1) AS shift_open_since,
               (SELECT COUNT(*)
                  FROM dispatch dd
                  WHERE dd.driver_id = d.driver_id
                    AND dd.workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')
               ) AS active_trips
        FROM drivers d
        LEFT JOIN units tu ON tu.unit_name = d.shift_truck
        WHERE EXISTS (
                SELECT 1 FROM driver_shift s
                WHERE s.driver_id = d.driver_id AND s.ended_at IS NULL
              )
          AND TRIM(d.shift_truck) <> ''
        ORDER BY d.driver_lname ASC";
$res = $conn->query($sql);
while ($r = $res->fetch()) {
    $violationBlocked = (int)($r['violation_blocked'] ?? 0) === 1;
    $assignedTrailer = trim((string)($r['assigned_trailer'] ?? ''));
    $assignedGenset = trim((string)($r['assigned_genset'] ?? ''));
    $latestActiveDispatchId = (int)($r['latest_active_dispatch_id'] ?? 0);
    $assignedTrailerJackedUp = false;

    if ($assignedTrailer !== '' && $latestActiveDispatchId > 0) {
        $stmtJackup = $conn->prepare(
            "SELECT tj_id
             FROM trailer_jackup
             WHERE d_id = ? AND trailer_code = ?
             ORDER BY tj_id DESC
             LIMIT 1"
        );
        $stmtJackup->execute([$latestActiveDispatchId, $assignedTrailer]);
        $assignedTrailerJackedUp = (bool)$stmtJackup->fetch();
}

    $drivers[] = [
        'driver_id'        => (int)$r['driver_id'],
        'driver_name'      => $r['driver_name'],
        'shift_truck'      => $r['shift_truck'],
        'segment'          => $r['segment'],
        'assigned_trailer' => $assignedTrailer,
        'assigned_genset'  => $assignedGenset,
        'assigned_trailer_jacked_up' => $assignedTrailerJackedUp,
        'driver_status'    => $r['driver_status'],
        // Most recent active dispatch id — used to sort the "On Trip" list
        // latest-first (a higher d_id means a more recently assigned trip).
        'latest_active_dispatch_id' => $latestActiveDispatchId,
        'violation_blocked'=> $violationBlocked,
        'has_gps'          => is_numeric($r['last_lat']) && is_numeric($r['last_lng']),
        'last_seen_at'     => $r['last_seen_at'],
        'shift_open_since' => $r['shift_open_since'],
        'active_trips'     => (int)$r['active_trips'],
        'last_lat'         => is_numeric($r['last_lat']) ? (float)$r['last_lat'] : null,
        'last_lng'         => is_numeric($r['last_lng']) ? (float)$r['last_lng'] : null,
    ];
}

// -- Drivers NOT on active shift --------------------------------------
// Off-shift = the exact complement of the dispatchable (on-shift) list above,
// which requires an open shift AND a chosen truck. So this includes both
// drivers with no open shift AND drivers stuck with an open shift but no truck
// (an orphan/never-ended shift) — otherwise those drivers vanish from both
// lists. The dispatcher can still assign them (a truck is entered to start a
// fresh shift).
$offShiftDrivers = [];
$sqlOff = "SELECT d.driver_id,
                  CONCAT(d.driver_lname, ', ', d.driver_fname) AS driver_name,
                  d.driver_assignsegment AS segment,
                  d.driver_status,
                  d.shift_truck,
                  d.last_lat, d.last_lng, d.last_seen_at
           FROM drivers d
           WHERE NOT (
                   EXISTS (
                     SELECT 1 FROM driver_shift s
                     WHERE s.driver_id = d.driver_id AND s.ended_at IS NULL
                   )
                   AND TRIM(COALESCE(d.shift_truck, '')) <> ''
                 )
           ORDER BY d.driver_lname ASC";
$resOff = $conn->query($sqlOff);
while ($r = $resOff->fetch()) {
    $offShiftDrivers[] = [
        'driver_id'     => (int)$r['driver_id'],
        'driver_name'   => $r['driver_name'],
        'segment'       => $r['segment'],
        'driver_status' => $r['driver_status'],
        'shift_truck'   => trim((string)($r['shift_truck'] ?? '')),
        'has_gps'       => is_numeric($r['last_lat']) && is_numeric($r['last_lng']),
        'last_seen_at'  => $r['last_seen_at'],
    ];
}

// -- Available trucks --------------------------------------------------
// "Available" here means: a truck unit, status 'good', not maintenance
// blocked, no driver assigned yet. Trucks already picked up by a driver
// at shift-start are NOT in this list — they live on the driver tile.
// When the admin allows dispatching in-use equipment, in-use trucks
// (status 'Dispatch' / already assigned) are included too so they can be
// re-selected in the assign modal.
require_once __DIR__ . '/../helpers/settings_helper.php';
$allowInUseEquip = pt_setting_bool($conn, 'allow_inuse_equipment', false);
$trucks = [];
// LOWER(unit_status) — Postgres is case-sensitive and data is stored as 'Good'.
$truckWhere = $allowInUseEquip
    ? "LOWER(unit_status) IN ('good', 'dispatch')"
    : "LOWER(unit_status) = 'good' AND (driver_id = 0 OR driver_id IS NULL)";
$sql = "SELECT unit_id, unit_name, unit_status, unit_assign, driver_id
        FROM units
        WHERE unit_type = 'truck'
          AND maintenance_blocked = FALSE
          AND $truckWhere
        ORDER BY unit_name ASC";
$res = $conn->query($sql);
while ($r = $res->fetch()) {
    $trucks[] = [
        'unit_id'   => (int)$r['unit_id'],
        'unit_name' => $r['unit_name'],
    ];
}

echo json_encode([
    'status'         => 'success',
    'bookings'       => $bookings,
    'drivers'        => $drivers,
    'off_shift_drivers' => $offShiftDrivers,
    'trucks'         => $trucks,
    'customer_stats' => $customerStats,
    'counts'         => [
        'bookings'      => count($bookings),
        'drivers'       => count($drivers),
        'off_shift_drivers' => count($offShiftDrivers),
        'trucks'        => count($trucks),
        'total_drivers' => (int)$conn->query("SELECT COUNT(*) AS total FROM drivers")->fetch()['total'],
    ],
    'fetched_at' => date('Y-m-d H:i:s'),
]);
