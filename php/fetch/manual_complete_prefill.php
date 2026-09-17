<?php
// Prefill feed for the dispatcher's "Manual Complete Trip" modal.
// Returns whatever the driver has already captured for a dispatch —
// movement timestamps, the pickup photo, and the POD (photos + recipient) —
// so the dispatcher only has to fill the gaps and won't be forced to
// re-upload things that already exist.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$dId = (int)($_GET['d_id'] ?? 0);
if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

$out = [
    'status'     => 'success',
    'timestamps' => ['picked_up' => null, 'on_the_way' => null, 'arrived' => null, 'delivered' => null],
    'pickup'     => null,
    'pod'        => null,
];

try {
    // Latest event time per movement stage (driver-recorded or back-filled).
    $stmt = $conn->prepare(
        "SELECT stage, MAX(event_at) AS event_at
         FROM workflow_event
         WHERE d_id = ?
           AND stage IN ('picked_up','on_the_way','arrived','delivered')
         GROUP BY stage"
    );
    $stmt->execute([$dId]);
    while ($r = $stmt->fetch()) {
        $stage = $r['stage'] ?? '';
        if (array_key_exists($stage, $out['timestamps'])) {
            $out['timestamps'][$stage] = $r['event_at'];
        }
    }

    // Most recent pickup capture.
    $stmt = $conn->prepare(
        "SELECT photo_path, captured_at
         FROM pickup_capture
         WHERE d_id = ?
         ORDER BY pc_id DESC
         LIMIT 1"
    );
    $stmt->execute([$dId]);
    $pickup = $stmt->fetch();
    if ($pickup) {
        $out['pickup'] = [
            'photo_path'  => $pickup['photo_path'] ?? '',
            'captured_at' => $pickup['captured_at'] ?? '',
        ];
    }
} catch (Throwable $e) {
    // pickup_capture may not exist on a legacy DB — leave pickup null.
}

try {
    // Most recent POD capture.
    $stmt = $conn->prepare(
        "SELECT photo1_path, photo2_path, photo3_path, signed_by, captured_at
         FROM pod_capture
         WHERE d_id = ?
         ORDER BY pod_id DESC
         LIMIT 1"
    );
    $stmt->execute([$dId]);
    $pod = $stmt->fetch();
    if ($pod) {
        $out['pod'] = [
            'photo1_path' => $pod['photo1_path'] ?? '',
            'photo2_path' => $pod['photo2_path'] ?? '',
            'photo3_path' => $pod['photo3_path'] ?? '',
            'signed_by'   => $pod['signed_by'] ?? '',
            'captured_at' => $pod['captured_at'] ?? '',
        ];
    }
} catch (Throwable $e) {
    // pod_capture missing — leave pod null.
}

echo json_encode($out);
