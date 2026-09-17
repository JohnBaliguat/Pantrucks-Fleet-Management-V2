<?php
// Phase 14 — container_status lifecycle helper.
//
// One place to encode the legal transitions for the Empty/Loaded
// 8-state lifecycle. Every event hook (gate OUT, POD capture, gateless
// completion, dispatcher "mark loaded") calls into this function
// rather than re-implementing the rules inline. Idempotent: a no-op
// when the booking is already at or past the target stage.
//
// Stage tokens (passed in as $target):
//   'pickup'    → bump from Empty/Loaded                              to Empty/Loaded Container Pickup
//   'on_trip'   → bump from Empty/Loaded Container Pickup             to Empty/Loaded Container On Trip
//   'delivered' → bump from Empty/Loaded Container On Trip            to Empty/Loaded Container Delivered
//
// The lane (Empty vs Loaded) is preserved through the transition.
// Legacy values ('EMPTY' / 'LOADED' / 'N/A') are normalised the same
// way Phase 11 normalises them on read.

if (!function_exists('cl_normalise_lane')) {
    /** Returns 'empty' or 'loaded' for the current container_status. */
    function cl_normalise_lane(string $current): string {
        $n = strtolower(trim($current));
        if ($n === '' || $n === 'n/a') return 'empty';
        if (strpos($n, 'loaded') === 0) return 'loaded';
        return 'empty';
    }

    /** Returns the current stage token: 'base' | 'pickup' | 'on_trip' | 'delivered'. */
    function cl_normalise_stage(string $current): string {
        $n = strtolower(trim($current));
        if (str_contains($n, 'delivered')) return 'delivered';
        if (str_contains($n, 'on trip'))   return 'on_trip';
        if (str_contains($n, 'pickup'))    return 'pickup';
        return 'base';
    }

    /**
     * Apply the new stage to a booking, preserving the Empty/Loaded lane.
     * Returns the new container_status string, or '' when nothing changed.
     */
    function cl_advance_booking(PDO $conn, string $booking_no, string $target): string {
        $target = strtolower($target);
        if (!in_array($target, ['pickup', 'on_trip', 'delivered'], true)) return '';

        $stmt = $conn->prepare("SELECT container_status FROM booking WHERE booking_no = ? LIMIT 1");
        $stmt->execute([$booking_no]);
        $row = $stmt->fetch();
if (!$row) return '';

        $current = (string)($row['container_status'] ?? '');
        $lane    = cl_normalise_lane($current);
        $stage   = cl_normalise_stage($current);

        // Ordering: base < pickup < on_trip < delivered.
        static $rank = ['base' => 0, 'pickup' => 1, 'on_trip' => 2, 'delivered' => 3];
        if ($rank[$target] <= $rank[$stage]) return '';   // already there, no rewind

        $laneLabel = ($lane === 'loaded') ? 'Loaded' : 'Empty';
        $stageLabel = [
            'pickup'    => 'Container Pickup',
            'on_trip'   => 'Container On Trip',
            'delivered' => 'Container Delivered',
        ][$target];
        $next = $laneLabel . ' ' . $stageLabel;

        $stmt = $conn->prepare("UPDATE booking SET container_status = ? WHERE booking_no = ?");
        $stmt->execute([$next, $booking_no]);
return $next;
    }

    /**
     * Wrapper that resolves booking_no from a dispatch id.
     * Returns the new status (or '') on no-op.
     */
    function cl_advance_dispatch(PDO $conn, int $d_id, string $target): string {
        $stmt = $conn->prepare("SELECT booking_no FROM dispatch WHERE d_id = ? LIMIT 1");
        $stmt->execute([$d_id]);
        $row = $stmt->fetch();
if (!$row || empty($row['booking_no'])) return '';
        return cl_advance_booking($conn, $row['booking_no'], $target);
    }
}

if (!function_exists('pt_customer_is_import')) {
    /**
     * Import-direction customer? Import bookings start LOADED and spawn an
     * EMPTY return (the CTH lifecycle direction, generalized via
     * customer.trade_type). CTH stays Import even on legacy rows. Result is
     * cached per request since the active-containers feed calls it per row.
     */
    function pt_customer_is_import(PDO $conn, string $customer_code): bool {
        static $cache = [];
        $key = strtoupper(trim($customer_code));
        if ($key === '') return false;
        if (array_key_exists($key, $cache)) return $cache[$key];
        if ($key === 'CTH') return $cache[$key] = true;
        try {
            $s = $conn->prepare("SELECT trade_type FROM customer WHERE UPPER(customer_code) = ? LIMIT 1");
            $s->execute([$key]);
            $tt = (string)($s->fetchColumn() ?: '');
        } catch (Throwable $e) { $tt = ''; }
        return $cache[$key] = (strcasecmp($tt, 'Import') === 0);
    }
}
