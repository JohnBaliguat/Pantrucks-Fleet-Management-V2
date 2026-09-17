<?php
/**
 * Import drivers CSV into the Supabase `drivers` table.
 *
 * Usage:
 *   php import_drivers.php "C:/Users/john.baliguat/Downloads/driversName.csv"
 *   php import_drivers.php "C:/Users/john.baliguat/Downloads/driversName.csv" --apply
 *
 * Default is dry-run (parses, validates, prints first 3 rows but does NOT
 * write to the DB). Pass --apply to commit.
 *
 * The CSV has explicit driver_id values that we must preserve so other
 * tables' driver_id foreign keys still align. `drivers.driver_id` is
 * GENERATED ALWAYS AS IDENTITY, so we use OVERRIDING SYSTEM VALUE on the
 * INSERT and then reset the sequence to MAX(driver_id)+1.
 *
 * Idempotent: ON CONFLICT (driver_id) DO NOTHING. Re-run is safe.
 */

require_once __DIR__ . '/config.php';

$csvPath = $argv[1] ?? null;
$apply   = in_array('--apply', $argv, true);

if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php import_drivers.php <csv-path> [--apply]\n");
    fwrite(STDERR, "CSV not found.\n");
    exit(1);
}

$fh = fopen($csvPath, 'r');
if (!$fh) { fwrite(STDERR, "Cannot open $csvPath\n"); exit(1); }

$header = fgetcsv($fh);
if (!$header) { fwrite(STDERR, "Empty CSV\n"); exit(1); }
$header = array_map('trim', $header);
// Strip UTF-8 BOM from first column if present.
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

// Columns in our drivers table that match the CSV header.
$tableCols = [
    'driver_id', 'driver_idnumber', 'driver_rfid', 'driver_fname', 'driver_mname', 'driver_lname',
    'driver_contact', 'driver_image', 'driver_signature', 'driver_assignunit', 'driver_assignsegment',
    'driver_assignbase', 'driver_status', 'driver_email', 'driver_uname', 'driver_pass',
    'driver_account_status', 'shift_truck', 'shift_started_at', 'shift_ended_at', 'last_seen_at',
    'last_lat', 'last_lng', 'microsoft_oid', 'microsoft_tenant_id', 'microsoft_linked_at',
];

// Nullable column kinds — these accept NULL / convert "" → NULL.
$nullableCols = ['shift_started_at', 'shift_ended_at', 'last_seen_at', 'last_lat', 'last_lng', 'microsoft_linked_at'];
$intCols      = ['driver_id', 'driver_idnumber'];
$numericCols  = ['last_lat', 'last_lng'];

// Build positional lookup from header.
$idx = array_flip($header);
foreach ($tableCols as $c) {
    if (!isset($idx[$c])) {
        fwrite(STDERR, "Missing CSV column: $c\n"); exit(1);
    }
}

/**
 * Normalise a raw CSV cell into a PDO-bindable value.
 * - "NULL" or "" → null (for nullable cols) or '' (for non-null string cols)
 * - numeric / int cols return int/float
 */
function normaliseCell($value, string $col, array $nullable, array $ints, array $nums) {
    $v = trim((string)$value);
    if ($v === '' || strtoupper($v) === 'NULL') {
        if (in_array($col, $nullable, true) || in_array($col, $ints, true)) {
            return null;
        }
        return '';
    }
    if (in_array($col, $ints, true))   return (int)$v;
    if (in_array($col, $nums, true))   return (float)$v;
    return $v;
}

// Build INSERT once; reuse with bindings.
$colList = implode(', ', array_map(fn($c) => in_array(strtolower($c), ['user','order','key','value','date','time','timestamp'], true) ? "\"$c\"" : $c, $tableCols));
$placeholders = implode(', ', array_fill(0, count($tableCols), '?'));
$sql = "INSERT INTO drivers ($colList)
        OVERRIDING SYSTEM VALUE
        VALUES ($placeholders)
        ON CONFLICT (driver_id) DO NOTHING";

if ($apply) {
    $stmt = $conn->prepare($sql);
}

$rowNum = 1; // header was row 1
$inserted = 0;
$skipped  = 0;
$errors   = [];
$preview  = [];

while (($row = fgetcsv($fh)) !== false) {
    $rowNum++;
    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue; // blank line

    $values = [];
    foreach ($tableCols as $c) {
        $raw = $row[$idx[$c]] ?? '';
        $values[] = normaliseCell($raw, $c, $nullableCols, $intCols, $numericCols);
    }

    if ($values[0] === null || $values[0] === 0) {
        $skipped++;
        $errors[] = "Row $rowNum: missing/zero driver_id";
        continue;
    }

    if (count($preview) < 3) {
        $preview[] = [
            'driver_id' => $values[0],
            'name'      => trim($values[3] . ' ' . $values[5]),
            'email'     => $values[13],
            'uname'     => $values[14],
        ];
    }

    if ($apply) {
        try {
            $stmt->execute($values);
            if ($stmt->rowCount() > 0) {
                $inserted++;
            } else {
                $skipped++; // ON CONFLICT DO NOTHING swallowed it
            }
        } catch (Throwable $e) {
            $errors[] = "Row $rowNum (driver_id={$values[0]}): " . $e->getMessage();
        }
    }
}
fclose($fh);

echo "CSV rows scanned: " . ($rowNum - 1) . "\n";
echo "Sample of first 3:\n";
foreach ($preview as $p) {
    printf("  - id=%s  %s  <%s>  uname=%s\n", $p['driver_id'], $p['name'], $p['email'], $p['uname']);
}

if (!$apply) {
    echo "\nDRY RUN. Re-run with --apply to write to the database.\n";
    exit(0);
}

echo "\nInserted: $inserted\n";
echo "Skipped:  $skipped\n";
if ($errors) {
    echo "Errors:   " . count($errors) . "\n";
    foreach (array_slice($errors, 0, 5) as $e) echo "  - $e\n";
    if (count($errors) > 5) echo "  ... and " . (count($errors) - 5) . " more\n";
}

// Reset the IDENTITY sequence so future INSERTs (without driver_id) start
// after the highest driver_id we just imported.
$maxId = (int)$conn->query("SELECT MAX(driver_id) FROM drivers")->fetchColumn();
if ($maxId > 0) {
    $seq = $conn->query(
        "SELECT pg_get_serial_sequence('drivers', 'driver_id')"
    )->fetchColumn();
    if ($seq) {
        $conn->exec("SELECT setval('" . $seq . "', $maxId)");
        echo "\nSequence reset: nextval('$seq') will return " . ($maxId + 1) . "\n";
    }
}

echo "\nTotal drivers now in table: " . $conn->query("SELECT COUNT(*) FROM drivers")->fetchColumn() . "\n";
