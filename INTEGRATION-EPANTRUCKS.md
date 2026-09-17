# Fleet → E-Pantrucks Integration Plan

How the new Fleet Management version feeds trip / waybill / trip-receipt data
into **E-Pantrucks** (`C:\xampp\htdocs\E-Pantrucks`) so encoders don't re-key it.

> **This was researched against the real E-Pantrucks code** — not assumed. Key
> finding below changes everything: **the integration already exists.**

---

## 0. The critical finding: they already share one database

E-Pantrucks already reads Fleet's data **today**. Its
`php/config/dispatch_config.php` connects to a "dispatch DB" whose credentials
are **identical** to the current Fleet system's
[php/config/config.php](php/config/config.php):

```
host aws-1-ap-southeast-1.pooler.supabase.com : 5432 / postgres
user postgres.ciuahgmwkenknrcccwvq
```

**It is the same Supabase database.** So E-Pantrucks is already wired to Fleet:

- E-Pantrucks is a server-rendered **PHP** app (React/Vite in `src/` is dead
  scaffolding). Encoders type waybills ("RV" records) into a very wide
  `operations` table (150 cols); billing reads from it.
- **E-Pantrucks is master** for drivers, units (trucks), trailer, location,
  rates (`trip_rates`, `rate_lane`, formula/fuel-priced) — confirmed.
- When an encoder types a **trip receipt / waybill number**, E-Pantrucks calls
  `php/fetch/lookup_trip_receipt.php`, which **queries the Fleet DB** and
  auto-fills the entry form. This is the existing "Fleet → E-Pantrucks" data
  path, pulled on demand by the encoder.

### The exact contract E-Pantrucks depends on
`lookup_trip_receipt.php` runs this against the Fleet DB (verbatim shape):

```sql
SELECT d.d_id, d.d_tripreceipt, d.d_ecs, d.workflow_stage, d.trip_completed_at,
       d.d_drivername, d.d_truck, d.d_trailer, d.d_genset, d.costumer,
       d.booking_no, d.d_origin,
       t.trip_from, t.trip_to, t.return_location, t.trip_container, t.trip_status,
       t.trip_departuredatetime, t.trip_arrivaldatetime, t.km_run
FROM dispatch d LEFT JOIN trips t ON t.d_id = d.d_id
WHERE TRIM(d.d_tripreceipt) = ?          -- join key = trip receipt / waybill no.
ORDER BY d.d_id DESC LIMIT 1
```

It then keeps a driver/truck/trailer/genset/customer value **only if it also
exists in E-Pantrucks' own master tables** (matched by name string:
`units.unit_name`, gensets `unit_name LIKE 'GS%'`, `trailer.trailer_name`,
`location.location_name`), else it blanks that field. Completion is derived from
`workflow_stage = 'pod_captured'` or a non-empty `trip_completed_at`.

**Implication:** the tables `dispatch` + `trips`, the columns above, the
`d_tripreceipt` join key, and the **name-string master matching** are a live
integration contract. The normalized rebuild (see [DATA-MODEL.md](DATA-MODEL.md))
renames/relFKs all of these — which **would break E-Pantrucks' auto-fill** unless
we preserve the contract.

---

## 1. Strategy — preserve the contract, decouple the coupling

The relationship is sound; the *mechanism* (E-Pantrucks reaching straight into
Fleet's raw tables with shared admin credentials) is the liability. Two ways to
keep the feature working after the rebuild:

### Option A — Compatibility views (recommended, least effort, zero E-Pantrucks change)
In the new normalized Fleet DB, create **read-only SQL views named `dispatch`
and `trips`** exposing exactly the legacy columns E-Pantrucks selects, mapped
from the normalized tables. E-Pantrucks' query keeps working unchanged.

```sql
-- Example: legacy-shaped view over the normalized model
CREATE VIEW dispatch AS
SELECT dp.id                         AS d_id,
       dp.dispatch_ref               AS d_tripreceipt,   -- or the real TR column
       dp.ecs                        AS d_ecs,
       ws.code                       AS workflow_stage,
       dp.trip_completed_at,
       drv.fname || ' ' || drv.lname AS d_drivername,
       u.code                        AS d_truck,
       tr.code                       AS d_trailer,
       gs.code                       AS d_genset,
       cust.name                     AS costumer,
       b.booking_no,
       origin_loc.name               AS d_origin
FROM dispatch_new dp
JOIN driver drv        ON drv.id = dp.driver_id
LEFT JOIN unit u       ON u.id   = dp.unit_id
LEFT JOIN trailer tr   ON tr.id  = dp.trailer_id
LEFT JOIN unit gs      ON gs.id  = dp.genset_unit_id
LEFT JOIN booking b    ON b.id   = dp.booking_id
LEFT JOIN customer cust ON cust.id = b.customer_id
LEFT JOIN workflow_stage ws ON ws.id = dp.workflow_stage_id
LEFT JOIN location origin_loc ON origin_loc.id = b.from_location_id;
-- similar CREATE VIEW trips AS SELECT ... AS trip_from, ... AS km_run ...
```
- **Pros:** E-Pantrucks needs **no code change**; the new schema stays clean
  behind the view; you can later give E-Pantrucks a **read-only DB role** that
  can see only these views (fixes the shared-admin-credentials risk).
- **Cons:** the view must keep matching E-Pantrucks' expected columns; add a
  regression check.

### Option B — Lookup API (cleaner long-term, needs a small E-Pantrucks edit)
Expose an endpoint on the new Fleet system, e.g.
`GET /api/waybill-lookup?tr=<no.>`, returning the same `record` JSON shape
E-Pantrucks already builds. Then change **one file** on the E-Pantrucks side —
`php/fetch/lookup_trip_receipt.php` — to call the API instead of querying the DB
directly, and drop `dispatch_config.php`.
- **Pros:** no shared DB at all; Fleet owns its schema entirely; auth via API
  key; you can log/rate-limit access.
- **Cons:** requires editing E-Pantrucks and standing up an API.

**Recommendation:** ship **Option A** at cutover (keeps E-Pantrucks working with
zero risk), then migrate to **Option B** when you want to sever the direct DB
coupling. They compose: the view can back the API too.

---

## 2. Master-data alignment (still required either way)

E-Pantrucks matches Fleet values to its own masters **by name string** and
blanks anything unknown. So the auto-fill silently drops fields when names
diverge. To make it reliable:

- Keep truck/genset/trailer/location/customer **names identical** across both
  systems, **or**
- Add E-Pantrucks external-id columns in the new Fleet model
  (`epantrucks_unit_id`, `…_trailer_id`, `…_location_id`, `…_customer_id`) and
  have the compatibility view/API emit the E-Pantrucks-canonical name so matches
  never miss. Since **E-Pantrucks is master**, Fleet should sync these names/ids
  **from** E-Pantrucks (one-way), never overwrite them.
- Gensets follow the `GS%` naming convention E-Pantrucks keys on — preserve it
  or map explicitly.

---

## 3. Optional Phase 2 — push full waybills (reduce encoder work further)

The on-demand auto-fill already removes most re-keying (encoder types the TR
number, the form fills). If you want the waybill to appear in E-Pantrucks with
**no encoder action at all**, two routes exist on the E-Pantrucks side:

1. **Bulk Excel import** — E-Pantrucks has `php/insert/import_entries.php` +
   `php/fetch/download_import_template.php`. Fleet can export completed trips in
   that template so an encoder imports a batch instead of typing each. **No
   E-Pantrucks code change.**
2. **Direct insert into `operations`** — heaviest; E-Pantrucks validates trips
   hard on save (locations against master, waybill-duplicate checks, date
   normalization, per-type required fields). Only pursue if full hands-off
   posting is required, and go through E-Pantrucks' own validation helpers, not
   raw INSERTs.

Keep Phase 2 optional — Phase 1 (working auto-fill after the rebuild) is the
must-have; hands-off posting is a nice-to-have.

---

## 4. Security must-fix (applies to both systems now)

- The **same hardcoded admin DB credentials** live in *both* apps'
  config files and in git. E-Pantrucks currently has **full read/write** to the
  Fleet DB. In the rebuild: give E-Pantrucks a **read-only role limited to the
  compatibility views** (Option A) or **no DB access at all** (Option B), and
  move every credential to env/secret storage. Rotate the shared password.

---

## 5. Rollout

| Phase | Deliverable | E-Pantrucks change? |
| --- | --- | --- |
| **1a. Preserve contract** | `dispatch` + `trips` compatibility views on the new DB matching `lookup_trip_receipt.php`'s columns (§1A) | None |
| **1b. Lock down access** | Read-only DB role scoped to those views; rotate/relocate creds (§4) | None |
| **2. Name/id alignment** | Sync E-Pantrucks master names/ids into Fleet so auto-fill never misses (§2) | None |
| **3. (Optional) API cutover** | `/api/waybill-lookup` + point E-Pantrucks' one file at it; drop shared DB (§1B) | 1 file |
| **4. (Optional) Hands-off posting** | Export to E-Pantrucks' import template, or validated `operations` insert (§3) | None / medium |

---

## 6. Answers this research settled (vs. the earlier open questions)

- **Does E-Pantrucks have an ingestion path?** Yes — it already **pulls** from
  the Fleet DB on trip-receipt lookup, and separately supports **Excel import**.
- **What is the join key / "waybill"?** `dispatch.d_tripreceipt` (the trip
  receipt number), one dispatch (its latest trip leg) per lookup.
- **Direction & master:** confirmed — Fleet supplies operational trip data;
  E-Pantrucks is master for drivers/units/trailer/location/rates and consumes
  Fleet data by name-string matching.

**Still worth confirming with you:**
1. In the new schema, which column is the **canonical trip-receipt number** the
   view should expose as `d_tripreceipt`? (Today it's `dispatch.d_tripreceipt`.)
2. Do you want to **keep the shared-DB view approach** (Option A) or move to an
   **API** (Option B) at rebuild time?
3. Is **hands-off posting** (Phase 4) in scope, or is encoder-triggered
   auto-fill enough?

---

## 7. Priority field mapping — truck, driver, trailer, container, genset, locations

You called out these six as the important ones. Here is exactly how each maps
today, and — importantly — **which the current auto-fill already fills vs. which
the encoder still types by hand** (traced through E-Pantrucks' entry pages,
which consume more `record.*` keys than `lookup_trip_receipt.php` returns).

| # | E-Pantrucks entry field(s) | Auto-fill `record` key | Fleet source (current DB) | New-model source | Status |
| --- | --- | --- | --- | --- | --- |
| **Truck** | `truck` | `record.truck` | `dispatch.d_truck` (matched to `units.unit_name`) | `unit.code` via `dispatch.unit_id` | ✅ filled |
| | `truck2` (return leg) | `record.truck2` | — | `unit.code` (return leg) | ⚠️ **gap — hand-keyed** |
| **Driver** | `driver` (name) | `record.driver` | `dispatch.d_drivername` | `driver.fname+lname` via `driver_id` | ✅ filled |
| | `driver_idNumber` | `record.driver_idNumber` | — (available as `drivers.driver_idnumber`) | `driver.id_number` | ⚠️ **gap — hand-keyed** |
| | `driver_return` (+ id) | `record.driver_return`, `…_idNumber` | — | return-leg driver | ⚠️ **gap** (only if Fleet tracks a return driver) |
| **Trailer** | `trailer` | `record.trailer` | `dispatch.d_trailer` (matched to `trailer.trailer_name`) | `trailer.code` via `trailer_id` | ✅ filled |
| **Container** | `van_name`/`van_alpha`/`van_number` (or `container`) | `record.container` + `_alpha` + `_number` | `trips.trip_container` | `container.container_no` via `trip.container_id` | ✅ filled (split into alpha/number) |
| **Genset** | `genset` | `record.genset` | `dispatch.d_genset` (matched to `units.unit_name LIKE 'GS%'`) | `unit.code` (type=genset) via `genset_unit_id` | ✅ filled |
| | `genset_hr_meter_start` / `_end` | `record.genset_hr_meter_start`/`_end` | — (relatable via `driver_shift.machine_hours` / `fuel_report` hubo) | `driver_shift.machine_hours` or a genset reading | ⚠️ **gap — hand-keyed** |
| **Locations — pickup = pullout** | `empty_pullout_location` / `deliver_from` | `record.deliver_from`, `record.origin` | Fleet **pickup** location: `trips.trip_from` / `dispatch.d_origin` (and the actual pickup point in `pickup_capture`) | `location.name` via `from_location_id` (pickup) | ✅ filled |
| | `pullout_location_arrival/departure_date/time` | — | Fleet pickup timestamps (`pickup_capture.captured_at`; `trips.trip_departuredatetime`) | pickup capture / trip times | ⚠️ **partly hand-keyed** |
| | `ph` / `delivered_to` (delivery location) | `record.ph`, `record.deliver_to` | `trips.trip_to` (matched to `location.location_name`) | `location.name` via `to_location_id` | ✅ filled |
| | `return_location` | `record.return_location` | `trips.return_location` | `location.name` via `return_location_id` | ✅ filled |
| | `customer` | `record.customer` | `dispatch.costumer` (matched to `location.location_name`) | `customer.name` / `location.name` | ✅ filled |

### What this tells us to do
1. **Preserve the ✅ fields** in the compatibility view/API (Option A/B, §1) —
   these already save the encoder work and must keep working after the rebuild.
2. **Close the ⚠️ gaps** — the highest-value improvement for your six priority
   fields is to **extend the lookup contract** to also return:
   - `driver_idNumber` (from `drivers.driver_idnumber` via `driver_id`) — cheap,
     Fleet already has it; stops the encoder retyping every driver's ID.
   - `genset_hr_meter_start`/`_end` — if Fleet captures genset hour readings
     (via `driver_shift.machine_hours` or a genset reading), surface them.
   - Return-leg `truck2` / `driver_return` — only if Fleet models a return
     driver/truck; otherwise leave for manual entry.
   - **Pullout (= pickup) timestamps** — Fleet's `pickup_capture` records the
     real pickup event with `captured_at` + GPS. Surface it so E-Pantrucks'
     `pullout_location_arrival/departure_date/time` prefill from the actual
     pickup, instead of the encoder typing them. (Terminology: **Fleet
     "pickup" = E-Pantrucks "pullout location"** — the location field itself
     already maps to `trips.trip_from`.)
3. **Guarantee master-name matches** (§2): truck/genset/trailer/location/customer
   values only auto-fill if the **name exists in E-Pantrucks' masters**. Keeping
   names identical (or emitting the E-Pantrucks-canonical name from the view) is
   what makes these six fields reliably populate instead of silently blanking.

> Net: for the fields you care about, **truck, driver name, trailer, container,
> genset name, and all locations already auto-fill** — the rebuild just has to
> keep the contract (§1) and name alignment (§2). The extra wins are **driver ID
> number** and **genset hour meters**, which are easy to add to the lookup.
