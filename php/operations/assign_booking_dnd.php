<?php
// Phase 13 — drag-and-drop dispatch assignment.
//
// This is the lean assign path used by the tile dashboard:
//   • Driver MUST be on an open shift with a chosen truck (the driver
//     "brings their own truck" — no separate truck pick on the
//     dispatcher side).
//   • Dispatcher drags the booking onto the driver tile; we commit a
//     dispatch row, a Trip 1, bump the container_status to its Pickup
//     stage, advance the workflow stage to dispatcher_assigned, and
//     fire the workflow_event audit.
//   • Trailer / genset stay optional and can be filled later in the
//     dispatch detail view; this keeps the dashboard click-fast.
//
// This is intentionally separate from the legacy
// `assign_booking.php` flow (which still serves the old screens) — the
// dispatcher dashboard tiles call this one, and Phase 14 will retire
// the old endpoint once the new flow is verified live.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/container_lifecycle.php';
require_once __DIR__ . '/../helpers/settings_helper.php';
require_once __DIR__ . '/../helpers/activity_log_helper.php';

function ensureDispatchColumn(PDO $conn, string $column, string $definition): void {
    pt_ensure_column($conn, 'dispatch', $column, $definition);
}

// Validates that $truck is a free, good, non-blocked truck unit the given
// driver may use. Throws on any problem. Used when starting a shift for an
// off-shift driver and when re-selecting a unit for an on-shift driver.
function dnd_assert_truck_available(PDO $conn, string $truck, int $driverId, bool $allowInUse = false): void {
    $stmt = $conn->prepare(
        "SELECT unit_status, maintenance_blocked, dispatch_blocked, driver_id
         FROM units
         WHERE unit_name = ? AND unit_type = 'truck'
         LIMIT 1"
    );
    $stmt->execute([$truck]);
    $row = $stmt->fetch();
    if (!$row)                                              throw new Exception("Truck '{$truck}' not found.");
    if ((int)$row['maintenance_blocked'] === 1)            throw new Exception("Truck '{$truck}' is maintenance-blocked.");
    if ((int)($row['dispatch_blocked'] ?? 0) === 1)        throw new Exception("Truck '{$truck}' is blocked by Dispatch Admin and cannot be assigned.");
    if (in_array($row['unit_status'], ['Rescue', 'Shop Unit'], true)) {
        throw new Exception("Truck '{$truck}' is unavailable: " . $row['unit_status']);
    }
    // When in-use equipment is allowed, accept a truck that's on a trip
    // ('Dispatch') and skip the "assigned to another driver" block.
    if (!$allowInUse) {
        if (strcasecmp((string)$row['unit_status'], 'good') !== 0) {
            throw new Exception("Truck '{$truck}' is not available (status: {$row['unit_status']}).");
        }
        if ((int)($row['driver_id'] ?? 0) !== 0 && (int)$row['driver_id'] !== $driverId) {
            throw new Exception("Truck '{$truck}' is already assigned to another driver.");
        }
    }
}

pt_ensure_trailer_jackup_table($conn);

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$booking_no   = trim($_POST['booking_no']   ?? '');
$driver_id    = (int)($_POST['driver_id']   ?? 0);
$trip_receipt = preg_replace('/\D+/', '', trim($_POST['trip_receipt'] ?? ''));
$hauling_segment = trim($_POST['hauling_segment'] ?? '');
$cth_eir      = trim($_POST['cth_eir'] ?? '');
$trailer      = trim($_POST['trailer']      ?? '');
$genset       = trim($_POST['genset']       ?? '');
// Trip origin (pre-leg displayed on the trip ticket above the booking leg):
//   PTSI    → origin label = "PTSI"
//   CONSOL  → origin label = "CONSOL"
//   OUTSIDE → origin label = whatever the dispatcher typed (truck's last location)
$trip_origin_code  = strtoupper(trim($_POST['trip_origin_code']  ?? ''));
$trip_origin_label = trim($_POST['trip_origin_label'] ?? '');
if (!in_array($trip_origin_code, ['PTSI', 'CONSOL', 'OUTSIDE'], true)) {
    $trip_origin_code = '';
}
if ($trip_origin_code === 'PTSI' || $trip_origin_code === 'CONSOL') {
    // Force the canonical label for the two fixed origins — ignore client tampering.
    $trip_origin_label = $trip_origin_code;
}
// Trailer hauled on the origin/pre-leg leg. Optional — blank means the truck
// ran bobtail to the pickup. Printed on the pre-leg row of the trip ticket.
$origin_trailer = trim($_POST['origin_trailer'] ?? '');
// Truck for an OFF-SHIFT driver. When the dispatcher assigns a driver who has
// no open shift, they enter the truck here; we auto-start a shift on that truck
// before committing the assignment. Ignored when the driver is already on shift.
$shift_truck = strtoupper(trim($_POST['shift_truck'] ?? ''));
// Optional container number entered by the dispatcher. When provided it sets
// the container for this dispatch/Trip 1, overriding the booking's value
// (useful for bookings created without a container number).
$container_no = strtoupper(trim($_POST['container_no'] ?? ''));
// Optional dispatcher-entered pickup location — overrides the booking's trip_from.
$trip_from_override = trim($_POST['trip_from'] ?? '');
// Multi-leg trip ticket — an ordered array of trip legs built in the
// Confirm Assignment modal (trip-ticket style). Each leg:
//   { from, to, hauling_seg, hauling_job, trailer, genset, container_stat, is_booking_leg }
// The booking leg (is_booking_leg) is persisted as 'Trip 1' (all downstream
// code keys off that); the other legs become 'Trip 2'..'Trip N'. `trip_seq`
// carries the dragged order so the printed ticket renders legs in run order
// even when the booking leg isn't first. When `rows` is absent we fall back
// to the classic single-leg behaviour (with the d_origin pre-leg).
$rows = [];
$rowsRaw = $_POST['rows'] ?? '';
if ($rowsRaw !== '') {
    $decoded = json_decode($rowsRaw, true);
    if (is_array($decoded)) { $rows = $decoded; }
}
$hasRows = !empty($rows);
// Per-assignment override — dispatch a PM truck with no trailer (bobtail).
$no_trailer = (string)($_POST['no_trailer'] ?? '') === '1';
if ($no_trailer) { $trailer = ''; }
// Per-assignment override — dispatch with no genset.
$no_genset = (string)($_POST['no_genset'] ?? '') === '1';
if ($no_genset) { $genset = ''; }
// Admin toggle — allow assigning a truck/trailer/genset already in use on
// another driver/trip. When off, the "already assigned" guards apply.
$allowInUseEquip = pt_setting_bool($conn, 'allow_inuse_equipment', false);

if ($booking_no === '' || $driver_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'booking_no and driver_id are required.']);
    exit;
}
if (!preg_match('/^\d{6}$/', $trip_receipt)) {
    echo json_encode(['status' => 'error', 'message' => 'Trip Receipt must be exactly 6 numbers.']);
    exit;
}
// Van no (container) — optional, but when given must be 4 letters + 7 digits.
if ($container_no !== '' && !preg_match('/^[A-Z]{4}[0-9]{7}$/', $container_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Van no must be 4 letters followed by 7 numbers (e.g. MSCU1234567).']);
    exit;
}
// Trip Origin is only required on the classic single-leg path. With the
// multi-leg trip ticket, a pre-leg (repositioning) is just another row.
if (!$hasRows) {
    if ($trip_origin_code === '') {
        echo json_encode(['status' => 'error', 'message' => 'Trip Origin (PTSI / CONSOL / OUTSIDE) is required.']);
        exit;
    }
    if ($trip_origin_label === '') {
        echo json_encode(['status' => 'error', 'message' => 'Trip Origin label is required (type the last location for OUTSIDE).']);
        exit;
    }
}

$dispatcherId   = (int)($_SESSION['user_id'] ?? 0);
$dispatcherName = 'system';
if ($dispatcherId > 0) {
    // Stored on the dispatch row so the printed receipt can show the full
    // "First Middle Last" name (and not just a session id or a username).
    $stmtName = $conn->prepare("SELECT user_fname, user_mname, user_lname FROM \"user\" WHERE user_id = ? LIMIT 1");
    $stmtName->execute([$dispatcherId]);
    $uRow = $stmtName->fetch();
if ($uRow) {
        $parts = array_filter([
            trim((string)$uRow['user_fname']),
            trim((string)$uRow['user_mname']),
            trim((string)$uRow['user_lname']),
        ], 'strlen');
        if (!empty($parts)) $dispatcherName = implode(' ', $parts);
    }
}
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');

ensureDispatchColumn($conn, 'cth_eirout', "VARCHAR(255) NOT NULL DEFAULT ''");
ensureDispatchColumn($conn, 'cth_eirin', "VARCHAR(255) NOT NULL DEFAULT ''");
// Per-assignment reference shown on the dispatch receipt (Jollibee-style ticket).
// Format: <booking_no>-<container_no | EV<n>>
ensureDispatchColumn($conn, 'dispatch_ref', "VARCHAR(160) NOT NULL DEFAULT ''");
// Trip origin (the truck's starting point before the booking pickup). Stored
// as the dispatcher's label — 'PTSI', 'CONSOL', or a free-form last location
// for OUTSIDE. Used by the print pages to render a pre-leg row on the ticket.
ensureDispatchColumn($conn, 'd_origin',         "VARCHAR(160) NOT NULL DEFAULT ''");
ensureDispatchColumn($conn, 'd_origin_code',    "VARCHAR(16)  NOT NULL DEFAULT ''");
// Trailer hauled on the origin/pre-leg leg (optional — printed on the pre-leg row).
ensureDispatchColumn($conn, 'd_origin_trailer', "VARCHAR(160) NOT NULL DEFAULT ''");
// Multi-leg trip ticket — per-leg run order (1-based). Lets the printed
// ticket render legs in the dispatcher's dragged order while the booking
// leg keeps trip_type = 'Trip 1' for all downstream lookups.
pt_ensure_column($conn, 'trips', 'trip_seq', "INTEGER NOT NULL DEFAULT 0");

$conn->beginTransaction();
try {
    // ---- Booking ------------------------------------------------------
    $stmt = $conn->prepare(
        "SELECT booking_id, costumer, customer_segment, container, container_status,
                booking_sn, booking_do, return_location,
                hauling_segment, hauling_type, trip_from, trip_to, booking_activity,
                booking_daterequired, quantity, quantity_use
         FROM booking WHERE booking_no = ? FOR UPDATE"
    );
    $stmt->execute([$booking_no]);
    $b = $stmt->fetch();
if (!$b)                                                throw new Exception("Booking not found.");
    if ((int)$b['quantity_use'] >= (int)$b['quantity'])     throw new Exception("Booking is already fully assigned.");

    $isCth = strcasecmp((string)$b['costumer'], 'CTH') === 0;
    $lane = cl_normalise_lane((string)($b['container_status'] ?? ''));
    $cthEirOut = '';
    $cthEirIn = '';
    if ($isCth) {
        if ($cth_eir === '') {
            throw new Exception($lane === 'loaded' ? 'EIR Out is required for CTH loaded dispatch.' : 'EIR In is required for CTH empty-return dispatch.');
        }
        if ($lane === 'loaded') {
            $cthEirOut = $cth_eir;
        } else {
            $cthEirIn = $cth_eir;
        }
    }

    $effectiveHaulingSegment = trim((string)($b['hauling_segment'] ?? ''));
    $effectiveHaulingType = trim((string)($b['hauling_type'] ?? ''));
    if ($effectiveHaulingSegment === '') {
        if ($hauling_segment === '') {
            throw new Exception("Hauling Segment is required for this booking.");
        }
        $stmt = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ? LIMIT 1");
        $stmt->execute([$hauling_segment]);
        $haulRow = $stmt->fetch();
if (!$haulRow) {
            throw new Exception("Invalid hauling segment.");
        }
        $effectiveHaulingSegment = $hauling_segment;
        $effectiveHaulingType = trim((string)($haulRow['hauling_type'] ?? ''));

        $stmt = $conn->prepare("UPDATE booking SET hauling_segment = ?, hauling_type = ? WHERE booking_no = ?");
        $stmt->execute([$effectiveHaulingSegment, $effectiveHaulingType, $booking_no]);
}

    // ---- Driver -------------------------------------------------------
    $stmt = $conn->prepare(
        "SELECT d.driver_id,
                CONCAT(d.driver_lname, ', ', d.driver_fname) AS driver_name,
                d.shift_truck,
                d.driver_assignbase,
                (
                    SELECT dd.d_id
                    FROM dispatch dd
                    WHERE dd.driver_id = d.driver_id
                      AND dd.workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')
                    ORDER BY dd.d_id DESC
                    LIMIT 1
                ) AS latest_active_dispatch_id,
                EXISTS(SELECT 1 FROM driver_shift s WHERE s.driver_id = d.driver_id AND s.ended_at IS NULL) AS on_shift,
                (SELECT COUNT(*)
                   FROM dispatch dd
                   WHERE dd.driver_id = d.driver_id
                     AND dd.workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')
                ) AS active_trips
         FROM drivers d WHERE d.driver_id = ? LIMIT 1"
    );
    $stmt->execute([$driver_id]);
    $drv = $stmt->fetch();
if (!$drv)                                              throw new Exception("Driver not found.");
    if (pt_driver_has_active_violation($conn, $driver_id))  throw new Exception(pt_driver_violation_block_message());

    $onShift   = (int)$drv['on_shift'] === 1;
    $shiftTruck = trim((string)$drv['shift_truck']);

    // Off-shift driver: the dispatcher can start a shift inline by entering a
    // truck in the Confirm Assignment modal. We validate the truck is a free,
    // good, non-blocked truck unit, open the shift, then carry on as normal.
    if (!$onShift) {
        if (!pt_setting_bool($conn, 'allow_offshift_assign', true)) {
            throw new Exception("Driver is not on an active shift.");
        }
        if ($shift_truck === '') {
            throw new Exception("Driver is not on an active shift. Enter a truck to start one.");
        }
        dnd_assert_truck_available($conn, $shift_truck, $driver_id, $allowInUseEquip);

        // Open the shift + denormalise the truck onto the drivers row, mirroring
        // the driver's own pre-departure shift-start.
        $stmtShift = $conn->prepare(
            "INSERT INTO driver_shift (driver_id, truck_code, started_at, notes)
             VALUES (?, ?, NOW(), ?)"
        );
        $stmtShift->execute([$driver_id, $shift_truck, 'Auto-started by dispatcher assignment for ' . $booking_no]);
        $stmtDrvShift = $conn->prepare(
            "UPDATE drivers SET shift_truck = ?, shift_started_at = NOW(), shift_ended_at = NULL WHERE driver_id = ?"
        );
        $stmtDrvShift->execute([$shift_truck, $driver_id]);

        $shiftTruck   = $shift_truck;
        $onShift      = true;
        $shiftStarted = true;
    } elseif ($shift_truck !== '' && strcasecmp($shift_truck, $shiftTruck) !== 0) {
        // On-shift driver: dispatcher re-selected a different unit. Validate the
        // new truck, point the open shift + drivers row at it, and release the
        // old truck back to the pool (only if this driver still holds it).
        dnd_assert_truck_available($conn, $shift_truck, $driver_id, $allowInUseEquip);

        $oldTruck = $shiftTruck;
        $conn->prepare("UPDATE driver_shift SET truck_code = ? WHERE driver_id = ? AND ended_at IS NULL")
             ->execute([$shift_truck, $driver_id]);
        $conn->prepare("UPDATE drivers SET shift_truck = ? WHERE driver_id = ?")
             ->execute([$shift_truck, $driver_id]);
        if ($oldTruck !== '') {
            $conn->prepare(
                "UPDATE units SET unit_assign = '', driver_id = 0, unit_status = 'Good'
                 WHERE unit_name = ? AND driver_id = ?"
            )->execute([$oldTruck, $driver_id]);
        }
        $conn->prepare(
            "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
             VALUES (NULL, ?, 'unit_reassigned', 'dispatcher', ?, ?)"
        )->execute([$booking_no, $dispatcherId, "Unit changed from {$oldTruck} to {$shift_truck} by dispatcher."]);

        $shiftTruck     = $shift_truck;
        $unitReselected = true;
    }

    if (!$onShift)                                          throw new Exception("Driver is not on an active shift.");
    if ($shiftTruck === '')                                 throw new Exception("Driver has no truck selected for this shift.");
    if ((int)$drv['active_trips'] >= 2)                     throw new Exception("Driver already has 2 active trips.");

    $truck = $shiftTruck;
    $dispatchHub = $drv['driver_assignbase'] ?: '';
    $latestActiveDispatchId = (int)($drv['latest_active_dispatch_id'] ?? 0);

    // ---- Truck guard --------------------------------------------------
    $stmt = $conn->prepare("SELECT unit_status, maintenance_blocked, dispatch_blocked, unit_assigntrailer, unit_assigngenset FROM units WHERE unit_name = ? LIMIT 1");
    $stmt->execute([$truck]);
    $tr = $stmt->fetch();
if (!$tr)                                               throw new Exception("Driver's shift truck not found in units.");
    if ((int)$tr['maintenance_blocked'] === 1)              throw new Exception("Driver's shift truck is maintenance-blocked.");
    if ((int)($tr['dispatch_blocked'] ?? 0) === 1)          throw new Exception("Driver's shift truck is blocked by Dispatch Admin and cannot be assigned.");
    if (in_array($tr['unit_status'], ['Rescue', 'Shop Unit'], true)) {
        throw new Exception("Driver's shift truck is unavailable: " . $tr['unit_status']);
    }

    $defaultTrailer = trim((string)($tr['unit_assigntrailer'] ?? ''));
    $defaultGenset = trim((string)($tr['unit_assigngenset'] ?? ''));
    if (!$no_trailer && $trailer === '' && $defaultTrailer !== '') {
        $canReuseTrailer = true;
        if ($latestActiveDispatchId > 0) {
            $stmt = $conn->prepare(
                "SELECT tj_id
                 FROM trailer_jackup
                 WHERE d_id = ? AND trailer_code = ?
                 ORDER BY tj_id DESC
                 LIMIT 1"
            );
            $stmt->execute([$latestActiveDispatchId, $defaultTrailer]);
            $canReuseTrailer = !$stmt->fetch();
}
        if ($canReuseTrailer) {
            $trailer = $defaultTrailer;
        }
    }
    if (!$no_genset && $genset === '' && $defaultGenset !== '') {
        $genset = $defaultGenset;
    }
    // Trailer + genset are required only when the assigned truck is a PM
    // (Prime Mover). Non-PM trucks (reefer vans, etc.) are self-contained
    // and skip both the required check and the per-unit guards when those
    // fields were left blank. Rule keyed off the truck name prefix, not
    // hauling_type — a PM pulling a reefer container still needs the rig.
    // Genset (reefer power) is only required for LOADED containers; an empty
    // container needs no genset.
    $isPmTruck = (stripos(trim((string)$truck), 'PM') === 0);
    if ($isPmTruck && $trailer === '' && !$no_trailer) {
        throw new Exception("Trailer is required.");
    }
    if ($isPmTruck && $lane === 'loaded' && $genset === '' && !$no_genset) {
        throw new Exception("Genset is required for loaded containers.");
    }

    // ---- Trailer guard (skipped when no trailer was supplied) ---------
    if ($trailer !== '') {
        $stmt = $conn->prepare("SELECT trailer_status, maintenance_blocked, trailer_assignto, driver_id FROM trailer WHERE trailer_name = ? LIMIT 1");
        $stmt->execute([$trailer]);
        $trlRow = $stmt->fetch();
        if (!$trlRow)                                           throw new Exception("Trailer not found.");
        if ((int)$trlRow['maintenance_blocked'] === 1)          throw new Exception("Trailer is maintenance-blocked.");
        if (in_array($trlRow['trailer_status'], ['Rescue', 'Shop Unit'], true)) {
            throw new Exception("Trailer is unavailable: " . $trlRow['trailer_status']);
        }
        if (!$allowInUseEquip && $trlRow['trailer_assignto'] !== '' && (int)$trlRow['driver_id'] !== 0 && (int)$trlRow['driver_id'] !== $driver_id) {
            throw new Exception("Trailer is already assigned to another driver.");
        }
    }

    // ---- Genset guard (skipped when no genset was supplied) -----------
    if ($genset !== '') {
        $stmt = $conn->prepare("SELECT unit_status, maintenance_blocked, unit_assign, driver_id FROM units WHERE unit_name = ? LIMIT 1");
        $stmt->execute([$genset]);
        $gsRow = $stmt->fetch();
        if (!$gsRow)                                            throw new Exception("Genset not found.");
        if ((int)$gsRow['maintenance_blocked'] === 1)           throw new Exception("Genset is maintenance-blocked.");
        if (in_array($gsRow['unit_status'], ['Rescue', 'Shop Unit'], true)) {
            throw new Exception("Genset is unavailable: " . $gsRow['unit_status']);
        }
        if (!$allowInUseEquip && $gsRow['unit_assign'] !== '' && (int)$gsRow['driver_id'] !== 0 && (int)$gsRow['driver_id'] !== $driver_id) {
            throw new Exception("Genset is already assigned to another driver.");
        }
    }

    // ---- Trip Receipt uniqueness -------------------------------------
    // Match the legacy assign flow: trip receipt must not already be in
    // use on an in-flight dispatch (LOADED or EMPTY trip). 'Done' trips
    // are out of the way, so we don't fail on historical numbers.
    $stmt = $conn->prepare(
        "SELECT 1 FROM dispatch d
         INNER JOIN trips t ON t.d_id = d.d_id
         WHERE d.d_tripreceipt = ? AND t.trip_status != 'Done'
         LIMIT 1"
    );
    $stmt->execute([$trip_receipt]);
    $dup = $stmt->fetch();
if ($dup) throw new Exception("Trip Receipt already in use on an active trip.");

    // ---- Build dispatch_ref (per-assignment reference) ---------------
    // If the booking carries a container number, use it as the suffix
    // (e.g. BN000003-ABC-DICT_CY-CATEEL-MSCU1234567). Otherwise, fall
    // back to EV<n> where <n> is the next number across this booking's
    // dispatches (declined slots leave a gap on purpose — keeps the
    // printed receipt unambiguous).
    // Dispatcher-entered container takes precedence over the booking's value.
    $containerNo = $container_no !== '' ? $container_no : trim((string)($b['container'] ?? ''));
    $dispatchRef = '';
    if ($containerNo !== '') {
        $dispatchRef = $booking_no . '-' . $containerNo;
    } else {
        $likePattern = $booking_no . '-EV%';
        $stmtRef = $conn->prepare(
            "SELECT dispatch_ref FROM dispatch
             WHERE booking_no = ? AND dispatch_ref LIKE ?"
        );
        $stmtRef->execute([$booking_no, $likePattern]);
        $resRef = $stmtRef;
        $maxN = 0;
        while ($rRef = $resRef->fetch()) {
            if (preg_match('/-EV(\d+)$/', (string)$rRef['dispatch_ref'], $m)) {
                $maxN = max($maxN, (int)$m[1]);
            }
        }
$dispatchRef = $booking_no . '-EV' . ($maxN + 1);
    }

    // ---- Dispatch row -------------------------------------------------
    $stmt = $conn->prepare(
        "INSERT INTO dispatch
            (booking_no, dispatch_ref, booking_sn, booking_do, cth_eirout, cth_eirin,
             d_datetime, d_dispatcher, d_dispatchhub, d_drivername, driver_id, d_truck, d_trailer, d_genset, d_tripreceipt, costumer,
             d_origin, d_origin_code, d_origin_trailer,
             workflow_stage, workflow_updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'dispatcher_assigned', ?)
         RETURNING d_id"
    );
    $stmt->execute([
        $booking_no, $dispatchRef, $b['booking_sn'], $b['booking_do'], $cthEirOut, $cthEirIn,
        $now, $dispatcherName, $dispatchHub, $drv['driver_name'],
        $drv['driver_id'], $truck, $trailer, $genset, $trip_receipt, $b['costumer'],
        $trip_origin_label, $trip_origin_code, $origin_trailer,
        $now
    ]);
    $d_id = (int)$stmt->fetchColumn();

    // ---- Trip legs ----------------------------------------------------
    // Multi-leg trip ticket: the booking leg is always persisted as 'Trip 1'
    // (downstream code keys off that), the remaining legs as 'Trip 2'..'Trip N'.
    // trip_seq preserves the dispatcher's dragged order for the printed ticket.
    //
    // Work out the booking leg's from/to (row values win over the booking) and
    // its display sequence, plus the list of extra legs to insert afterwards.
    $bookingLegSeq   = 1;
    $bookingLegFrom  = ($trip_from_override !== '' ? $trip_from_override : $b['trip_from']);
    $bookingLegTo    = $b['trip_to'];
    $extraLegs       = [];   // each: [seq, from, to, hauling_seg, hauling_job, trailer, genset, container_stat]

    if ($hasRows) {
        $extraTripNo = 2;    // Trip 2, Trip 3, … for non-booking legs
        foreach ($rows as $i => $r) {
            $seq   = $i + 1;                                  // 1-based dragged order
            $rFrom = trim((string)($r['from'] ?? ''));
            $rTo   = trim((string)($r['to'] ?? ''));
            if ($rFrom === '' && $rTo === '') continue;       // skip blank rows
            $rVan  = strtoupper(trim((string)($r['container'] ?? '')));
            if ($rVan !== '' && !preg_match('/^[A-Z]{4}[0-9]{7}$/', $rVan)) {
                throw new Exception('Van no must be 4 letters followed by 7 numbers (e.g. MSCU1234567).');
            }
            if (!empty($r['is_booking_leg'])) {
                $bookingLegSeq = $seq;
                if ($rFrom !== '') $bookingLegFrom = $rFrom;
                if ($rTo   !== '') $bookingLegTo   = $rTo;
                continue;
            }
            $extraLegs[] = [
                'trip_type'      => 'Trip ' . $extraTripNo,
                'seq'            => $seq,
                'from'           => $rFrom,
                'to'             => $rTo,
                'hauling_seg'    => trim((string)($r['hauling_seg'] ?? '')),
                'hauling_job'    => trim((string)($r['hauling_job'] ?? '')),
                'trailer'        => trim((string)($r['trailer'] ?? '')),
                'genset'         => trim((string)($r['genset'] ?? '')),
                'container'      => $rVan,
                'container_stat' => trim((string)($r['container_stat'] ?? '')),
            ];
            $extraTripNo++;
        }
    }

    // ---- Trip 1 (booking leg) -----------------------------------------
    // Rate stamped at dispatch time via the SAME matcher the coupon uses,
    // so trips.piece_rate agrees with the Trip Verification Coupon.
    require_once __DIR__ . '/../helpers/trip_rate_lookup.php';
    $pieceRate = fleet_compute_trip_piece_rate($conn, $effectiveHaulingSegment, $effectiveHaulingType, $b['container_status']);

    $stmt = $conn->prepare(
        "INSERT INTO trips
            (d_id, trip_type, costumer, segment_costumer, trip_container, container_activity,
             trip_containerstat, trip_haulingsegment, trip_haulingtype, trip_from, trip_to,
             trip_trailer, trip_genset, trip_seq, required_date, trip_status, segment_status, piece_rate)
         VALUES (?, 'Trip 1', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', 'Assigned', ?)"
    );
    $stmt->execute([
        $d_id,
        $b['costumer'],
        $b['customer_segment'],
        $containerNo,
        $b['booking_activity'],
        $b['container_status'],
        $effectiveHaulingSegment,
        $effectiveHaulingType,
        // Dispatcher-entered pickup takes precedence over the booking's.
        $bookingLegFrom,
        $bookingLegTo,
        // Primary rig — also stored per-leg so the ticket renders it on this row.
        $trailer,
        $genset,
        $bookingLegSeq,
        $b['booking_daterequired'],
        $pieceRate
    ]);

    // Auto-add a 0.00 placeholder Trip Rate for any SKU that has no rate yet,
    // so un-priced combos surface on the Trip Rates page for the admin.
    fleet_ensure_trip_rate_stub($conn, $effectiveHaulingSegment, $effectiveHaulingType, $b['container_status']);

    // ---- Extra legs (Trip 2..N) — repositioning / return / manual -----
    if (!empty($extraLegs)) {
        $legStmt = $conn->prepare(
            "INSERT INTO trips
                (d_id, trip_type, costumer, segment_costumer, trip_container, trip_containerstat,
                 trip_haulingsegment, trip_haulingtype, trip_from, trip_to,
                 trip_trailer, trip_genset, trip_seq, required_date, trip_status, segment_status, piece_rate)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', 'Assigned', ?)"
        );
        foreach ($extraLegs as $leg) {
            $legRate = fleet_compute_trip_piece_rate($conn, $leg['hauling_seg'], $leg['hauling_job'], $leg['container_stat']);
            $legStmt->execute([
                $d_id,
                $leg['trip_type'],
                $b['costumer'],
                $b['customer_segment'],
                $leg['container'],
                $leg['container_stat'],
                $leg['hauling_seg'],
                $leg['hauling_job'],
                $leg['from'],
                $leg['to'],
                $leg['trailer'],
                $leg['genset'],
                $leg['seq'],
                $b['booking_daterequired'],
                $legRate
            ]);
            fleet_ensure_trip_rate_stub($conn, $leg['hauling_seg'], $leg['hauling_job'], $leg['container_stat']);
        }
    }

    // ---- Container status — bump to Pickup ----------------------------
    // Empty → Empty Container Pickup; Loaded → Loaded Container Pickup.
    // Anything else, leave alone (e.g. already Pickup / On Trip — dispatcher
    // may be re-assigning a foul trip).
    $currentStatus = $b['container_status'] ?: 'Empty';
    $next = $currentStatus;
    if (in_array($currentStatus, ['Empty', 'EMPTY', 'N/A'], true))   $next = 'Empty Container Pickup';
    if (in_array($currentStatus, ['Loaded', 'LOADED'], true))         $next = 'Loaded Container Pickup';
    if ($next !== $currentStatus) {
        $stmt = $conn->prepare("UPDATE booking SET container_status = ? WHERE booking_no = ?");
        $stmt->execute([$next, $booking_no]);
}

    // ---- Booking quantity_use bump + auto-complete --------------------
    $stmt = $conn->prepare("UPDATE booking SET quantity_use = quantity_use + 1 WHERE booking_no = ?");
    $stmt->execute([$booking_no]);
$stmt = $conn->prepare("UPDATE booking SET status = 'Complete'
                            WHERE booking_no = ? AND quantity_use >= quantity AND status <> 'Complete'");
    $stmt->execute([$booking_no]);
// ---- Driver / truck status flips ----------------------------------
    $stmt = $conn->prepare("UPDATE drivers SET driver_status = 'Dispatch' WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
$stmt = $conn->prepare(
        "UPDATE units SET unit_assign = ?, driver_id = ?, unit_assigngenset = ?, unit_assigntrailer = ?, unit_status = 'Dispatch' WHERE unit_name = ?"
    );
    $stmt->execute([$drv['driver_name'], $driver_id, $genset, $trailer, $truck]);
// ---- Trailer assignment + movement log ---------------------------
    $stmt = $conn->prepare("UPDATE trailer SET trailer_assignto = ?, driver_id = ? WHERE trailer_name = ?");
    $stmt->execute([$drv['driver_name'], $driver_id, $trailer]);
$stmt = $conn->prepare(
        "INSERT INTO trailer_movement
            (tm_trailername, tm_driverassign, tm_location, tm_recordedtype, tm_recordedby, tm_date)
         VALUES (?, ?, ?, 'Dispatch', ?, ?)"
    );
    $tripTo = $b['trip_to'];
    $stmt->execute([$trailer, $drv['driver_name'], $tripTo, $dispatcherName, $now]);
// ---- Genset assignment -------------------------------------------
    $stmt = $conn->prepare("UPDATE units SET unit_assign = ?, driver_id = ?, unit_status = 'Dispatch' WHERE unit_name = ?");
    $stmt->execute([$drv['driver_name'], $driver_id, $genset]);
// ---- Workflow audit ----------------------------------------------
    $stage = 'dispatcher_assigned';
    $note  = 'Assigned via tile dashboard (DnD).';
    $stmt = $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, ?, 'dispatcher', ?, ?)"
    );
    $stmt->execute([$d_id, $booking_no, $stage, $dispatcherId, $note]);
$conn->commit();

    // Activity log — record the assignment (and inline shift-start, if any).
    $logDetails = $booking_no . ' → ' . $drv['driver_name'] . ' (' . $truck . ')';
    if (!empty($shiftStarted)) {
        $logDetails .= ' — started shift on ' . $truck;
    } elseif (!empty($unitReselected)) {
        $logDetails .= ' — unit changed to ' . $truck;
    }
    pt_log_activity($conn, $dispatcherId, ($_SESSION['user_type'] ?? 'Dispatcher'), $dispatcherName, 'Assigned booking', $logDetails);

    echo json_encode([
        'status'       => 'success',
        'message'      => 'Booking assigned.',
        'd_id'         => $d_id,
        'booking_no'   => $booking_no,
        'dispatch_ref' => $dispatchRef,
        'driver_id'    => $driver_id,
        'truck'        => $truck,
        'next_status'  => $next,
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
