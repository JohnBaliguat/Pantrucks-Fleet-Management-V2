<?php
/**
 * Import trailer CSV into Supabase. Usage:
 *   php import_trailer.php "C:/path/to/trailer.csv"
 *   php import_trailer.php "C:/path/to/trailer.csv" --apply
 *
 * trailer_id values are preserved (OVERRIDING SYSTEM VALUE) so any
 * existing FK references stay aligned. Other adapters:
 *   - empty / "NULL" → null for nullable timestamp/date columns
 *   - empty t_date (NOT NULL TIMESTAMP) → NOW() so the row passes the constraint
 *   - empty for INTEGER driver_id → 0
 *   - "0"/"1" for BOOLEAN maintenance_blocked → false/true
 * Idempotent: ON CONFLICT (trailer_id) DO NOTHING.
 */

require_once __DIR__ . '/config.php';

$csvPath = $argv[1] ?? null;
$apply   = in_array('--apply', $argv, true);

if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php import_trailer.php <csv-path> [--apply]\n");
    exit(1);
}

$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
$header = array_map('trim', $header);

$insertCols = array_values(array_filter($header, fn($c) => $c !== ''));

$nullableCols = ['current_base_updated_at', 'maintenance_expected_return', 'maintenance_blocked_at'];
$boolCols     = ['maintenance_blocked'];
$intCols      = ['trailer_id', 'driver_id'];
$nowDefaultCols = ['t_date']; // NOT NULL TIMESTAMP — default to NOW() when blank

function coerce($value, string $col, array $nullable, array $bools, array $ints, array $nowDefault) {
    $v = trim((string)$value);
    if ($v === '' || strtoupper($v) === 'NULL') {
        if (in_array($col, $nullable, true))   return null;
        if (in_array($col, $bools, true))      return false;
        if (in_array($col, $ints, true))       return 0;
        if (in_array($col, $nowDefault, true)) return date('Y-m-d H:i:s');
        return '';
    }
    if (in_array($col, $bools, true)) return ($v === '1' || strtolower($v) === 'true' || strtolower($v) === 't');
    if (in_array($col, $ints, true))  return (int)$v;
    return $v;
}

$colList     = implode(', ', $insertCols);
$placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
$sql = "INSERT INTO trailer ($colList)
        OVERRIDING SYSTEM VALUE
        VALUES ($placeholders)
        ON CONFLICT (trailer_id) DO NOTHING";

if ($apply) $stmt = $conn->prepare($sql);

$rowNum = 1; $inserted = 0; $skipped = 0; $errors = []; $preview = [];
$idx = array_flip($header);

while (($row = fgetcsv($fh)) !== false) {
    $rowNum++;
    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

    $values = [];
    foreach ($insertCols as $col) {
        $raw = $row[$idx[$col]] ?? '';
        $values[] = coerce($raw, $col, $nullableCols, $boolCols, $intCols, $nowDefaultCols);
    }

    if (count($preview) < 3) {
        $preview[] = [
            'id'     => $values[array_search('trailer_id', $insertCols)],
            'name'   => $values[array_search('trailer_name', $insertCols)],
            'status' => $values[array_search('trailer_status', $insertCols)],
        ];
    }

    if ($apply) {
        try {
            foreach ($values as $i => $v) {
                $col = $insertCols[$i];
                if ($v === null)                          $type = PDO::PARAM_NULL;
                elseif (in_array($col, $boolCols, true))  $type = PDO::PARAM_BOOL;
                else                                       $type = PDO::PARAM_STR;
                $stmt->bindValue($i + 1, $v, $type);
            }
            $stmt->execute();
            if ($stmt->rowCount() > 0) $inserted++; else $skipped++;
        } catch (Throwable $e) {
            $errors[] = "Row $rowNum: " . $e->getMessage();
        }
    }
}
fclose($fh);

echo "CSV rows scanned: " . ($rowNum - 1) . "\n";
echo "Sample of first 3:\n";
foreach ($preview as $p) printf("  - id=%s  %-12s status=%s\n", $p['id'], $p['name'], $p['status']);

if (!$apply) { echo "\nDRY RUN. Re-run with --apply.\n"; exit(0); }

echo "\nInserted: $inserted\n";
echo "Skipped:  $skipped (ON CONFLICT)\n";
if ($errors) {
    echo "Errors:   " . count($errors) . "\n";
    foreach (array_slice($errors, 0, 5) as $e) echo "  - $e\n";
}

// Reset IDENTITY sequence past the max imported id.
$maxId = (int)$conn->query("SELECT MAX(trailer_id) FROM trailer")->fetchColumn();
if ($maxId > 0) {
    $seq = $conn->query("SELECT pg_get_serial_sequence('trailer', 'trailer_id')")->fetchColumn();
    if ($seq) {
        $conn->exec("SELECT setval('$seq', $maxId)");
        echo "Sequence reset: next trailer_id will be " . ($maxId + 1) . "\n";
    }
}

echo "Total trailers now in table: " . $conn->query("SELECT COUNT(*) FROM trailer")->fetchColumn() . "\n";
