<?php
// Shared gate for the printable Trip Ticket pages.
//
// Policy: trip tickets may be printed at any time after assignment — the
// dispatcher no longer has to wait for the driver to accept the booking.
// The function is kept (as a permissive no-op) so the four print pages that
// call it stay unchanged and the acceptance check can be reinstated in one
// place later if needed.

if (!function_exists('require_driver_accepted_for_ticket')) {
    function require_driver_accepted_for_ticket(array $dispatch): void
    {
        // Printing is always allowed — no acceptance requirement.
        return;
    }
}
