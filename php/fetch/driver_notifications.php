<?php
// Driver notifications feed.
//
// Returns a single merged list combining three sources:
//   1. unit_maintenance rows scheduled or active for the driver's
//      currently assigned truck / trailer / genset.
//   2. Unread chat messages addressed to the driver.
//   3. Trip verification outcomes (POD verified / rejected) on the
//      driver's recent dispatches.
//
// Each item carries a stable, deduplicating `id` (e.g. "msg-123",
// "verif-456", "maint-101") so the navbar JS can mark them seen in
// localStorage without needing a server-side "read" column.
//
// Called by driver/navbar.php roughly every 30 seconds.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Driver' || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised', 'items' => [], 'count' => 0]);
    exit;
}

$driverId = (int)$_SESSION['user_id'];
$items    = [];

try {
    // ---- 1. Scheduled / active maintenance on driver's units ----------
    // Look up what the driver currently has: truck (drivers.shift_truck)
    // plus whatever trailer / genset is attached to that truck.
    $unitCodes = []; // ['kind' => 'truck'|'trailer'|'genset', 'code' => string]
    $stmt = $conn->prepare("SELECT shift_truck FROM drivers WHERE driver_id = ? LIMIT 1");
    $stmt->execute([$driverId]);
    $truckCode = trim((string)($stmt->fetchColumn() ?: ''));
    if ($truckCode !== '') {
        $unitCodes[] = ['kind' => 'truck', 'code' => $truckCode];
        $stmt = $conn->prepare(
            "SELECT unit_assigntrailer, unit_assigngenset FROM units WHERE unit_name = ? LIMIT 1"
        );
        $stmt->execute([$truckCode]);
        if ($row = $stmt->fetch()) {
            $trailer = trim((string)($row['unit_assigntrailer'] ?? ''));
            $genset  = trim((string)($row['unit_assigngenset']  ?? ''));
            if ($trailer !== '') $unitCodes[] = ['kind' => 'trailer', 'code' => $trailer];
            if ($genset  !== '') $unitCodes[] = ['kind' => 'genset',  'code' => $genset];
        }
    }
    foreach ($unitCodes as $unit) {
        $stmt = $conn->prepare(
            "SELECT um_id, unit_kind, unit_code, category, severity, reason,
                    expected_return, scheduled_start_at, blocked_at, status
               FROM unit_maintenance
              WHERE unit_kind = ?
                AND unit_code = ?
                AND status IN ('active', 'scheduled')
              ORDER BY COALESCE(scheduled_start_at, blocked_at) ASC
              LIMIT 5"
        );
        $stmt->execute([$unit['kind'], $unit['code']]);
        while ($m = $stmt->fetch()) {
            $when = $m['scheduled_start_at'] ?? $m['blocked_at'];
            $title = ucfirst($m['unit_kind']) . ' ' . $m['unit_code'] . ' — ' .
                     ($m['status'] === 'scheduled' ? 'Scheduled maintenance' : 'In maintenance');
            $body  = trim((string)$m['reason']) !== ''
                ? $m['reason']
                : (ucfirst((string)$m['category']) . ' (' . $m['severity'] . ')');
            if (!empty($m['expected_return'])) {
                $body .= ' · back ' . $m['expected_return'];
            }
            $items[] = [
                'id'    => 'maint-' . (int)$m['um_id'],
                'type'  => 'maintenance',
                'icon'  => 'ti-tool',
                'title' => $title,
                'body'  => $body,
                'at'    => $when,
                'link'  => null,
            ];
        }
    }

    // ---- 2. Unread chat messages addressed to this driver --------------
    // Mirrors the rules in messages_unread.php so the count matches.
    $stmt = $conn->prepare(
        "SELECT m.msg_id, m.from_role, m.from_id, m.body, m.sent_at,
                TRIM(CONCAT(u.user_fname, ' ', u.user_lname)) AS user_name,
                TRIM(CONCAT(d.driver_lname, ', ', d.driver_fname)) AS driver_name
           FROM message m
           LEFT JOIN \"user\"  u ON u.user_id   = m.from_id AND m.from_role <> 'driver'
           LEFT JOIN drivers  d ON d.driver_id = m.from_id AND m.from_role  = 'driver'
          WHERE m.read_at IS NULL
            AND m.from_role <> 'driver'
            AND m.to_role = 'driver'
            AND (m.to_id = ? OR m.to_id IS NULL)
          ORDER BY m.msg_id DESC
          LIMIT 20"
    );
    $stmt->execute([$driverId]);
    while ($r = $stmt->fetch()) {
        $sender = trim((string)($r['user_name'] ?? ''));
        if ($sender === '') $sender = trim((string)($r['driver_name'] ?? ''));
        if ($sender === '') {
            $sender = ($r['from_role'] === 'system')
                ? 'System'
                : ucfirst((string)$r['from_role']) . ' #' . (int)$r['from_id'];
        }
        $items[] = [
            'id'    => 'msg-' . (int)$r['msg_id'],
            'type'  => 'message',
            'icon'  => 'ti-message-circle',
            'title' => $sender,
            'body'  => mb_substr((string)$r['body'], 0, 140),
            'at'    => $r['sent_at'],
            'link'  => 'driver-messages',
        ];
    }

    // ---- 3. Recent trip verification outcomes --------------------------
    // workflow_event rows with stage 'pod_captured' (verified) or
    // 'pod_rejected'. Scope to this driver's dispatches in the last 7 days.
    $stmt = $conn->prepare(
        "SELECT we.we_id, we.stage, we.notes, we.created_at,
                d.d_id, d.booking_no
           FROM workflow_event we
           JOIN dispatch d ON d.d_id = we.d_id
          WHERE d.driver_id = ?
            AND we.stage IN ('pod_captured', 'pod_rejected')
            AND we.created_at >= NOW() - INTERVAL '7 days'
          ORDER BY we.we_id DESC
          LIMIT 15"
    );
    $stmt->execute([$driverId]);
    while ($v = $stmt->fetch()) {
        $isVerified = ($v['stage'] === 'pod_captured');
        $items[] = [
            'id'    => ($isVerified ? 'verif-' : 'reject-') . (int)$v['we_id'],
            'type'  => $isVerified ? 'verified' : 'rejected',
            'icon'  => $isVerified ? 'ti-circle-check' : 'ti-circle-x',
            'title' => ($isVerified ? 'Trip verified' : 'Trip rejected')
                       . ' — ' . ($v['booking_no'] ?: '#' . (int)$v['d_id']),
            'body'  => trim((string)($v['notes'] ?? ''))
                       ?: ($isVerified
                            ? 'POD accepted by dispatch.'
                            : 'POD rejected — please re-capture.'),
            'at'    => $v['created_at'],
            'link'  => 'driver-tripReport',
        ];
    }

    // ---- Sort newest first across all sources --------------------------
    usort($items, function ($a, $b) {
        return strcmp((string)$b['at'], (string)$a['at']);
    });
    // Cap at 50 — the navbar panel only needs to show the latest.
    if (count($items) > 50) $items = array_slice($items, 0, 50);

    echo json_encode([
        'status'     => 'success',
        'count'      => count($items),
        'items'      => $items,
        'fetched_at' => date('Y-m-d H:i:s'),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'items' => [], 'count' => 0]);
}
