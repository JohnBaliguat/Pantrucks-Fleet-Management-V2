<?php
/**
 * Convert phpMyAdmin MySQL dump → PostgreSQL CREATE TABLE / CREATE INDEX.
 * Structure only — INSERT INTO blocks are skipped.
 *
 * Reads:  migrations/000_base_schema.sql
 * Writes: migrations_postgres/000_base_schema.sql
 */

$root    = realpath(__DIR__ . '/../..');
$inFile  = $root . DIRECTORY_SEPARATOR . 'migrations'           . DIRECTORY_SEPARATOR . '000_base_schema.sql';
$outFile = $root . DIRECTORY_SEPARATOR . 'migrations_postgres'  . DIRECTORY_SEPARATOR . '000_base_schema.sql';

if (!is_file($inFile)) {
    fwrite(STDERR, "Input not found: $inFile\n"); exit(1);
}

$fh = fopen($inFile, 'r');
if (!$fh) { fwrite(STDERR, "Cannot open $inFile\n"); exit(1); }

// Reserved PostgreSQL identifiers we need to double-quote.
$RESERVED = [
    'user', 'order', 'group', 'select', 'where', 'from', 'table',
    'key', 'value', 'time', 'date', 'timestamp', 'current_user',
    'session_user', 'authorization', 'check', 'default', 'primary',
    'foreign', 'references', 'constraint', 'collate', 'using',
    'inner', 'outer', 'join', 'on', 'and', 'or', 'not', 'null',
    'true', 'false', 'asc', 'desc', 'limit', 'offset', 'in', 'is',
    'like', 'between', 'as', 'with', 'cast', 'case', 'when', 'then',
    'else', 'end',
];

function q(string $ident, array $reserved): string {
    $ident = strtolower($ident);
    return in_array(strtolower($ident), $reserved, true) ? "\"$ident\"" : $ident;
}

/**
 * Convert one MySQL type spec to PostgreSQL.
 * Returns [pgType, isBool] — isBool is true when we mapped tinyint(1) to BOOLEAN
 * (the caller needs to translate DEFAULT 0/1 → DEFAULT FALSE/TRUE).
 */
function mapType(string $mysqlType): array {
    $t = strtolower(trim($mysqlType));

    if (preg_match('/^tinyint\(\s*1\s*\)/', $t))          return ['BOOLEAN', true];
    if (preg_match('/^tinyint(\([^)]*\))?/', $t))         return ['SMALLINT', false];
    if (preg_match('/^smallint(\([^)]*\))?/', $t))        return ['SMALLINT', false];
    if (preg_match('/^mediumint(\([^)]*\))?/', $t))       return ['INTEGER', false];
    if (preg_match('/^int(\([^)]*\))?/', $t))             return ['INTEGER', false];
    if (preg_match('/^bigint(\([^)]*\))?/', $t))          return ['BIGINT', false];

    if (preg_match('/^decimal\(([0-9]+),\s*([0-9]+)\)/', $t, $m))  return ["NUMERIC({$m[1]},{$m[2]})", false];
    if (preg_match('/^decimal(\([^)]*\))?/', $t))                  return ['NUMERIC', false];

    if (preg_match('/^double\(([0-9]+),\s*([0-9]+)\)/', $t, $m))   return ["NUMERIC({$m[1]},{$m[2]})", false];
    if (preg_match('/^double(\([^)]*\))?/', $t))                   return ['DOUBLE PRECISION', false];
    if (preg_match('/^float(\([^)]*\))?/', $t))                    return ['REAL', false];

    if ($t === 'date')                                              return ['DATE', false];
    if (preg_match('/^datetime(\([^)]*\))?/', $t))                  return ['TIMESTAMP', false];
    if (preg_match('/^timestamp(\([^)]*\))?/', $t))                 return ['TIMESTAMP', false];
    if (preg_match('/^time(\([^)]*\))?/', $t))                      return ['TIME', false];
    if ($t === 'year')                                              return ['INTEGER', false];

    if (preg_match('/^varchar\(([0-9]+)\)/', $t, $m))               return ["VARCHAR({$m[1]})", false];
    if (preg_match('/^char\(([0-9]+)\)/', $t, $m))                  return ["CHAR({$m[1]})", false];

    if (in_array($t, ['text', 'longtext', 'mediumtext', 'tinytext'], true)) return ['TEXT', false];
    if (in_array($t, ['blob', 'longblob', 'mediumblob', 'tinyblob'], true)) return ['BYTEA', false];

    if (preg_match('/^enum\((.+)\)$/', $t))                          return ['VARCHAR(100)', false];
    if (preg_match('/^set\((.+)\)$/', $t))                           return ['TEXT', false];

    if ($t === 'json')                                               return ['JSONB', false];

    // Fallback — emit raw and let Postgres complain.
    return [strtoupper($t), false];
}

/**
 * Translate the trailing column attributes (DEFAULT / NULL / NOT NULL / etc.).
 * Returns: [attrString, isAutoIncrement (always false here — set by ALTER pass)]
 */
function mapAttrs(string $attrs, bool $isBool): string {
    $a = $attrs;

    // Drop MySQL-specific cruft.
    $a = preg_replace('/\bCHARACTER SET \S+/i', '', $a);
    $a = preg_replace('/\bCOLLATE \S+/i', '', $a);
    $a = preg_replace('/\bUNSIGNED\b/i', '', $a);
    $a = preg_replace('/\bON UPDATE current_timestamp\(\)/i', '', $a);
    $a = preg_replace('/\bAUTO_INCREMENT\b/i', '', $a); // handled at table level
    $a = preg_replace("/\bCOMMENT\s+'(?:[^'\\\\]|\\\\.)*'/i", '', $a); // strip MySQL inline comments

    // current_timestamp() → CURRENT_TIMESTAMP
    $a = preg_replace('/\bcurrent_timestamp\(\)/i', 'CURRENT_TIMESTAMP', $a);

    // Booleans
    if ($isBool) {
        $a = preg_replace("/\bDEFAULT\s+'?0'?/i", 'DEFAULT FALSE', $a);
        $a = preg_replace("/\bDEFAULT\s+'?1'?/i", 'DEFAULT TRUE',  $a);
    }

    // Collapse whitespace.
    $a = trim(preg_replace('/\s+/', ' ', $a));
    return $a;
}

$tables = [];  // name => ['cols' => [['name'=>, 'type'=>, 'attrs'=>, 'isBool'=>]], 'pk' => [], 'indexes' => [], 'unique' => [], 'identity' => null]
$order  = [];  // preserve table creation order

$mode          = 'idle';       // 'idle' | 'create' | 'insert'
$insertParen   = 0;            // brace depth inside an INSERT block
$currentTable  = null;
$alterTable    = null;

while (($line = fgets($fh)) !== false) {
    // Skip the INSERT INTO blocks — they can span many lines, possibly with parentheses.
    if ($mode === 'insert') {
        if (str_contains($line, ';')) { $mode = 'idle'; }
        continue;
    }

    $trim = ltrim($line);

    // Skip comments, MySQL-only directives, transactions, locks.
    if ($trim === '' || str_starts_with($trim, '--')
        || str_starts_with($trim, '/*') || str_starts_with($trim, '*/')
        || preg_match('/^(SET|START TRANSACTION|COMMIT|LOCK TABLES|UNLOCK TABLES|USE)\b/i', $trim)
        || str_starts_with($trim, 'DROP TABLE')
    ) {
        continue;
    }

    // Begin INSERT INTO — skip until the terminating ';'.
    if (preg_match('/^INSERT INTO/i', $trim)) {
        $mode = str_contains($line, ';') ? 'idle' : 'insert';
        continue;
    }

    // Begin CREATE TABLE.
    if (preg_match('/^CREATE TABLE `(\w+)`\s*\(/', $trim, $m)) {
        $currentTable = $m[1];
        $tables[$currentTable] = ['cols' => [], 'pk' => [], 'indexes' => [], 'unique' => [], 'identity' => null];
        $order[] = $currentTable;
        $mode = 'create';
        continue;
    }

    // Inside CREATE TABLE.
    if ($mode === 'create') {
        // End of CREATE TABLE: ") ENGINE=..." or just ");"
        if (preg_match('/^\)/', $trim)) {
            $mode = 'idle';
            $currentTable = null;
            continue;
        }

        // Column definition: `name` type ...
        if (preg_match('/^`(\w+)`\s+(\S+(?:\([^)]*\))?)\s*(.*?),?\s*$/', $trim, $m)) {
            $colName       = $m[1];
            $colTypeRaw    = $m[2];
            $colAttrs      = $m[3];
            [$pgType, $isBool] = mapType($colTypeRaw);
            $pgAttrs           = mapAttrs($colAttrs, $isBool);

            $tables[$currentTable]['cols'][] = [
                'name'   => $colName,
                'type'   => $pgType,
                'attrs'  => $pgAttrs,
                'isBool' => $isBool,
            ];
        }
        continue;
    }

    // ALTER TABLE for PK / KEY / AUTO_INCREMENT — can span multiple lines.
    if (preg_match('/^ALTER TABLE `(\w+)`/', $trim, $m)) {
        $alterTable = $m[1];
        // Collect the full statement until ';'.
        $stmt = $line;
        while (!str_contains($stmt, ';') && ($next = fgets($fh)) !== false) {
            $stmt .= $next;
        }
        if (!isset($tables[$alterTable])) continue;

        // Strip MySQL prefix-index notation like `col`(255) → `col` so the
        // single-paren index-column regex below isn't confused by nested parens.
        $stmt = preg_replace('/(`\w+`)\(\d+\)/', '$1', $stmt);

        // PRIMARY KEY (`col`,`col2`)
        if (preg_match_all('/ADD PRIMARY KEY\s*\(([^)]+)\)/i', $stmt, $pkMatches)) {
            foreach ($pkMatches[1] as $cols) {
                $names = array_map(fn($s) => trim($s, " `"), explode(',', $cols));
                $tables[$alterTable]['pk'] = array_merge($tables[$alterTable]['pk'], $names);
            }
        }
        // ADD UNIQUE KEY `name` (`col`)
        if (preg_match_all('/ADD UNIQUE KEY `(\w+)`\s*\(([^)]+)\)/i', $stmt, $uMatches, PREG_SET_ORDER)) {
            foreach ($uMatches as $u) {
                $cols = array_map(function($s) {
                    $s = trim($s, " `");
                    return preg_replace('/\(\d+\)$/', '', $s); // strip prefix index
                }, explode(',', $u[2]));
                $tables[$alterTable]['unique'][] = ['name' => $u[1], 'cols' => $cols];
            }
        }
        // ADD KEY `name` (`col`)
        if (preg_match_all('/ADD KEY `(\w+)`\s*\(([^)]+)\)/i', $stmt, $kMatches, PREG_SET_ORDER)) {
            foreach ($kMatches as $k) {
                $cols = array_map(function($s) {
                    $s = trim($s, " `");
                    return preg_replace('/\(\d+\)$/', '', $s);
                }, explode(',', $k[2]));
                $tables[$alterTable]['indexes'][] = ['name' => $k[1], 'cols' => $cols];
            }
        }

        // MODIFY `col` int(11) NOT NULL AUTO_INCREMENT
        if (preg_match('/MODIFY `(\w+)`[^,;]*AUTO_INCREMENT/i', $stmt, $aiMatch)) {
            $tables[$alterTable]['identity'] = $aiMatch[1];
        }
        continue;
    }
}
fclose($fh);

// -----------------------------------------------------------------------
// Emit PostgreSQL.
// -----------------------------------------------------------------------
$out  = "-- =====================================================================\n";
$out .= "-- Base schema for Fleet Management (auto-converted from MySQL dump).\n";
$out .= "-- Generated by php/config/convert_schema.php\n";
$out .= "-- Idempotent: uses CREATE TABLE IF NOT EXISTS and CREATE INDEX IF NOT EXISTS.\n";
$out .= "-- =====================================================================\n\n";

foreach ($order as $tbl) {
    $def = $tables[$tbl];
    $tableIdent = q($tbl, $RESERVED);

    $colLines = [];
    foreach ($def['cols'] as $c) {
        $colName = q($c['name'], $RESERVED);
        $type    = $c['type'];

        // If this column is the AUTO_INCREMENT one AND is the sole PK,
        // promote it to GENERATED ALWAYS AS IDENTITY PRIMARY KEY inline.
        $isIdentity = ($def['identity'] === $c['name']);
        $isLonePK   = (count($def['pk']) === 1 && $def['pk'][0] === $c['name']);

        if ($isIdentity && $isLonePK) {
            $colLines[] = "    $colName " . ($type === 'BIGINT' ? 'BIGINT' : 'INTEGER')
                . " GENERATED ALWAYS AS IDENTITY PRIMARY KEY";
            continue;
        }

        $line = "    $colName $type";
        if ($c['attrs'] !== '') {
            $line .= ' ' . $c['attrs'];
        }
        $colLines[] = $line;
    }

    // Composite or non-AI primary key handled here.
    $needTablePK = (!empty($def['pk']) && (
        $def['identity'] === null
        || count($def['pk']) !== 1
        || $def['pk'][0] !== $def['identity']
    ));
    if ($needTablePK) {
        $pkCols = implode(', ', array_map(fn($c) => q($c, $RESERVED), $def['pk']));
        $colLines[] = "    PRIMARY KEY ($pkCols)";
    }

    $out .= "CREATE TABLE IF NOT EXISTS $tableIdent (\n";
    $out .= implode(",\n", $colLines) . "\n";
    $out .= ");\n";

    // Indexes (skip PK; UNIQUE → CREATE UNIQUE INDEX; KEY → CREATE INDEX)
    foreach ($def['unique'] as $u) {
        // Skip the PRIMARY KEY's implicit unique (phpMyAdmin usually emits it as ADD PRIMARY KEY, not ADD UNIQUE).
        $cols    = implode(', ', array_map(fn($c) => q($c, $RESERVED), $u['cols']));
        $idxName = $u['name'];
        $out .= "CREATE UNIQUE INDEX IF NOT EXISTS $idxName ON $tableIdent ($cols);\n";
    }
    foreach ($def['indexes'] as $k) {
        $cols    = implode(', ', array_map(fn($c) => q($c, $RESERVED), $k['cols']));
        $idxName = $k['name'];
        $out .= "CREATE INDEX IF NOT EXISTS $idxName ON $tableIdent ($cols);\n";
    }

    $out .= "\n";
}

@mkdir(dirname($outFile), 0777, true);
file_put_contents($outFile, $out);

echo "Wrote " . count($order) . " tables to:\n  $outFile\n";
echo "Size: " . number_format(strlen($out)) . " bytes\n";
