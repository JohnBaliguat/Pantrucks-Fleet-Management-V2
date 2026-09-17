<?php
/**
 * Payroll cutoffs at Pantrucks run on two cycles per month:
 *   Period A:  the 6th  →  the 20th  (same calendar month)
 *   Period B:  the 21st →  the 5th of the NEXT calendar month
 *
 * Given any reference date, return [start, end] (inclusive) for the
 * payroll period that contains it, plus a human-readable label.
 *
 * All dates are returned as 'YYYY-MM-DD' strings.
 */

if (!function_exists('payroll_period_for')) {
    /**
     * @param DateTimeInterface|string|null $ref  Reference date (defaults to "now")
     * @return array{start:string,end:string,label:string,period:string}
     */
    function payroll_period_for($ref = null): array
    {
        $d = ($ref instanceof DateTimeInterface)
            ? DateTime::createFromInterface($ref)
            : new DateTime($ref ?? 'now');
        $d->setTime(0, 0, 0);

        $day  = (int) $d->format('j');
        $year = (int) $d->format('Y');
        $mon  = (int) $d->format('n');

        if ($day >= 6 && $day <= 20) {
            // Period A — sits entirely inside this month.
            $start = sprintf('%04d-%02d-06', $year, $mon);
            $end   = sprintf('%04d-%02d-20', $year, $mon);
            $label = (new DateTime($start))->format('M j') . ' – ' . (new DateTime($end))->format('M j');
            $period = 'A';
        } elseif ($day >= 21) {
            // Period B — starts this month, ends day 5 of next month.
            $start = sprintf('%04d-%02d-21', $year, $mon);
            $endDt = (clone $d)->modify('first day of next month')->setDate($year, $mon, 1)->modify('+1 month');
            $endDt->setDate((int)$endDt->format('Y'), (int)$endDt->format('n'), 5);
            $end   = $endDt->format('Y-m-d');
            $label = (new DateTime($start))->format('M j') . ' – ' . $endDt->format('M j');
            $period = 'B';
        } else {
            // day 1-5: tail end of previous month's Period B.
            $prevDt = (clone $d)->modify('first day of last month');
            $py = (int) $prevDt->format('Y');
            $pm = (int) $prevDt->format('n');
            $start = sprintf('%04d-%02d-21', $py, $pm);
            $end   = sprintf('%04d-%02d-05', $year, $mon);
            $label = (new DateTime($start))->format('M j') . ' – ' . (new DateTime($end))->format('M j');
            $period = 'B';
        }

        return [
            'start'  => $start,
            'end'    => $end,
            'label'  => $label,
            'period' => $period,
        ];
    }
}

if (!function_exists('payroll_previous_period')) {
    /**
     * Returns the period immediately preceding the one containing $ref.
     */
    function payroll_previous_period($ref = null): array
    {
        $cur = payroll_period_for($ref);
        $beforeStart = (new DateTime($cur['start']))->modify('-1 day');
        return payroll_period_for($beforeStart);
    }
}

if (!function_exists('payroll_periods_back')) {
    /**
     * Walk backwards from the current cutoff and return $count periods.
     * Index 0 is the most recent (current) cutoff.
     */
    function payroll_periods_back(int $count, $ref = null): array
    {
        $out = [];
        $cursor = payroll_period_for($ref);
        for ($i = 0; $i < $count; $i++) {
            $out[] = $cursor;
            $beforeStart = (new DateTime($cursor['start']))->modify('-1 day');
            $cursor = payroll_period_for($beforeStart);
        }
        return $out;
    }
}
