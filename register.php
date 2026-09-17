<?php
// Driver self-registration has been retired — drivers now sign in with
// their company Microsoft account, matched against drivers.driver_email.
header('Location: login?error=' . urlencode('Drivers now sign in with their company Microsoft account — no registration needed.'));
exit;
