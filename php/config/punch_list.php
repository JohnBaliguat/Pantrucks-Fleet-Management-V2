<?php
/**
 * Build a deduplicated punch list of files that still need manual fixup.
 * Outputs a JSON file the agents can read.
 */
$root = realpath(__DIR__ . '/../..');
$skipDirs = ['#/migrations(_postgres)?/#', '#/Pantrucks/#', '#/alert/#',
             '#/reports/TCPDF-main/#', '#/node_modules/#', '#/vendor/#',
             '#/datatable/#', '#/php/config/#'];

$patterns = [
    'bind_param'        => '/->bind_param\(/',
    'bind_result'       => '/->bind_result\(/',
    'pt_todo'           => '/PT_TODO/',
    'on_duplicate_key'  => '/ON DUPLICATE KEY/i',
    'group_concat'      => '/GROUP_CONCAT\s*\(/i',
    'date_format'       => '/\bDATE_FORMAT\s*\(/i',
    'last_insert_sql'   => '/LAST_INSERT_ID\s*\(\s*\)/i',
    'mysqli_func'       => '/\bmysqli_\w+\(/',
];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $pat) if (preg_match($pat, $path)) continue 2;
    $files[] = $path;
}

$punch = [];   // path => [issues]
foreach ($files as $path) {
    $src = file_get_contents($path);
    if ($src === false) continue;
    $issues = [];
    foreach ($patterns as $name => $pat) {
        if (preg_match($pat, $src)) $issues[] = $name;
    }
    if ($issues) {
        $rel = str_replace($root . '/', '', $path);
        $punch[$rel] = $issues;
    }
}

ksort($punch);

echo "Files needing manual fix: " . count($punch) . "\n\n";
foreach ($punch as $rel => $issues) {
    echo "$rel\n";
    echo "  [" . implode(', ', $issues) . "]\n";
}

file_put_contents(__DIR__ . '/punch_list.json', json_encode($punch, JSON_PRETTY_PRINT));
echo "\nJSON written to php/config/punch_list.json\n";
