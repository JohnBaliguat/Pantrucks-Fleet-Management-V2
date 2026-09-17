<?php
/**
 * Hunt for `=== '1'` / `=== '0'` / `== '1'` / `== '0'` comparisons on
 * known BOOLEAN columns. mysqli returns booleans as string '0'/'1';
 * PDO_PGSQL returns them as real PHP bool (or sometimes 't'/'f' depending
 * on driver options). Either way these strict comparisons silently break.
 */
$root = realpath(__DIR__ . '/../..');
$BOOL_COLS = ['maintenance_blocked', 'foul_trip', 'requires_ack', 'billing_active',
              'verified', 'authorised', 'customs_cleared', 'offline',
              'fuel_ok', 'tyres_ok', 'lights_ok', 'cargo_area_ok', 'genset_ok'];

$alt = implode('|', array_map(fn($c) => preg_quote($c, '/'), $BOOL_COLS));
$regex = '/\$\w+\[\s*[\'"](?:' . $alt . ')[\'"]\s*\]\s*(===?)\s*[\'"]?[01][\'"]?/';

$skip = ['#/migrations(_postgres)?/#', '#/Pantrucks/#', '#/alert/#',
         '#/reports/TCPDF-main/#', '#/node_modules/#', '#/vendor/#',
         '#/datatable/#', '#/php/config/#'];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
$hits = [];
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $p = str_replace('\\', '/', $f->getPathname());
    foreach ($skip as $s) if (preg_match($s, $p)) continue 2;
    $src = file_get_contents($p);
    if ($src === false) continue;
    if (preg_match_all($regex, $src, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $m) {
            $ln = substr_count(substr($src, 0, $m[1]), "\n") + 1;
            $hits[] = [str_replace($root . '/', '', $p), $ln, trim(explode("\n", $src)[$ln - 1] ?? '')];
        }
    }
}

echo count($hits) . " strict-bool-comparison hits\n";
foreach ($hits as [$f, $ln, $snip]) {
    if (strlen($snip) > 110) $snip = substr($snip, 0, 107) . '...';
    echo "  $f:$ln  $snip\n";
}
