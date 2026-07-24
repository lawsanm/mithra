# Automated pull-request review

When a teammate opens a PR into `main`, GitHub Actions judges it and posts the verdict
as a review. Nobody has to read the diff to find out that a file is unformatted, a
migration was edited, or a form lost its CSRF token — the bot says so within a minute.

## The flow a teammate sees

1. They push `member/booking-crud`. **Every push to every branch runs the checks**, so
   they find out their code does not fit `main` before anyone else is involved.
2. They open a pull request — as a **draft**.
3. Green → the bot marks the PR *ready for review*, which is what requests the code
   owner's review and puts it in the reviewer's queue. It also posts an approving review
   and labels it `ci:passed`.
4. Red → the bot **puts the PR back into draft**, posts a request-changes review naming
   every violation, and labels it `ci:changes-requested`. A draft PR cannot be merged and
   does not ask anyone for a review. They fix, push, and the verdict re-runs.

GitHub cannot prevent a pull request from being *created* — nothing can. The draft state
is the lever: an incompatible PR never reaches the reviewer's queue and never becomes
mergeable, and it flips itself the moment the branch passes.

## What runs

`.github/workflows/ci.yml` has four jobs. The first three are the required status checks.

| Job | What it proves |
| --- | --- |
| **`quality`** | Every `.php` file parses (`php -l`), and every file in `/migrations/` runs top-to-bottom on an empty MySQL 8 — so a new migration cannot contradict the existing schema. |
| **`conventions`** | `.github/scripts/conventions-check.php` finds no violation of the hard rules in `Rules/CONVENTIONS.md`. |
| **`hygiene`** | The branch is named `actor/short-feature-name` (§4) and every commit message is `type: short description` with type ∈ `feat\|fix\|refactor\|test\|docs\|chore\|migration` (§12). |
| **Accept or request changes** | Reads the three results and reviews the PR. |

The verdict job:

- **all green** → posts an **approving review**, adds the `ci:passed` label, and (if enabled)
  arms auto-merge;
- **anything red** → posts a **request-changes review** listing every violation with
  `file:line`, and adds the `ci:changes-requested` label.

Pushing a fix re-runs everything and replaces the verdict. GitHub blocks merging while a
request-changes review is outstanding, which is the "reject" half of the automation.

## What the checker enforces

Blocking (a violation fails the PR):

- no frameworks, libraries, packages, CDNs, `composer.json`, `package.json`, `vendor/`, `node_modules/`
- `public/css/main.css` is the only stylesheet; no `<style>` blocks, no inline `onclick=`
- PDO only — no `mysqli`/`mysql_*`, `new PDO` only in `app/Core/Database.php`
- `prepare`/`query`/`exec` only inside `/app/Models` and `/app/Core`
- `declare(strict_types=1)`, no closing `?>`, file name matches class name, no `global`,
  no `die()`/`exit()` outside the front controller, no `var_dump`/`print_r`/`dd`
- every echoed variable goes through `e()`; every POST form contains `csrf_field()`
- services never touch superglobals
- `config/config.php`, `.env*` and uploads are never committed
- migrations are named `NNN_verb_noun.sql`, and an already-applied migration is never
  modified or deleted
- (in `hygiene`) branch `actor/short-feature-name`, commits `type: short description`

The branch prefix is the actor whose screens the work belongs to — `member`,
`moderator`, `sponsor`, `sponsor-liaison`, `admin` — plus `shared` for cross-cutting work
that is nobody's feature (tooling, CI, the stylesheet). Examples: `member/booking-crud`,
`moderator/damage-queue`, `shared/ci-setup`.

Advisory (reported to the reviewer, never blocking): variables interpolated into SQL
(fine when they are whitelisted identifiers), `SELECT *`, `innerHTML`, `console.log`.

An intentional exception to the escaping rule is marked on the line itself:

```php
<?= $tag ?><!-- escape-ok: literal tag name chosen in this file -->
```

## Run it before you push

```bash
php .github/scripts/conventions-check.php
```

Same script, same output as CI. Exit code 0 means the bot will approve.

## Repository setup (one-off, done in GitHub Settings)

1. **Settings → Rules → New ruleset** for `main`:
   - Require a pull request before merging: 1 approving review, require review from Code Owners
   - Require status checks to pass, with branches up to date: `quality`, `conventions`, `hygiene`
   - Block force pushes. Leave admin bypass allowed.
2. **Settings → Actions → General → Workflow permissions**: *Read and write permissions*
   and *Allow GitHub Actions to create and approve pull requests* — without this the bot
   cannot post its review.
3. **Settings → General → Pull Requests**: tick *Allow auto-merge* if you want step 4.
4. **Settings → Secrets and variables → Actions → Variables**: add `AUTO_MERGE` = `true`
   to let a green PR merge itself. Leave it unset (or `false`) to keep the final click.

With `AUTO_MERGE` unset you get: bot approves → you glance at the diff → you press merge.
With `AUTO_MERGE=true` and a branch ruleset requiring a human approval, the PR merges the
moment you approve it. With `AUTO_MERGE=true` and no human approval required, green PRs
merge themselves.

## Limits — read this before trusting it

The bot proves a branch is *compatible*: it parses, it obeys the conventions, its SQL
applies on top of `main`. It cannot tell whether the feature works, whether the points
arithmetic is right, or whether an ownership check is missing on a route it has never
seen. Keep the merge checklist in the PR template; the reviewer still reads the diff.

PRs opened from a **fork** get a read-only token, so the verdict job cannot post its
review or manage the draft state there. Have teammates push branches to this repository
instead — add them under Settings → Collaborators.

A branch with an open PR is checked twice per push (once for the `push` event, once for
the `pull_request` event). Harmless, and it keeps the pre-PR feedback loop.
