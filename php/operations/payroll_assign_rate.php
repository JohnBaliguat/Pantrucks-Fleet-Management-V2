<?php
// Payroll assigns the piece-rate for a coupon's trips. Payroll picks the
// activity per billable trip; the matching trip_rates.total_rates is stamped
// onto trips.piece_rate, and the dispatch is marked payroll_status='rated'.
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Payroll', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']); exit;
}

$dId   = (int)($_POST['d_id'] ?? 0);
$actor = (int)($_SESSION['user_id'] ?? 0);
$picks = isset($_POST['picks']) && is_array($_POST['picks']) ? $_POST['picks'] : [];

if ($dId <= 0) { echo json_encode(['status' => 'error', 'message' => 'd_id required']); exit; }
if (!$picks)   { echo json_encode(['status' => 'error', 'message' => 'Pick an activity for at least one trip.']); exit; }

$stmt = $conn->prepare("SELECT booking_no, control_no FROM dispatch WHERE d_id = ? LIMIT 1");
$stmt->execute([$dId]);
$d = $stmt->fetch();
if (!$d) { echo json_encode(['status' => 'error', 'message' => 'Dispatch not found']); exit; }
if (trim((string)$d['control_no']) === '') {
    echo json_encode(['status' => 'error', 'message' => 'This trip has no coupon yet — it must be approved first.']); exit;
}

// Resolve every pick to a rate up front so we fail before writing anything.
//
// Segment-aware lookup: the same activity name (e.g. "Reefer Van - Loaded")
// exists under several segments at DIFFERENT rates (DOLE ₱295, SUMIFRU ₱405,
// GOOD FARMER ₱270 …). Matching on activity alone stamped whichever row had
// the highest id — the wrong segment's rate. We now disambiguate by the trip's
// own segment: exact segment match wins, then a segment-blank generic row,
// otherwise we refuse rather than guess. A globally-unique activity (the
// compound "...RV.Loaded" names) still resolves with no segment needed.
$tripUpdates = []; // trip_id => ['activity' => str, 'rate' => float, 'segment' => str]
$segStmt  = $conn->prepare("SELECT trip_haulingsegment FROM trips WHERE trip_id = ? AND d_id = ? LIMIT 1");
$candStmt = $conn->prepare("SELECT total_rates, TRIM(segment) AS segment FROM trip_rates WHERE LOWER(TRIM(activity)) = LOWER(TRIM(?))");
foreach ($picks as $tid => $name) {
    $tid  = (int)$tid;
    $name = trim((string)$name);
    if ($tid <= 0 || $name === '') continue;

    // The trip's own segment decides which same-named rate applies.
    $segStmt->execute([$tid, $dId]);
    $seg = trim((string)($segStmt->fetchColumn() ?: ''));

    $candStmt->execute([$name]);
    $cands = $candStmt->fetchAll();
    if (!$cands) {
        echo json_encode(['status' => 'error', 'message' => 'Unknown activity: ' . $name]); exit;
    }

    if (count($cands) === 1) {
        // Unique activity name — unambiguous regardless of segment.
        $rate = (float)$cands[0]['total_rates'];
    } else {
        // Duplicated across segments — require the trip's segment (or a blank/generic row).
        $rate = null; $blankRate = null;
        foreach ($cands as $c) {
            $cseg = trim((string)$c['segment']);
            if ($cseg !== '' && strcasecmp($cseg, $seg) === 0) { $rate = (float)$c['total_rates']; break; }
            if ($cseg === '' && $blankRate === null)          { $blankRate = (float)$c['total_rates']; }
        }
        if ($rate === null) $rate = $blankRate;
        if ($rate === null) {
            echo json_encode(['status' => 'error', 'message' =>
                'Activity "' . $name . '" has segment-specific rates and none match this trip\'s segment ("' . ($seg !== '' ? $seg : 'blank') . '"). Choose a segment-specific activity for this trip.']);
            exit;
        }
    }
    $tripUpdates[$tid] = ['activity' => $name, 'rate' => $rate, 'segment' => $seg];
}
if (!$tripUpdates) { echo json_encode(['status' => 'error', 'message' => 'No valid trip picks.']); exit; }

$conn->beginTransaction();
try {
    // Stamp only the money. The SKU lives in trips.trip_sku (derived), and the
    // operational verb (WITHDRAW/DELIVER) stays in container_activity — payroll
    // no longer overwrites it. The picked activity is still recorded in the
    // workflow_event audit below.
    $upd = $conn->prepare(
        "UPDATE trips SET piece_rate = ? WHERE trip_id = ? AND d_id = ?"
    );
    $total = 0.0;
    $summary = [];
    foreach ($tripUpdates as $tid => $info) {
        $upd->execute([$info['rate'], $tid, $dId]);
        $total += $info['rate'];
        $segNote = $info['segment'] !== '' ? ' @' . $info['segment'] : '';
        $summary[] = 'trip#' . $tid . ' ' . $info['activity'] . $segNote . ' (₱' . number_format($info['rate'], 2) . ')';
    }

    $conn->prepare(
        "UPDATE dispatch
            SET payroll_status = 'rated', payroll_rated_by = ?, payroll_rated_at = NOW()
          WHERE d_id = ?"
    )->execute([$actor, $dId]);

    $note = 'Payroll rated by #' . $actor . ' — ' . implode('; ', $summary);
    $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes) VALUES (?, ?, 'payroll_rated', ?, ?, ?)"
    )->execute([$dId, $d['booking_no'], $role, $actor, $note]);

    $conn->commit();
    echo json_encode([
        'status'  => 'success',
        'message' => 'Piece-rate assigned. Total ₱' . number_format($total, 2) . '.',
        'total'   => $total,
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Assign failed: ' . $e->getMessage()]);
}
