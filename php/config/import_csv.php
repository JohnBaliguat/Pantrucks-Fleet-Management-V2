<?php
/**
 * Generic CSV → Postgres importer.
 *
 * Usage:
 *   php import_csv.php <table> <csv-path>             # dry run
 *   php import_csv.php <table> <csv-path> --apply
 *
 * Inspects information_schema to find the table's columns and their data
 * types, then coerces each CSV cell to the right SQL type:
 *   - "NULL" / "" → null (for nullable cols) or default sentinel (0 / '' / false / NOW())
 *   - 0/1 / "true"/"false" → BOOLEAN for boolean columns
 *   - Numeric strings → INT/NUMERIC where applicable
 *   - Empty NOT NULL timestamps → NOW()
 *
 * If the CSV has the table's primary identity column, uses OVERRIDING
 * SYSTEM VALUE so explicit IDs are preserved, and resets the IDENTITY
 * sequence afterward. ON CONFLICT (pk) DO NOTHING keeps re-runs idempotent.
 */

require_once __DIR__ . '/config.php';

$table   = $argv[1] ?? null;
$csvPath = $argv[2] ?? null;
$apply   = in_array('--apply', $argv, true);

if (!$table || !$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php import_csv.php <table> <csv-path> [--apply]\n");
    exit(1);
}

if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $table)) {
    fwrite(STDERR, "Bad table name.\n"); exit(1);
}

// Pull column metadata for the table.
$colMeta = $conn->prepare(
    "SELECT column_name, data_type, is_nullable, column_default, is_identity, identity_generation
     FROM information_schema.columns
     WHERE table_schema = 'public' AND table_name = ?
     ORDER BY ordinal_position"
);
$colMeta->execute([$table]);
$tableCols = $colMeta->fetchAll();
if (!$tableCols) { fwrite(STDERR, "Table '$table' not found in public schema.\n"); exit(1); }

// Find the identity / primary-key column (single-column PK assumed).
$pkRow = $conn->prepare(
    "SELECT kcu.column_name
     FROM information_schema.table_constraints tc
     JOIN information_schema.key_column_usage kcu
       ON tc.constraint_name = kcu.constraint_name
      AND tc.table_schema = kcu.table_schema
     WHERE tc.table_schema = 'public' AND tc.table_name = ?
       AND tc.constraint_type = 'PRIMARY KEY'
     ORDER BY kcu.ordinal_position
     LIMIT 1"
);
$pkRow->execute([$table]);
$pkCol = (string)($pkRow->fetchColumn() ?: '');

// Index column metadata by name for fast lookups.
$colByName = [];
foreach ($tableCols as $c) $colByName[strtolower($c['column_name'])] = $c;

// Open the CSV.
$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh);
if (!$header) { fwrite(STDERR, "Empty CSV\n"); exit(1); }
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
$header = array_map(fn($h) => strtolower(trim($h)), $header);

// Restrict insert to columns the CSV actually has AND that exist in the table.
$insertCols = [];
foreach ($header as $h) {
    if ($h !== '' && isset($colByName[$h])) $insertCols[] = $h;
}
if (!$insertCols) { fwrite(STDERR, "No CSV columns match table columns.\n"); exit(1); }

// Detect identity behaviour for the PK column.
$identityAlways = $pkCol && (($colByName[strtolower($pkCol)]['is_identity'] ?? '') === 'YES')
                 && (($colByName[strtolower($pkCol)]['identity_generation'] ?? '') === 'ALWAYS');

// If the PK column exists in the CSV but every row leaves it empty, drop
// it from the INSERT so Postgres assigns fresh IDs via IDENTITY.
$pkColLower = strtolower((string)$pkCol);
if ($pkColLower && in_array($pkColLower, $insertCols, true)) {
    $pkIdx = $idx ?? array_flip($header);
    $pkPosInHeader = $pkIdx[$pkColLower] ?? null;
    $allEmpty = true;
    if ($pkPosInHeader !== null) {
        $peek = fopen($csvPath, 'r');
        fgetcsv($peek); // skip header
        while (($r = fgetcsv($peek)) !== false) {
            $v = trim((string)($r[$pkPosInHeader] ?? ''));
            if ($v !== '' && strcasecmp($v, 'NULL') !== 0) { $allEmpty = false; break; }
        }
        fclose($peek);
    }
    if ($allEmpty) {
        $insertCols = array_values(array_filter($insertCols, fn($c) => $c !== $pkColLower));
        echo "PK column '$pkCol' is empty in every CSV row — letting IDENTITY assign new IDs.\n";
    }
}

$overriding = ($identityAlways && in_array(strtolower($pkCol), $insertCols, true)) ? 'OVERRIDING SYSTEM VALUE' : '';

// Coerce a single CSV cell against the column metadata.
function coerce(?string $value, array $colInfo) {
    $v = $value === null ? '' : trim($value);
    $dataType = strtolower($colInfo['data_type'] ?? 'text');
    $nullable = ($colInfo['is_nullable'] ?? 'YES') === 'YES';
    $hasDefault = !empty($colInfo['column_default']);

    $isEmpty = ($v === '' || strcasecmp($v, 'NULL') === 0);

    if ($isEmpty) {
        if ($nullable)                                       return null;
        if (str_contains($dataType, 'bool'))                 return false;
        if (in_array($dataType, ['smallint','integer','bigint','numeric','real','double precision'], true)) return 0;
        if (in_array($dataType, ['timestamp without time zone','timestamp with time zone','date','time without time zone'], true)) {
            return $hasDefault ? null : date('Y-m-d H:i:s');
        }
        return '';
    }

    if (str_contains($dataType, 'bool')) {
        return in_array(strtolower($v), ['1','true','t','yes','y'], true);
    }
    if (in_array($dataType, ['smallint','integer','bigint'], true)) {
        return (int)$v;
    }
    if (in_array($dataType, ['numeric','real','double precision'], true)) {
        return (float)$v;
    }
    return $v;
}

$reservedQuote = ['user','order','group','key','value','date','time','timestamp'];
$tableRefInsert = in_array(strtolower($table), $reservedQuote, true) ? "\"$table\"" : $table;
$colList = implode(', ', array_map(fn($c) => in_array($c, $reservedQuote, true) ? "\"$c\"" : $c, $insertCols));
$placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
$onConflict = $pkCol ? "ON CONFLICT ($pkCol) DO NOTHING" : '';
$sql = "INSERT INTO $tableRefInsert ($colList) $overriding VALUES ($placeholders) $onConflict";

if ($apply) $stmt = $conn->prepare($sql);

$idx = array_flip($header);
$rowNum = 1; $inserted = 0; $skipped = 0; $errors = []; $preview = [];

while (($row = fgetcsv($fh)) !== false) {
    $rowNum++;
    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

    $values = [];
    foreach ($insertCols as $col) {
        $raw = $row[$idx[$col]] ?? '';
        $values[] = coerce((string)$raw, $colByName[$col]);
    }

    if (count($preview) < 3) {
        $first2 = array_slice($insertCols, 0, 2);
        $line = '  -';
        foreach ($first2 as $c) $line .= " $c=" . var_export($values[array_search($c, $insertCols)], true);
        $preview[] = $line;
    }

    if ($apply) {
        try {
            foreach ($values as $i => $v) {
                $col = $insertCols[$i];
                $dt  = strtolower($colByName[$col]['data_type']);
                if ($v === null)                          $type = PDO::PARAM_NULL;
                elseif (str_contains($dt, 'bool'))        $type = PDO::PARAM_BOOL;
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

echo "Table:           $table\n";
echo "Insert columns:  " . implode(', ', $insertCols) . "\n";
echo "CSV rows:        " . ($rowNum - 1) . "\n";
echo "Sample of first 3:\n";
foreach ($preview as $p) echo "$p\n";

if (!$apply) { echo "\nDRY RUN. Add --apply to write.\n"; exit(0); }

echo "\nInserted: $inserted\n";
echo "Skipped:  $skipped (ON CONFLICT)\n";
if ($errors) {
    echo "Errors:   " . count($errors) . "\n";
    foreach (array_slice($errors, 0, 5) as $e) echo "  - $e\n";
}

// Reset IDENTITY sequence if we preserved IDs.
if ($identityAlways && in_array(strtolower($pkCol), $insertCols, true)) {
    $tableRef = in_array(strtolower($table), $reservedQuote, true) ? "\"$table\"" : $table;
    $maxId = (int)$conn->query("SELECT MAX($pkCol) FROM $tableRef")->fetchColumn();
    if ($maxId > 0) {
        $seq = $conn->query("SELECT pg_get_serial_sequence('$tableRef', '$pkCol')")->fetchColumn();
        if ($seq) {
            $conn->exec("SELECT setval('$seq', $maxId)");
            echo "Sequence reset: next $pkCol will be " . ($maxId + 1) . "\n";
        }
    }
}

$tableRefFinal = in_array(strtolower($table), $reservedQuote, true) ? "\"$table\"" : $table;
echo "Total in $table: " . $conn->query("SELECT COUNT(*) FROM $tableRefFinal")->fetchColumn() . "\n";
