<?php
include '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id     = $_POST['user_id'];
    $driver_id   = $_POST['driver_id'];
    $violation   = $_POST['violation'];
    $description = $_POST['description'];
    // Optional schedule — datetime-local "YYYY-MM-DDTHH:MM". When set to a
    // future moment the violation is recorded but the driver is NOT blocked yet;
    // an auto-activation sweep blocks them once the scheduled time arrives.
    $scheduledRaw = trim((string)($_POST['scheduled_at'] ?? ''));

    date_default_timezone_set("Asia/Manila");
    $date = date("Y-m-d H:i:s");

    // Make sure the schedule column exists (self-bootstrapping).
    try { pt_ensure_column($conn, 'violation_record', 'vr_scheduled_at', 'TIMESTAMP DEFAULT NULL'); }
    catch (Throwable $e) { /* best-effort */ }

    // Normalise the schedule value; treat an invalid or past/now time as immediate.
    $scheduledAt = null;
    if ($scheduledRaw !== '') {
        $scheduledAt = str_replace('T', ' ', $scheduledRaw);
        if (strlen($scheduledAt) === 16) $scheduledAt .= ':00';   // add seconds
        $ts = strtotime($scheduledAt);
        if ($ts === false || $ts <= time()) {
            $scheduledAt = null;   // invalid or not in the future → block now
        }
    }
    $isScheduled = $scheduledAt !== null;
    $status = $isScheduled ? 'Scheduled' : 'Active';

    // 1. Insert violation record
    $insert = $conn->prepare("
        INSERT INTO violation_record
        (driver_id, vr_type, vr_description, vr_status, vr_recordedby, vr_date, vr_scheduled_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if ($insert->execute([$driver_id, $violation, $description, $status, $user_id, $date, $scheduledAt])) {

        if ($isScheduled) {
            // Scheduled — leave the driver active; the sweep blocks them at time.
            echo json_encode([
                'status'  => 'success',
                'message' => 'Violation scheduled for ' . $scheduledAt . '. The driver will be blocked from dispatch automatically at that time.'
            ]);
        } else {
            // Immediate — block the driver now. Snapshot the current operational
            // status into driver_prev_status first, so clearing the violation
            // later restores it. The IS DISTINCT FROM guard keeps a genuine
            // prior status from being overwritten with 'With Violation' when a
            // second violation is added to an already-blocked driver.
            try { pt_ensure_column($conn, 'drivers', 'driver_prev_status', "VARCHAR(50) DEFAULT NULL"); }
            catch (Throwable $e) { /* best-effort */ }
            $update = $conn->prepare("
                UPDATE drivers
                SET driver_prev_status = driver_status,
                    driver_status = 'With Violation'
                WHERE driver_id = ?
                  AND driver_status IS DISTINCT FROM 'With Violation'
            ");
            $update->execute([$driver_id]);

            // Notify the driver on WhatsApp (best-effort; skipped if no contact
            // number or the WhatsApp integration isn't configured).
            require_once __DIR__ . '/../lib/whatsapp.php';
            $wa = pt_wa_send_violation($conn, (int)$driver_id, (string)$violation, (string)$description);

            $msg = 'Violation successfully recorded. Driver is now blocked from dispatch until cleared.';
            if (!empty($wa['ok']))          $msg .= ' A WhatsApp notice was sent to the driver.';
            elseif (empty($wa['skipped']))  $msg .= ' (WhatsApp notice could not be sent.)';

            echo json_encode([
                'status'   => 'success',
                'message'  => $msg,
                'whatsapp' => $wa,
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to insert violation.'
        ]);
    }
}
