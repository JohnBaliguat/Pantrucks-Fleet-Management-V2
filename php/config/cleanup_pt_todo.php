<?php
/**
 * Second-pass cleanup of PT_TODO markers left by convert_mysqli_to_pdo.php.
 *
 * Many files have the pattern:
 *   $stmt = $conn->prepare(...);
 *   /* PT_TODO ... /* $stmt->execute([$args]) *\/
 *   ...possibly other code...
 *   $stmt->execute();
 *
 * The PT_TODO comment carries the correct execute() form (with params).
 * This script finds the comment, extracts the params, and rewrites the
 * NEXT empty $stmt->execute() call to use them. Then removes the comment.
 *
 * Usage:  php cleanup_pt_todo.php             # dry run
 *         php cleanup_pt_todo.php --apply     # rewrite files in place
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
    $files[] = $path;
}
sort($files);

$totalResolved = 0;
$totalSkipped  = 0;
$changedFiles  = 0;

foreach ($files as $path) {
    $src = file_get_contents($path);
    if ($src === false || !str_contains($src, 'PT_TODO bind_param')) continue;

    $orig    = $src;
    $resolved = 0;
    $skipped  = 0;

    // Find every PT_TODO marker.
    $pattern = '~/\*\s*PT_TODO bind_param without paired execute\(\)[^*]*\*/\s*/\*\s*(\$(\w+)->execute\((\[.*?\]|\(\)|\(\s*\))\))\s*\*/~s';
    $src = preg_replace_callback($pattern, function ($m) use (&$resolved, &$skipped, &$src) {
        $fullCall = $m[1];   // "$stmt->execute([$a, $b])"
        $stmtVar  = $m[2];   // "stmt"
        // We can't easily look "ahead" in a replace_callback, so just leave a
        // sentinel containing the correct execute call, and run a second pass.
        return "__PT_RESOLVE__($stmtVar|$fullCall)__";
    }, $src);

    // Second pass: for each __PT_RESOLVE__ sentinel, find the next
    // $<stmtVar>->execute(<anything>); call and replace it. Then drop sentinel.
    while (preg_match('/__PT_RESOLVE__\(([^|]+)\|(.+?)\)__/s', $src, $m, PREG_OFFSET_CAPTURE)) {
        $stmtVar  = $m[1][0];
        $fullCall = $m[2][0];
        $startPos = $m[0][1];
        $endPos   = $startPos + strlen($m[0][0]);

        // Find the next $stmtVar->execute(...) call after the sentinel.
        $needle = '/\$' . preg_quote($stmtVar, '/') . '->execute\([^;]*\)/';
        if (preg_match($needle, $src, $em, PREG_OFFSET_CAPTURE, $endPos)) {
            $callStart = $em[0][1];
            $callLen   = strlen($em[0][0]);
            // Replace the execute() call with the corrected one from PT_TODO.
            $src = substr($src, 0, $callStart) . $fullCall . substr($src, $callStart + $callLen);
            // Remove the sentinel.
            $src = substr($src, 0, $startPos) . substr($src, $startPos + strlen($m[0][0]));
            // Note: the sentinel was BEFORE callStart, so removing it shifts later
            // offsets. The next loop iteration uses fresh preg_match offsets.
            $resolved++;
        } else {
            // No paired execute() found — convert sentinel back to a clear comment so we don't loop.
            $src = substr($src, 0, $startPos)
                 . "/* PT_REVIEW: no paired execute() found for: $fullCall */"
                 . substr($src, $endPos);
            $skipped++;
        }
    }

    if ($src !== $orig) {
        $changedFiles++;
        $totalResolved += $resolved;
        $totalSkipped  += $skipped;
        $rel = str_replace($root . '/', '', $path);
        echo ($apply ? "✓" : "~") . " $rel  (resolved $resolved, skipped $skipped)\n";
        if ($apply) file_put_contents($path, $src);
    }
}

echo "\n=== summary ===\n";
echo "Files changed:    $changedFiles\n";
echo "PT_TODO resolved: $totalResolved\n";
echo "Need manual fix:  $totalSkipped  (look for PT_REVIEW)\n";
