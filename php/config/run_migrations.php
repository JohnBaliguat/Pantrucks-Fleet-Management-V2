<?php
/**
 * Apply migrations_postgres/*.sql to the Supabase database via the PDO
 * connection in php/config/config.php.
 *
 * Splits files into statements while respecting:
 *   - Single-quoted strings ('Doesn''t break')
 *   - Postgres dollar-quoted strings ($$ ... $$  and  $tag$ ... $tag$)
 *   - Line comments (-- to end of line)
 *
 * Idempotent: every statement in our migrations uses IF NOT EXISTS / IF
 * EXISTS, so re-running is safe.
 */

require_once __DIR__ . '/config.php';

$migrationsDir = realpath(__DIR__ . '/../../migrations_postgres');
if (!$migrationsDir || !is_dir($migrationsDir)) {
    fwrite(STDERR, "migrations_postgres/ not found\n"); exit(1);
}

$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql');
sort($files, SORT_STRING);
if (!$files) { fwrite(STDERR, "No .sql files found\n"); exit(1); }

/**
 * Split a Postgres SQL file into individual statements.
 */
function splitStatements(string $sql): array {
    $stmts = [];
    $buf   = '';
    $i     = 0;
    $n     = strlen($sql);
    $dollarTag = null;       // null when not inside a $$..$$ block

    while ($i < $n) {
        $ch  = $sql[$i];
        $two = substr($sql, $i, 2);

        // Inside a dollar-quoted block — only $tag$ ends it.
        if ($dollarTag !== null) {
            if (substr($sql, $i, strlen($dollarTag)) === $dollarTag) {
                $buf .= $dollarTag;
                $i   += strlen($dollarTag);
                $dollarTag = null;
                continue;
            }
            $buf .= $ch; $i++; continue;
        }

        // Dollar-quote opener: $$ or $tag$
        if ($ch === '$' && preg_match('/\$([A-Za-z_][A-Za-z0-9_]*)?\$/A', $sql, $m, 0, $i)) {
            $dollarTag = $m[0];
            $buf      .= $dollarTag;
            $i        += strlen($dollarTag);
            continue;
        }

        // Line comment: skip to newline (keep newline).
        if ($two === '--') {
            while ($i < $n && $sql[$i] !== "\n") { $buf .= $sql[$i]; $i++; }
            continue;
        }

        // Single-quoted string.
        if ($ch === "'") {
            $buf .= $ch; $i++;
            while ($i < $n) {
                $c = $sql[$i];
                $buf .= $c; $i++;
                if ($c === "'") {
                    // Postgres escape: '' inside string = literal apostrophe.
                    if ($i < $n && $sql[$i] === "'") { $buf .= $sql[$i]; $i++; continue; }
                    break;
                }
            }
            continue;
        }

        if ($ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') $stmts[] = $stmt;
            $buf = '';
            $i++; continue;
        }

        $buf .= $ch; $i++;
    }

    $stmt = trim($buf);
    if ($stmt !== '') $stmts[] = $stmt;
    return $stmts;
}

$totalStmts = 0;
$totalOk    = 0;
$totalErr   = 0;
$errors     = [];

foreach ($files as $file) {
    $name = basename($file);
    echo "▶ $name\n";

    $sql   = file_get_contents($file);
    $stmts = splitStatements($sql);
    echo "  " . count($stmts) . " statements\n";

    foreach ($stmts as $idx => $stmt) {
        $totalStmts++;
        try {
            $conn->exec($stmt);
            $totalOk++;
        } catch (PDOException $e) {
            $totalErr++;
            $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 100);
            $errors[] = [
                'file'    => $name,
                'idx'     => $idx + 1,
                'preview' => $preview,
                'msg'     => $e->getMessage(),
            ];
            echo "  ✗ stmt #" . ($idx + 1) . " — " . $e->getMessage() . "\n";
            echo "    > $preview\n";
        }
    }
}

echo "\n=== summary ===\n";
echo "Files:      " . count($files) . "\n";
echo "Statements: $totalStmts\n";
echo "OK:         $totalOk\n";
echo "Errors:     $totalErr\n";

if ($errors) {
    echo "\nFirst 10 errors:\n";
    foreach (array_slice($errors, 0, 10) as $e) {
        echo " - [{$e['file']} #{$e['idx']}] {$e['msg']}\n";
        echo "   > {$e['preview']}\n";
    }
    exit(1);
}

// Final verification — list tables in the DB.
$tbls = $conn->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = 'public' ORDER BY table_name"
)->fetchAll(PDO::FETCH_COLUMN);
echo "\nPublic tables now in Supabase: " . count($tbls) . "\n";
foreach ($tbls as $t) echo "  - $t\n";
