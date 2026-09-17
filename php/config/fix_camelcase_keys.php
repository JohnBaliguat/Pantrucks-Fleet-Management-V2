<?php
/**
 * PostgreSQL folds unquoted identifiers to lowercase, so columns named
 * `driver_IdNumber` etc. in the original MySQL schema landed in Supabase
 * as `driver_idnumber`. PDO returns row keys exactly as Postgres stored
 * them, which breaks every $row['driver_IdNumber'] access in PHP code.
 *
 * This script:
 *   1) Reads the original MySQL schema dump (migrations/000_base_schema.sql)
 *      and lists every column whose name has at least one uppercase letter.
 *   2) For each PHP file, replaces $row['<camelCase>'] / $row["<camelCase>"]
 *      (and variations like $foo['<camelCase>']) with the lowercase form so
 *      they line up with the Postgres column names PDO returns.
 *
 * The SQL queries themselves don't need changing — unquoted SQL identifiers
 * fold to lowercase automatically and still resolve to the correct column.
 *
 * Usage:
 *   php fix_camelcase_keys.php             # dry run
 *   php fix_camelcase_keys.php --apply     # rewrite files
 */

$root  = realpath(__DIR__ . '/../..');
$apply = in_array('--apply', $argv, true);

$dumpPath = $root . '/migrations/000_base_schema.sql';
$dump = file_get_contents($dumpPath);
if ($dump === false) {
    fwrite(STDERR, "Cannot read $dumpPath\n"); exit(1);
}

// Collect every column name from the dump (between backticks). The dump
// uses `col_name` consistently for column definitions.
preg_match_all('/`([A-Za-z_][A-Za-z0-9_]*)`/', $dump, $m);
$allIdents = array_unique($m[1]);

// Filter to identifiers that have at least one uppercase letter — those
// are the ones Postgres lowercased on us. Exclude pure-lowercase and
// table names that are commonly written as $row keys (we still want
// column-name granularity).
$camelCols = [];
foreach ($allIdents as $id) {
    if ($id !== strtolower($id)) {
        $camelCols[$id] = strtolower($id);
    }
}
ksort($camelCols);

echo count($camelCols) . " camelCase column names to lowercase:\n";
foreach (array_slice(array_keys($camelCols), 0, 12) as $c) {
    echo "  - $c → " . $camelCols[$c] . "\n";
}
if (count($camelCols) > 12) echo "  ... and " . (count($camelCols) - 12) . " more\n";

// Walk PHP files and replace `$any['CamelCase']` / `$any["CamelCase"]`.
$skipDirs = ['#/migrations(_postgres)?/#', '#/Pantrucks/#', '#/alert/#',
             '#/reports/TCPDF-main/#', '#/node_modules/#', '#/vendor/#',
             '#/datatable/#', '#/php/config/#'];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $pat) if (preg_match($pat, $path)) continue 2;
    $files[] = $path;
}
sort($files);

$changedFiles = 0;
$totalReplaces = 0;
$perColCount = array_fill_keys(array_keys($camelCols), 0);

foreach ($files as $path) {
    $src = file_get_contents($path);
    if ($src === false) continue;

    $new = $src;
    foreach ($camelCols as $cc => $lc) {
        // $foo['driver_IdNumber']  → $foo['driver_idnumber']
        // $foo["driver_IdNumber"]  → $foo["driver_idnumber"]
        $pat1 = "/(\\\$\\w+\\[\\s*')" . preg_quote($cc, '/') . "(\\s*'\\s*\\])/";
        $pat2 = '/(\\\$\\w+\\[\\s*")' . preg_quote($cc, '/') . '(\\s*"\\s*\\])/';
        $new2 = preg_replace_callback($pat1, function ($m) use (&$totalReplaces, &$perColCount, $cc, $lc) {
            $totalReplaces++; $perColCount[$cc]++;
            return $m[1] . $lc . $m[2];
        }, $new);
        $new2 = preg_replace_callback($pat2, function ($m) use (&$totalReplaces, &$perColCount, $cc, $lc) {
            $totalReplaces++; $perColCount[$cc]++;
            return $m[1] . $lc . $m[2];
        }, $new2);
        $new = $new2;
    }

    if ($new !== $src) {
        $changedFiles++;
        if ($apply) file_put_contents($path, $new);
    }
}

echo "\n=== summary ===\n";
echo "Files changed: $changedFiles\n";
echo "Replacements: $totalReplaces\n";

arsort($perColCount);
echo "Top 10 columns by replacement count:\n";
$i = 0;
foreach ($perColCount as $col => $cnt) {
    if ($cnt === 0) continue;
    echo "  $col → " . $camelCols[$col] . "  ($cnt times)\n";
    if (++$i >= 10) break;
}

if (!$apply) echo "\nDRY RUN. Re-run with --apply to write.\n";
