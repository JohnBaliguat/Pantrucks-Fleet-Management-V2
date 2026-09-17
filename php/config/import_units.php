<?php
/**
 * Import units CSV into Supabase. Usage:
 *   php import_units.php "C:/Users/john.baliguat/Downloads/units (13).csv"
 *   php import_units.php "C:/Users/john.baliguat/Downloads/units (13).csv" --apply
 *
 * `unit_id` in the CSV is blank — let Postgres' IDENTITY assign it.
 * Other adapters:
 *   - "NULL" / empty → null for nullable timestamp/date columns
 *   - empty string for NOT NULL varchar columns → ''
 *   - empty for INTEGER driver_id → 0
 *   - empty/"" for NUMERIC unit_std → 0
 *   - "0"/"1" for BOOLEAN maintenance_blocked → false/true
 * Idempotent: ON CONFLICT does nothing (unique key is unit_name + unit_type).
 */

require_once __DIR__ . '/config.php';

$csvPath = $argv[1] ?? null;
$apply   = in_array('--apply', $argv, true);

if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php import_units.php <csv-path> [--apply]\n");
    exit(1);
}

$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // strip BOM
$header = array_map(fn($h) => trim($h), $header);

// Columns to insert (omit unit_id — let IDENTITY assign).
$insertCols = array_filter($header, fn($c) => $c !== 'unit_id' && $c !== '');
$insertCols = array_values($insertCols);

// Type hints — controls how we coerce each cell.
$nullableCols = ['current_location_updated_at', 'maintenance_expected_return', 'maintenance_blocked_at'];
$boolCols     = ['maintenance_blocked'];
$intCols      = ['driver_id'];
$numericCols  = ['unit_std'];

function coerce($value, string $col, array $nullable, array $bools, array $ints, array $nums) {
    $v = trim((string)$value);
    if ($v === '' || strtoupper($v) === 'NULL') {
        if (in_array($col, $nullable, true)) return null;
        if (in_array($col, $bools, true))    return false;
        if (in_array($col, $ints, true))     return 0;
        if (in_array($col, $nums, true))     return 0;
        return '';
    }
    if (in_array($col, $bools, true)) return ($v === '1' || strtolower($v) === 'true' || strtolower($v) === 't');
    if (in_array($col, $ints, true))  return (int)$v;
    if (in_array($col, $nums, true))  return (float)$v;
    return $v;
}

$colList     = implode(', ', $insertCols);
$placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
$sql = "INSERT INTO units ($colList) VALUES ($placeholders)";

if ($apply) $stmt = $conn->prepare($sql);

$rowNum = 1; $inserted = 0; $errors = []; $preview = [];
$idx = array_flip($header);

while (($row = fgetcsv($fh)) !== false) {
    $rowNum++;
    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

    $values = [];
    foreach ($insertCols as $col) {
        $raw = $row[$idx[$col]] ?? '';
        $values[] = coerce($raw, $col, $nullableCols, $boolCols, $intCols, $numericCols);
    }

    if (count($preview) < 3) {
        $preview[] = ['unit_name' => $values[array_search('unit_name', $insertCols)],
                      'unit_type' => $values[array_search('unit_type', $insertCols)],
                      'unit_std'  => $values[array_search('unit_std', $insertCols)]];
    }

    if ($apply) {
        try {
            // Bind each value with the correct PDO type so booleans / nulls / ints
            // hit Postgres as the right SQL type rather than ''.
            foreach ($values as $i => $v) {
                $col = $insertCols[$i];
                if ($v === null)                       $type = PDO::PARAM_NULL;
                elseif (in_array($col, $boolCols, true)) $type = PDO::PARAM_BOOL;
                elseif (in_array($col, $intCols, true) || in_array($col, $numericCols, true)) $type = PDO::PARAM_STR; // numeric goes as string, PG casts
                else                                    $type = PDO::PARAM_STR;
                $stmt->bindValue($i + 1, $v, $type);
            }
            $stmt->execute();
            $inserted++;
        } catch (Throwable $e) {
            $errors[] = "Row $rowNum: " . $e->getMessage();
        }
    }
}
fclose($fh);

echo "CSV rows scanned: " . ($rowNum - 1) . "\n";
echo "Sample of first 3:\n";
foreach ($preview as $p) {
    printf("  - %-12s type=%-8s std=%s\n", $p['unit_name'], $p['unit_type'], $p['unit_std']);
}

if (!$apply) { echo "\nDRY RUN. Re-run with --apply.\n"; exit(0); }

echo "\nInserted: $inserted\n";
if ($errors) {
    echo "Errors:   " . count($errors) . "\n";
    foreach (array_slice($errors, 0, 5) as $e) echo "  - $e\n";
}

echo "\nTotal units now in table: " . $conn->query("SELECT COUNT(*) FROM units")->fetchColumn() . "\n";
