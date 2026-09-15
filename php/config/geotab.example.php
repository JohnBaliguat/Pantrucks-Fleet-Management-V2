<?php
// MyGeotab API credentials.
//
// Copy this file to `geotab.php` (same folder) and fill in the values.
// `geotab.php` is git-ignored — never commit real credentials.
//
// =====================================================================
// IMPORTANT — use a DEDICATED API service user
// =====================================================================
// Create a separate MyGeotab user for this integration (Administration →
// Users → Add). Give it a role with API/data read access. Do NOT reuse an
// SSO or MFA-protected login: accounts that require multi-factor auth or
// third-party SSO CANNOT authenticate against the JSON-RPC API, so the
// poller would fail every time.
//
// Setup steps (one-time, in MyGeotab):
//   1. Note your database name — it's in the MyGeotab URL after the host,
//      e.g. https://my.geotab.com/DATABASE_NAME/  → GEOTAB_DATABASE.
//   2. Create the service user (email + password) → GEOTAB_USER / GEOTAB_PASSWORD.
//   3. GEOTAB_SERVER is the login host ('my.geotab.com'). After the first
//      Authenticate call Geotab may return a different data server (e.g.
//      my3.geotab.com); the client follows that automatically and caches it,
//      so you only need the login host here.

if (!defined('GEOTAB_DATABASE')) define('GEOTAB_DATABASE', 'your_database_name');
if (!defined('GEOTAB_SERVER'))   define('GEOTAB_SERVER',   'my.geotab.com');
if (!defined('GEOTAB_USER'))     define('GEOTAB_USER',     'api-service@yourcompany.com');
if (!defined('GEOTAB_PASSWORD')) define('GEOTAB_PASSWORD', 'CHANGE_ME');

// ---------------------------------------------------------------------
// Phase 3 (vehicle health) — diagnostic ids for odometer & engine hours.
// These are Geotab's well-known Diagnostic ids and are correct for most
// databases. If odometer/engine-hours don't populate, look up the exact ids
// in MyGeotab (a StatusData record's diagnostic) and set them here.
// ---------------------------------------------------------------------
if (!defined('GEOTAB_DIAG_ODOMETER'))     define('GEOTAB_DIAG_ODOMETER',     'DiagnosticOdometerAdjustmentId');
if (!defined('GEOTAB_DIAG_ENGINE_HOURS')) define('GEOTAB_DIAG_ENGINE_HOURS', 'DiagnosticEngineHoursAdjustmentId');

// ---------------------------------------------------------------------
// Phase 4 (fuel reconciliation) — cumulative "total fuel used" diagnostic
// (litres). Delta between two ticket times = engine-measured litres burned.
// Verify against a real StatusData record if fuel doesn't reconcile.
// GEOTAB_FUEL_VARIANCE_PCT: litres variance (%) above which a ticket is
// flagged. GEOTAB_KM_VARIANCE_PCT: same for reported distance.
// ---------------------------------------------------------------------
if (!defined('GEOTAB_DIAG_TOTAL_FUEL'))   define('GEOTAB_DIAG_TOTAL_FUEL',   'DiagnosticDeviceTotalFuelId');
if (!defined('GEOTAB_FUEL_VARIANCE_PCT')) define('GEOTAB_FUEL_VARIANCE_PCT', 15);
if (!defined('GEOTAB_KM_VARIANCE_PCT'))   define('GEOTAB_KM_VARIANCE_PCT',   15);
