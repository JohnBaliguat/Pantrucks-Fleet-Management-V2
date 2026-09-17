# Pantrucks Fleet Management — Rebuild Specification

A complete, implementation-agnostic reference of everything the current system
does, so a new version can be built without reverse-engineering the old code.
Captures **data model, roles, features, business rules, workflows, and
integrations** as of 2026. Pair with [CLAUDE.md](CLAUDE.md) (architecture of the
current build) and [CHANGES.md](CHANGES.md) (phase history).

> **Domain:** container/reefer trucking & hauling for a Philippine logistics
> company (Anflocor / Pantrucks). Core job: move shipping containers between
> ports, container yards (CY), and customer sites with trucks (prime movers),
> trailers/chassis, and gensets (for reefers), tracked end-to-end.

---

## 1. System overview

| Aspect | Current build | Notes for rebuild |
| --- | --- | --- |
| Type | Multi-role web app + driver PWA + native Android wrapper | Keep the driver as a mobile-first PWA/app |
| Backend | PHP 8 (procedural, PDO), Apache/XAMPP | Any stack; the domain logic below is what matters |
| Database | PostgreSQL on Supabase | Relational; ~55 tables (see §4) |
| Frontend | Server-rendered PHP + Bootstrap 5, jQuery, DataTables, ApexCharts/Chart.js, Leaflet/MapTiler | SPA optional |
| Auth | Session-based; local password + Microsoft Entra SSO | 15 roles (see §2) |
| Realtime | Web Push (VAPID), polling AJAX, live GPS ingest | Consider websockets in rebuild |
| Integrations | Microsoft SSO, WhatsApp (violations), Web Push, Map tiles, Excel (PhpSpreadsheet) | See §7 |

---

## 2. Roles & permissions

15 user types. Staff/admin live in the `"user"` table; drivers in `drivers`.
Each role has its own landing page and menu.

| Role | Landing | Can do |
| --- | --- | --- |
| **Admin** | dashboard | Everything: bookings, dispatch, units, drivers, users, settings, reports, billing, payroll config |
| **Dispatcher** | dispatch dashboard | Bookings, dispatch/assign, monitoring, gate, driver ops — scoped to dispatch |
| **Dispatch Admin** | dispatch admin dashboard | Dispatcher + analytics/oversight |
| **Booker** | add booking | Create bookings only |
| **Client** | client portal | Self-service: book trips, view own bookings/containers (scoped by `customer_code`) |
| **Driver** | driver PWA | Accept/decline jobs, run trips, checklist, POD, incidents, earnings |
| **Gate-Guard** | gate dashboard | Gate check-in/out, verify QR, gate queue, incidents |
| **Gastender** | fuel ticketing | Issue fuel tickets, manage fuel inventory, fuel reports |
| **Payroll** | payroll dashboard | Assign piece-rate via coupon control no., close periods |
| **Maintenance** | maintenance dashboard | Block/unblock units & trailers, maintenance history |
| **Shop** | shop dashboard | Workshop repairs, manpower assignment |
| **Rescue** | shop dashboard | Breakdown rescue dispatch |
| **User (HR)** | HR dashboard | Driver performance, attendance, violations (read/report) |
| **HR-Admin** | HR Admin dashboard | HR + payroll + violation management (can block drivers) |
| **Visual** | analytics dashboard | Manager analytics, recommendations, performance dashboards |

**Account status:** `Pending` accounts are blocked until approved. Drivers have
a separate `driver_account_status`.

---

## 3. Core domain entities

- **Unit** = truck / prime mover (also stores gensets via `unit_type`). Has
  plate, OR/CR, brand, engine/chassis no., fuel std ratio, 4 photos, assigned
  driver/segment/trailer/genset, current location, maintenance-block state.
- **Trailer** (chassis) = separate asset; assigned to a truck/driver, tracked by
  location & base, has its own maintenance-block state; can be "jacked up"
  (detached) mid-trip.
- **Genset** = refrigeration unit for reefer containers (tracked as a unit type
  and on trips/dispatch).
- **Driver** = has RFID, license/ID number, contact, photo, signature, assigned
  unit/segment/base, live GPS (last_lat/lng/last_seen), shift state.
- **Customer** = has `customer_code` and `customer_segment` (ABC, DOLE, SUMI,
  CTH, DM, FARM…); segment drives monitoring views and trip rates.
- **Booking** = a haul request (container to move). Snapshots customer segment.
- **Dispatch** = assignment of a booking to driver + truck + trailer + genset.
- **Trip** = the actual movement executed under a dispatch (a dispatch can spawn
  multiple trip legs/segments). Carries the stamped `piece_rate`.
- **Container** = tracked through a lifecycle (pickup → on trip → delivered).

---

## 4. Data model (≈55 tables)

Primary keys are identity integers unless noted. Column prefixes indicate the
entity (`driver_*`, `unit_*`, `trailer_*`, `user_*`, `booking_*`, `d_*`,
`trip_*`). `"user"` must be quoted (Postgres reserved word).

### Identity & access
- **`"user"`** — staff accounts. Key cols: `user_name`, `user_pass` (hashed),
  `user_type` (role), `user_fname/lname/mname`, `user_email`,
  `user_assignlocation`, `customer_code` (links a Client to a customer),
  `user_accountstat`, Microsoft SSO: `microsoft_oid`, `microsoft_tenant_id`,
  `microsoft_email`, `microsoft_linked_at`.
- **`drivers`** — driver accounts + profile. `driver_idnumber`, `driver_rfid`,
  name fields, `driver_contact`, `driver_image`, `driver_signature`,
  `driver_assignunit/segment/base`, `driver_status`, `driver_uname`,
  `driver_pass`, `driver_account_status`, shift (`shift_truck`,
  `shift_started_at`, `shift_ended_at`), live GPS (`last_seen_at`, `last_lat`,
  `last_lng`), Microsoft SSO cols (added in phase 15).
- *(Laravel scaffold, unused: `users`, `sessions`, `password_reset_tokens`,
  `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` —
  drop in rebuild.)*

### Fleet assets
- **`units`** — trucks/prime movers/gensets (full profile + maintenance block +
  `current_location`).
- **`trailer`** — chassis (assignment, location, `current_base`, maintenance
  block).
- **`trailer_jackup`** — a trailer detached mid-trip at GPS point; billing runs
  while `billing_active` until `returned_at`.
- **`trailer_movement`**, **`trailer_transaction`** — trailer movement/txn logs.

### Bookings & dispatch
- **`booking`** — the haul request. Rich: booking no/sn/do, dates
  (`booking_date`, required, hauling start, last storage/demurrage/detention),
  `costumer`, `customer_segment`, container + seal, activity, hauling
  segment/type, `trip_from`/`trip_to`/`return_location`, vessel/voyage/BL/port
  fields, `customs_cleared`(+at), `client_notified_at`, `quantity`/`quantity_use`,
  `status`.
- **`dispatch`** — assignment. `booking_no`, `dispatch_ref`, `d_datetime`,
  dispatcher/hub, `d_drivername`+`driver_id`, `d_truck`/`d_trailer`/`d_genset`,
  `d_tripreceipt`, `costumer`, **`workflow_stage`** (see §5), driver
  accept/decline timestamps + `decline_reason`, `gate_cleared_at`,
  `billing_closed_at`, `billing_amount`/currency/notes, `trip_started_at`,
  `trip_completed_at`, verification (`verified_by/at`, notes).
- **`trips`** — executed legs. `d_id`, type, trailer/genset, customer +
  `segment_costumer`, container type/no, activity, container status, hauling
  segment/type, from/to/return, `km_run`, departure/arrival/PH-arrival
  datetimes, deliver/withdraw location+datetime, `trip_status`,
  `segment_status`, `foul_trip`, cancelled at/reason, `scheduled_at`,
  `trip_seq`, **`piece_rate`** (stamped at insert by a DB trigger).
- **`hauling`** — hauling segment/type dictionary.
- **`location`** — named locations with lat/long.
- **`customer`** — code, name, `customer_segment`, `notify_email`,
  `notify_phone`.

### Container tracking
- **`container_monitoring`** — live tracking board (number, shipping line,
  location tag, status, priority, PM no., driver, ETD/CY-arrival/CY-departure/
  PH-arrival/PTSI timestamps, seal, vessel ETD, week, stage, remarks).
- **`container_activity`** — activity log per container (location, truck,
  trailer, genset, driver, container/trip status).
- **`reefer_monitoring`** — reefer-specific monitoring (van/PM no., ETD/arrival/
  departure dates, CIR, week, vessel ETD, customer ref).
- **`pickup_capture`** — driver photo + GPS at container pickup.
- **`pod_capture`** — proof of delivery: photo + signature + signer + GPS +
  verification.
- **`gateless_completion`** — trip completed without a gate (GPS-verified,
  supports offline sync).
- **`hustling_record`** — port "hustling" productivity (trips, x-ray, latag,
  inspection, ECD-on-dock counts per driver/shift).

### Gate
- **`gate_log`** — every gate in/out (direction, truck/trailer/genset codes,
  driver, QR payload, `verified`, `mismatch_reason`, `authorised`, guard).
- **`gate_queue`** — pending gate requests with accept/decide decision.
- **`outin`** — legacy in/out record (driver, truck, trailer, genset, segment,
  status).

### Driver operations
- **`driver_shift`** — shift session (truck, start/end, `machine_hours`, linked
  checklist `pdc_id`).
- **`drivers_attendance`** — attendance (control no., status, time in/out, date,
  VL/SL dates).
- **`pre_departure_checklist`** — fuel/tyres/lights/cargo/genset OK flags +
  remarks, per driver+truck.
- **`message`** — 2-way chat (from/to role+id, body, optional `d_id`, read_at).

### Fuel
- **`fuel_inventory`** — deliveries (PO no., liters, invoice, plate, consumable/
  consume liters).
- **`fuel_report`** — per-unit consumption (last/current hubo, km run, liters,
  actual vs std ratio, excess/saving, hour meter, control no., trip ticket,
  segment, transacted by).
- **`consume_fuel`** — per-unit fuel draw (hubo, liters).
- **`ticket_code`** — generated fuel ticket codes (`tc_code`, driver id, unit,
  status Unused/Used).

### Billing & receipts
- **`dispatch_receipt`** — files attached to a dispatch (type, title, path, mime,
  `requires_ack`, attached by/at).
- **`receipt_acknowledge`** — driver ack of a receipt.
- **`trip_receipts`** — trip receipt / control no. with km details.

### Maintenance
- **`unit_maintenance`** — block record: `unit_kind`(truck/trailer), `unit_code`,
  category, severity, reason, photo, `expected_return`, `scheduled_start_at`
  (for scheduled blocks), `activated_at`, blocked/released by+at, release notes,
  `cost_labor`/`cost_parts`, `status` (scheduled/active/released).

### Shop / rescue
- **`shop_unit`**, **`shop_record`** — workshop repair units + logs.
- **`rescue_unit`**, **`rescue_record`** — breakdown rescue units + logs.
- **`shop_manpower`**, **`assign_manpower`** — mechanics/helpers and their
  assignment to rescue/shop jobs.

### HR / compliance
- **`violation_record`** — driver violation (type, description, `vr_status`
  Active/Scheduled/Done, recorded by, date, done date, **`vr_scheduled_at`** for
  auto-activation). An **Active** violation blocks the driver from dispatch.
- **`incident`** — on-trip incident (type, severity, description, GPS, photo,
  assistance requested, reassigned dispatch, status open/resolved).

### Workflow, audit, notifications
- **`workflow_event`** — append-only pipeline event log (dispatch, booking,
  stage, actor role/id, notes, timestamp).
- **`update_log`** — fuel-report edit log.
- **Activity log** (via helper) — all user actions (login, CRUD, dispatch…).
- **`push_subscription`** — driver web-push endpoints (endpoint, keys, UA).
- **`push_send_log`** — every push attempt (to role/id, channel, subject, body,
  outcome, error).

### Payroll (piece-rate)
- **`trip_rates`** — rate lookup keyed by (`segment`, `activity`); `base_rate` +
  `additional` → generated `total_rates`. Unique on `LOWER(segment,activity)`.
- **`trips.piece_rate`** — stamped from `trip_rates` at trip insert via trigger
  `trg_trips_stamp_piece_rate`, so historical payroll is immune to later rate
  changes.

---

## 5. The workflow pipeline (most important business logic)

Every dispatch advances through an ordered set of **workflow stages** (stored in
`dispatch.workflow_stage`, each transition appended to `workflow_event`):

```
dispatcher_assigned
  → driver_accepted        (driver taps Accept in PWA)   |  driver_declined → reassigned
  → en_route               (driver starts trip; pre-departure checklist done)
  → gate_cleared           (gate guard verifies QR + truck/trailer/driver match)
                           ⚠️ gate module dormant today — this stage is
                           currently BYPASSED; trips complete via gateless
                           completion. Wire it back in when gate goes live.
  → pod_captured           (driver captures proof of delivery: photo + signature + GPS)
  → delivered              (container delivered / gateless completion)
  → pending_verification   (office verifies POD / trip data)
  → billing_closed         (billing amount finalized)
  → client_notified        (customer notified of completion)
```

Side/terminal states: `recalled` (dispatcher pulls back an assignment before
acceptance), `reassigned` (after decline or incident-driven reassignment).

**Rebuild note:** model this as an explicit state machine with allowed
transitions and an event log. The current build spreads transitions across many
operation files; centralize them.

---

## 6. Feature areas & business rules

### 6.1 Bookings
- Types: **Local**, **multi** (many containers at once), **CTH** (container/
  trailer haul with broker + EIR in/out fields).
- Booking numbers auto-generated (sequential, per scheme).
- Segment is snapshotted from the customer at creation (rates/monitoring depend
  on it).
- Clients can self-book via the portal (scoped to their `customer_code`).

### 6.2 Dispatch / assignment
- Assign driver + truck + trailer + genset to a booking. Flows: standard, CTH,
  drag-and-drop tile board, service trips.
- **Guards enforced at assignment:**
  - Driver with an **Active** violation → blocked (HR block).
  - Truck/trailer flagged **maintenance_blocked** → not assignable.
  - Truck can be explicitly **blocked** by dispatch (with unblock).
  - Unit availability / already-assigned checks.
- Recall an assignment before the driver accepts.
- Driver recommendation helper suggests eligible drivers.

### 6.3 Driver PWA
Accept/decline jobs; pre-departure checklist (gates `en_route`); update trip
status; capture **pickup** (photo+GPS), **POD** (photo+signature+GPS);
**gateless completion** (GPS, offline-capable); report **breakdown**, **jackup**
(detach trailer), **hustling** (port productivity); send **SOS**; **chat**;
view **earnings** (sum of stamped `piece_rate`) & payroll history. Live GPS
streamed to the server (`ingest_driver_gps`). Installable PWA + push.

### 6.3a Driver app — delivery model (PWA + native Android)

The driver experience ships **two ways over the same web codebase** (`driver/`):

**A. Installable PWA** (primary)
- Manifest [manifest.webmanifest](manifest.webmanifest): `id` `pantrucks-driver`,
  `start_url` `driver-dashboard`, `display: standalone`, portrait, themed.
- Service worker [sw-driver.js](sw-driver.js) + `driver/pwa-register.js`:
  offline caching of the driver shell, background sync, and **Web Push**
  handling (job offers, messages).
- Works on any modern mobile browser; "Add to Home Screen" installs it.

**B. Native Android wrapper** (`android-driver-app/`, Kotlin/Gradle)
- A **WebView shell** (`MainActivity.kt`) around the same driver pages — *not*
  a native rewrite. Single-activity, full-screen.
- Base URL is build-time configurable via `gradle.properties`
  (`DRIVER_APP_BASE_URL`, default `https://app.fleet-pantrucks.com/driver-dashboard`).
- Adds device capabilities the browser gates: **persistent login/session
  cookies**, **geolocation** prompts for live tracking, **camera + file
  upload** for POD / pickup / jack-up / breakdown captures, **pull-to-refresh**,
  Android back navigation, and routing downloads to Android's download manager.
- Config files: `AndroidManifest.xml`, `res/xml/file_paths.xml` (upload paths),
  `res/xml/network_security_config.xml` (allow http for LAN/XAMPP testing).
- A **Trusted Web Activity (TWA)** path is possible later via
  [.well-known/assetlinks.json](.well-known/assetlinks.json) but needs a stable
  HTTPS domain.

**Rebuild guidance:** keep the driver app as a mobile-first PWA and decide early
whether the native shell stays a WebView/TWA wrapper (cheap, one codebase) or
becomes a real native/React-Native/Flutter app (needed only if you want richer
offline, background GPS, or hardware integration). The capabilities the wrapper
currently exposes (camera, GPS, persistent session, downloads) are the
must-haves either way.

**Driver app screens / routes** (all under `driver-*`): dashboard, profile,
booking list, unit, checklist, POD, gateless, jackup, hustling, breakdown,
messages, receipts, earnings, payroll-history, trip-report, drivers-report.

### 6.4 Gate control — ⚠️ NOT CURRENTLY IN USE (planned for future)
> **Status:** The gate module is built but **not active in production today**.
> It is a **planned future feature** — keep it in the rebuild scope, but treat
> it as phase-2/optional, not part of the initial live cutover. Because it is
> dormant, dispatches today complete via **gateless completion** (GPS-verified,
> §6.3) rather than passing through `gate_cleared`. Do not let gate absence
> block the workflow.

Guard scans a QR (dispatch payload), system verifies truck/trailer/genset/driver
match → logs `gate_log` with `verified`/`mismatch_reason`/`authorised`. Gate
queue lets guards accept/decide entry. Advances dispatch to `gate_cleared`.

### 6.5 Container tracking & monitoring
Live board Pickup → On Trip → Delivered with GPS. Segment-specific monitoring
views per customer (ABC, DOLE, DM, FARM, SUMI, CTH) + reefer monitoring +
real-time truck stats. Manager analytics/recommendations for the Visual role.

### 6.6 Fuel (Gastender)
Issue fuel via generated **ticket codes**; check availability against
`fuel_inventory`; record consumption in `fuel_report`/`consume_fuel` (computes
actual vs standard ratio, excess/saving); MS (management summary) reports.

### 6.7 Maintenance
Block/unblock units & trailers (immediate or **scheduled** via
`scheduled_start_at` → auto-activates when due; runs as a bootstrap check now,
should be a cron). Auto-release when `expected_return` passes. Tracks
labor/parts cost. Blocked assets can't be dispatched.

### 6.8 Shop / rescue (breakdown → repair)
Breakdown reported → rescue unit dispatched → forwarded to workshop → manpower
assigned → status tracked to good. Rescue & shop reports.

### 6.9 Billing & receipts
Attach receipts to a dispatch (some `requires_ack`); driver acknowledges; close
billing with amount/currency/notes → advances to `billing_closed`.

### 6.10 Payroll (piece-rate) & driver earnings display
Driver pay = sum of `piece_rate` stamped on completed trips, looked up from
`trip_rates` by (segment, activity). Rates assigned/confirmed via coupon
**control number**; payroll periods defined by a helper. HR-Admin/Payroll manage.

**Driver-facing earnings (must preserve in rebuild):**
- **Shown on the driver dashboard**, not just a separate page: the driver's home
  screen displays **current-period** and **previous-period** earnings + trip
  counts (`driver/dashboard.php`).
- **Earnings = `SUM(trips.piece_rate)`** over the period — **only VERIFIED
  dispatches count** (a completed-but-unverified trip is excluded until the
  office verifies it). This ties earnings to the workflow's verification gate.
- **Payroll cutoff cycle: 6→20 and 21→5** (semi-monthly), computed by
  `php/helpers/payroll_period.php` (`payroll_period_for()`,
  `payroll_previous_period()`).
- Dedicated pages too: **`driver-earnings`** (date-range summary: total trips,
  total ₱, breakdown) and **`driver-payrollHistory`** (past periods).

### 6.11 HR / performance / violations
Driver performance metrics, attendance, and violations. Violations can be
**scheduled** (activate at `vr_scheduled_at`) and, when active, **block
dispatch**. Violation notices can be sent via **WhatsApp**.

### 6.12 Coupons (trip tickets)
Trip-ticket coupons carry a QR to a **public, no-login** transaction view for
verification. Tie into fuel ticket codes and payroll control numbers.

### 6.13 Incidents & SOS
Drivers report incidents (type, severity, GPS, photo, assistance) or SOS;
office acknowledges/reassigns/resolves; can trigger dispatch reassignment.

### 6.14 Equipment & service trips
Equipment utilization tracking and non-hauling service trips.

### 6.15 Executive overview (mobile-first)
A quick read-only view for management (`Executive` role, also open to Admin /
Dispatch Admin). One phone-friendly screen:
- **Truck search** (typeahead of truck codes) → the truck's **current trip**
  (prefers the Active leg): driver, customer, booking, route, container +
  Empty/Loaded, segment, workflow stage, dispatched time.
- **Operations prediction** — completed trips this month + a pace-based
  **projection to month-end**, and active-trips-now.
- **Completed-trip trend** — last 14 days (area chart, from
  `dispatch.trip_completed_at`).
- **Customer ranking** — top customers by completed trips (last 30 days).
- **Driver ranking** — top drivers by completed trips (last 30 days), medaled top 3.
- **Truck utilization** — % of the fleet that ran in the last 30 days (active
  trucks ÷ registered trucks, gensets excluded) + a most-utilized-trucks ranking.
Implemented in the current build as `admin/executive.php` (route `executive`),
server-rendered + ApexCharts, all read-only queries.

---

## 7. Integrations

| Integration | Purpose | Notes |
| --- | --- | --- |
| **Microsoft Entra ID (Azure AD) SSO** | Staff + driver login | OAuth start/callback; links `microsoft_oid` to account. Secrets in git-ignored config. |
| **Web Push (VAPID)** | Notify drivers of jobs/messages | `push_subscription` + `push_send_log`; service worker `sw-driver.js`. |
| **WhatsApp** | Send violation notices | Helper `php/lib/whatsapp.php`. |
| **Map tiles (Leaflet/MapTiler)** | Live maps, GPS display, distance | Driver location + trip mapping. |
| **PhpSpreadsheet** | Excel import/export | Bulk import drivers/units/trailers/trip-rates/customers. |
| **Supabase (PostgreSQL)** | Managed DB | Pooled connection. |
| **Native Android app** | Driver app wrapper (TWA/WebView) | Linked via `.well-known/assetlinks.json`. |

---

## 8. Key business rules to preserve (checklist)

1. **Two identity stores** — staff vs drivers — with distinct login flows.
2. **Segment drives everything** — monitoring views, trip rates, client scoping.
3. **Piece rate is stamped at trip creation**, never recomputed from current
   rates (historical payroll integrity). Preserve the trigger-equivalent.
   Drivers see their **earnings on the dashboard** (current + previous cutoff);
   **only verified trips count**; cutoff cycle is **6→20 / 21→5**.
4. **Active violation ⇒ driver cannot be dispatched.** Scheduled violations
   auto-activate at a timestamp.
5. **Maintenance-blocked units/trailers ⇒ not dispatchable.** Blocks can be
   scheduled and auto-released.
6. **Workflow is an ordered state machine** with an append-only event log and
   per-stage timestamps on the dispatch.
7. **POD requires photo + signature + GPS**; gateless completion is GPS-verified
   and offline-tolerant.
8. **Gate clearance verifies asset+driver match** against the dispatch via QR.
9. **Every auditable action is activity-logged.**
10. **Client portal is strictly scoped** to the user's `customer_code`.

---

## 9. What to fix / drop in the rebuild

Carried from the current build's known issues (see CLAUDE.md §11–12):

- **Secrets in code** → use env/secret manager; never commit DB/SSO/VAPID creds.
- **Inline SQL (`pt_pg_escape`)** → parameterized queries / ORM everywhere.
- **No CSRF, per-page copy-pasted auth** → centralized auth middleware + CSRF.
- **Runtime DDL / scheduler on page load** → migrations + real cron jobs.
- **`admin/` vs `dispatcher/` mirror duplication** → one codebase, role-gated.
- **Per-customer monitoring page copies** → one parameterized monitoring view.
- **Drop Laravel scaffold tables and the unused `users` table.**
- **Add automated tests** for dispatch, booking, payroll rate lookup, workflow
  transitions, container lifecycle.
- **Confusing `*1/*2/*3` file variants and `login.php`/`login-php.php`** → clear
  names/structure.

---

## 10. Suggested module list for the new version

Bookings · Customers & Segments · Fleet (Units/Trailers/Gensets) · Drivers ·
Dispatch & Assignment · Workflow Engine · Container Tracking · Driver App
(PWA/native) · Gate Control · Fuel/Gastender · Maintenance · Shop/Rescue ·
Billing & Receipts · Payroll (piece-rate) · HR/Performance/Violations ·
Incidents/SOS · Coupons/Trip-tickets · Reporting & Analytics · Notifications
(push/WhatsApp/email) · Admin/Users/Settings · Activity Log/Audit.
