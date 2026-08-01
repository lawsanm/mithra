# Mithra — Coding Conventions & AI Contract

> **How to use this file:** Paste this entire file (or point your AI tool at it) at the
> start of EVERY AI coding session, regardless of which model you use (Claude, ChatGPT,
> Copilot, Gemini, etc.). Then give your task. This file is the single source of truth.
> If AI output conflicts with this file, this file wins — fix the output before committing.
>
> Tools that auto-read instruction files: copy this file to `CLAUDE.md` / `AGENTS.md` /
> `.github/copilot-instructions.md` in the repo root (keep them identical — edit only
> CONVENTIONS.md and re-copy).

---

## 1. Project summary (context for the AI)

Mithra is a community lending, sharing & caring platform for Sri Lankan GN divisions.
Members lend items to each other using a closed points system (no real money). It is a
second-year university group project: 4 developers, 12 months, 24 CRUD modules.

**Architecture:** server-rendered MVC monolith with a front controller and a service
layer. Every HTTP request goes: `public/index.php` → `Router` → middleware chain
(`Auth` → `Rbac` → `Csrf`) → `Controller` → `Service` (business logic) → `Model`
(thin PDO wrapper) → MySQL, then the controller renders a PHP view built from partials.
A small AJAX surface (~10–15 JSON endpoints) exists for notification polling, handover
status, and photo upload progress. Cron-run CLI scripts in `/scripts/` call Services
directly for scheduled work.

## 2. Hard constraints — NEVER violate these

- **Frontend:** vanilla HTML, CSS, JavaScript ONLY. No frameworks, no libraries, no CDNs,
  no npm packages, no jQuery, no Bootstrap, no Tailwind, no Alpine, no htmx.
- **Backend:** pure PHP 8.1+. No Laravel, no Symfony, no Slim, no Composer *packages*.
  (Composer may be used for PSR-4 autoloading only, if the team confirms; otherwise the
  hand-written SPL autoloader in `/app/autoload.php` is used.)
- **Database:** MySQL 8.x with InnoDB. Access through PDO only. Never `mysqli`, never
  `mysql_*` functions.
- **No new architecture.** Do not introduce ORMs, template engines, event buses,
  dependency-injection containers, or REST API layers. Follow the existing pattern.
- If a task seems to require a library, STOP and say so instead of adding one.

## 3. Folder map — where every file goes

| Path | Contents | Rule |
| --- | --- | --- |
| `/public/index.php` | Front controller | Only bootstrap + dispatch. Never add logic here. |
| `/public/css/main.css` | THE stylesheet | The only CSS file. Use its custom properties. Never create new CSS files or inline `<style>` blocks. |
| `/public/js/` | Vanilla JS | One file per concern (`validate.js`, `polling.js`, `photo-uploader.js`, `calendar.js`). |
| `/public/uploads/` | (production: outside web root) | Never reference directly in HTML; photos are served via the PHP proxy. |
| `/app/Controllers/` | `XxxController.php` | One per feature. Thin — see §6. |
| `/app/Models/` | `Xxx.php`, one per table | Thin PDO wrappers extending `BaseModel`. SQL lives here and nowhere else. |
| `/app/Services/` | `XxxService.php` etc. | All business logic. Plain PHP classes, no `$_SESSION`, no `$_POST`, no HTML. |
| `/app/Middleware/` | `XxxMiddleware.php` | Each has a single `handle()` method. |
| `/views/` | Page templates | One file per controller action, named `feature/action.php`. |
| `/partials/` | Reusable HTML chunks | `header.php`, `nav.php`, `listing-card.php`, etc. |
| `/scripts/` | Cron CLI scripts | Each logs a row to `cron_runs`. Never accessed via HTTP. |
| `/migrations/` | Numbered SQL files | `NNN_verb_noun.sql`, append-only, never edit an applied migration. |
| `/config/` | `config.php` | Git-ignored. Code reads config via `Config::get()`, never `require`s it directly. |
| `/tests/` | PHPUnit tests | Mirrors `/app/` structure: `tests/Services/PointLedgerTest.php`. |

## 4. Naming — exactly these, no variations

| Thing | Convention | Example |
| --- | --- | --- |
| PHP classes & files | `PascalCase`, file name = class name | `BookingController.php` |
| Methods & variables | `camelCase` | `calculateLateFee()`, `$dailyRate` |
| Constants | `UPPER_SNAKE_CASE` | `MAX_GIFT_PER_DAY` |
| DB tables | `snake_case`, plural | `point_ledger`, `condition_photos` |
| DB columns | `snake_case` | `declared_value`, `created_at` |
| Foreign keys | `<singular_table>_id` | `booking_id`, `member_id` |
| URLs / routes | lowercase, hyphenated | `/items/edit`, `/aid-grants/apply` |
| JS files & functions | kebab-case files, `camelCase` functions | `photo-uploader.js`, `uploadPhoto()` |
| CSS classes | kebab-case, component-prefixed | `.listing-card`, `.listing-card__title` |
| View files | `feature/action.php` | `views/bookings/create.php` |
| Migrations | `NNN_verb_noun.sql` | `014_create_point_ledger.sql` |
| Git branches | `memberN/short-feature-name` | `member3/booking-crud` |

Standard column set for every table: `id` (BIGINT UNSIGNED AUTO_INCREMENT PK),
`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, and `updated_at` where rows are mutable.
Ledger and audit tables are append-only: no `updated_at`, no UPDATE, no DELETE — ever.

## 5. PHP style (PSR-12, enforced by CI)

- `<?php` + `declare(strict_types=1);` at the top of every PHP class file.
- 4-space indentation, opening brace on the same line for control structures, next line
  for classes/methods. LF line endings, UTF-8.
- Type-hint every parameter and return type. Use `?Type` for nullable, never untyped.
- One class per file. No closing `?>` tag in pure-PHP files.
- Views/partials are the only files that mix PHP and HTML, and there use the alternative
  syntax: `<?php foreach ($items as $item): ?> … <?php endforeach; ?>` and short echo
  `<?= e($item['name']) ?>`.
- No `global`. No `static` mutable state. No `die()`/`exit()` outside the front
  controller and the photo proxy.

## 6. Layer responsibilities (Single Responsibility in practice)

**Controllers** — translate HTTP to service calls, nothing else. A controller method:
reads validated input, calls ONE service method (or simple model reads for plain CRUD),
then either renders a view or redirects. Hard limits: no SQL, no business rules, no
`points` arithmetic, target ≤ 30 lines per action.

**Services** — all business logic and every multi-step operation. Anything touching the
point ledger, damage flow, aid approval, gifting limits, or trust scores lives here.
Services receive their dependencies (PDO, other services) through the **constructor** —
they never call `new PDO(...)`, never read superglobals, never echo. This is what makes
them unit-testable.

**Models** — one per table, extending `BaseModel`. Only parameterised SQL for that table:
`find`, `findBy`, `create`, `update`, `paginate`, plus purpose-named queries like
`findActiveByDivision()`. No business decisions, no cross-table workflows.

**Views** — display only. May loop, branch on view data, include partials, and call the
escaping helper `e()`. Never query the database, never compute business values.

**The golden rule:** when adding a module, imitate the Items module file-for-file.
If unsure where code goes, ask: "could this line change for a business reason?" → Service.
"Is it SQL?" → Model. "Is it HTML?" → View. "Is it HTTP plumbing?" → Controller.

## 7. Security — non-negotiable on every single page

1. **SQL:** PDO prepared statements with bound parameters, always. Never interpolate or
   concatenate ANY variable into SQL — including "safe" ones like sort columns (whitelist
   those instead).
2. **Output:** every dynamic value printed into HTML goes through
   `e($value)` (wrapper for `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`). No exceptions,
   including values that "came from us".
3. **CSRF:** every `<form>` includes `<?= csrf_field() ?>`; every state-changing request
   (POST) is verified by `CsrfMiddleware`. GET requests never change state.
4. **Auth/RBAC:** never check roles inside a controller with ad-hoc ifs; declare the
   required role in the route registration so `RbacMiddleware` enforces it.
5. **Uploads:** validate with `finfo` MIME check (never trust extension or
   `$_FILES['type']`), re-encode through GD, store outside the web root with a generated
   name, serve via the PHP proxy with an access check.
6. **Passwords:** `password_hash()` / `password_verify()` with default cost. Never store
   or log plaintext.
7. **Sessions:** regenerate the session ID on login; store only the member id and role.
8. **Money-like data:** points are INTEGER columns. Never float arithmetic on points.

## 8. Robustness & error handling

- **Validate at the boundary.** Every controller action validates input via the shared
  `Validator` before calling a service: required, type, length, range, enum. On failure,
  re-render the form with per-field errors and the user's previous input — never a blank
  page, never a raw exception.
- **Trust nothing from the client**, including hidden fields, IDs in URLs (always check
  ownership: does this booking belong to the logged-in member?), and select options.
- **Transactions:** every operation that writes more than one row (and EVERY point
  movement) runs inside a single PDO transaction. Wallet rows are locked with
  `SELECT ... FOR UPDATE` before balance checks. Commit last; any exception → rollback.
- **Exceptions over error codes.** Services throw domain exceptions
  (`InsufficientPointsException`, `BookingConflictException`); controllers catch them and
  show a friendly message. The front controller has a top-level handler: log the real
  error with `error_log()`, show a generic 500 page. `display_errors=Off` outside dev.
- **Fail closed.** If an auth, CSRF, ownership, or invariant check cannot be completed,
  deny the action.
- **Cron scripts** are idempotent (safe to re-run), log start/end/outcome to `cron_runs`,
  and exit non-zero on failure.

## 9. Scalability habits (cheap now, valuable later)

- Add an index for every foreign key and every column used in WHERE/ORDER BY of a listed
  query. Declare indexes in the migration that creates the table.
- Never `SELECT *` in production code paths; name the columns.
- Every listing page paginates (`?page=N`, default 20 rows) — no unbounded result sets.
- No queries inside loops (N+1). Fetch related rows with one JOIN or one `IN (...)` query.
- Wallet balances are cached columns, verified nightly by the invariant cron — reads are
  O(1), correctness is guaranteed by the append-only ledger.
- Keep the polling endpoint's query trivial (indexed `WHERE member_id = ? AND read_at IS
  NULL LIMIT 1`) since it fires every 8 seconds per user.

## 10. Design principles — how the AI must apply them

- **SRP:** one reason to change per class. If a class name needs "And", split it.
- **OCP:** extend by adding (a new middleware in the chain, a new state in the damage
  state machine table, a new cron script) rather than modifying frozen core plumbing.
- **LSP:** every Model must be usable wherever `BaseModel` is expected — same method
  signatures, same return shapes; never throw from an override where the base succeeds.
- **ISP:** keep contracts minimal — `MiddlewareInterface` has exactly one method
  `handle(Request $r, callable $next)`. Don't force classes to implement methods they
  don't need.
- **DIP:** high-level code depends on abstractions passed in, not concretions created
  inside. Constructor-inject PDO and services. `new` appears in the front controller /
  bootstrap, not scattered through business logic.
- **DRY:** before writing anything, check whether it exists: `e()`, `csrf_field()`,
  `Validator`, `BaseModel` CRUD, partials, CSS custom properties. Duplicate HTML → make a
  partial. Duplicate SQL → add a model method. Duplicate logic → move to a service.
  (But: don't merge two things that merely look similar today — duplication is cheaper
  than the wrong abstraction.)
- **KISS:** the boring solution wins. Full page reloads over AJAX, polling over
  websockets, plain PHP includes over a template engine, one MySQL database over anything
  exotic. If the AI proposes a "clever" pattern (registry, observer, generic repository
  factory), reject it — write the simple version.

See `DESIGN_PRINCIPLES.md` for the full justification of each decision (used in the
report and viva).

## 11. Frontend rules

- Semantic HTML5 (`<main>`, `<nav>`, `<form>`, `<label for>` on every input, `alt` on
  every image). Forms work without JavaScript; JS only enhances (client-side validation
  duplicates, never replaces, server-side validation).
- All colours, spacing, and font sizes come from the custom properties in `main.css`
  (`var(--color-primary)`, `var(--space-2)` …). Hard-coded hex values or px paddings in
  page markup are a review-rejection.
- JS: `const`/`let` only, `fetch` + `async/await` for the AJAX surface,
  `addEventListener` (no `onclick=` attributes), strict mode. No `innerHTML` with
  user-derived data — use `textContent` or `createElement`.

## 12. Git workflow

- `main` is protected: PRs only, 1 approving review + green CI required.
- Branch per task: `memberN/short-feature-name`. Small PRs — one module or sub-feature.
- Commit messages: `type: short description` where type ∈
  `feat | fix | refactor | test | docs | chore | migration`.
  Example: `feat: booking creation with conflict check`.
- Never commit: `config/config.php`, `/public/uploads/` contents, editor folders,
  AI chat logs. (All in `.gitignore`.)
- A module is DONE only when: code follows this file, CI green (lint + PHPStan + tests),
  service-layer tests exist for any logic touching points, PR checklist ticked, reviewed
  by another member.

## 13. Prompt preamble (copy-paste before every AI task)

```text
You are contributing to Mithra, a pure-PHP 8.1 / vanilla JS / MySQL university project.
Follow the attached CONVENTIONS.md strictly: front-controller MVC + service layer;
PSR-12; controllers thin; business logic only in /app/Services; SQL only in /app/Models
via PDO prepared statements; every output escaped with e(); csrf_field() in every form;
no frameworks, no libraries, no packages. Imitate the existing Items module structure
exactly. Ask before deviating. Task: <describe the task, list the relevant existing
files, and paste the golden-module files it should imitate>.
```

---
*Version 1.0 — agreed by all 4 members on ____-__-__. Changes require team consensus and
a version bump.*
