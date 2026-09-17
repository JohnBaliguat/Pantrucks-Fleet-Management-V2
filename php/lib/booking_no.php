<?php
// Phase 14.1 — booking_no generator.
//
// Format: BN{6-digit sequence}-{Customer}-{From}-{To}
//   e.g. BN000001-DOLE-PH01-PH02
//
// The sequence guarantees uniqueness; the customer + route are along
// for human readability. The counter is taken from the highest existing
// `BN######-…` row + 1, so we coexist with legacy `PTSIBN-…` and the
// earlier `DOLE-BN-PH01-PH02` rows without conflict.
//
// Slug rules — applied to each text segment:
//   • Trim
//   • Upper-case
//   • Replace any run of non-alphanumerics with a single underscore
//   • Strip leading/trailing underscores
//   • Cap at 40 chars
//
// Why a helper: there are five call sites that mint booking_no.
// Keeping the rule in one place avoids drift.

if (!function_exists('bn_slug')) {
    function bn_slug(string $raw): string {
        $s = strtoupper(trim($raw));
        $s = preg_replace('/[^A-Z0-9]+/', '_', $s);
        $s = trim($s, '_');
        if ($s === '') $s = 'X';
        if (strlen($s) > 40) $s = substr($s, 0, 40);
        return $s;
    }

    /**
     * Generate the next booking_no.
     *
     * @param PDO $conn
     * @param string $customer  e.g. 'DOLE' (customer_code)
     * @param string $from      trip_from location name
     * @param string $to        trip_to   location name
     * @return string           e.g. 'BN000042-DOLE-PH01-PH02'
     */
    function bn_generate(PDO $conn, string $customer, string $from, string $to): string {
        // Find the highest existing BN###### prefix. We can't use
        // ORDER BY booking_no DESC LIMIT 1 because string ordering
        // breaks once the digits widen (BN000099 > BN000100 alpha-
        // betically). Pull the numeric tail in SQL and take MAX().
        $sql = "SELECT MAX(
                    CAST(
                        SUBSTRING(SPLIT_PART(booking_no, '-', 1) FROM 3)
                        AS INTEGER
                    )
                ) AS max_seq
                FROM booking
                WHERE booking_no ~ '^BN[0-9]+-'";
        $row = $conn->query($sql)->fetch();
        $next = (int)($row['max_seq'] ?? 0) + 1;

        $seq = str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        return 'BN' . $seq . '-' . bn_slug($customer) . '-' . bn_slug($from) . '-' . bn_slug($to);
    }
}
