<?php
// Auto-release maintenance blocks whose expected return date has arrived.
//
// A unit blocked with an "expected return" date should come back into the
// dispatch pool on that date without a person having to click Release. This
// sweep closes any ACTIVE unit_maintenance ticket whose expected_return has
// been reached (CURRENT_DATE >= expected_return) and resets the master unit
// row to 'Good' — mirroring the manual release in unblock_unit.php.
//
// Scheduled ('scheduled') tickets that haven't started are left alone; only
// tickets that are actually in force ('active') are auto-released.
//
// Called opportunistically from the maintenance fetch endpoints and by the
// standalone operations/auto_release_maintenance.php endpoint (for cron / Task
// Scheduler). Idempotent and safe to run as often as you like.

if (!function_exists('pt_auto_release_expired_maintenance')) {
    /**
     * @return int number of units auto-released this sweep
     */
    function pt_auto_release_expired_maintenance(PDO $conn): int
    {
        // Active blocks whose expected return date+time has arrived or passed.
        $sel = $conn->query(
            "SELECT um_id, unit_kind, unit_code
               FROM unit_maintenance
              WHERE status = 'active'
                AND expected_return IS NOT NULL
                AND expected_return <= NOW()"
        );
        $rows = $sel ? $sel->fetchAll() : [];
        if (!$rows) return 0;

        $released = 0;
        $conn->beginTransaction();
        try {
            $closeTicket = $conn->prepare(
                "UPDATE unit_maintenance
                    SET status = 'released',
                        released_at = NOW(),
                        release_notes = CASE WHEN release_notes = ''
                                             THEN 'Auto-released: expected return date/time reached'
                                             ELSE release_notes END
                  WHERE um_id = ? AND status = 'active'"
            );
            $resetUnit = $conn->prepare(
                "UPDATE units
                    SET maintenance_blocked = FALSE, maintenance_reason = '',
                        maintenance_expected_return = NULL, maintenance_blocked_at = NULL,
                        unit_status = 'Good'
                  WHERE unit_name = ? AND unit_type = ?"
            );
            $resetTrailer = $conn->prepare(
                "UPDATE trailer
                    SET maintenance_blocked = FALSE, maintenance_reason = '',
                        maintenance_expected_return = NULL, maintenance_blocked_at = NULL,
                        trailer_status = 'Good'
                  WHERE trailer_name = ?"
            );

            foreach ($rows as $r) {
                $closeTicket->execute([$r['um_id']]);
                if ($closeTicket->rowCount() === 0) continue;   // already released elsewhere
                if ($r['unit_kind'] === 'trailer') {
                    $resetTrailer->execute([$r['unit_code']]);
                } else {
                    $resetUnit->execute([$r['unit_code'], $r['unit_kind']]);
                }
                $released++;
            }
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            return 0;
        }
        return $released;
    }
}
