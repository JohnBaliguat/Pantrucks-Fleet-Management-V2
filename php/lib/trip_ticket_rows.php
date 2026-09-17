<?php
// Trip-ticket equipment rows — shared by the print_dispatch* pages
// (dispatcher/ and admin/, loaded/empty + CTH variants).
//
// Renders every trip leg of a dispatch in run order, then pads the table to a
// fixed height so the printed ticket keeps its familiar look. The optional
// origin pre-leg row is still rendered by the caller (above this include) for
// legacy single-leg dispatches; multi-leg dispatches carry any pre-leg as a
// real trip row instead.
//
// Expects in scope:
//   $dispatch — the dispatch row (for d_trailer / d_genset fallback)
//   $trips    — trips rows keyed by trip_type (e.g. $trips['Trip 1'])
//   $ticketCopy (optional) — 'driver' (default) or 'payroll'. On the Payroll
//     copy the ATD / ATA columns are repurposed to SKU / Piece Rate: each leg's
//     SKU key (haulingSegment.haulingType.containerStat) is looked up against
//     trip_rates and the matched rate is printed. Requires $conn in scope.
//
// Ordering: trip_seq (the dispatcher's dragged run order) then trip_id.
// Legacy rows have trip_seq = 0 and fall back to insertion (trip_id) order.

// Copy mode — Payroll copy swaps the ATD/ATA cells for SKU / Piece Rate.
$ticketCopy    = $ticketCopy ?? 'driver';
$isPayrollCopy = ($ticketCopy === 'payroll');
if ($isPayrollCopy) {
    require_once __DIR__ . '/../helpers/trip_rate_lookup.php';
}
// Running total of matched piece rates — rendered as a TOTAL row on the
// Payroll copy after the leg rows.
$payrollRateTotal = 0.0;

$legRows = array_values($trips);
usort($legRows, function ($a, $b) {
    $sa = (int)($a['trip_seq'] ?? 0);
    $sb = (int)($b['trip_seq'] ?? 0);
    if ($sa !== $sb) {
        if ($sa === 0) return 1;   // unsequenced legacy rows sink to the end
        if ($sb === 0) return -1;
        return $sa <=> $sb;
    }
    return (int)($a['trip_id'] ?? 0) <=> (int)($b['trip_id'] ?? 0);
});

$minRows  = 10;                                  // fixed ticket height
$padCount = max(0, $minRows - count($legRows));

// Per-leg trailer/genset. Fall back to the dispatch-level rig for the booking
// leg (Trip 1) of legacy dispatches that never stored a per-leg value.
$legEquip = function ($t) use ($dispatch) {
    $isTrip1 = ($t['trip_type'] ?? '') === 'Trip 1';
    $tr = trim((string)($t['trip_trailer'] ?? ''));
    if ($tr === '' && $isTrip1) $tr = (string)($dispatch['d_trailer'] ?? '');
    $gs = trim((string)($t['trip_genset'] ?? ''));
    if ($gs === '' && $isTrip1) $gs = (string)($dispatch['d_genset'] ?? '');
    return [$tr, $gs];
};

// First run-order leg's equipment — this is what physically exits the gate
// first, so the Security Pass / gate pass reads it (not always the booking leg).
// Exposed to the including print page after this partial runs.
if (!empty($legRows)) {
    list($firstLegTrailer, $firstLegGenset) = $legEquip($legRows[0]);
} else {
    $firstLegTrailer = (string)($dispatch['d_trailer'] ?? '');
    $firstLegGenset  = (string)($dispatch['d_genset'] ?? '');
}
?>
<?php foreach ($legRows as $t): ?>
<?php
    list($legTrailer, $legGenset) = $legEquip($t);

    // Payroll copy — derive this leg's SKU and its piece rate.
    //
    // Rate precedence:
    //   1. trips.piece_rate — the authoritative stamped rate (payroll rating,
    //      dispatcher correction, and hustling containers all write it).
    //   2. Hustling legs — the flat per-container DICT HUSTLING rate. Hustling
    //      trips carry a blank hauling type, so the 3-part SKU can't price them.
    //   3. 3-part SKU lookup (Segment.HaulingType.ContainerStat) — estimate for
    //      regular trips not yet rated.
    $skuCell  = '';
    $rateCell = '';
    if ($isPayrollCopy) {
        $seg     = trim((string)($t['trip_haulingsegment'] ?? ''));
        $type    = trim((string)($t['trip_haulingtype'] ?? ''));
        $stat    = trim((string)($t['trip_containerstat'] ?? ''));
        $purpose = trim((string)($t['trip_purpose'] ?? ''));
        $stored  = (float)($t['piece_rate'] ?? 0.0);

        // Hustling = per-container flat rate (trip_key 'DICT HUSTLING').
        $isHustling = (strcasecmp($purpose, 'Hustling') === 0)
                   || (strcasecmp($seg, 'DICT HUSTLING') === 0);

        if ($isHustling) {
            $skuCell = 'DICT Hustling (per container)';
            $rate    = $stored > 0 ? $stored : fleet_lookup_flat_rate($conn, 'DICT HUSTLING');
        } else {
            $lookup = fleet_lookup_rate_by_trip_key($conn, $seg, $type, $stat);
            // Prefer the matched Trip Rates code; otherwise show the trip's own
            // stored SKU (trips.trip_sku), falling back to the derived key on
            // legacy rows that predate the column.
            if ($lookup['matched'] && $lookup['matched_key'] !== '') {
                $skuCell = $lookup['matched_key'];
            } else {
                $storedSku = trim((string)($t['trip_sku'] ?? ''));
                if ($storedSku !== '') {
                    $skuCell = $storedSku;
                } elseif ($seg !== '' || $type !== '' || $stat !== '') {
                    $skuCell = $lookup['key'];
                }
            }
            $rate = $stored > 0 ? $stored : (float)($lookup['total_rates'] ?? 0.0);
        }

        $payrollRateTotal += $rate;
        $rateCell = number_format($rate, 2);   // unpriced legs resolve to 0.00
    }
?>
        <tr>
            <td><?= htmlspecialchars((string)($t['trip_from'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string)($t['trip_to'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($t['trip_haulingsegment'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string)($t['trip_haulingtype'] ?? '-')) ?></td>
            <td><?= htmlspecialchars($legTrailer) ?></td>
            <td><?= htmlspecialchars($legGenset) ?></td>
            <td><?= htmlspecialchars((string)($t['trip_container'] ?? '-')) ?></td>
            <td class="atd"><?= htmlspecialchars($skuCell) ?></td>
            <td class="ata"><?= htmlspecialchars($rateCell) ?></td>
            <td><?= htmlspecialchars((string)($t['trip_containerstat'] ?? '-')) ?></td>
        </tr>
<?php endforeach; ?>
<?php for ($i = 0; $i < $padCount; $i++): ?>
        <tr>
            <td> - </td>
            <td></td><td></td><td></td><td></td><td></td><td></td>
            <td class="atd"></td><td class="ata"></td><td></td>
        </tr>
<?php endfor; ?>
<?php if ($isPayrollCopy): ?>
        <tr>
            <td colspan="8" style="text-align:right; font-weight:bold;">TOTAL PIECE RATE</td>
            <td class="ata" style="font-weight:bold;"><?= number_format($payrollRateTotal, 2) ?></td>
            <td></td>
        </tr>
<?php endif; ?>
