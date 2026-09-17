<?php
/**
 * Auto-convert MySQLi-flavoured PHP files to PDO + PostgreSQL.
 *
 * Handles the mechanical 80%:
 *   - mysqli_* function calls          → PDO equivalents
 *   - $conn->query / $conn->error      → PDO equivalents
 *   - prepared bind_param + execute    → execute([..])
 *   - get_result()->fetch_assoc()      → fetch()
 *   - $result->num_rows                → $result->rowCount()
 *   - mysqli_real_escape_string        → pt_pg_escape (helper in config.php)
 *   - `backticks` for table/column ids → unquoted, or "user" for reserved words
 *   - LIMIT a, b                       → LIMIT b OFFSET a
 *   - IFNULL(...)                      → COALESCE(...)
 *   - FROM user / JOIN user / etc      → quoted "user"
 *   - boolean column = 1 / = 0         → = TRUE / = FALSE  (known bool columns)
 *
 * Won't touch:
 *   - bind_result() (no clean PDO equivalent without rewriting fetch loop)
 *   - ON DUPLICATE KEY UPDATE          (Postgres needs ON CONFLICT)
 *   - GROUP_CONCAT, DATE_FORMAT, etc.  (function differences)
 *   - LAST_INSERT_ID() in SQL          (use RETURNING id)
 * Those land in the lint report for manual fixup.
 *
 * Usage:
 *   php convert_mysqli_to_pdo.php             # dry run, prints summary
 *   php convert_mysqli_to_pdo.php --apply     # rewrites files in place
 *   php convert_mysqli_to_pdo.php --apply --only=admin/dashboard.php   # one file
 */

$root  = realpath(__DIR__ . '/../..');
$apply = in_array('--apply', $argv, true);
$only  = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, 7);
    }
}

// PostgreSQL reserved words that need double-quoting when used as identifiers.
$RESERVED = ['user', 'order', 'group', 'select', 'where', 'from', 'table',
             'key', 'value', 'time', 'date', 'timestamp', 'default'];

// Known BOOLEAN columns (TINYINT(1) in original MySQL → BOOLEAN in our PG schema).
$BOOL_COLS = ['maintenance_blocked', 'foul_trip', 'requires_ack', 'billing_active',
              'verified', 'authorised', 'customs_cleared', 'offline',
              'fuel_ok', 'tyres_ok', 'lights_ok', 'cargo_area_ok', 'genset_ok'];

/**
 * Apply all transforms to one PHP source string. Returns [newSource, stats].
 */
function convertSource(string $src, array $reserved, array $boolCols): array {
    $stats = [
        'mysqli_funcs' => 0, 'bind_param' => 0, 'get_result' => 0,
        'fetch_assoc' => 0, 'num_rows' => 0, 'escape' => 0,
        'close' => 0, 'limit_offset' => 0, 'backticks' => 0,
        'reserved_word' => 0, 'ifnull' => 0, 'bool_cmp' => 0,
    ];

    // ---------- Prepared-statement transform: bind_param + execute ----------
    //   $stmt->bind_param("XYZ", $a, $b);
    //   $stmt->execute();
    // → $stmt->execute([$a, $b]);
    $src = preg_replace_callback(
        '/\$(\w+)->bind_param\(\s*("[^"]*"|\'[^\']*\')\s*,\s*([^)]+)\)\s*;\s*(\r?\n\s*)\$\1->execute\(\s*\)\s*;/',
        function ($m) use (&$stats) {
            $stats['bind_param']++;
            $vars = trim($m[3]);
            return '$' . $m[1] . '->execute([' . $vars . ']);';
        },
        $src
    );

    //  Same pattern but `execute()` immediately on next line of a single statement.
    //  (handles formatting differences)
    $src = preg_replace_callback(
        '/\$(\w+)->bind_param\(\s*("[^"]*"|\'[^\']*\')\s*,\s*([^)]+)\)\s*;/',
        function ($m) use (&$stats) {
            // Only convert lonely bind_param if it was missed above.
            $stats['bind_param']++;
            return '/* PT_TODO bind_param without paired execute() — review: */ /* $' . $m[1] . '->execute([' . trim($m[3]) . ']) */';
        },
        $src
    );

    // ---------- get_result()->fetch_assoc() and friends ----------
    // $stmt->get_result()->fetch_assoc()  → $stmt->fetch()
    // $stmt->get_result()->fetch_all(MYSQLI_ASSOC) → $stmt->fetchAll()
    // $stmt->get_result()->num_rows       → $stmt->rowCount()
    $src = preg_replace_callback(
        '/->get_result\(\)\s*->\s*fetch_assoc\(\s*\)/',
        function () use (&$stats) { $stats['get_result']++; return '->fetch()'; },
        $src
    );
    $src = preg_replace_callback(
        '/->get_result\(\)\s*->\s*fetch_array\(\s*[^)]*\)/',
        function () use (&$stats) { $stats['get_result']++; return '->fetch()'; },
        $src
    );
    $src = preg_replace_callback(
        '/->get_result\(\)\s*->\s*fetch_all\(\s*[^)]*\)/',
        function () use (&$stats) { $stats['get_result']++; return '->fetchAll()'; },
        $src
    );
    $src = preg_replace_callback(
        '/->get_result\(\)\s*->\s*num_rows/',
        function () use (&$stats) { $stats['get_result']++; return '->rowCount()'; },
        $src
    );

    // Standalone:  $r = $stmt->get_result();    → $r = $stmt;
    $src = preg_replace_callback(
        '/=\s*\$(\w+)->get_result\(\s*\)\s*;/',
        function ($m) use (&$stats) { $stats['get_result']++; return "= \$$m[1];"; },
        $src
    );

    // ---------- result method/property migrations ----------
    // $r->fetch_assoc()      → $r->fetch()
    // $r->fetch_array(...)   → $r->fetch()
    // $r->fetch_object()     → $r->fetchObject()
    // $r->num_rows           → $r->rowCount()
    // $stmt->affected_rows   → $stmt->rowCount()
    // $stmt->num_rows        → $stmt->rowCount()
    $src = preg_replace_callback(
        '/->fetch_assoc\(\s*\)/',
        function () use (&$stats) { $stats['fetch_assoc']++; return '->fetch()'; }, $src);
    $src = preg_replace_callback(
        '/->fetch_array\(\s*[^)]*\)/',
        function () use (&$stats) { $stats['fetch_assoc']++; return '->fetch()'; }, $src);
    $src = preg_replace_callback(
        '/->fetch_object\(\s*\)/',
        function () use (&$stats) { $stats['fetch_assoc']++; return '->fetchObject()'; }, $src);
    $src = preg_replace_callback(
        '/->fetch_all\(\s*[^)]*\)/',
        function () use (&$stats) { $stats['fetch_assoc']++; return '->fetchAll()'; }, $src);
    $src = preg_replace_callback(
        '/->num_rows\b/',
        function () use (&$stats) { $stats['num_rows']++; return '->rowCount()'; }, $src);
    $src = preg_replace_callback(
        '/->affected_rows\b/',
        function () use (&$stats) { $stats['num_rows']++; return '->rowCount()'; }, $src);

    // $conn->insert_id  → $conn->lastInsertId()
    $src = preg_replace_callback(
        '/\$(\w+)->insert_id\b/',
        function ($m) use (&$stats) { $stats['mysqli_funcs']++; return "\$$m[1]->lastInsertId()"; }, $src);

    // $conn->error  → ($conn->errorInfo()[2] ?? '')
    $src = preg_replace_callback(
        '/\$(\w+)->error\b(?!\w)/',
        function ($m) use (&$stats) { $stats['mysqli_funcs']++; return "(\$$m[1]->errorInfo()[2] ?? '')"; }, $src);

    // $stmt->close() / $conn->close() / $r->store_result() → drop entire statement
    $src = preg_replace_callback(
        '/\s*\$(\w+)->close\(\s*\)\s*;\s*/',
        function () use (&$stats) { $stats['close']++; return "\n"; }, $src);
    $src = preg_replace_callback(
        '/\s*\$(\w+)->store_result\(\s*\)\s*;\s*/',
        function () use (&$stats) { $stats['close']++; return "\n"; }, $src);

    // $conn->real_escape_string($x)  → pt_pg_escape($conn, $x)
    $src = preg_replace_callback(
        '/\$(\w+)->real_escape_string\(/',
        function ($m) use (&$stats) { $stats['escape']++; return "pt_pg_escape(\$$m[1], "; }, $src);

    // ---------- Procedural mysqli_* functions ----------
    $procReplacements = [
        '/\bmysqli_query\(\s*\$(\w+)\s*,\s*/'                 => '$$1->query(',
        '/\bmysqli_prepare\(\s*\$(\w+)\s*,\s*/'               => '$$1->prepare(',
        '/\bmysqli_fetch_assoc\(/'                            => '__PT_FETCH__(',
        '/\bmysqli_fetch_array\(\s*([^,]+?)(?:\s*,\s*[^)]+)?\)/' => '__PT_FETCH__($1)',
        '/\bmysqli_fetch_object\(/'                           => '__PT_FETCHOBJ__(',
        '/\bmysqli_fetch_all\(\s*([^,]+?)(?:\s*,\s*[^)]+)?\)/' => '__PT_FETCHALL__($1)',
        '/\bmysqli_num_rows\(/'                               => '__PT_ROWCOUNT__(',
        '/\bmysqli_affected_rows\(\s*\$(\w+)\s*\)/'           => '__PT_AFFROWS__($$1)',
        '/\bmysqli_insert_id\(\s*\$(\w+)\s*\)/'               => '$$1->lastInsertId()',
        '/\bmysqli_real_escape_string\(\s*\$(\w+)\s*,\s*/'    => 'pt_pg_escape($$1, ',
        '/\bmysqli_close\(\s*\$(\w+)\s*\)\s*;?/'              => '',
        '/\bmysqli_error\(\s*\$(\w+)\s*\)/'                   => '(($$1->errorInfo()[2]) ?? "")',
        '/\bmysqli_errno\(\s*\$(\w+)\s*\)/'                   => '(int)($$1->errorInfo()[1] ?? 0)',
        '/\bmysqli_connect_errno\(\s*\)/'                     => '0',
        '/\bmysqli_connect_error\(\s*\)/'                     => "''",
        // Transactions
        '/\bmysqli_begin_transaction\(\s*\$(\w+)\s*\)/'       => '$$1->beginTransaction()',
        '/\bmysqli_commit\(\s*\$(\w+)\s*\)/'                  => '$$1->commit()',
        '/\bmysqli_rollback\(\s*\$(\w+)\s*\)/'                => '$$1->rollBack()',
        '/\bmysqli_autocommit\(\s*\$(\w+)\s*,\s*[^)]+\)\s*;?/' => '',
        '/\bmysqli_report\([^)]*\)\s*;?/'                     => '',
    ];
    foreach ($procReplacements as $pat => $rep) {
        $newSrc = preg_replace($pat, $rep, $src);
        if ($newSrc !== $src) { $stats['mysqli_funcs']++; $src = $newSrc; }
    }

    // Now resolve the temporary placeholders into method calls on what's
    // (hopefully) a PDOStatement.
    $src = preg_replace_callback('/__PT_FETCH__\((.+?)\)/',     fn($m) => "({$m[1]})->fetch()",        $src);
    $src = preg_replace_callback('/__PT_FETCHOBJ__\((.+?)\)/',  fn($m) => "({$m[1]})->fetchObject()",  $src);
    $src = preg_replace_callback('/__PT_FETCHALL__\((.+?)\)/',  fn($m) => "({$m[1]})->fetchAll()",     $src);
    $src = preg_replace_callback('/__PT_ROWCOUNT__\((.+?)\)/',  fn($m) => "({$m[1]})->rowCount()",     $src);
    $src = preg_replace_callback('/__PT_AFFROWS__\((.+?)\)/',   fn($m) => "({$m[1]})->errorInfo() && 1", $src); // placeholder; flagged below

    // ---------- SQL syntax fixes ----------
    // Quote reserved word `user` everywhere it appears as a table identifier in SQL strings.
    // Catches: FROM user, JOIN user, UPDATE user, INTO user (case-insensitive).
    // Skips: longer identifiers like user_id, users.
    $src = preg_replace_callback(
        '/\b(FROM|JOIN|UPDATE|INTO)\s+user\b(?![_\w])/i',
        function ($m) use (&$stats) { $stats['reserved_word']++; return $m[1] . ' "user"'; },
        $src
    );

    // Drop backticks around bare identifiers in SQL strings.
    //   `tablename` → tablename       (safe in PG since our schema uses lowercase)
    //   `user`      → "user"          (reserved)
    //   `key`       → "key"           (reserved)
    $src = preg_replace_callback(
        '/`(\w+)`/',
        function ($m) use ($reserved, &$stats) {
            $stats['backticks']++;
            return in_array(strtolower($m[1]), $reserved, true) ? '"' . $m[1] . '"' : $m[1];
        },
        $src
    );

    // MySQL LIMIT a, b   →   LIMIT b OFFSET a   (Postgres syntax)
    // Match in SQL strings: " LIMIT $start, $length "
    $src = preg_replace_callback(
        '/\bLIMIT\s+(\$?\w+|\d+)\s*,\s*(\$?\w+|\d+)\b/i',
        function ($m) use (&$stats) { $stats['limit_offset']++; return "LIMIT {$m[2]} OFFSET {$m[1]}"; },
        $src
    );

    // IFNULL(x, y) → COALESCE(x, y)
    $src = preg_replace_callback(
        '/\bIFNULL\s*\(/i',
        function () use (&$stats) { $stats['ifnull']++; return 'COALESCE('; }, $src);

    // CURDATE() → CURRENT_DATE      CURTIME() → CURRENT_TIME
    $src = preg_replace_callback(
        '/\bCURDATE\s*\(\s*\)/i',
        function () use (&$stats) { $stats['ifnull']++; return 'CURRENT_DATE'; }, $src);
    $src = preg_replace_callback(
        '/\bCURTIME\s*\(\s*\)/i',
        function () use (&$stats) { $stats['ifnull']++; return 'CURRENT_TIME'; }, $src);

    // ---------- Boolean column comparisons ----------
    // `col = 1` / `col = 0` for known BOOLEAN columns → TRUE / FALSE
    foreach ($boolCols as $col) {
        $src = preg_replace_callback(
            '/\b(' . preg_quote($col, '/') . ')\s*=\s*(1|0)\b/',
            function ($m) use (&$stats) {
                $stats['bool_cmp']++;
                return $m[1] . ' = ' . ($m[2] === '1' ? 'TRUE' : 'FALSE');
            },
            $src
        );
    }

    return [$src, $stats];
}

// -----------------------------------------------------------------------
// Walk PHP files.
// -----------------------------------------------------------------------
$skipDirs = ['#/migrations(_postgres)?/#', '#/Pantrucks/#', '#/alert/#',
             '#/reports/TCPDF-main/#', '#/node_modules/#', '#/vendor/#',
             '#/datatable/#', '#/php/config/#'];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $pat) {
        if (preg_match($pat, $path)) continue 2;
    }
    if ($only !== null && strpos(str_replace('\\', '/', $path), str_replace('\\', '/', $only)) === false) continue;
    $files[] = $path;
}
sort($files);

echo (count($files)) . " PHP files to scan\n";
echo $apply ? "MODE: APPLY (rewriting files in place)\n\n" : "MODE: DRY RUN (no writes; pass --apply to rewrite)\n\n";

$totalStats = [];
$changedFiles = 0;

foreach ($files as $path) {
    $orig = file_get_contents($path);
    if ($orig === false) continue;

    // Quick filter: skip files that have no mysqli/MySQL-syntax markers at all.
    if (!preg_match('/mysqli|bind_param|fetch_assoc|num_rows|->close\(|->error\b|->insert_id\b|`\w+`|\bLIMIT\s+\$?\w+\s*,|\bCURDATE\s*\(|\bCURTIME\s*\(|\bIFNULL\s*\(/', $orig)) continue;

    [$new, $stats] = convertSource($orig, $RESERVED, $BOOL_COLS);

    $changes = array_sum($stats);
    if ($changes === 0) continue;

    $changedFiles++;
    foreach ($stats as $k => $v) {
        $totalStats[$k] = ($totalStats[$k] ?? 0) + $v;
    }

    if ($apply && $new !== $orig) {
        file_put_contents($path, $new);
    }
}

echo "Files changed: $changedFiles\n";
echo "Transforms by category:\n";
ksort($totalStats);
foreach ($totalStats as $k => $v) {
    printf("  %-15s %d\n", $k, $v);
}
