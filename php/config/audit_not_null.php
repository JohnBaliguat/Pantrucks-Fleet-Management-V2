<?php
/**
 * Sweep all `public` tables for NOT NULL columns that lack a default and
 * have a type that MySQL silently filled when an INSERT omitted them but
 * Postgres refuses. Reports them so we can decide table-by-table.
 *
 *   - VARCHAR / TEXT NOT NULL no-default  → add DEFAULT ''   (--apply-text)
 *   - DATE / TIMESTAMP / TIME NOT NULL    → drop NOT NULL    (--apply-date)
 *   - BOOLEAN NOT NULL no-default         → leave alone (suspicious; needs eyes)
 *   - INTEGER / NUMERIC NOT NULL          → leave alone (usually FK / counter)
 *
 * Identity / primary key columns are excluded automatically (they're
 * trivially NOT NULL and auto-assigned).
 *
 * Usage:
 *   php audit_not_null.php                 # dry run, print report
 *   php audit_not_null.php --apply-text    # add DEFAULT '' to VARCHAR/TEXT
 *   php audit_not_null.php --apply-date    # drop NOT NULL on date/timestamp/time
 *   php audit_not_null.php --apply-text --apply-date
 */

require_once __DIR__ . '/config.php';

$applyText = in_array('--apply-text', $argv, true);
$applyDate = in_array('--apply-date', $argv, true);

// Skip these columns even if they're NOT NULL — they should stay strict.
$NEVER_TOUCH = [
    'd_id', 'driver_id', 'unit_id', 'trailer_id', 'booking_id', 'customer_id',
    'trip_id', 'user_id', 'tc_id', 'um_id', 'inc_id', 'pod_id', 'gc_id',
    'tj_id', 'msg_id', 'we_id', 'ps_id', 'ra_id', 'dr_id', 'pdc_id',
    'gl_id', 'gq_id', 'ds_id', 'psl_id', 'pc_id', 'r_id', 'rr_id',
    's_id', 'sr_id', 'smp_id', 'da_id', 'am_id', 'fi_id', 'f_id',
    'log_id', 'monitoring_id', 'hauling_id', 'location_id', 'tm_id',
    't_id', 'receipts_id', 'gs_id', 'id',
];

$cols = $conn->query(
    "SELECT table_name, column_name, data_type, is_identity
     FROM information_schema.columns
     WHERE table_schema = 'public'
       AND is_nullable = 'NO'
       AND column_default IS NULL
     ORDER BY table_name, ordinal_position"
)->fetchAll();

$varcharFixes = [];
$dateFixes    = [];
$other        = [];

foreach ($cols as $c) {
    if ($c['is_identity'] === 'YES') continue;
    if (in_array($c['column_name'], $NEVER_TOUCH, true)) continue;
    $dt = $c['data_type'];
    if (in_array($dt, ['character varying', 'text', 'character'], true)) {
        $varcharFixes[] = $c;
    } elseif (in_array($dt, ['date', 'timestamp without time zone', 'timestamp with time zone', 'time without time zone'], true)) {
        $dateFixes[] = $c;
    } else {
        $other[] = $c;
    }
}

function groupByTable(array $rows): array {
    $out = [];
    foreach ($rows as $r) $out[$r['table_name']][] = $r['column_name'];
    return $out;
}

echo "=== VARCHAR / TEXT NOT NULL with no default (candidates for DEFAULT '') ===\n";
$byTbl = groupByTable($varcharFixes);
foreach ($byTbl as $tbl => $list) printf("  %-30s %s\n", $tbl, implode(', ', $list));
echo "Total: " . count($varcharFixes) . " columns across " . count($byTbl) . " tables\n\n";

echo "=== DATE / TIMESTAMP / TIME NOT NULL (candidates for DROP NOT NULL) ===\n";
$byTblD = groupByTable($dateFixes);
foreach ($byTblD as $tbl => $list) printf("  %-30s %s\n", $tbl, implode(', ', $list));
echo "Total: " . count($dateFixes) . " columns across " . count($byTblD) . " tables\n\n";

echo "=== Other types — left alone, review by hand ===\n";
foreach ($other as $c) printf("  %s.%s (%s)\n", $c['table_name'], $c['column_name'], $c['data_type']);
echo "Total: " . count($other) . "\n\n";

if ($applyText && $varcharFixes) {
    echo "Applying DEFAULT '' to VARCHAR/TEXT columns...\n";
    foreach ($varcharFixes as $c) {
        try {
            $tbl = $c['table_name'];
            $col = $c['column_name'];
            $tblRef = in_array($tbl, ['user','order','group'], true) ? "\"$tbl\"" : $tbl;
            $conn->exec("ALTER TABLE $tblRef ALTER COLUMN $col SET DEFAULT ''");
            echo "  ✓ $tbl.$col\n";
        } catch (Throwable $e) {
            echo "  ✗ $tbl.$col — " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

if ($applyDate && $dateFixes) {
    echo "Dropping NOT NULL on DATE/TIMESTAMP columns...\n";
    foreach ($dateFixes as $c) {
        try {
            $tbl = $c['table_name'];
            $col = $c['column_name'];
            $tblRef = in_array($tbl, ['user','order','group'], true) ? "\"$tbl\"" : $tbl;
            $conn->exec("ALTER TABLE $tblRef ALTER COLUMN $col DROP NOT NULL");
            echo "  ✓ $tbl.$col\n";
        } catch (Throwable $e) {
            echo "  ✗ $tbl.$col — " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

if (!$applyText && !$applyDate) {
    echo "DRY RUN. Pass --apply-text and/or --apply-date to apply.\n";
}
