# Design — New Version (Stack, Architecture, Structure, UI/UX)

Opinionated design recommendations for building the new Pantrucks Fleet system.
Covers **tech stack**, **technical architecture**, **project structure**, and
**UI/UX**. Written to fix the current build's specific problems (denormalized
data, name-string coupling, duplicated pages, no tests, secrets in code, page-
load schedulers) while keeping its strengths (offline driver queue, piece-rate
integrity, thorough dispatch guards, E-Pantrucks data sharing).

---

## 1. Tech stack (recommendation) — no Laravel

**Primary recommendation: CodeIgniter 4 (PHP 8.3) + PostgreSQL + a Vue 3 (Vite)
frontend + Tailwind, with a Vue PWA driver app (Capacitor for native).**
Bolt on framework-agnostic **Symfony components** (Workflow, Messenger, Console)
where CI4 has no first-class equivalent.

Why this fits *this* project specifically (and why not Laravel):
- **Stay in PHP** — the team writes PHP and **E-Pantrucks is frameworkless PHP**
  on the **same Postgres/Supabase** DB. CodeIgniter 4 is a **light MVC** with a
  gentle learning curve for a procedural-PHP team: it adds structure (routing,
  controllers, migrations, query builder, validation, built-in CSRF) **without
  Laravel's heavy conventions/magic**. Lower ceremony, easy to reason about
  alongside E-Pantrucks.
- **Query Builder + prepared statements by default** eliminate the
  `pt_pg_escape` inline-SQL / SQL-injection surface.
- **Built-in migrations** replace the page-load "ensure schema" DDL.
- **Built-in CSRF + filters (middleware)** give the centralized auth/CSRF the
  current build lacks.
- **Cron + a queue worker** (Symfony Messenger, or a simple DB-backed queue)
  handle driver offline-replay, the E-Pantrucks sync outbox, push/WhatsApp, and
  maintenance/violation auto-activation — off the request path.

| Layer | Recommended (no Laravel) | Alternatives (still no Laravel) |
| --- | --- | --- |
| Backend | **CodeIgniter 4 (PHP 8.3)** — light MVC | **Slim 4 + components** (micro, most control); **Symfony** (heavier, more built-in); Node/NestJS or Go if you accept a language change |
| DB access | **CI4 Query Builder** (+ raw for reports) | **Doctrine DBAL** query builder (framework-agnostic) |
| DB | **PostgreSQL (Supabase)** — keep it | — |
| Web frontend | **Vue 3 SPA (Vite) + Tailwind**, talking to a REST API | **Twig + Alpine.js / htmx** (server-rendered, lighter, closest to today) |
| Driver app | **Vue 3 + Vite PWA**, wrapped with **Capacitor** for native | React Native / Flutter (full native rewrite) |
| Maps | **MapLibre GL / Leaflet** (open) | MapTiler / Google (paid) |
| Auth | **CI4 Shield** (sessions + API tokens) + **league/oauth2-client** (Azure) for Microsoft SSO | firebase/php-jwt for tokens; custom SSO |
| RBAC | **CI4 Shield groups/permissions** | CI4 filters + a `require_role()` policy layer |
| Workflow | **symfony/workflow** (standalone state machine) | custom enum + transition guard table |
| Queues/jobs | **symfony/messenger** worker via Supervisor | DB-backed queue table + cron worker |
| Scheduler | **system cron** → CI4 **spark** CLI commands | any cron runner |
| PDF/Excel | **PhpSpreadsheet** (keep) + **dompdf** | mpdf |
| Realtime | **Supabase Realtime** (already on Supabase) for live dispatch/GPS | Mercure, Soketi/Pusher, or polling |
| Tests | **PHPUnit** + **Playwright** (E2E) | — |

> **Why CI4 over Laravel or Slim?** Laravel is ruled out by request. **Slim 4**
> is an excellent lighter choice if you want maximum control and minimal
> framework — pick it if the team prefers assembling their own components; the
> architecture in §2 is identical either way. **CI4** is the middle ground:
> batteries for the things you need (migrations, validation, CSRF, auth via
> Shield) without a large convention surface. Either keeps you in one language
> with E-Pantrucks. **Capacitor** (not a bare WebView) is the pragmatic native
> path — one Vue codebase, real camera/GPS/background access, store packaging.

---

## 2. Technical architecture

**Shape: a modular monolith** — not microservices. The team and domain are one
cohesive operation; a well-structured monolith is faster to build, easier to
run, and can be split later if ever needed.

### Layers (dependencies point inward)
```
┌─────────────────────────────────────────────────────────────┐
│ Interface:  Web (Inertia/Vue)  ·  Driver API  ·  E-Pantrucks │
│             controllers, form requests, API resources        │
├─────────────────────────────────────────────────────────────┤
│ Application: use-case services — AssignDispatch,             │
│   EligibilityService (canAssign), WorkflowStateMachine,      │
│   CompleteTrip, ReserveBookingCapacity, PushToEpantrucks     │
├─────────────────────────────────────────────────────────────┤
│ Domain:      entities + rules — Booking, Dispatch, Trip,     │
│   Unit, Trailer, Driver, PieceRate, Violation, Maintenance   │
├─────────────────────────────────────────────────────────────┤
│ Infrastructure: Eloquent repos, queues, integrations         │
│   (MS SSO, WebPush, WhatsApp, E-Pantrucks views/API, maps)   │
└─────────────────────────────────────────────────────────────┘
```

### Key architectural decisions (each fixes a current problem)
1. **One codebase, role-gated** — no more `admin/` vs `dispatcher/` mirror
   files. A single set of pages/components; RBAC middleware + policies decide
   access. (Fixes the ~51 duplicated pages.)
2. **Centralized eligibility service** — `canAssign(driver, unit, trailer,
   genset, booking)` used by every assignment path (standard/CTH/DnD/service).
   (Fixes duplicated guard logic.)
3. **Explicit workflow state machine** — allowed transitions enforced in one
   place; illegal jumps rejected; every transition writes a `workflow_event`.
   (Fixes transitions scattered across endpoints.)
4. **Outbox + queue for integrations** — E-Pantrucks sync, push, WhatsApp, and
   driver offline-replay all go through jobs with retry + idempotency keys.
5. **Cron worker, not page-load cron** — maintenance auto-release and violation
   auto-activation run as CLI commands (CI4 `spark` tasks) invoked by system
   cron, not on every request. (Fixes DDL/scheduler on the hot path.)
6. **Secrets in `.env` / secret store** — never in code. Rotate the shared DB
   password; give E-Pantrucks a **read-only role scoped to compatibility views**
   (see Part III). (Fixes committed credentials.)
7. **Normalized schema with real FKs + constraints** (Part II), with legacy
   `dispatch`/`trips` **views** so E-Pantrucks keeps working.
8. **API versioning** — `/api/v1/*` for the driver app and any E-Pantrucks
   endpoint, so mobile clients don't break on server changes.

### Cross-cutting
- **Auth:** unified users table + roles (CI4 Shield); session for the web
  console, API tokens for the driver app; Azure SSO via `league/oauth2-client`.
  Every request authorized by a filter/policy, not copy-pasted checks.
- **Audit:** a central write path (model events / a repository decorator) writes
  the activity log (replaces the manual `pt_log_activity` calls everywhere).
- **Offline (driver):** keep the proven **IndexedDB outbox + idempotency-key
  replay** pattern; server endpoints stay idempotent.

---

## 3. Project / folder structure

Domain-oriented layout (CI4-flavored, but the shape works for Slim/plain PHP
too — the point is one folder per business area, not the framework):

```
app/
  Domains/
    Booking/        (Models, Services, Controllers, Requests/Validation, Policies)
    Dispatch/       (AssignDispatch, EligibilityService, WorkflowStateMachine…)
    Fleet/          (Unit, Trailer, Genset, Maintenance)
    Driver/         (profile, shift, checklist, captures, earnings)
    Container/      (tracking, monitoring, pickup/POD/gateless)
    Fuel/           Gate/  Shop/  Incident/  Payroll/  HR/  Coupon/
    Integration/    (Epantrucks/, Notifications/{Push,WhatsApp})
  Filters/          (auth, RBAC, CSRF — applied centrally, not per page)
  Commands/         (spark CLI: cron tasks, queue worker, migrations)
  Support/          (shared helpers, base classes)
  Config/           (Routes.php, etc.)
database/  (or app/Database/)
  Migrations/       (versioned, additive — the authoritative schema)
  Seeds/            (roles, segments, workflow_stages, activities, trip_rates)
  views/            (legacy-compat dispatch/trips SQL views for E-Pantrucks)
public/             (web root: index.php front controller + built assets)
frontend/           (Vite build)
  web/              (Vue 3 SPA for staff: dashboards, dispatch board…)
  driver/           (the PWA: Vue app, service worker, offline queue)
  components/        (shared design-system components)
  css/              (Tailwind config + tokens)
tests/   Unit/  Feature/  Browser/(Playwright)
driver-native/      (Capacitor wrapper around frontend/driver)
```

Rules: one module owns its tables/models/logic; shared UI lives in
`components/`; auth/CSRF/RBAC live in `Filters/` (applied once, centrally);
**no `*1/*2/*3` variants** — a change lives in one place.
*(If you choose Twig + Alpine/htmx instead of a Vue SPA, replace `frontend/web`
with a `templates/` server-rendered views folder; everything else is the same.)*

---

## 4. UI/UX design

### Design language — modern, clean, "operations SaaS"
Target a contemporary product look (think Linear / Vercel / modern logistics
dashboards): calm, high-contrast, content-first, no skeuomorphic gradients or
heavy chrome. The current Bootstrap look reads dated — this is a deliberate
break from it.

**Visual direction**
- **Clean & minimal, data-dense but airy** — generous whitespace, clear
  hierarchy, one accent color used sparingly for primary actions/active state.
- **Flat surfaces with soft depth** — subtle shadows and hairline borders, not
  heavy cards; rounded corners (8–12px) for a friendly-but-professional feel.
- **Dark mode as a first-class theme** (not an afterthought) — many dispatch/ops
  rooms run dark; drivers use phones at night.
- **Purposeful motion** — 150–250ms transitions, subtle hover/press states,
  skeleton loaders instead of spinners, optimistic UI. Motion communicates, it
  doesn't decorate.

**Design tokens (starting point)**
- **Type:** `Inter` (or `Geist`) for UI, a tabular/mono face for numbers
  (rates, km, ₱) so columns align. Clear scale (12/14/16/20/24/32).
- **Color:** neutral gray foundation + **one brand accent**; semantic status
  colors mapped to workflow stages (assigned / en-route / delivered / verified /
  blocked) and to health (ok / warning / danger). Define as CSS variables so
  light/dark just swap the token values.
- **Space & radius:** 4px spacing scale; radius tokens (sm 6 / md 8 / lg 12 /
  full). **Shadow:** 2–3 soft elevation levels only.
- **Density toggle:** comfortable vs compact — dispatchers want compact tables.

**System & stack**
- **Tailwind + a headless/modern component library** (**shadcn-vue** or
  **PrimeVue unstyled**) so components inherit *your* tokens, not a generic
  theme. All tokens centralized; **no per-page styling**.
- **Modern UX patterns** to include: a **command palette** (⌘K) for power users,
  keyboard navigation on the dispatch board, global search, toast notifications,
  slide-over panels (not full-page reloads) for detail/edit, empty-state and
  loading-state designs for every list.
- **One app, three surfaces** on the same system: **staff web console**
  (role-gated nav), **driver mobile app** (PWA/native), and **role dashboards**.
- **Mobile-first for drivers, desktop-first for office**; PH context (₱, English,
  PH dates). Accessibility (WCAG AA) baked into the tokens (contrast) and
  components (focus rings, labels).

### Staff web console
- **Persistent left sidebar + top bar**, navigation filtered by role (kills the
  duplicated per-role page sets). Global search + notification bell.
- **Dispatch board:** the tile/kanban board with **drag-and-drop assign**, live
  status colors per workflow stage, and inline eligibility feedback (why a
  driver/unit is blocked). This is the app's centerpiece — make it fast and
  real-time (websockets).
- **Container tracking:** map + list, pickup → on-trip → delivered, live GPS
  pins; segment filter (ABC/DOLE/SUMI/…) instead of separate pages.
- **Dashboards:** KPI cards (active trips, utilization, deadhead ratio,
  pending verifications, maintenance-blocked units), charts, and worklists
  (stuck assignments, verification backlog) — turn the SLA queues (Part IV) into
  visible tiles.
- **Data tables:** server-side pagination, filters, export — consistent
  component, not per-page reinventions.

### Driver app (the highest-stakes UX)
- **Big touch targets, minimal typing, high contrast** (readable in sunlight,
  usable with gloves). One primary action per screen.
- **Job cards:** clear Accept / Decline, trip details, one-tap navigation.
- **Guided capture flows:** pre-departure checklist, **pickup** and **POD**
  (camera-first, signature, auto GPS) — the camera opens directly; no manual
  location typing.
- **Offline-first, visible:** keep the **offline pill** ("N waiting to sync"),
  optimistic UI, and background replay. The driver must always know their work
  is saved.
- **Earnings front-and-center:** dashboard shows a prominent **total** for the
  cutoff, broken into **Earned (verified) ₱** and **Pending verification ₱**
  (Part IV §3.4), plus **previous-cutoff** and **year-to-date** totals for
  context (cutoffs 6→20 / 21→5), with a tap-through to the breakdown.
- **Earnings projection:** forecast end-of-period pay from the driver's current
  pace (trips × piece-rate ÷ days elapsed × days remaining), toggled `By cutoff`
  or `By month`. Show it clearly labelled an **estimate**, with a progress bar from
  "now" to "projected". Turns the pay cycle into a visible goal. (Server computes
  it from the same verified-trip earnings so it stays honest.)
- **Leaderboard — opt-in, first name + rank:** a **Top 10 earners** list for the
  cutoff, shown as **first name + rank only** (never full names or ID numbers),
  and **opt-in** — a driver who doesn't join isn't listed. If an opted-in driver
  is outside the top 10, **pin their own row** below with their rank and the gap
  to break in (e.g. "₱4,510 to reach top 10"); a driver always sees their own
  standing even when others can't. Store the opt-in flag per driver; rank by the
  same verified-earnings figure; **refresh on a schedule, not per request**, to
  keep DB egress low (see the Supabase egress note).
- **Push notifications** for new jobs, messages, and verification results.

### Quality bars
- **Accessibility** (WCAG AA contrast, focus states, labels), **responsive**
  (no horizontal scroll on mobile), **performance budget** for the driver app on
  a low-end phone and a 3G connection, and **theme-aware** (light/dark).

---

## 5. Build order (how the design phases in)

1. **Foundation:** CI4 (or Slim) project + normalized schema (migrations) +
   compat views + auth/RBAC (Shield/filters) + design-system shell.
2. **Core spine:** Booking → Dispatch (eligibility service + state machine) →
   Trip completion; the dispatch board and driver job flow.
3. **Driver app:** PWA with capture flows + offline queue + earnings.
4. **Money & compliance:** piece-rate, payroll, verification, billing handoff to
   E-Pantrucks (views/API).
5. **Supporting modules:** fuel, maintenance, shop/rescue, incidents, monitoring,
   analytics; then the dormant **gate** module when it goes live.
6. **Hardening:** tests (Pest + Playwright), secrets rotation, performance,
   accessibility pass.

> The design intent throughout: **normalize the data, centralize the rules,
> unify the UI, and make the driver app fast and offline-proof** — the same four
> themes as the rest of this guide, expressed as concrete stack and structure
> choices.
