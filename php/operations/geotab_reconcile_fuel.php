<?php
// =====================================================================
// Fuel reconciliation — Phase 4.
//
// For each recent Gastender fuel ticket (fuel_report), compare the litres
// dispensed and km reported against Geotab's engine-measured total-fuel and
// odometer deltas over the interval since that unit's previous ticket.
// Writes one fuel_reconciliation row per ticket (upsert by f_id).
//
// Batch job — run ~daily from Task Scheduler:
//   php "…/php/operations/geotab_reconcile_fuel.php" [lookback_days]
// =====================================================================

$isCli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/geotab_client.php';

if (!$isCli) {
    session_start();
    header('Content-Type: application/json');
    if (($_SESSION['user_type'] ?? '') !== 'Admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Admin only']);
        exit;
    }
}

$lookbackDays = (int)($argv[1] ?? ($_GET['lookback_days'] ?? 90));
if ($lookbackDays < 1 || $lookbackDays > 365) { $lookbackDays = 90; }

$fuelPct = defined('GEOTAB_FUEL_VARIANCE_PCT') ? (float)GEOTAB_FUEL_VARIANCE_PCT : 15;
$kmPct   = defined('GEOTAB_KM_VARIANCE_PCT')   ? (float)GEOTAB_KM_VARIANCE_PCT   : 15;
$diagFuel = defined('GEOTAB_DIAG_TOTAL_FUEL') ? GEOTAB_DIAG_TOTAL_FUEL : null;
$diagOdo  = defined('GEOTAB_DIAG_ODOMETER')   ? GEOTAB_DIAG_ODOMETER   : null;

/** ISO-8601 UTC 'Z' string from a DB timestamp (assumed UTC). */
function pt_fuel_iso(string $ts): ?string {
    try { return (new DateTime($ts, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'); }
    catch (Throwable $e) { return null; }
}

$processed = 0; $flagged = 0;

try {
    // unit_name => device_id
    $deviceMap = [];
    foreach ($conn->query(
        "SELECT unit_name, geotab_device_id FROM units WHERE geotab_device_id IS NOT NULL"
    )->fetchAll() as $u) {
        $deviceMap[(string)$u['unit_name']] = (string)$u['geotab_device_id'];
    }

    // Latest cumulative diagnostic value at or before $isoTo, or null.
    $valueAt = function (string $deviceId, string $diagId, string $isoTo, string $isoFrom): ?float {
        $rows = pt_geotab_get('StatusData', [
            'deviceSearch'     => ['id' => $deviceId],
            'diagnosticSearch' => ['id' => $diagId],
            'fromDate'         => $isoFrom,
            'toDate'           => $isoTo,
        ], 500);
        $best = null;
        foreach ($rows as $r) {
            if (!isset($r['data']) || !is_numeric($r['data'])) continue;
            $dt = (string)($r['dateTime'] ?? '');
            if ($best === null || strcmp($dt, (string)$best['dt']) > 0) {
                $best = ['v' => (float)$r['data'], 'dt' => $dt];
            }
        }
        return $best['v'] ?? null;
    };

    // Tickets in the lookback window, oldest first per unit so we can pair each
    // with the immediately-previous ticket for the same unit.
    $tickets = $conn->prepare(
        "SELECT f_id, f_unit, f_date, f_noOfLit, f_kmRun
           FROM fuel_report
          WHERE f_date >= NOW() - (? || ' days')::interval
          ORDER BY f_unit, f_date ASC"
    );
    $tickets->execute([$lookbackDays]);

    $prevByUnit = [];  // f_unit => previous f_date

    $upsert = $conn->prepare(
        "INSERT INTO fuel_reconciliation
            (f_id, unit_name, device_id, period_start, period_end,
             ticket_liters, geotab_liters, liters_variance,
             ticket_km, geotab_km, km_variance, flag, computed_at)
         VALUES
            (:fid, :unit, :dev, :pstart, :pend,
             :tl, :gl, :lv, :tk, :gk, :kv, :flag, NOW())
         ON CONFLICT (f_id) DO UPDATE SET
            unit_name=EXCLUDED.unit_name, device_id=EXCLUDED.device_id,
            period_start=EXCLUDED.period_start, period_end=EXCLUDED.period_end,
            ticket_liters=EXCLUDED.ticket_liters, geotab_liters=EXCLUDED.geotab_liters,
            liters_variance=EXCLUDED.liters_variance, ticket_km=EXCLUDED.ticket_km,
            geotab_km=EXCLUDED.geotab_km, km_variance=EXCLUDED.km_variance,
            flag=EXCLUDED.flag, computed_at=NOW()"
    );

    foreach ($tickets->fetchAll() as $t) {
        $unit = (string)$t['f_unit'];
        $end  = (string)$t['f_date'];
        $start = $prevByUnit[$unit] ?? null;
        $prevByUnit[$unit] = $end;   // advance for the next ticket of this unit

        $ticketL = is_numeric($t['f_noOfLit']) ? (float)$t['f_noOfLit'] : null;
        $ticketK = is_numeric($t['f_kmRun'])   ? (float)$t['f_kmRun']   : null;

        $deviceId = $deviceMap[$unit] ?? '';
        $geoL = null; $geoK = null; $flag = 'no_data';

        // Need a device, a previous ticket (interval), and diagnostics configured.
        if ($deviceId !== '' && $start !== null) {
            $isoStart = pt_fuel_iso($start);
            $isoEnd   = pt_fuel_iso($end);
            if ($isoStart && $isoEnd) {
                // Look back up to 2 days before each boundary for the nearest reading.
                $win = function (string $iso): string {
                    return (new DateTime($iso))->modify('-2 days')->format('Y-m-d\TH:i:s\Z');
                };
                try {
                    if ($diagFuel) {
                        $fEnd   = $valueAt($deviceId, $diagFuel, $isoEnd, $win($isoStart));
                        $fStart = $valueAt($deviceId, $diagFuel, $isoStart, $win($isoStart));
                        if ($fEnd !== null && $fStart !== null && $fEnd >= $fStart) {
                            $geoL = round($fEnd - $fStart, 2);  // litres (cumulative)
                        }
                    }
                    if ($diagOdo) {
                        $oEnd   = $valueAt($deviceId, $diagOdo, $isoEnd, $win($isoStart));
                        $oStart = $valueAt($deviceId, $diagOdo, $isoStart, $win($isoStart));
                        if ($oEnd !== null && $oStart !== null && $oEnd >= $oStart) {
                            $geoK = round(($oEnd - $oStart) / 1000, 1);  // metres -> km
                        }
                    }
                } catch (Throwable $de) {
                    error_log('[geotab_reconcile_fuel] status read: ' . $de->getMessage());
                }
            }
        }

        $litersVar = ($ticketL !== null && $geoL !== null) ? round($ticketL - $geoL, 2) : null;
        $kmVar     = ($ticketK !== null && $geoK !== null) ? round($ticketK - $geoK, 1) : null;

        // Classify. Litres over-dispensing takes precedence over km drift.
        if ($geoL !== null && $litersVar !== null && abs($litersVar) > ($geoL * $fuelPct / 100)) {
            $flag = 'liters_flag';
        } elseif ($geoK !== null && $kmVar !== null && $geoK > 0 && abs($kmVar) > ($geoK * $kmPct / 100)) {
            $flag = 'km_flag';
        } elseif ($geoL !== null || $geoK !== null) {
            $flag = 'ok';
        } else {
            $flag = 'no_data';
        }
        if ($flag === 'liters_flag' || $flag === 'km_flag') { $flagged++; }

        $upsert->execute([
            ':fid' => (int)$t['f_id'], ':unit' => $unit, ':dev' => $deviceId,
            ':pstart' => $start, ':pend' => $end,
            ':tl' => $ticketL, ':gl' => $geoL, ':lv' => $litersVar,
            ':tk' => $ticketK, ':gk' => $geoK, ':kv' => $kmVar, ':flag' => $flag,
        ]);
        $processed++;
    }

    $summary = "processed=$processed flagged=$flagged lookback={$lookbackDays}d";
    if ($isCli) {
        echo "[geotab_reconcile_fuel] OK — $summary\n";
    } else {
        echo json_encode(['status' => 'success', 'processed' => $processed, 'flagged' => $flagged]);
    }
} catch (Throwable $e) {
    error_log('[geotab_reconcile_fuel] ' . $e->getMessage());
    if ($isCli) {
        fwrite(STDERR, '[geotab_reconcile_fuel] ERROR — ' . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
