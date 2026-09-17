<?php
/**
 * Piece-rate lookup for Fleet Management.
 * Mirrors the E-Pantrucks helper but adapted for Fleet's column names.
 *
 * Rates are keyed on (segment, activity). The segment match prefers exact —
 * if no segment-specific row exists, falls back to a row with empty segment
 * (generic catch-all). This lets you define a default rate per activity AND
 * override it for specific segments.
 */

if (!function_exists('fleet_trip_rate_key')) {
    /**
     * Build the trip-side SKU / lookup key (for DISPLAY):
     * haulingSegment.haulingType.containerStat. Kept human-readable — matching
     * is done on the normalized form (fleet_normalize_key_for_match).
     */
    function fleet_trip_rate_key(string $segment, string $haulingType, string $containerStat): string
    {
        return trim($segment) . '.' . trim($haulingType) . '.' . trim($containerStat);
    }
}

if (!function_exists('fleet_normalize_hauling_type')) {
    /**
     * Canonicalise a hauling-type spelling so near-duplicates resolve to one
     * rate. Uppercases, collapses whitespace, tidies '/', fixes the common
     * "REFEER" typo, then applies an editable alias map.
     *
     * Extend $aliases below to fold new spellings onto a canonical value.
     */
    function fleet_normalize_hauling_type(string $s): string
    {
        $t = strtoupper(trim($s));
        $t = preg_replace('/\s*\/\s*/', '/', $t);   // "REEFER VAN / DRY VAN" -> "REEFER VAN/DRY VAN"
        $t = preg_replace('/\s+/', ' ', $t);         // collapse internal spaces
        $t = str_replace('REFEER', 'REEFER', $t);    // common typo
        $t = str_replace('DRYVAN', 'DRY VAN', $t);   // spacing variant

        // Canonical map — key is the already-tidied form above (UPPER, spaces
        // collapsed, typos fixed). Map each full hauling-type name onto the
        // UPPERCASED short code stored in trip_rates.trip_key, so a trip's full
        // name and the rate's code normalise to the same value and match.
        // e.g. "Reefer Van" (trip) and "RV" (rate) both become "RV".
        //
        // ADD YOUR HAULING-TYPE CODES HERE (full name in UPPER => CODE in UPPER):
        static $aliases = [
            'REEFER VAN'         => 'RV',
            'REEFER VAN/DRY VAN' => 'RV',
            'DRY VAN/REEFER VAN' => 'RV',
            // 'DRY VAN'         => 'DV',
        ];
        return $aliases[$t] ?? $t;
    }
}

if (!function_exists('fleet_normalize_segment')) {
    /**
     * Canonicalise a hauling-segment spelling. Base normalisation (upper /
     * trim / whitespace-collapse) already folds pure case variants (goo/GOO);
     * the alias map below folds known spelling variants onto a canonical value.
     *
     * Seeded conservatively — only add pairs you're sure are the same segment
     * (don't fold, say, "ABC" into "ABC CATEEL" unless they truly are one).
     */
    function fleet_normalize_segment(string $s): string
    {
        $t = preg_replace('/\s+/', ' ', strtoupper(trim($s)));

        // Map each full segment name onto the UPPERCASED short code stored in
        // trip_rates.trip_key, so a trip's full segment and the rate's code
        // normalise to the same value and match. e.g. "ABC CATEEL" (trip) and
        // "ABCCat" (rate) both become "ABCCAT".
        //
        // ADD YOUR SEGMENT CODES HERE (full name in UPPER => CODE in UPPER):
        static $aliases = [
            'ABC CATEEL' => 'ABCCAT',
            // 'GOOD FARMER' => 'GF',
        ];
        return $aliases[$t] ?? $t;
    }
}

if (!function_exists('fleet_normalize_containerstat')) {
    /**
     * Canonicalise a container status to its core state. Every status in the
     * data starts with Loaded / Empty (e.g. "Empty Container Delivered",
     * "Loaded Container Pickup"), and the piece rate only depends on that, so
     * we keep just the first word: LOADED / EMPTY. Blank stays blank.
     */
    function fleet_normalize_containerstat(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        $first = preg_split('/\s+/', $s)[0];
        return strtoupper($first);
    }
}

if (!function_exists('fleet_normalize_key_for_match')) {
    /**
     * Normalise a full SKU key for comparison: upper/trim/whitespace-collapse
     * every dot-separated part, then canonicalise the segment (first),
     * hauling-type (middle) and container-status (last) parts of a 3-part key.
     */
    function fleet_normalize_key_for_match(string $key): string
    {
        $parts = explode('.', $key);
        foreach ($parts as $i => $p) {
            $parts[$i] = preg_replace('/\s+/', ' ', strtoupper(trim($p)));
        }
        if (count($parts) >= 1) {
            $parts[0] = fleet_normalize_segment($parts[0]);
        }
        if (count($parts) === 3) {
            $parts[1] = fleet_normalize_hauling_type($parts[1]);
            $parts[2] = fleet_normalize_containerstat($parts[2]);
        }
        return implode('.', $parts);
    }
}

if (!function_exists('fleet_lookup_rate_by_trip_key')) {
    /**
     * Look a rate up by a trip's SKU (haulingSegment.haulingType.containerStat)
     * against trip_rates.trip_key, matching on the NORMALISED key so hauling-type
     * spelling variants (Reefer Van / REEFER VAN / REFEER VAN / …) resolve to one
     * rate. trip_rates is small, so we normalise in PHP for flexibility.
     * Returns ['matched' => bool, 'total_rates' => float, 'key' => string,
     *          'matched_key' => string]. 'key' is the trip-derived SKU (full
     * names); 'matched_key' is the matched trip_rates.trip_key (the short code)
     * or '' when nothing matched.
     */
    function fleet_lookup_rate_by_trip_key(PDO $conn, string $segment, string $haulingType, string $containerStat): array
    {
        $key = fleet_trip_rate_key($segment, $haulingType, $containerStat);
        $out = ['matched' => false, 'total_rates' => 0.0, 'key' => $key, 'matched_key' => ''];
        if (trim($segment) === '' && trim($haulingType) === '' && trim($containerStat) === '') {
            return $out;
        }
        $target = fleet_normalize_key_for_match($key);
        try {
            // Match the trip's SKU against BOTH columns:
            //   - trip_key: the dedicated SKU column (populated on ~23 rows).
            //   - activity: many coded SKUs actually live here (e.g. the
            //     "ABCCat.RV.Loaded" family), so we match it too. Both are
            //     normalised, so only dotted-SKU activities line up — descriptive
            //     activities like "Reefer Van - Loaded" can't collide with a
            //     3-part key. A trip_key hit is preferred over an activity hit.
            // Latest id wins on ties, matching the previous ORDER BY id DESC.
            $stmt = $conn->query(
                "SELECT trip_key, activity, total_rates FROM trip_rates ORDER BY id DESC"
            );
            $activityHit = null;
            while ($r = $stmt->fetch()) {
                $tk = trim((string)($r['trip_key'] ?? ''));
                if ($tk !== '' && fleet_normalize_key_for_match($tk) === $target) {
                    $out['matched'] = true;
                    $out['total_rates'] = (float)$r['total_rates'];
                    $out['matched_key'] = $tk;   // the code, as stored
                    return $out;                 // trip_key match wins outright
                }
                $act = trim((string)($r['activity'] ?? ''));
                if ($activityHit === null && $act !== ''
                    && fleet_normalize_key_for_match($act) === $target) {
                    $activityHit = ['total_rates' => (float)$r['total_rates'], 'key' => $act];
                }
            }
            if ($activityHit !== null) {
                $out['matched'] = true;
                $out['total_rates'] = $activityHit['total_rates'];
                $out['matched_key'] = $activityHit['key'];
            }
        } catch (Throwable $e) { /* column may not exist on legacy DB */ }
        return $out;
    }
}

if (!function_exists('fleet_lookup_flat_rate')) {
    /**
     * Look up a flat (non-SKU) rate by an exact trip_key, matching on the
     * normalised key. Used for per-container activities like DICT Hustling,
     * whose rate is stored under a 1-part trip_key ('DICT HUSTLING') rather
     * than the 3-part Segment.HaulingType.ContainerStat SKU. Returns 0.0 when
     * no such key exists.
     */
    function fleet_lookup_flat_rate(PDO $conn, string $tripKey): float
    {
        $target = fleet_normalize_key_for_match($tripKey);
        if ($target === '') return 0.0;
        try {
            $stmt = $conn->query(
                "SELECT trip_key, total_rates FROM trip_rates WHERE trip_key <> '' ORDER BY id DESC"
            );
            while ($r = $stmt->fetch()) {
                if (fleet_normalize_key_for_match((string)$r['trip_key']) === $target) {
                    return (float)$r['total_rates'];
                }
            }
        } catch (Throwable $e) { /* legacy DB */ }
        return 0.0;
    }
}

if (!function_exists('fleet_ensure_trip_rate_stub')) {
    /**
     * Ensure a trip_rates row exists for this trip's SKU. Called at dispatch
     * time: if the SKU (Segment.HaulingType.<Loaded|Empty>) matches no existing
     * rate — by trip_key OR activity, normalised — insert a 0.00 placeholder
     * flagged auto_created so the admin sees the missing SKU on the Trip Rates
     * page and can price it.
     *
     * No-op for incomplete SKUs (any part blank) and safe to call repeatedly
     * (the unique (segment, activity) index prevents duplicates). Returns true
     * if a stub was inserted.
     */
    function fleet_ensure_trip_rate_stub(PDO $conn, string $segment, string $haulingType, string $containerStat): bool
    {
        $seg      = trim($segment);
        $type     = trim($haulingType);
        $statWord = fleet_normalize_containerstat($containerStat);   // LOADED / EMPTY / ''
        if ($seg === '' || $type === '' || $statWord === '') {
            return false;   // incomplete — nothing to key a rate on
        }

        // Already priced/known? (trip_key OR activity, normalised — same matcher
        // the coupon/payroll copy use, so aliases like RV↔Reefer Van count.)
        $lk = fleet_lookup_rate_by_trip_key($conn, $seg, $type, $containerStat);
        if ($lk['matched']) {
            return false;
        }

        $statTitle = ucfirst(strtolower($statWord));    // Loaded / Empty
        $tripKey   = $seg . '.' . $type . '.' . $statTitle;
        $activity  = $type . ' - ' . $statTitle;        // readable, unique per (segment, activity)

        // This runs inside the dispatch transaction. In Postgres a failed
        // statement aborts the whole transaction, so we (a) ON CONFLICT DO
        // NOTHING to swallow the expected unique(segment,activity) clash, and
        // (b) wrap in a SAVEPOINT so any other surprise rolls back to here and
        // can never poison the caller's dispatch transaction.
        $inTx = $conn->inTransaction();
        try {
            if ($inTx) { $conn->exec('SAVEPOINT fleet_rate_stub'); }
            $ins = $conn->prepare(
                "INSERT INTO trip_rates (segment, activity, trip_key, base_rate, additional, auto_created)
                 VALUES (?, ?, ?, 0, 0, TRUE)
                 ON CONFLICT (LOWER(segment), LOWER(activity)) DO NOTHING"
            );
            $ins->execute([$seg, $activity, $tripKey]);
            if ($inTx) { $conn->exec('RELEASE SAVEPOINT fleet_rate_stub'); }
            return $ins->rowCount() > 0;
        } catch (Throwable $e) {
            if ($inTx) { try { $conn->exec('ROLLBACK TO SAVEPOINT fleet_rate_stub'); } catch (Throwable $e2) {} }
            return false;
        }
    }
}

if (!function_exists('fleet_compute_trip_piece_rate')) {
    /**
     * Compute the piece_rate to stamp on a trip AT DISPATCH TIME, using the
     * SAME matcher the Trip Verification Coupon uses
     * (fleet_lookup_rate_by_trip_key). This keeps the value stored in
     * trips.piece_rate identical to the rate the coupon displays.
     *
     * Why this exists: the DB trigger (migration 028) matches trip_rates on a
     * plain lower(segment) + activity-ends-with-Loaded/Empty rule, which does
     * NOT apply the segment/hauling-type alias maps in this file (e.g.
     * "ABC CATEEL" ↔ "ABCCat", "Reefer Van" ↔ "RV") and can't match a rate
     * stored only under a dotted trip_key SKU. So the coupon would find a rate
     * the trigger couldn't, leaving trips.piece_rate at 0. Passing this
     * value into the INSERT makes the coupon and the stored rate agree.
     *
     * Returns 0.0 when nothing matches — the caller can safely pass that
     * through to the trigger (which will also resolve to 0 / its own match),
     * so this never regresses the previous trigger-only behaviour.
     */
    function fleet_compute_trip_piece_rate(PDO $conn, string $segment, string $haulingType, string $containerStat): float
    {
        $lk = fleet_lookup_rate_by_trip_key($conn, $segment, $haulingType, $containerStat);
        if (empty($lk['matched'])) {
            return 0.0;
        }
        $rate = (float)$lk['total_rates'];
        return $rate > 0 ? $rate : 0.0;
    }
}

if (!function_exists('fleet_lookup_piece_rate')) {
    /**
     * Return the total_rates (NUMERIC) for the given segment + activity, or 0
     * if no matching row exists.
     */
    function fleet_lookup_piece_rate(PDO $conn, string $segment, string $activity): float
    {
        $segment  = trim($segment);
        $activity = trim($activity);
        if ($activity === '') return 0.0;

        $sql = "SELECT total_rates
                FROM trip_rates
                WHERE LOWER(TRIM(activity)) = LOWER(TRIM(?))
                  AND (? = '' OR LOWER(TRIM(segment)) = LOWER(TRIM(?)) OR segment = '')
                ORDER BY CASE WHEN LOWER(TRIM(segment)) = LOWER(TRIM(?)) THEN 0 ELSE 1 END,
                         id DESC
                LIMIT 1";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$activity, $segment, $segment, $segment]);
            return (float) ($stmt->fetchColumn() ?: 0.0);
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}
