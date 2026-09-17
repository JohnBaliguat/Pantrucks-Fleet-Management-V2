<?php
/**
 * Hunt for MySQL → Postgres porting bugs that are likely to break at
 * runtime even though `php -l` is clean.
 *
 * Categories (with brief explanation of why each is a bug):
 *
 *   stmt_insert_id     — $<stmt>->lastInsertId() is undefined on PDOStatement
 *                       (PDO::lastInsertId() lives on the connection, and on
 *                       pgsql it needs a sequence name → use RETURNING)
 *   mysqli_funcs       — leftover mysqli_*() calls
 *   real_escape        — leftover $conn->real_escape_string or
 *                       array_map([$conn, 'real_escape_string'], ...)
 *   bind_param         — leftover prepared-statement bind_param
 *   bind_result        — leftover bind_result (no PDO equivalent)
 *   store_result       — leftover mysqli store_result
 *   insert_id_prop     — leftover $conn->insert_id property
 *   limit_offset_my    — `LIMIT $a, $b` syntax (PG uses LIMIT b OFFSET a)
 *   ifnull             — IFNULL( — PG wants COALESCE(
 *   date_format        — DATE_FORMAT( — PG wants TO_CHAR(
 *   group_concat       — GROUP_CONCAT( — PG wants STRING_AGG(
 *   on_duplicate       — ON DUPLICATE KEY — PG wants ON CONFLICT
 *   curdate            — CURDATE() — PG wants CURRENT_DATE
 *   bool_eq_int        — `= 1` or `= 0` on known BOOLEAN columns
 *   backticks_sql      — surviving `backticks` (only the SQL-string ones)
 *   datatables_json    — DataTables endpoints that echo JSON without
 *                       suppressing PHP warnings or setting JSON header
 */

$root = realpath(__DIR__ . '/../..');

$BOOL_COLS = ['maintenance_blocked', 'foul_trip', 'requires_ack', 'billing_active',
              'verified', 'authorised', 'customs_cleared', 'offline',
              'fuel_ok', 'tyres_ok', 'lights_ok', 'cargo_area_ok', 'genset_ok'];

$patterns = [
    'stmt_insert_id'   => '/\$\w*stmt\w*->lastInsertId\(\)/',
    'mysqli_funcs'     => '/\bmysqli_[a-z_]+\(/',
    'real_escape'      => '/->real_escape_string\(|array_map\(\s*\[\s*\$\w+\s*,\s*[\'"]real_escape_string[\'"]\s*\]/',
    'bind_param'       => '/->bind_param\(/',
    'bind_result'      => '/->bind_result\(/',
    'store_result'     => '/->store_result\(/',
    'insert_id_prop'   => '/\$\w+->insert_id\b/',
    'limit_offset_my'  => '/\bLIMIT\s+\$?\w+\s*,\s*\$?\w+\b/i',
    'ifnull'           => '/\bIFNULL\s*\(/i',
    'date_format'      => '/\bDATE_FORMAT\s*\(/i',
    'group_concat'     => '/\bGROUP_CONCAT\s*\(/i',
    'on_duplicate'     => '/\bON DUPLICATE KEY/i',
    'curdate'          => '/\bCURDATE\s*\(\s*\)/i',
    'bool_eq_int'      => '/\b(' . implode('|', $BOOL_COLS) . ')\s*=\s*[01]\b(?!\d)/i',
    'backticks_sql'    => '/`[a-zA-Z_][a-zA-Z0-9_]*`/',
];

$skipDirs = ['#/migrations(_postgres)?/#', '#/Pantrucks/#', '#/alert/#',
             '#/reports/TCPDF-main/#', '#/node_modules/#', '#/vendor/#',
             '#/datatable/#', '#/php/config/#'];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $p = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $pat) if (preg_match($pat, $p)) continue 2;
    $files[] = $p;
}
sort($files);

$hits = array_fill_keys(array_keys($patterns), []);

// Also flag DataTables endpoints that print JSON without guards.
$datatableHits = [];

foreach ($files as $path) {
    $rel = str_replace($root . '/', '', $path);
    $src = file_get_contents($path);
    if ($src === false) continue;

    foreach ($patterns as $cat => $regex) {
        if (preg_match_all($regex, $src, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $m) {
                $lineNo = substr_count(substr($src, 0, $m[1]), "\n") + 1;
                $hits[$cat][] = [$rel, $lineNo, trim(explode("\n", $src)[$lineNo - 1] ?? '')];
            }
        }
    }

    // DataTables endpoint heuristic: echo json_encode([... 'data' => ...])
    if (preg_match('/echo\s+json_encode\([^)]*[\'"]data[\'"]/', $src)
        && !preg_match('/Content-Type:\s*application\/json/i', $src)) {
        $datatableHits[] = $rel;
    }
}

// Report
foreach ($hits as $cat => $rows) {
    if (!$rows) continue;
    $unique = array_unique(array_map(fn($r) => $r[0], $rows));
    echo "[$cat] " . count($rows) . " hits in " . count($unique) . " files\n";
    foreach (array_slice($rows, 0, 8) as [$rel, $ln, $snippet]) {
        if (strlen($snippet) > 110) $snippet = substr($snippet, 0, 107) . '...';
        echo "  $rel:$ln  $snippet\n";
    }
    if (count($rows) > 8) echo "  ... and " . (count($rows) - 8) . " more\n";
    echo "\n";
}

if ($datatableHits) {
    echo "[datatables_json] " . count($datatableHits) . " endpoints emit JSON without Content-Type header\n";
    foreach (array_slice($datatableHits, 0, 20) as $f) echo "  $f\n";
    if (count($datatableHits) > 20) echo "  ... and " . (count($datatableHits) - 20) . " more\n";
    echo "\n";
}

$total = array_sum(array_map('count', $hits)) + count($datatableHits);
echo "TOTAL: $total findings across " . count($files) . " PHP files scanned.\n";
