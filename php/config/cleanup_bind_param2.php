<?php
/**
 * Third-pass cleanup — handle conditional bind_param patterns the first
 * converter didn't catch:
 *
 *   if ($params) {
 *       $stmt->bind_param($types, ...$params);
 *   }
 *   $stmt->execute();
 *
 *   → $stmt->execute($params);
 *
 *   if (!empty($params)) { $stmt->bind_param(...) }
 *   if (count($params)) { ... }
 *
 * Plus standalone:
 *   $stmt->bind_param("ssi", $a, $b, $c);
 *     ... (multiple lines / blank lines / comments) ...
 *   $stmt->execute();
 *
 *   → $stmt->execute([$a, $b, $c]);   (and original bind_param removed)
 */

$root  = realpath(__DIR__ . '/../..');
$apply = in_array('--apply', $argv, true);

$skipDirs = ['#/migrations(_postgres)?/#', '#/Pantrucks/#', '#/alert/#',
             '#/reports/TCPDF-main/#', '#/node_modules/#', '#/vendor/#',
             '#/datatable/#', '#/php/config/#'];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $pat) if (preg_match($pat, $path)) continue 2;
    if (!str_contains(file_get_contents($path) ?: '', 'bind_param')) continue;
    $files[] = $path;
}
sort($files);

$totalChanges = 0;
$changedFiles = 0;

foreach ($files as $path) {
    $orig = file_get_contents($path);
    $src  = $orig;
    $changes = 0;

    // Pattern A: if-block with spread, then execute() (most common in chartjs/)
    //   if (...) { $stmt->bind_param($types, ...$X); }   $stmt->execute();
    //   → $stmt->execute($X);
    $src = preg_replace_callback(
        '/if\s*\([^{}]+\)\s*\{\s*\$(\w+)->bind_param\([^,]+,\s*\.\.\.\$(\w+)\s*\)\s*;\s*\}\s*\$\1->execute\(\s*\)\s*;/',
        function ($m) use (&$changes) { $changes++; return '$' . $m[1] . '->execute($' . $m[2] . ');'; },
        $src
    );

    // Pattern B: bind_param with positional vars followed by execute() on later line
    //   $stmt->bind_param("ssi", $a, $b, $c);
    //   <comments / blank lines>
    //   $stmt->execute();
    //   → $stmt->execute([$a, $b, $c]);    (bind_param removed)
    //
    // Allow up to ~10 lines between, skipping only whitespace/comments.
    $src = preg_replace_callback(
        '/\$(\w+)->bind_param\(\s*("[^"]*"|\'[^\']*\'|\$\w+)\s*,\s*([^)]+)\)\s*;((?:\s*(?:\/\/[^\n]*|\/\*.*?\*\/)?\s*\n){0,15}?)\s*\$\1->execute\(\s*\)\s*;/s',
        function ($m) use (&$changes) {
            $changes++;
            $vars   = trim($m[3]);
            $between = $m[4];
            // If $vars starts with "..." it's a spread (single array variable).
            if (preg_match('/^\.\.\.\$(\w+)$/', $vars, $sm)) {
                return $between . '$' . $m[1] . '->execute($' . $sm[1] . ');';
            }
            return $between . '$' . $m[1] . '->execute([' . $vars . ']);';
        },
        $src
    );

    if ($src !== $orig) {
        $changedFiles++;
        $totalChanges += $changes;
        $rel = str_replace($root . '/', '', $path);
        echo ($apply ? "✓" : "~") . " $rel  ($changes changes)\n";
        if ($apply) file_put_contents($path, $src);
    }
}

echo "\n=== summary ===\n";
echo "Files changed: $changedFiles\n";
echo "Total fixes:   $totalChanges\n";
