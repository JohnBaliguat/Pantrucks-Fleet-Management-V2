<?php
/**
 * Enable Row Level Security on every table in the `public` schema.
 *
 * Why: Supabase exposes a REST API and anon key over every `public` table by
 * default. Without RLS, that's a public read of all rows. With RLS enabled and
 * no policies, the REST API returns zero rows for non-service-role callers.
 *
 * Our PHP app connects as the `postgres` role via PDO, which bypasses RLS, so
 * the app keeps working unchanged.
 */

require_once __DIR__ . '/config.php';

$tables = $conn->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
     ORDER BY table_name"
)->fetchAll(PDO::FETCH_COLUMN);

echo "Found " . count($tables) . " tables in public schema.\n\n";

$ok = 0; $err = 0;
foreach ($tables as $t) {
    $quoted = '"' . str_replace('"', '""', $t) . '"';
    try {
        $conn->exec("ALTER TABLE $quoted ENABLE ROW LEVEL SECURITY");
        echo "  ✓ $t\n";
        $ok++;
    } catch (PDOException $e) {
        echo "  ✗ $t — " . $e->getMessage() . "\n";
        $err++;
    }
}

// Verify
$status = $conn->query(
    "SELECT tablename, rowsecurity FROM pg_tables
     WHERE schemaname = 'public' ORDER BY tablename"
)->fetchAll(PDO::FETCH_ASSOC);

$enabled = 0; $disabled = [];
foreach ($status as $row) {
    if ($row['rowsecurity']) { $enabled++; } else { $disabled[] = $row['tablename']; }
}

echo "\n=== summary ===\n";
echo "Altered OK:   $ok\n";
echo "Errors:       $err\n";
echo "RLS enabled:  $enabled / " . count($status) . "\n";
if ($disabled) {
    echo "Still off:    " . implode(', ', $disabled) . "\n";
}
