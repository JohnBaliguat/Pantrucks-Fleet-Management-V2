<?php
/**
 * Scan PHP code for column-name references that won't match what Supabase
 * actually returns. PostgreSQL folds unquoted identifiers to lowercase, so
 * a column originally written as `driver_IdNumber` is stored as
 * `driver_idnumber`. PDO returns keys exactly as Postgres stored them.
 *
 * The earlier fix script `fix_camelcase_keys.php` handled `$x['Camel']` and
 * `$x["Camel"]`. This script audits for everything else:
 *
 *   - Object access:        $row->camelCase
 *   - array_key_exists:     array_key_exists('camelCase', $row)
 *   - isset on object:      isset($row->camelCase)
 *   - in_array / array_keys callers that pass camelCase strings
 *   - SQL queries that quote the column in DOUBLE-quotes inside a SQL string
 *     (only those break; unquoted SQL is fine — PG folds)
 *
 * Each hit is reported with file:line and category. Nothing is written.
 */

$root = realpath(__DIR__ . '/../..');

// Camel-case column names taken from the original MySQL dump.
$dumpPath = $root . '/migrations/000_base_schema.sql';
$dump = file_get_contents($dumpPath);
preg_match_all('/`([A-Za-z_][A-Za-z0-9_]*)`/', $dump, $m);
$camelCols = array_values(array_unique(array_filter($m[1], fn($id) => $id !== strtolower($id))));
sort($camelCols);

// Pre-build alternation for the regex. preg_quote each, then OR them.
$alt = implode('|', array_map(fn($c) => preg_quote($c, '/'), $camelCols));

// Files to scan.
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

$patterns = [
    'object_access'      => '/->(' . $alt . ')\b/',                              // $row->driver_IdNumber
    'isset_object'       => '/isset\s*\(\s*\$\w+->(' . $alt . ')\b/',            // isset($row->Camel)
    'array_key_exists'   => '/array_key_exists\s*\(\s*[\'"](' . $alt . ')[\'"]/',// array_key_exists('Camel', $row)
    'in_array_haystack'  => '/in_array\s*\(\s*[\'"](' . $alt . ')[\'"]/',        // in_array('Camel', ...)
    'sql_dquote'         => '/"(' . $alt . ')"/',                                // SQL: "Camel" inside any string
];

$hits = [];           // category => [ [file, line, snippet, col], ... ]
foreach ($patterns as $cat => $regex) $hits[$cat] = [];

foreach ($files as $path) {
    $rel = str_replace($root . '/', '', $path);
    $src = file_get_contents($path);
    if ($src === false) continue;

    // Skip files with no camelCase token at all (fast filter).
    $hasAny = false;
    foreach ($camelCols as $c) {
        if (str_contains($src, $c)) { $hasAny = true; break; }
    }
    if (!$hasAny) continue;

    $lines = explode("\n", $src);
    foreach ($patterns as $cat => $regex) {
        if (preg_match_all($regex, $src, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $fullMatch) {
                $offset = $fullMatch[1];
                $lineNo = substr_count(substr($src, 0, $offset), "\n") + 1;
                $snippet = trim($lines[$lineNo - 1] ?? '');
                if (strlen($snippet) > 110) $snippet = substr($snippet, 0, 107) . '...';
                $hits[$cat][] = [$rel, $lineNo, $snippet, $matches[1][$i][0]];
            }
        }
    }
}

// Filter sql_dquote false positives: "Camel" inside something that is
// definitely an SQL string we control (FROM/JOIN/UPDATE/INTO/SELECT/AS
// preceding). Those quoted forms are intentional. Otherwise it's a hit.
$hits['sql_dquote'] = array_values(array_filter($hits['sql_dquote'], function ($h) {
    return !preg_match('/(FROM|JOIN|UPDATE|INTO|SELECT|AS|WHERE|AND|OR|,|\(|=)\s*"[A-Za-z_]+"/', $h[2]);
}));

echo "Camel-case columns considered: " . count($camelCols) . "\n";
echo "Files scanned: " . count($files) . "\n\n";

foreach ($hits as $cat => $list) {
    if (!$list) continue;
    echo "=== $cat (" . count($list) . " hits) ===\n";
    foreach ($list as [$rel, $line, $snip, $col]) {
        echo "  $rel:$line  [$col]\n    $snip\n";
    }
    echo "\n";
}

if (array_sum(array_map('count', $hits)) === 0) {
    echo "No camelCase mismatches found.\n";
}
