<?php
/**
 * Import/Export customer catalog helper — REPORT ONLY (writes nothing).
 *
 * Prints the trade direction for each customer the user listed and whether a
 * matching `customer` row already exists, so staff can set Trade Type on the
 * Segment/Customer management page (customer codes are entered by hand, so we
 * never invent them here). CTH is already set to Import by migration 020.
 *
 * Usage:  php php/config/seed_import_customers.php
 */
require_once __DIR__ . '/config.php';

// name (as listed) => 'Import' | 'Export' | 'Both'
$catalog = [
    'TPD (IMPORT)'                          => 'Import',
    'TPD (EXPORT)'                          => 'Export',
    'FRANKLIN BAKER (EXPORT)'               => 'Export',
    'TETRA PACK (FRANKLIN BAKER IMPORT)'    => 'Import',
    'EYE CARGO - NEW HOPE (IMPORT)'         => 'Import',
    'EYE CARGO - PRIME PACIFIC (IMPORT)'    => 'Import',
    'BIG4TUNA/GMAC (EXPORT)'                => 'Export',
    'ECOSSENTIAL (IMPORT)'                  => 'Import',
    'AGRI EXIM (EXPORT)'                    => 'Export',
    'BIO PULP (IMPORT)'                     => 'Import',
    'SOUTHERN HARVEST (IMPORT)'             => 'Import',
    'SOUTHERN HARVEST (EXPORT)'             => 'Export',
    'SOLARIS (IMPORT & EXPORT)'             => 'Both',
    'PHIL JDU (IMPORT & EXPORT)'            => 'Both',
    'HEADSPORT (IMPORT)'                    => 'Import',
    'HEADSPORT (EXPORT)'                    => 'Export',
    'NOVOCOCONUT (IMPORT)'                  => 'Import',
    'PHIL CEMENT (IMPORT)'                  => 'Import',
];

// Load existing customers once.
$existing = [];
foreach ($conn->query("SELECT customer_code, customer_name, trade_type FROM customer") as $r) {
    $existing[] = $r;
}

// Strip the "(...)" trade suffix to get the base name for matching.
$base = function (string $s): string {
    return strtoupper(trim(preg_replace('/\s*\(.*\)\s*$/', '', $s)));
};

printf("%-38s | %-8s | %s\n", 'Listed customer', 'Trade', 'Existing customer row(s)');
echo str_repeat('-', 90) . "\n";
foreach ($catalog as $name => $dir) {
    $b = $base($name);
    $matches = [];
    foreach ($existing as $c) {
        if (strpos(strtoupper($c['customer_name']), $b) !== false
            || strpos($b, strtoupper($c['customer_name'])) !== false) {
            $matches[] = $c['customer_code'] . ' [' . $c['customer_name'] . ', ' . ($c['trade_type'] ?? '') . ']';
        }
    }
    $note = $matches ? implode('; ', $matches) : 'NOT FOUND — add via Customer management';
    if ($dir === 'Both') $note .= '  (add TWO entries: one Import, one Export)';
    printf("%-38s | %-8s | %s\n", $name, $dir, $note);
}

echo "\nNext step: on Segment → Customer management, set each customer's Trade Type\n";
echo "(Import = starts Loaded + Empty return; Export = starts Empty + Mark Loaded).\n";
echo "Split 'IMPORT & EXPORT' customers into two entries. CTH is already Import.\n";
