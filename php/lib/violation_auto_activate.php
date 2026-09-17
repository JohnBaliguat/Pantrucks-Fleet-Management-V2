<?php
// Auto-activate scheduled driver violations.
//
// A violation can be scheduled for a future date/time (vr_status='Scheduled',
// vr_scheduled_at set). This sweep activates any scheduled violation whose time
// has arrived (vr_scheduled_at <= NOW()): it flips the record to 'Active' and
// blocks the driver (driver_status='With Violation') — exactly what an
// immediate violation does, just deferred to the scheduled moment.
//
// Called opportunistically from the HR driver lists + the dispatch board fetch,
// and by the standalone operations/auto_activate_violations.php endpoint (for
// cron / Task Scheduler). Idempotent and safe to run as often as you like.

if (!function_exists('pt_activate_due_violations')) {
    /**
     * @return int number of violations activated this sweep
     */
    function pt_activate_due_violations(PDO $conn): int
    {
        // Make sure the schedule column exists (self-bootstrapping on installs
        // that haven't run migration 014 yet).
        try { pt_ensure_column($conn, 'violation_record', 'vr_scheduled_at', 'TIMESTAMP DEFAULT NULL'); }
        catch (Throwable $e) { /* best-effort */ }

        // Snapshot column for the driver's pre-violation status (self-bootstrapping),
        // so clearing the violation later restores it instead of forcing 'Good'.
        try { pt_ensure_column($conn, 'drivers', 'driver_prev_status', "VARCHAR(50) DEFAULT NULL"); }
        catch (Throwable $e) { /* best-effort */ }

        $sel = $conn->query(
            "SELECT vr_id, driver_id, vr_type, vr_description
               FROM violation_record
              WHERE vr_status = 'Scheduled'
                AND vr_scheduled_at IS NOT NULL
                AND vr_scheduled_at <= NOW()"
        );
        $rows = $sel ? $sel->fetchAll() : [];
        if (!$rows) return 0;

        $activated = 0;
        $notify = [];   // drivers actually activated this sweep → WhatsApp after commit
        $conn->beginTransaction();
        try {
            $activate = $conn->prepare(
                "UPDATE violation_record
                    SET vr_status = 'Active'
                  WHERE vr_id = ? AND vr_status = 'Scheduled'"
            );
            $blockDriver = $conn->prepare(
                "UPDATE drivers
                    SET driver_prev_status = driver_status,
                        driver_status = 'With Violation'
                  WHERE driver_id = ?
                    AND driver_status IS DISTINCT FROM 'With Violation'"
            );
            foreach ($rows as $r) {
                $activate->execute([$r['vr_id']]);
                if ($activate->rowCount() === 0) continue;   // activated elsewhere
                $blockDriver->execute([$r['driver_id']]);
                $notify[] = $r;
                $activated++;
            }
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            return 0;
        }

        // Notify each newly-blocked driver on WhatsApp — after commit so the
        // network call never holds the transaction open. Best-effort.
        if ($notify) {
            require_once __DIR__ . '/whatsapp.php';
            foreach ($notify as $r) {
                pt_wa_send_violation($conn, (int)$r['driver_id'], (string)$r['vr_type'], (string)$r['vr_description']);
            }
        }
        return $activated;
    }
}
