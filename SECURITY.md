# Security Findings & Remediation

Security review of the Pantrucks Fleet Management app. Findings are ordered by
severity. Each has evidence (file:line), status, and the fix. Items marked
**✅ Fixed** were addressed in the change that introduced this file; the rest are
tracked follow-ups.

---

## Critical

### 1. Live production DB credentials committed to source — ⚠️ ACTION REQUIRED
The Supabase Postgres DSN, user, and password were hard-coded in
`php/config/config.php` and are in git history.

- **✅ Partial fix:** credentials moved out of `config.php` into a git-ignored
  `php/config/db.php` (loaded at runtime; env vars `PT_DB_DSN`/`PT_DB_USER`/
  `PT_DB_PASS` take precedence). Template: `php/config/db.example.php`.
- **⚠️ Still required — you must do this:** the old password is already in git
  history, so externalizing does not undo the exposure. **Rotate the Supabase
  database password** in the Supabase dashboard, then update `php/config/db.php`
  (or the env var) with the new value. Consider purging the secret from git
  history (e.g. `git filter-repo`) if the history is shared.

---

## High

### 2. Endpoints missing authentication/authorization — IN PROGRESS
Most `table-fetch/`, `php/fetch/`, `php/crud/`, and `php/operations/` endpoints
just `include config.php` and run, with no session/role check. Only ~5 of 33
`table-fetch/` files reference the session. Any anonymous request could read
data or trigger actions.

- **✅ Fix delivered:** centralized guard `php/helpers/auth_guard.php`
  (`require_login()`, `require_role([...])`, with a `'json'` mode for AJAX).
- **✅ Applied to:** `php/crud/add/adduser.php` (was unauthenticated account
  creation + file upload; now `require_role(['Admin','HR-Admin'])`).
- **Follow-up:** roll `require_login()` / `require_role()` into the top of every
  non-public endpoint. Suggested batches: `php/crud/**`, `php/operations/**`,
  `table-fetch/**`, `php/fetch/**`. Verify each role can still reach its own
  pages after adding guards.

### 3. SQL injection via unsanitized `ORDER BY` direction
`table-fetch/violation-table.php` concatenated the raw `order[0][dir]` request
value into `ORDER BY` (PDO/pgsql permits stacked statements).

- **✅ Fixed:** column now whitelisted by index, direction forced to
  `ASC`/`DESC`. All other `table-fetch/*` endpoints already did this correctly.
- **Follow-up:** the `pt_pg_escape()` shim (189 uses / 76 files) is only safe
  inside quoted string literals and gives no protection in numeric/identifier
  contexts. Continue migrating inline SQL to prepared statements; treat
  `pt_pg_escape` as deprecated.

### 4. No CSRF protection — IN PROGRESS
No form or POST handler verified a CSRF token, so state-changing actions can be
forged against a logged-in user.

- **✅ Fix delivered:** `php/helpers/csrf_helper.php` (`csrf_token()`,
  `csrf_field()`, `csrf_verify()`). Wired into `adduser.php`.
- **Follow-up (2 steps, do together per page):**
  1. Emit the token on pages that POST: `<meta name="csrf-token" content="<?= csrf_token() ?>">`
     and set it globally for jQuery: `$.ajaxSetup({headers:{'X-CSRF-Token': $('meta[name=csrf-token]').attr('content')}})`.
  2. Add `csrf_verify()` at the top of each POST handler. Roll out page-by-page
     so a handler is only enforced once its page sends the token.

---

## Medium

### 5. Stored/reflected XSS in table renderers
`table-fetch/booking-table.php` (and siblings) interpolate DB values
(`container`, `costumer`, `status`, …) into HTML without escaping. Combined with
unauthenticated writes (#2), a payload could execute in a dispatcher's browser.

- **Fix:** `htmlspecialchars()` on all values rendered into DataTables cells.

### 6. Database errors echoed to the client
`config.php` printed the raw connection error; `php/crud/add/adduser.php` echoed
`$e->getMessage()` — leaks schema/DSN details.

- **✅ Fixed in config.php** (logs server-side, returns a generic 500).
- **Follow-up:** audit other `echo ... getMessage()` handlers and replace with
  generic messages + `error_log()`.

---

## Notes / lower risk
- File uploads (`adduser`, `save_pod`, etc.) **do** whitelist extensions, which
  blocks PHP-shell uploads — keep that pattern on any new upload endpoint. Upload
  dirs are created `0777`; tighten to `0755` where possible.
- The login flow (`login-php.php`) and driver POD flow (`php/operations/save_pod.php`)
  are good models: parameterized queries, `password_hash`/`password_verify`,
  ownership checks, idempotency. Bring other endpoints up to that bar.

## New shared helpers (use these on new code)
- `php/helpers/auth_guard.php` — `require_login()`, `require_role([...])`.
- `php/helpers/csrf_helper.php` — `csrf_token()`, `csrf_field()`, `csrf_verify()`.
