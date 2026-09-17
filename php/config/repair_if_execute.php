<?php
/**
 * Repair if-execute syntax corruption from the PT_TODO cleanup.
 *
 * Bugs:
 *   1) The greedy regex in cleanup_pt_todo.php ate the closing ')' of
 *      surrounding `if (...)` when it replaced $stmt->execute().
 *      → `if ($X->execute([..])) {` is missing one ')' before '{'.
 *      → `if ($X->execute([..]);` is missing one ')' before ';'.
 *
 *   2) Some __PT_RESOLVE__ sentinels survived (no paired execute() found).
 *      Format: `$stmt->execute([args]);`
 *      → drop the wrapper, keep just the execute() call.
 */

$root  = realpath(__DIR__ . '/../..');
$apply = in_array('--apply', $argv, true);
$skipDirs = ['#/migrations(_postgres)?/#', '#/Pantrucks/#', '#/alert/#',
             '#/reports/TCPDF-main/#', '#/node_modules/#', '#/vendor/#',
             '#/datatable/#'];

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
$totalRepairs = 0;

foreach ($files as $path) {
    $orig = file_get_contents($path);
    if ($orig === false) continue;

    $src = $orig;
    $r = 0;

    // ----- Fix 2: surviving __PT_RESOLVE__ sentinels -----
    // Format: $stmtVar->execute([args]); ...)__
    // The original cleanup left this when no paired execute() was found.
    // Just strip the wrapper and keep the execute call.
    $src = preg_replace_callback(
        '/__PT_RESOLVE__\([^|]+\|(\$\w+->execute\([^;]*\)\s*;)\)?__?/',
        function ($m) use (&$r) { $r++; return $m[1]; },
        $src
    );
    // Also handle a half-formed variant where the closing ')__' got eaten.
    $src = preg_replace_callback(
        '/__PT_RESOLVE__\([^|]+\|(\$\w+->execute\([^;]*\)\s*;)/',
        function ($m) use (&$r) { $r++; return $m[1]; },
        $src
    );

    // ----- Fix 1a: `if ($X->execute([..])) {`  → add missing ')' before '{' -----
    // The execute argument is an array literal [...]. After the ']' and ')'
    // of execute, we expect ')' then ' {'. The bug dropped one ')'.
    $src = preg_replace_callback(
        '/(if\s*\(\s*!?\s*\$\w+->execute\(\[[^\]]*\]\))\s*\{/m',
        function ($m) use (&$r) { $r++; return $m[1] . ') {'; },
        $src
    );

    // ----- Fix 1b: `if ($X->execute([..]);` → `$X->execute([..]);` -----
    // The 'if' with empty body is an unconditional execute() followed by
    // dead else-branch elimination. Since we don't know what error handling
    // was lost, drop the `if (` wrapper and run unconditionally. PDO is
    // configured with PDO::ERRMODE_EXCEPTION so failures still throw.
    $src = preg_replace_callback(
        '/^(\s*)if\s*\(\s*!?\s*(\$\w+->execute\(\[[^\]]*\]\))\s*;/m',
        function ($m) use (&$r) { $r++; return $m[1] . $m[2] . ';'; },
        $src
    );

    // ----- Fix 1c: similar but `if ($X->execute([..])` followed by ')' on
    // its own line (a multi-line condition with split parens).
    // Rarely seen — handle conservatively.

    if ($src !== $orig) {
        $changedFiles++;
        $totalRepairs += $r;
        $rel = str_replace($root . '/', '', $path);
        echo ($apply ? '✓' : '~') . " $rel  ($r repairs)\n";
        if ($apply) file_put_contents($path, $src);
    }
}

echo "\n=== summary ===\n";
echo "Files changed: $changedFiles\n";
echo "Total repairs: $totalRepairs\n";
