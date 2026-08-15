<!--
  Open this as a DRAFT. CI runs php -l, the CONVENTIONS.md checker and the migrations
  against a clean MySQL 8. Green marks the PR ready for review and requests the code
  owner; red puts it back into draft with every violation listed.

  Run the same checks before pushing:  php .github/scripts/conventions-check.php

  The boxes below are the part no script can judge — the reviewer ticks them.
-->


## What does this PR do?

<!-- 1–3 sentences. Which module / feature? Link the task if tracked. -->

**Module:** <!-- e.g. Bookings (Member 3) -->

## How was it tested?

<!-- Manual steps performed + automated tests added. -->

---

## Merge checklist — reviewer verifies every box

### Conventions
- [ ] Follows `CONVENTIONS.md` (naming, folder placement, PSR-12) and imitates the golden Items module
- [ ] Controllers are thin (≤ ~30 lines/action); business logic lives in `/app/Services`
- [ ] No new libraries, frameworks, packages, or CSS files introduced
- [ ] Migration is a new numbered file; no previously applied migration was edited

### Security (from Risk Register — all Critical)
- [ ] All SQL uses PDO prepared statements — zero variable interpolation/concatenation
- [ ] All dynamic output escaped with `e()` (including AJAX/JSON contexts appropriately)
- [ ] Every form contains `csrf_field()`; state changes are POST-only
- [ ] Route registrations declare the required role (RBAC not hand-rolled in controllers)
- [ ] Ownership checks on every client-supplied ID (member can only touch their own rows)
- [ ] File uploads (if any): finfo MIME check, GD re-encode, stored outside web root, served via proxy

### Robustness
- [ ] Input validated via `Validator`; failure re-renders the form with field errors
- [ ] Multi-row writes and ALL point movements wrapped in a single transaction; wallet rows locked with `SELECT ... FOR UPDATE`
- [ ] Domain exceptions caught and shown as friendly messages; nothing leaks stack traces

### Quality gates
- [ ] CI green: `php -l`, code style (PSR-12), PHPStan, PHPUnit
- [ ] Tests added/updated for any service-layer logic (mandatory if points are touched)
- [ ] Listing pages paginate; no `SELECT *`; no queries inside loops; new FKs/filter columns indexed

### Design principles (spot-check one)
- [ ] Reviewer can name where SRP/DRY/KISS shows up in this PR — and nothing here contradicts `DESIGN_PRINCIPLES.md`

---

**Reviewer:** <!-- @member -->  |  **Author confirms every line of this PR was read and understood:** yes / no
