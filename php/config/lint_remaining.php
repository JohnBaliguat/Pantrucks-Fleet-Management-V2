<?php
/**
 * Find PHP files that still need manual MySQLi → PDO/PostgreSQL fixes
 * after the auto-converter ran. Prints a punch list grouped by category.
 */

$root = realpath(__DIR__ . '/../..');
$skipDirs = ['#/migrations(_postgres)?/#', '#/Pantrucks/#', '#/alert/#',
             '#/reports/TCPDF-main/#', '#/node_modules/#', '#/vendor/#',
             '#/datatable/#', '#/php/config/#'];

$patterns = [
    'mysqli_func'        => '/\bmysqli_\w+\(/',
    'bind_param'         => '/->bind_param\(/',
    'bind_result'        => '/->bind_result\(/',
    'get_result'         => '/->get_result\(/',
    'fetch_assoc'        => '/->fetch_assoc\(/',
    'fetch_array'        => '/->fetch_array\(/',
    'num_rows_prop'      => '/->num_rows\b/',
    'affected_rows_prop' => '/->affected_rows\b/',
    'insert_id_prop'     => '/->insert_id\b/',
    'real_escape'        => '/->real_escape_string\(/',
    'pt_todo'            => '/PT_TODO/',
    'on_duplicate_key'   => '/ON DUPLICATE KEY/i',
    'group_concat'       => '/GROUP_CONCAT\s*\(/i',
    'date_format'        => '/\bDATE_FORMAT\s*\(/i',
    'last_insert_id_sql' => '/LAST_INSERT_ID\s*\(\s*\)/i',
    'curdate'            => '/\bCURDATE\s*\(\s*\)/i',
    'now_format'         => '/\bNOW\s*\(\s*\)\b/i', // works in PG, just count
    'backtick'           => '/`\w+`/',
];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $pat) if (preg_match($pat, $path)) continue 2;
    $files[] = $path;
}
sort($files);

$counts  = array_fill_keys(array_keys($patterns), 0);
$matches = array_fill_keys(array_keys($patterns), []); // category → [paths]

foreach ($files as $path) {
    $src = file_get_contents($path);
    if ($src === false) continue;
    foreach ($patterns as $name => $pat) {
        if (preg_match($pat, $src)) {
            $counts[$name]++;
            $matches[$name][] = str_replace($root . '/', '', $path);
        }
    }
}

echo "Files scanned: " . count($files) . "\n\n";
foreach ($counts as $name => $n) {
    if ($n === 0) continue;
    printf("[%s] %d files\n", $name, $n);
    foreach (array_slice($matches[$name], 0, 8) as $p) {
        echo "  - $p\n";
    }
    if ($n > 8) echo "  ... and " . ($n - 8) . " more\n";
    echo "\n";
}
