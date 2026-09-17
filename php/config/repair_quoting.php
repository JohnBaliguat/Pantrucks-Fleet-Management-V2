<?php
/**
 * Repair the double-quoting bug introduced by convert_mysqli_to_pdo.php.
 *
 * Bug: when SQL like "SELECT * FROM user WHERE …" was inside a
 * double-quoted PHP string, the converter inserted "user" literally,
 * which broke the PHP string into "SELECT * FROM " + user + " WHERE …".
 *
 * Fix: tokenize each PHP file; when we see the pattern
 *
 *   T_CONSTANT_ENCAPSED_STRING ending with '"'  +
 *   T_STRING 'user' (or other reserved word)    +
 *   T_CONSTANT_ENCAPSED_STRING starting with '"'
 *
 * we know the bareword used to live inside the string. Re-join them
 * with the bareword escaped as \"X\".
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

// Reserved words that the converter may have wrapped in "..."
$RESERVED = ['user', 'order', 'group', 'key', 'value', 'date', 'time', 'timestamp'];

$changedFiles = 0;
$totalRepairs = 0;

foreach ($files as $path) {
    $src = file_get_contents($path);
    if ($src === false) continue;
    // Cheap pre-filter
    $hit = false;
    foreach ($RESERVED as $w) {
        if (str_contains($src, "\"$w\"")) { $hit = true; break; }
    }
    if (!$hit) continue;

    $tokens = @token_get_all($src);
    if (!is_array($tokens)) continue;

    $repairs = 0;
    $out     = '';
    $i = 0; $n = count($tokens);
    while ($i < $n) {
        $tok = $tokens[$i];

        // We're scanning for the broken triplet:
        //   T_CONSTANT_ENCAPSED_STRING (ends with '"')
        //   T_STRING (a reserved word)
        //   T_CONSTANT_ENCAPSED_STRING (starts with '"')
        if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING
            && substr($tok[1], -1) === '"' && substr($tok[1], 0, 1) === '"'
            && isset($tokens[$i+1], $tokens[$i+2])
        ) {
            $mid = $tokens[$i+1];
            $right = $tokens[$i+2];
            if (is_array($mid) && $mid[0] === T_STRING
                && in_array(strtolower($mid[1]), $RESERVED, true)
                && is_array($right) && $right[0] === T_CONSTANT_ENCAPSED_STRING
                && substr($right[1], 0, 1) === '"' && substr($right[1], -1) === '"'
            ) {
                // Merge: trim trailing " from left, prepend \"<word>\" + leading " is gone from right.
                $leftBody  = substr($tok[1], 0, -1);            // drops trailing "
                $rightBody = substr($right[1], 1);              // drops leading "
                $merged    = $leftBody . '\\"' . $mid[1] . '\\"' . $rightBody;
                $out .= $merged;
                $i += 3;
                $repairs++;
                continue;
            }
        }

        // Normal token — append source.
        $out .= is_array($tok) ? $tok[1] : $tok;
        $i++;
    }

    if ($repairs > 0) {
        $changedFiles++;
        $totalRepairs += $repairs;
        $rel = str_replace($root . '/', '', $path);
        echo ($apply ? '✓' : '~') . " $rel  ($repairs repairs)\n";
        if ($apply) file_put_contents($path, $out);
    }

    // ----- Second pass: regex fallback for interpolated double-quoted PHP
    // strings (which the tokenizer above splits across multiple tokens).
    //
    // Pattern:  "...some SQL... FROM \"user\" ...more SQL..."
    // The literal `"user"` was inserted inside what was a single PHP string.
    // We can detect it because the line still has the surrounding `"..."` pair
    // and the `"user"` sits in the middle. We re-escape as \"user\".
    $src2 = file_get_contents($path);
    if ($src2 === false) continue;

    $r2 = 0;
    $src2 = preg_replace_callback(
        '/"([^"\n]*\b(?:FROM|JOIN|UPDATE|INTO)\s+)"(user|order|key|value)"((?:[^"\n]|\\\\")*)"/i',
        function ($m) use (&$r2) {
            $r2++;
            return '"' . $m[1] . '\\"' . $m[2] . '\\"' . $m[3] . '"';
        },
        $src2
    );

    if ($r2 > 0 && $apply) {
        file_put_contents($path, $src2);
        $rel = str_replace($root . '/', '', $path);
        echo "  ↺ regex pass: $rel  ($r2 repairs)\n";
        $totalRepairs += $r2;
    }
}

echo "\n=== summary ===\n";
echo "Files changed: $changedFiles\n";
echo "Total repairs: $totalRepairs\n";
