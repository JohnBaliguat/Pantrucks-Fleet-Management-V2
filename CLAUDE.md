# CLAUDE.md

Guidance for Claude Code (and developers) working in this repository. This
documents the architecture, logic, features, and conventions of the
**Pantrucks Fleet Management** system.

---

## 1. What this project is

A web-based **fleet / trucking dispatch and operations platform** for a
Philippine hauling company ("Pantrucks" / Anflocor). It manages the full
lifecycle of trucking operations: bookings → dispatch → driver execution →
gate control → billing/payroll → HR performance. It is a multi-role system
(13+ user types) with a driver-facing PWA and a companion native Android app.

- **Language / stack:** PHP (procedural, PDO), served under XAMPP/Apache.
- **Database:** PostgreSQL, hosted on **Supabase** (connection in
  [php/config/config.php](php/config/config.php)). The app was **migrated from
  MySQL/mysqli to PostgreSQL/PDO** — much of the legacy-porting tooling in
  `php/config/` exists for that reason.
- **Frontend:** server-rendered PHP + Bootstrap 5, jQuery, DataTables,
  ApexCharts / Chart.js, Leaflet/MapTiler maps. Built assets via Vite/Sass
  ([package.json](package.json)).
- **PWA:** driver app is an installable PWA ([manifest.webmanifest](manifest.webmanifest),
  [sw-driver.js](sw-driver.js)) with Web Push notifications (VAPID).
- **Native app:** `android-driver-app/` is a Kotlin/Gradle Android wrapper
  (TWA/WebView) linked via [.well-known/assetlinks.json](.well-known/assetlinks.json).

---

## 2. How it runs / routing

There is **no framework router**. [index.php](index.php) is a front controller
holding a single `$routes` associative array that maps a `?route=` key to a PHP
file, then `require`s it. Unknown routes fall back to `admin/404.php`.

- URL shape: `index.php?route=<key>` (clean URLs via [.htaccess](.htaccess)).
- Routes are **namespaced by role prefix**: `dispatch-*` (dispatcher),
  `driver-*`, `client-*`, `gate-*`, `gas-*`, `hr-*`, `hra-*` (HR Admin),
  `visual-*`, `maintenance-*`, `payroll-*`, `shop-*`. Un-prefixed routes are
  the **Admin** area (`admin/*.php`).
- Role landing pages after login are decided in [login-php.php](login-php.php)
  by `user_type`.

**To add a page:** create the PHP file in the right role folder, then register
a route key in `index.php`. Follow the existing `_layout_top.php` /
`_layout_bottom.php` (or `navbar.php` + `sidebar.php`) include pattern used in
that role's folder.

---

## 3. Authentication & roles

- Auth entry: [login.php](login.php) (form) → [login-php.php](login-php.php)
  (POST handler). Sessions via `$_SESSION['user_id']`, `$_SESSION['user_type']`.
- **Two credential tables** are checked in order:
  1. `"user"` table (note: `user` is a reserved word in PostgreSQL, always
     quoted as `"user"`) — staff/admin roles.
  2. `drivers` table (`driver_uname` / `driver_pass`) — driver logins.
- Passwords hashed with `password_hash` / verified with `password_verify`.
- Accounts with status `Pending` are blocked until approved.
- **Microsoft Entra ID SSO** (staff and drivers): `ms-login` / `ms-callback`
  routes → [php/operations/ms_sso_start.php](php/operations/ms_sso_start.php)
  and `ms_sso_callback.php`; config in `php/config/microsoft_sso.php`
  (git-ignored; see `microsoft_sso.example.php`).
- Every login writes an activity-log entry via
  [php/helpers/activity_log_helper.php](php/helpers/activity_log_helper.php).

### User types (roles)
`Admin`, `Dispatcher`, `Dispatch Admin`, `Booker`, `Shop`, `Rescue`, `User`
(HR), `HR-Admin`, `Visual` (analytics/manager), `Gate-Guard`, `Maintenance`,
`Client`, `Gastender` (fuel), `Payroll`, and `Driver`.

---

## 4. Directory map

| Path | Purpose |
| --- | --- |
| `index.php` | Front-controller route table |
| `admin/` | Admin console — dashboards, dispatch, monitoring, reports, settings (~60 pages) |
| `dispatcher/` | Dispatcher console — mirror of admin scoped to dispatch ops |
| `driver/` | Driver PWA pages (dashboard, checklist, POD, breakdown, earnings…) |
| `client/` | Client self-service portal (book trips, my-bookings) |
| `gate/` | Gate-guard check-in / incident module |
| `gas/` | Gastender fuel ticketing & inventory |
| `payroll/` | Piece-rate payroll dashboard |
| `shop/` | Shop / rescue (breakdown repair) module |
| `maintenance/` | Unit maintenance blocking & history |
| `HR/`, `HR Admin/` | HR performance / violations / attendance / payroll views |
| `data-visual/` | Manager analytics & recommendations dashboards |
| `php/config/` | DB config + one-off migration/port/seed/audit scripts |
| `php/operations/` | **Action endpoints** — state-changing POST handlers (~90 files) |
| `php/crud/` | `add/`, `update/`, `delete/` record handlers |
| `php/fetch/` | Read-only JSON/AJAX data endpoints (~100 files) |
| `php/reports/` | Report data builders (trip, truck, trailer, violation, attendance…) |
| `php/helpers/` | Shared helpers (see §7) |
| `php/lib/` | Domain modules (container lifecycle, coupons, violations, whatsapp) |
| `table-fetch/` | Server-side DataTables endpoints |
| `migrations_postgres/` | **Authoritative Postgres schema** (numbered, phase-based) |
| `migrations/` | Legacy MySQL-era migrations (historical) |
| `assets/`, `js/`, `chartjs/`, `datatable/` | Frontend assets and page scripts |
| `android-driver-app/` | Native Android driver app (Kotlin/Gradle) |
| `CHANGES.md` | Detailed phase-by-phase refactor changelog (very useful history) |

---

## 5. Data model (PostgreSQL)

Schema is defined in `migrations_postgres/`, starting with
[000_base_schema.sql](migrations_postgres/000_base_schema.sql) and extended by
numbered phase migrations. Migrations are **additive and idempotent**
(`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`). Run them via
[php/config/run_migrations.php](php/config/run_migrations.php).

### Core tables
- **Users/drivers:** `"user"`, `users`, `drivers`, `sessions`,
  `password_reset_tokens`
- **Fleet units:** `units` (trucks/prime movers), `trailer`, plus
  `rescue_unit`, `shop_unit`
- **Bookings/dispatch:** `booking`, `dispatch`, `trips`, `hauling`,
  `location`, `customer`, `segment`/booking-segments
- **Container ops:** `container_monitoring`, `container_activity`,
  `reefer_monitoring`, `pickup_capture`, `pod_capture`, `gateless_completion`,
  `hustling_record`, `trailer_jackup`
- **Gate:** `gate_log`, `gate_queue`, `outin`
- **Driver ops:** `driver_shift`, `drivers_attendance`,
  `pre_departure_checklist`, `message`
- **Fuel:** `fuel_inventory`, `fuel_report`, `consume_fuel`, `ticket_code`
- **Billing/payroll:** `dispatch_receipt`, `trip_receipts`,
  `receipt_acknowledge`
- **Maintenance:** `unit_maintenance`
- **HR/compliance:** `violation_record`, incident (`incident`), rescue/shop
  records (`rescue_record`, `shop_record`, `shop_manpower`, `assign_manpower`)
- **Workflow/audit:** `workflow_event`, `update_log`, activity log
- **Push/notifications:** `push_subscription`, `push_send_log`
- **Laravel-style infra tables** (present but framework is not used):
  `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`

### Movement/transaction tables
`trailer_movement`, `trailer_transaction`

Naming conventions: table columns are prefixed by entity (`driver_*`,
`unit_*`, `trailer_*`, `user_*`). The codebase has audit scripts in
`php/config/` (`audit_camelcase_mismatches.php`, `normalize_sql_identifiers.php`)
because the Postgres port lowercased identifiers.

---

## 6. Feature areas (the "what it does")

### Bookings
Single, multi, and CTH (container/trailer haul) bookings. Admin/dispatcher and
client-portal entry points. Handlers: `php/crud/add/addbooking*.php`,
`php/operations/client_book*.php`, `editbooking.php`. Booking numbers are
generated in [php/lib/booking_no.php](php/lib/booking_no.php).

### Dispatch
Assign a driver + unit + trailer to a booking. Multiple assign flows:
`assign_booking.php`, `assign_bookingCTH.php`, `assign_booking_dnd.php`
(drag-and-drop tile board — `dispatch-tiles`), `assign_service_trip.php`.
Includes truck blocking/unblocking (`dispatch_block_truck.php`), driver
violation gating (a driver with an active violation cannot be dispatched — see
`pt_driver_has_active_violation` in config.php), and rescue dispatch.

### Container tracking (live)
`container-tracking` route: Pickup → On Trip → Delivered with live GPS.
Lifecycle logic in [php/lib/container_lifecycle.php](php/lib/container_lifecycle.php);
GPS ingest via `ingest_driver_gps.php`; states captured in `pickup_capture`,
`pod_capture`, `gateless_completion`.

### Driver PWA (`driver/`)
Installable PWA. Drivers accept/decline jobs, update trip status, run
pre-departure **checklist**, capture **POD** (proof of delivery photos), report
**breakdown** / **jackup** / **hustling** / **gateless** events, send **SOS**,
chat via **messages**, and view **earnings** & payroll history. Offline support
and Web Push via [sw-driver.js](sw-driver.js) + `driver/pwa-register.js`.
Push send helper: `php/operations/_push_send.php`.

### Gate-Guard (`gate/`) — ⚠️ NOT CURRENTLY IN USE (planned future feature)
The gate module is built but **not active in production today**; it is planned
for future use. Dispatches currently complete via **gateless completion**
(GPS-verified) instead of passing through the gate. Keep the code, but don't
assume the `gate_cleared` workflow stage is exercised in live flows.
Check-in/out at the gate, a gate queue with accept/decide, and incident
reporting. Endpoints: `gate_checkin.php`, `gate_queue_add.php`,
`gate_queue_decide.php`, `checkin_checkout.php`.

### Fuel / Gastender (`gas/`)
Fuel ticketing with generated ticket codes, fuel inventory, and MS reports.
Endpoints: `generate_ticket_code.php`, `add_fuel_inventory.php`,
`check_fuel_availability.php`, `addrecord_fuel.php`.

### Maintenance (`maintenance/`)
Block/unblock units and trailers for maintenance, with **scheduled**
maintenance that auto-activates. Bootstrapped on every request by
`pt_apply_scheduled_maintenance()` in config.php (throttled via a temp-file
lock). Auto-release logic in `php/lib/maintenance_auto_release.php`.

### Shop / Rescue (`shop/`)
Breakdown → rescue dispatch → workshop repair. Manpower assignment,
rescue/shop status tracking and reports.

### Billing & Receipts
Dispatch receipts and trip receipts with driver acknowledgement.
`save_dispatch_receipt.php`, `acknowledge_receipt.php`, `close_billing.php`.

### Payroll (`payroll/`)
**Piece-rate** payroll driven by trip rates and coupon control numbers.
Rate lookup: [php/helpers/trip_rate_lookup.php](php/helpers/trip_rate_lookup.php),
periods: [php/helpers/payroll_period.php](php/helpers/payroll_period.php).
Drivers see self-earnings (`driver-earnings`, `driver-payrollHistory`).

### HR / Performance / Violations
Driver performance, attendance, and **violation records**. Violations can be
scheduled to auto-activate (`auto_activate_violations.php`,
`php/lib/violation_auto_activate.php`) and can notify via WhatsApp
(`resend_violation_whatsapp.php`, [php/lib/whatsapp.php](php/lib/whatsapp.php)).

### Coupons
Trip-ticket coupons with QR codes for a **public, no-login** transaction view
([coupon-view.php](coupon-view.php), route `coupon-view`). Panel logic in
`php/lib/trip_ticket_coupon_panel.php`.

### Monitoring dashboards
Many customer/segment-specific monitoring views (`abcmonitoring`,
`dolemonitoring`, `dmmonitoring`, `farmmonitoring`, `sumimonitoring`,
`cthmonitoring`, `containerMonitoring`) plus real-time truck stats
(`truck-stats-realtime.php`) and manager analytics in `data-visual/`.

### Equipment & Service trips
Equipment utilization tracking and non-hauling **service trips**
(`serviceTrips`, `equipmentUtil`).

---

## 7. Conventions & patterns

- **Everything routes through `index.php`.** Register new pages there.
- **Naming prefix `pt_`** marks shared helper functions (Pantrucks), many
  defined in [php/config/config.php](php/config/config.php) and guarded with
  `if (!function_exists(...))`.
- **DB access:** use the `$conn` PDO instance from `config.php`. **Prefer
  prepared statements** (`$conn->prepare(...)->execute([...])`). `pt_pg_escape()`
  exists only as a legacy shim for un-ported inline SQL — do not use it in new
  code.
- **Postgres reserved words:** always quote `"user"`. Identifiers are lowercase.
- **Runtime schema self-healing:** helpers like `pt_ensure_column()`,
  `pt_ensure_trailer_jackup_table()`, `pt_ensure_pickup_capture_table()` create
  missing columns/tables on demand. New schema should still get a proper
  numbered migration in `migrations_postgres/`.
- **Endpoint layout:** read-only JSON goes in `php/fetch/` or `table-fetch/`;
  state changes go in `php/operations/` or `php/crud/{add,update,delete}/`.
- **Activity logging:** call `pt_log_activity($conn, userId, userType, name,
  action, detail)` for auditable actions.
- **Settings:** app settings via [php/helpers/settings_helper.php](php/helpers/settings_helper.php)
  (`settings` route / `admin/settings.php`).
- **Idempotency:** POST handlers can use
  [php/helpers/idempotency_helper.php](php/helpers/idempotency_helper.php).
- Read **[CHANGES.md](CHANGES.md)** before large changes — it documents the
  phased refactor (workflow pipeline, gate module, driver PWA, SSO, fuel,
  piece-rate payroll) and which look-alike files are intentionally kept.

### Helpers reference (`php/helpers/`)
`activity_log_helper.php`, `datatables_helper.php`, `idempotency_helper.php`,
`microsoft_auth.php`, `payroll_period.php`, `settings_helper.php`,
`trip_rate_lookup.php`, `upload_error_helper.php`.

### Domain libs (`php/lib/`)
`booking_no.php`, `container_lifecycle.php`, `maintenance_auto_release.php`,
`trip_deletion_requests.php`, `trip_ticket_coupon_panel.php`,
`trip_ticket_gate.php`, `trip_ticket_rows.php`, `violation_auto_activate.php`,
`whatsapp.php`.

---

## 8. Setup / operations

- **DB config & secrets:** [php/config/config.php](php/config/config.php)
  (Supabase Postgres DSN — contains live credentials; treat as sensitive).
  SSO and VAPID secrets are git-ignored (`microsoft_sso.php`, `vapid.php`);
  copy the `*.example.php` files to create them.
- **Run migrations:** `php php/config/run_migrations.php` (applies
  `migrations_postgres/` in order).
- **Seed data:** `php/config/seed_admin.php`, `seed_import_customers.php`,
  `seed_payroll.php`, `seed_trip_rate_combos.php`, plus `import_*.php` CSV
  importers (drivers, trailers, units, trip rates).
- **Composer:** [phpoffice/phpspreadsheet](composer.json) for Excel
  import/export (`php/operations/import-excel.php`).
- **Frontend build:** `npm run compile-sass` (Bootstrap/Sass watch).
- **Serve:** place under XAMPP `htdocs`; open via Apache. Base URL routes to
  `index.php`.

### Housekeeping / audit scripts (`php/config/`)
One-off maintenance tools from the MySQL→Postgres port: `audit_*.php`,
`convert_mysqli_to_pdo.php`, `convert_schema.php`, `normalize_sql_identifiers.php`,
`fix_camelcase_keys.php`, `cleanup_*.php`, `lint_remaining.php`, `punch_list.php`.
These are developer utilities, not part of the request flow.

---

## 9. Notes & cautions for changes

- **No automated test suite.** Verify changes manually against the affected
  role/page; there is only `tools/test_booking_schema.php`.
- Credentials are committed in `config.php` — do not add more secrets to
  version control; use the git-ignored config files.
- Because routing is a manual table, a new page is invisible until added to
  `index.php`. Reuse the role folder's layout includes.
- Many `*1.php` / `*2.php` / `*3.php` variants exist and are **intentionally
  live** (referenced from JS). Check `CHANGES.md` and `grep` before deleting
  anything that looks like a duplicate.
- Prefer prepared statements and quote `"user"`; keep migrations additive.

---

## 10. Common tasks (cheat-sheet)

### Add a new page to a role
1. Create the PHP file in the role folder (e.g. `admin/my-report.php`,
   `dispatcher/my-report.php`).
2. Reuse the folder's layout: include `navbar.php` + `sidebar.php` (admin/HR
   style) **or** `_layout_top.php` + `_layout_bottom.php` (driver/gate/gas/
   client/payroll/maintenance style) — match the sibling files.
3. Register a route key in [index.php](index.php)'s `$routes` array, using the
   role prefix (`dispatch-`, `driver-`, `gate-`, etc.); un-prefixed = Admin.
4. Add a menu link in that role's `sidebar.php`/`navbar.php`.
5. Guard the top of the page with a session/role check (copy from a sibling
   page — they verify `$_SESSION['user_type']`).

### Add a booking type / field
- Entry forms: `php/crud/add/addbooking*.php` (+ `js/addbooking.js`);
  client portal: `client/book-trip*.php` → `php/operations/client_book*.php`.
- Booking numbers come from [php/lib/booking_no.php](php/lib/booking_no.php).
- If you add a column, write a numbered migration in `migrations_postgres/`
  (additive/idempotent) **and** update the insert/select statements.

### Add a report
1. Data builder in `php/reports/` (or a `table-fetch/*-table.php` DataTables
   endpoint for a paginated grid).
2. A view page in the role folder that consumes it (fetch via AJAX / jQuery
   DataTables — see existing `*-report.php` + `js/*-report.js` pairs).
3. Route key in `index.php` + sidebar link.

### Add a read-only data (AJAX/JSON) endpoint
- Put it in `php/fetch/` (returns JSON) or `table-fetch/` (DataTables shape).
- `include "php/config/config.php";` for `$conn`, use prepared statements,
  `echo json_encode(...)`. Do **not** mutate state here.

### Add a state-changing action (POST)
- Put it in `php/operations/` (business action) or `php/crud/{add,update,
  delete}/` (record CRUD).
- Validate input, use prepared statements, wrap multi-step writes in a
  transaction (`$conn->beginTransaction()`), and call
  `pt_log_activity(...)` for auditable changes.
- For POSTs that must not double-apply, use
  [php/helpers/idempotency_helper.php](php/helpers/idempotency_helper.php).

### Add / change database schema
1. New file `migrations_postgres/NNN_description.sql`, next number in sequence.
2. Additive & idempotent only: `CREATE TABLE IF NOT EXISTS`,
   `ADD COLUMN IF NOT EXISTS` — no destructive drops/renames.
3. Apply with `php php/config/run_migrations.php`.
4. Lowercase identifiers; quote `"user"` if referenced.

### Add a new user role
- Add the `user_type` handling branch in [login-php.php](login-php.php)
  (landing redirect), create the role folder + pages, add its routes in
  `index.php`, and add role checks on each page.

### Send a push notification to a driver
- Use `php/operations/_push_send.php`; subscriptions live in
  `push_subscription`, sends are logged to `push_send_log`. VAPID keys in the
  git-ignored `php/config/vapid.php`.

### Quick verification (no test suite)
- Log in as the affected role and exercise the page/flow manually.
- `tools/test_booking_schema.php` is the only script-style check.
- `grep`/search before deleting look-alike `*1/*2/*3.php` files — many are
  live (see `CHANGES.md`).

---

## 11. Improvement recommendations

Findings from a scan of the codebase, roughly highest-impact first. None are
blockers, but they are the areas most worth investing in.

### Security (highest priority)
1. **Committed live database credentials.** The Supabase Postgres user/password
   are hard-coded in [php/config/config.php](php/config/config.php) and in git
   history. **Rotate the password**, move the DSN/user/pass into a git-ignored
   `.env` (or `php/config/db.php` like the SSO/VAPID pattern), and load it at
   runtime. Anyone with repo access currently has production DB access.
2. **Widespread inline SQL via the `pt_pg_escape()` shim (~69 files).** The
   shim was meant as a temporary bridge, but string-interpolated SQL is still
   the norm across `php/operations`, `php/crud`, `php/fetch`, and `table-fetch`.
   This is the largest **SQL-injection** surface. Convert these to prepared
   statements incrementally (the audit scripts in `php/config/` can help locate
   them) and treat `pt_pg_escape` as deprecated.
3. **No CSRF protection.** No page or POST handler uses a CSRF token. All
   state-changing forms/AJAX (`php/operations/`, `php/crud/`) are vulnerable to
   cross-site request forgery. Add a per-session token helper and verify it in
   every POST handler (a shared `require` at the top of operation files).
4. **Per-page role authorization is copy-pasted, not centralized.** Each page
   re-checks `$_SESSION['user_type']` by hand, which is easy to forget on a new
   page (a missing check = privilege escalation). Add a single
   `require_role([...])` guard helper and include it at the top of every page.

### Reliability / correctness
5. **Runtime schema self-healing on the hot path.** `config.php` runs
   `pt_apply_scheduled_maintenance()` (and `pt_ensure_*` helpers elsewhere) on
   nearly every request. It is throttled by a temp-file lock, but doing DDL /
   scheduler work inside page loads is fragile and slow. Move scheduled
   maintenance and violation auto-activation to a **cron job** (or Supabase
   scheduled function) and keep `config.php` to connection setup only.
6. **No automated tests.** Only `tools/test_booking_schema.php` exists. The
   high-value flows (dispatch assignment, booking creation, payroll rate
   lookup, container lifecycle) have no regression coverage. Even a small set
   of PHP smoke tests around `php/lib/` and `php/helpers/` would catch a lot.
7. **Error handling swallows failures silently.** Several helpers catch
   `Throwable` and return quietly (e.g. maintenance bootstrap). Good for not
   breaking page render, but failures become invisible. Add lightweight logging
   to a single error log so silent failures are traceable.

### Maintainability
8. **`admin/` and `dispatcher/` are ~51 near-mirror files that have drifted.**
   Shared pages like `truck.php`, `monitoring.php` differ by ~75% of their
   lines — meaning bug fixes must be applied twice and often aren't. Extract the
   shared page bodies into includes (e.g. `admin/_truck_body.php`) that both
   role wrappers pull in, parameterized by role. This is the single biggest
   duplication in the project.
9. **Confusing `*1/*2/*3` file variants.** Files like `updatetruck{1,2,3}.php`,
   `get_drivers{1,2}.php`, `get_trucks1.php` are all **live** (referenced from
   JS) but their names carry no meaning. Rename them to describe what they do
   (`get_trucks_for_dispatch.php`, etc.) and update the callers, so future
   readers don't assume they're deletable duplicates.

### Consistency
10. **Fetch/table-fetch overlap.** `php/fetch/` (~100 files) and `table-fetch/`
    (~33 files) both return read data with no crisp boundary. Document (or
    consolidate) the rule: DataTables server-side endpoints in `table-fetch/`,
    everything else in `php/fetch/`.

---

## 12. Redundant / dead features & files

Confirmed by search. **Verify with `grep` and `CHANGES.md` before removing**
anything — some look-alikes are intentionally live.

### Likely dead / removable
- **`users` table** — defined in the base schema but **never queried**; the app
  uses the quoted `"user"` table exclusively. Candidate to drop (after
  confirming no external consumer).
- **Laravel-style infra tables** — `cache`, `cache_locks`, `jobs`,
  `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`,
  `migrations` exist from a scaffold but the app uses PHP sessions and its own
  `migrations_postgres/` runner. These are unused unless a Laravel component is
  reintroduced.
- **`php/assign_booking1.php`** (root `php/`) vs
  **`php/operations/assign_booking1.php`** — two different files with the same
  name; only the one referenced by `dispatcher/dispatch-test.php` is needed.
  Consolidate.
- **`dispatcher/dispatch-test.php`** (`dispatch-dispatchTest` route) — a test
  page still wired into the live route table. Remove the route (and file) once
  confirmed unused in production.
- **`migrations/`** (legacy MySQL-era) — historical only; `migrations_postgres/`
  is authoritative. Keep for reference or archive, but it is not run.
- **Housekeeping/port scripts in `php/config/`** — `audit_*.php`,
  `convert_*.php`, `cleanup_*.php`, `normalize_sql_identifiers.php`,
  `lint_remaining.php`, `punch_list.php` were one-off MySQL→Postgres tools.
  They should not ship to production; move them to a `tools/` / `scripts/`
  folder outside the web root.

### Redundant-by-design (do NOT blindly delete — refactor instead)
- **`admin/` ↔ `dispatcher/`** mirror pages (see #8 above) — consolidate via
  shared includes rather than deleting.
- **`login.php` + `login-php.php`** — form vs POST handler; both needed, but the
  naming is confusing (consider `login.php` + `login_handler.php`).
- The many **monitoring variants** (`abcmonitoring`, `dolemonitoring`,
  `dmmonitoring`, `farmmonitoring`, `sumimonitoring`, `cthmonitoring`) are
  per-customer copies that likely share most logic — candidates to merge into
  one parameterized `monitoring.php?segment=…`.

> Recommended approach: tackle **Security #1–#4 first** (rotate creds, then
> chip away at prepared statements + CSRF), then the **admin/dispatcher
> consolidation (#8)** since it compounds every other change. Do redundancy
> cleanup last, one item per PR, each verified manually against the affected
> role.
