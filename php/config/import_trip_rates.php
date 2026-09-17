<?php
require_once __DIR__ . '/config.php';

$csvPath = $argv[1] ?? null;
$apply   = in_array('--apply', $argv, true);
if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php import_trip_rates.php <csv> [--apply]\n");
    exit(1);
}

$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
$header = array_map(fn($h) => trim($h), $header);
$idx = array_flip($header);

if (!isset($idx['id'], $idx['segment'], $idx['activity'], $idx['baseRate'], $idx['additional'])) {
    fwrite(STDERR, "CSV must have id, segment, activity, baseRate, additional columns\n");
    exit(1);
}

// total_rates is GENERATED ALWAYS in the DB — don't insert into it.
$sql = "INSERT INTO trip_rates (id, segment, activity, base_rate, additional)
        OVERRIDING SYSTEM VALUE
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (id) DO NOTHING";

if ($apply) $stmt = $conn->prepare($sql);

$rowNum = 1; $inserted = 0; $skipped = 0; $errors = []; $preview = [];

while (($row = fgetcsv($fh)) !== false) {
    $rowNum++;
    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

    $id       = (int)$row[$idx['id']];
    $segment  = trim((string)$row[$idx['segment']]);
    $activity = trim((string)$row[$idx['activity']]);
    $base     = (float)$row[$idx['baseRate']];
    $add      = (float)$row[$idx['additional']];

    if (count($preview) < 3) {
        $preview[] = sprintf("  - id=%d %s/%s base=%.2f add=%.2f total=%.2f",
            $id, $segment, $activity, $base, $add, $base + $add);
    }

    if ($apply) {
        try {
            $stmt->execute([$id, $segment, $activity, $base, $add]);
            if ($stmt->rowCount() > 0) $inserted++; else $skipped++;
        } catch (Throwable $e) {
            $errors[] = "Row $rowNum (id=$id): " . $e->getMessage();
        }
    }
}
fclose($fh);

echo "CSV rows scanned: " . ($rowNum - 1) . "\n";
echo "Sample:\n" . implode("\n", $preview) . "\n";

if (!$apply) { echo "\nDRY RUN. Re-run with --apply.\n"; exit(0); }

echo "\nInserted: $inserted\n";
echo "Skipped:  $skipped (ON CONFLICT)\n";
if ($errors) {
    echo "Errors:   " . count($errors) . "\n";
    foreach (array_slice($errors, 0, 5) as $e) echo "  - $e\n";
}

// Bump the IDENTITY sequence past the highest inserted id.
$maxId = (int)$conn->query("SELECT MAX(id) FROM trip_rates")->fetchColumn();
if ($maxId > 0) {
    $seq = $conn->query("SELECT pg_get_serial_sequence('trip_rates', 'id')")->fetchColumn();
    if ($seq) {
        $conn->exec("SELECT setval('$seq', $maxId)");
        echo "Sequence reset: next id will be " . ($maxId + 1) . "\n";
    }
}

echo "Total trip_rates rows: " . $conn->query("SELECT COUNT(*) FROM trip_rates")->fetchColumn() . "\n";
