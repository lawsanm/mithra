# Mithra — Design Principles & Justification

This document maps the assessment criteria — **SOLID, DRY, KISS, maintainability,
scalability, robustness** — onto the concrete design decisions in Mithra. Use it three
ways:

1. **Feed it to your AI** together with `CONVENTIONS.md` so generated code embodies the
   principles, not just the style.
2. **Quote it in the report** — each section ends with a ready justification sentence.
3. **Revise from it before the viva** — every claim points at real files in the repo.

---

## 1. SOLID

### 1.1 Single Responsibility Principle (SRP)

*A class should have exactly one reason to change.*

**Where Mithra applies it**

- The request pipeline splits four responsibilities into four layers: `Controllers/`
  (HTTP translation), `Services/` (business rules), `Models/` (persistence),
  `views/` (presentation). A change to the late-fee formula touches only
  `Services/PointLedger.php`; a change to the booking page layout touches only
  `views/bookings/`.
- Cross-cutting concerns are isolated into middleware with one job each:
  `AuthMiddleware` (is anyone logged in?), `RbacMiddleware` (may this role do this?),
  `CsrfMiddleware` (is this POST genuine?). None of the 24 modules re-implements these.
- Each cron script does one scheduled job (`charge_late_fees.php`,
  `check_invariant.php`), so a bug in stipend payment cannot break the invariant check.

**Justification:** "We separated HTTP handling, business rules, persistence, and
presentation into distinct layers so that each class has a single reason to change,
which localises the impact of requirement changes across a 24-module codebase."

### 1.2 Open/Closed Principle (OCP)

*Open for extension, closed for modification.*

**Where Mithra applies it**

- The **middleware chain** is extended by registering a new middleware on a route —
  adding a future `RateLimitMiddleware` requires zero changes to existing middleware,
  the router, or any controller.
- The **damage state machine** (`created → simple/moderator path → resolved → closed`)
  is data-driven: allowed transitions live in one transition table/map. New states or
  transitions are added there without editing the code that executes transitions.
- The **router** maps patterns to controllers declaratively; every new module extends the
  system by *adding* a controller + routes, never by modifying dispatch logic.
- Core plumbing (router, middleware, `BaseModel`, `BaseController`) is **frozen after
  Month 2**; all later work extends it. This freeze is our OCP enforcement mechanism.

**Justification:** "Core infrastructure was frozen early and designed for declarative
extension — new routes, middleware, and workflow states are added as data or new classes
rather than edits to tested code, reducing regression risk across the year."

### 1.3 Liskov Substitution Principle (LSP)

*Subtypes must be usable anywhere their base type is expected.*

**Where Mithra applies it**

- Every model extends `BaseModel` and honours its contract exactly: `find(int $id):
  ?array`, `create(array $data): int`, `paginate(int $page): array` return the same
  shapes for every table. Generic code — pagination partials, the admin listing screens,
  test helpers — works with any model without knowing which one it received.
- Overrides may strengthen behaviour (e.g., `PointLedgerModel` refuses `update()` and
  `delete()` because the ledger is append-only) but this is declared in the base contract
  as a documented capability check, not a surprise exception. No override widens
  parameter expectations or narrows return types.

**Justification:** "All models are substitutable behind the BaseModel contract, which is
what allows shared components such as pagination and the admin screens to operate over
any of the 20+ tables without type-specific branching."

### 1.4 Interface Segregation Principle (ISP)

*No client should be forced to depend on methods it does not use.*

**Where Mithra applies it**

- `MiddlewareInterface` declares exactly one method: `handle(Request $r, callable
  $next)`. A middleware never has to stub out irrelevant methods.
- Instead of one fat `NotificationInterface`, notification creation is a small
  `Notifier::push(int $memberId, string $type, array $data)` service — pages that only
  *read* notifications depend on the polling read model, not on sending capability.
- `BaseModel` stays deliberately small (CRUD + pagination). Specialised behaviour
  (`findActiveByDivision()`) lives on the specific model that needs it, so other models
  are not forced to carry meaningless methods.

**Justification:** "We kept contracts minimal — single-method middleware and a small
persistence base class — so no class implements methods it doesn't need, which keeps
each of the 24 modules easy to reason about in isolation."

### 1.5 Dependency Inversion Principle (DIP)

*Depend on abstractions; high-level policy must not depend on low-level detail.*

**Where Mithra applies it**

- Services receive the `PDO` connection and any collaborating services through their
  **constructors**. `PointLedger` never calls `new PDO(...)` and never reads config —
  the front controller's bootstrap wires everything once.
- This inversion is what makes the highest-risk code **testable**: PHPUnit constructs
  `PointLedger` with a connection to a throwaway test database (or an SQLite/transaction
  sandbox) and exercises the accounting invariant directly, with no HTTP involved.
- Controllers depend on services (policy), not on SQL (detail); views depend on plain
  arrays handed to them, not on models.

**Justification:** "Constructor injection inverts the dependency between business logic
and infrastructure, which is precisely what allows our CI pipeline to unit-test the
point ledger — the component our risk register rates as critical — on every pull
request."

---

## 2. DRY — Don't Repeat Yourself

**Where Mithra applies it**

- **HTML:** shared chrome and repeated widgets live in `/partials/` (`header.php`,
  `nav.php`, `listing-card.php`, `trust-score.php`, `photo-grid.php`) and are included,
  never copy-pasted.
- **CSS:** one design system in `/public/css/main.css`; every colour, spacing step, and
  font size is a custom property used by all four developers — the direct mitigation for
  the "inconsistent UI across four developers" risk.
- **PHP:** generic CRUD lives once in `BaseModel`; validation rules once in `Validator`;
  escaping once in `e()`; CSRF once in `csrf_field()` + middleware. The 24 modules are
  thin compositions of these shared pieces.
- **Schema:** derived business numbers (late fees, rate suggestions) are computed in one
  service each; the same formula is never re-implemented in a second place.
- **Guard rail:** DRY is applied to *knowledge*, not to lines that merely look alike.
  Two workflows that are coincidentally similar today (gifting vs. aid grants) stay
  separate — premature abstraction is treated as a defect, per KISS.

**Justification:** "Every piece of knowledge — a formula, a validation rule, a UI
component, a design token — has exactly one authoritative home, so a change is made once
and cannot drift out of sync across four developers' modules."

---

## 3. KISS — Keep It Simple, Stupid

**Where Mithra applies it (deliberate simplicity choices)**

| Chosen | Rejected | Why the simple option wins |
| --- | --- | --- |
| Server-rendered pages | SPA + JSON API | Halves the code, no client state bugs, works without JS, fits the vanilla constraint. |
| 8-second polling | WebSockets/SSE | Runs on any LAMP host, trivial to debug, adequate for community-scale traffic. |
| PHP includes for views | Template engine | Zero dependencies; escaping enforced by convention + review + one helper. |
| One MySQL database | Caches/queues/microservices | One backup, one transaction model, one thing to learn and secure. |
| Cached wallet balance + nightly invariant cron | Live event-sourced projections | Simple reads with mathematically checkable correctness. |
| Cron CLI scripts | In-app scheduler daemon | Uses the OS scheduler that already exists; each job is a plain testable script. |

The one place we accept *extra* structure — the append-only ledger inside SERIALIZABLE
transactions — is justified because points are the system's trust anchor; simplicity is
never allowed to compromise correctness of money-like data.

**Justification:** "For every technical decision we chose the simplest mechanism that
meets the requirement on a standard LAMP host, and we can articulate the rejected
alternative for each — complexity was spent in exactly one place, the point ledger,
where correctness demands it."

---

## 4. Maintainability

**How the design achieves it**

- **Uniform module recipe.** All 24 modules follow the identical file pattern
  (migration → model → service → controller → views → routes). Any member can open any
  other member's module and know where everything is — essential in a 4-person,
  12-month project with AI-assisted code from different tools.
- **Separation of concerns** (see SRP) means changes are local; the frozen core means
  the foundation under everyone's code stops moving after Month 2.
- **Conventions are machine-enforced**, not aspirational: `.editorconfig`, PSR-12 code
  style checks, and PHPStan run in CI on every pull request; a human review with the PR
  checklist covers what machines can't.
- **Self-documenting structure** is preferred to comments: purpose-named service methods
  (`chargeLateFee()`, `promoteTemporaryCommunity()`), purpose-named model queries, and
  numbered migrations that read as the schema's history.
- **Tests as a safety net** on the service layer let future changes (Month 9 refactors,
  post-pilot fixes) be made with confidence.

**Justification:** "Maintainability comes from repetition of one well-understood pattern
across all modules, enforced by CI rather than discipline, so the codebase reads the
same regardless of which member — or which AI assistant — produced a given file."

---

## 5. Scalability

**How the design handles growth (within a hyper-local pilot's realistic bounds)**

- **Read path:** every foreign key and filtered column is indexed in its migration; all
  listings paginate; no `SELECT *`; no N+1 query loops. The hottest query (notification
  polling every 8 s per user) is a single indexed lookup.
- **Write path:** wallet balances are cached columns updated inside the same transaction
  as the ledger INSERT, so reads never aggregate the ledger. Contention is controlled
  with `SELECT ... FOR UPDATE` row locks on exactly the wallets involved — concurrent
  bookings on different members don't block each other. Concurrency is explicitly
  load-tested in Month 8.
- **Storage:** photos go to the filesystem (organised by entity), not the database, so
  the DB stays small and the ~1.5 GB pilot photo estimate never bloats backups.
- **Stateless-enough app tier:** because pages are server-rendered and state lives in
  MySQL + the session, the first scaling step is simply a bigger LAMP host (vertical),
  and the known second step is session storage moved out of files — documented, not
  built, per KISS/YAGNI.
- **Honest scope:** the platform is deliberately per-GN-division; data volume grows with
  community size, not virally. We designed for ~10× pilot load, and can state exactly
  what changes beyond that.

**Justification:** "We scaled by design where it is cheap — indexes, pagination, cached
balances, row-level locking — and consciously deferred infrastructure that a
community-scale pilot cannot justify, while documenting the upgrade path."

---

## 6. Robustness

**How the design resists and recovers from failure**

- **Input validation at every boundary:** the shared `Validator` runs in every controller
  action (type, range, length, enum, ownership); client-side JS validation is a UX
  duplicate, never the defence. Invalid input re-renders the form with field errors —
  the app never white-screens on bad data.
- **Defence in depth against hostile input:** PDO prepared statements (SQLi), `e()` on
  all output (XSS), CSRF tokens on all forms, RBAC middleware on every route, finfo +
  GD re-encoding + out-of-webroot storage on uploads, ownership checks on every ID the
  client supplies.
- **Transactional integrity:** every multi-row write — and *every* point movement — is a
  single ACID transaction with row locks; any exception rolls the whole operation back,
  so the ledger can never half-apply.
- **Systemic self-checking:** the nightly cron verifies the accounting invariant
  (Σ six pools = points issued − points exited) over the append-only ledger; the Reserve
  Pool guarantees no member balance goes negative. Errors are *detected*, not assumed
  absent.
- **Graceful failure:** domain exceptions map to friendly user messages; a top-level
  handler logs details server-side and shows a generic error page
  (`display_errors=Off`); auth/CSRF/ownership checks fail closed.
- **Operational resilience:** every cron job is idempotent and logs to `cron_runs`; the
  admin dashboard flags any job >24 h overdue; if the host lacks cron, checks fall back
  to lazy per-request triggering.

**Justification:** "Robustness is layered: inputs are validated and distrusted at the
boundary, writes are atomic, the ledger is append-only and nightly-audited against a
mathematical invariant, and every failure path either rolls back, fails closed, or is
logged and surfaced — so errors are contained and detected rather than silently
corrupting community trust."

---

## 7. Traceability table (for the report appendix)

| Criterion | Primary evidence in repo |
| --- | --- |
| SRP | `/app/{Controllers,Services,Models,Middleware}` split; one-job cron scripts |
| OCP | Route/middleware registration; data-driven damage state machine; Month-2 core freeze |
| LSP | `BaseModel` contract + uniform return shapes across all models |
| ISP | Single-method `MiddlewareInterface`; small `BaseModel` |
| DIP | Constructor injection in `/app/Services`; wiring only in bootstrap; service unit tests |
| DRY | `/partials/`, `main.css` custom properties, `BaseModel`, `Validator`, `e()` |
| KISS | Decision table §3; absence of frameworks/queues/websockets |
| Maintainability | Identical module recipe ×24; CI style+static-analysis gates; PR checklist |
| Scalability | Indexed migrations; pagination everywhere; cached balances; Month-8 concurrency test |
| Robustness | `Validator`; transactions + `FOR UPDATE`; invariant cron; `cron_runs`; fail-closed middleware |
