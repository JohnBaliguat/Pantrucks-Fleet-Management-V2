<?php
/**
 * Normalize mixed-case schema identifiers in PHP source to lowercase for
 * Supabase/PostgreSQL compatibility.
 *
 * Scope:
 * - Rewrites only PHP string literals that look database-facing:
 *   SQL statements, SQL fragments, and simple column-list strings.
 * - Leaves normal PHP variables, function names, JS/HTML text, and most UI
 *   strings untouched.
 *
 * Usage:
 *   php php/config/normalize_sql_identifiers.php
 *   php php/config/normalize_sql_identifiers.php --apply
 */

$root = realpath(__DIR__ . '/../..');
$apply = in_array('--apply', $argv, true);

$dumpPath = $root . '/migrations/000_base_schema.sql';
$dump = file_get_contents($dumpPath);
if ($dump === false) {
    fwrite(STDERR, "Cannot read $dumpPath\n");
    exit(1);
}

preg_match_all('/`([A-Za-z_][A-Za-z0-9_]*)`/', $dump, $m);
$camelMap = [];
foreach (array_unique($m[1]) as $ident) {
    if ($ident !== strtolower($ident)) {
        $camelMap[$ident] = strtolower($ident);
    }
}
ksort($camelMap);

$skipDirs = [
    '#/migrations(_postgres)?/#',
    '#/Pantrucks/#',
    '#/alert/#',
    '#/reports/TCPDF-main/#',
    '#/node_modules/#',
    '#/vendor/#',
    '#/datatable/#',
];

$files = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $pat) {
        if (preg_match($pat, $path)) {
            continue 2;
        }
    }
    if (str_contains($path, '/php/config/normalize_sql_identifiers.php')) {
        continue;
    }
    $files[] = $path;
}
sort($files);

function containsCamelIdent(string $text, array $camelMap): bool
{
    foreach ($camelMap as $camel => $_) {
        if (preg_match('/\b' . preg_quote($camel, '/') . '\b/', $text)) {
            return true;
        }
    }
    return false;
}

function looksLikeDbString(string $text, array $camelMap): bool
{
    if (!containsCamelIdent($text, $camelMap)) {
        return false;
    }

    $trim = trim($text);
    $lower = strtolower($trim);

    if (preg_match('/\b(select|insert|update|delete|from|join|where|values|set|into|order by|group by|left join|right join|inner join|on conflict|create table|alter table|references|limit|offset)\b/i', $trim)) {
        return true;
    }

    if (preg_match('/^\s*[A-Za-z_][A-Za-z0-9_]*(\s*,\s*[A-Za-z_][A-Za-z0-9_]*)+\s*$/', $trim)) {
        return true;
    }

    if (isset($camelMap[$trim])) {
        return true;
    }

    if (str_starts_with($lower, 'where ') || str_starts_with($lower, 'and ') || str_starts_with($lower, 'or ')) {
        return true;
    }

    return false;
}

function normalizeStringLiteral(string $tokenText, array $camelMap, int &$replacements): string
{
    $quote = $tokenText[0] ?? '';
    if (($quote !== "'" && $quote !== '"') || substr($tokenText, -1) !== $quote) {
        return $tokenText;
    }

    $body = substr($tokenText, 1, -1);
    if (!looksLikeDbString($body, $camelMap)) {
        return $tokenText;
    }

    $newBody = $body;
    foreach ($camelMap as $camel => $lower) {
        $count = 0;
        $newBody = preg_replace('/\b' . preg_quote($camel, '/') . '\b/', $lower, $newBody, -1, $count);
        $replacements += $count;
    }

    if ($newBody === $body) {
        return $tokenText;
    }

    return $quote . $newBody . $quote;
}

$changedFiles = 0;
$totalReplacements = 0;
$changedList = [];

foreach ($files as $path) {
    $src = file_get_contents($path);
    if ($src === false) {
        continue;
    }

    $tokens = token_get_all($src);
    $new = '';
    $fileReplacements = 0;

    foreach ($tokens as $token) {
        if (is_array($token)) {
            [$id, $text] = $token;
            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $text = normalizeStringLiteral($text, $camelMap, $fileReplacements);
            }
            $new .= $text;
        } else {
            $new .= $token;
        }
    }

    if ($new === $src) {
        continue;
    }

    $changedFiles++;
    $totalReplacements += $fileReplacements;
    $changedList[] = [str_replace($root . '/', '', $path), $fileReplacements];

    if ($apply) {
        file_put_contents($path, $new);
    }
}

echo "Camel-case identifiers available: " . count($camelMap) . "\n";
echo "Files changed: $changedFiles\n";
echo "Total replacements: $totalReplacements\n\n";

foreach ($changedList as [$file, $count]) {
    echo $file . " ($count)\n";
}

if (!$apply) {
    echo "\nDRY RUN. Re-run with --apply to write.\n";
}
