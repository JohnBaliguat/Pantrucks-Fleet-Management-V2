<?php
/**
 * For every `$row['key']` (or "key") in PHP code, check if `key` is an
 * actual column anywhere in the Supabase schema. Anything that ISN'T —
 * may be a typo, a renamed column, or just a query alias (the common
 * legitimate case). Report what looks suspicious so we can audit.
 */

require_once __DIR__ . '/config.php';
$root = realpath(__DIR__ . '/../..');

// Get every column name across all tables.
$cols = $conn->query(
    "SELECT DISTINCT column_name FROM information_schema.columns WHERE table_schema='public'"
)->fetchAll(PDO::FETCH_COLUMN);
$colSet = array_flip(array_map('strtolower', $cols));

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

// Vars whose array access is likely DB-row content (heuristic — these are
// the variable names commonly used after fetch()/fetchAll()).
$dbRowVars = ['row', 'r', 'record', 'data', 'rec', 'driver', 'user', 'unit',
              'trailer', 'booking', 'trip', 'customer', 'dispatch'];

$suspectsByKey = []; // unknown_key => [ [file, line, var, snippet], ... ]

foreach ($files as $path) {
    $rel = str_replace($root . '/', '', $path);
    $src = file_get_contents($path);
    if ($src === false) continue;

    // Match $varname['key'] or $varname["key"] where varname looks DB-ish.
    if (!preg_match_all(
        '/\$(' . implode('|', $dbRowVars) . ')\b\s*\[\s*[\'"]([a-zA-Z_][\w]*)[\'"]\s*\]/',
        $src, $matches, PREG_OFFSET_CAPTURE
    )) continue;

    foreach ($matches[0] as $i => $fullMatch) {
        $var = $matches[1][$i][0];
        $key = $matches[2][$i][0];
        $offset = $fullMatch[1];

        if (isset($colSet[strtolower($key)])) continue; // it's a real column

        // Skip obvious PHP idioms (not DB keys).
        if (in_array($key, ['key','value','length','size','type','count','status','message','error','success','draw','start','data','recordstotal','recordsfiltered'], true)) continue;

        $lineNo = substr_count(substr($src, 0, $offset), "\n") + 1;
        $line = explode("\n", $src)[$lineNo - 1] ?? '';
        $line = trim($line);
        if (strlen($line) > 120) $line = substr($line, 0, 117) . '...';

        $suspectsByKey[$key][] = [$rel, $lineNo, $var, $line];
    }
}

// Sort by total hit count per key (most-referenced first).
uksort($suspectsByKey, function ($a, $b) use ($suspectsByKey) {
    return count($suspectsByKey[$b]) - count($suspectsByKey[$a]);
});

$totalUnknownKeys = count($suspectsByKey);
$totalRefs = array_sum(array_map('count', $suspectsByKey));

echo "Supabase columns: " . count($cols) . "\n";
echo "PHP files scanned: " . count($files) . "\n";
echo "Unknown keys: $totalUnknownKeys  (across $totalRefs references)\n\n";

// Print top 20 unknown keys with up to 3 example refs each.
$i = 0;
foreach ($suspectsByKey as $key => $refs) {
    if (++$i > 20) break;
    echo "[$key]  " . count($refs) . " refs\n";
    foreach (array_slice($refs, 0, 3) as [$file, $line, $var, $snip]) {
        echo "  $file:$line  (\$$var)\n    $snip\n";
    }
    if (count($refs) > 3) echo "  ... and " . (count($refs) - 3) . " more\n";
    echo "\n";
}
