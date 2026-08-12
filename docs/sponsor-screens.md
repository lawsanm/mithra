# Sponsor screens — Figma → conventions map

Source: Figma page **Sponsor** (node `367:261`, file `m9EZDG6TpxnaBCFbHCiyQn`), frame set marked
"✅ Uniform — design system · 1440×1024 · no tiers, per project plan §15". Seven frames, in
canvas order.

## Screens → routes → views

| # | Figma frame | Node | Route | View file | Notes |
| --- | --- | --- | --- | --- | --- |
| 1 | Sponsor Dashboard | `385:11` | `/sponsor`, `/sponsor/dashboard` | `views/sponsor/dashboard/index.php` | Stat tiles (total contributed, points generated, aid grants funded, unread alerts), recent-notifications strip, disaster-mode banner, pending-aid-request banner, CSR impact summary. |
| 2 | Purchase Points Package | `385:81` | `/sponsor/purchase-points` | `views/sponsor/purchase-points/create.php` | Package tiles (Starter/Community/Impact/Custom, 1:1 LKR→points) + Sponsor/Aid pool allocation slider (100/0, 70/30, 50/50, 0/100). No in-app payment — submitting creates a request for the Sponsor Liaison to record offline with a receipt number. |
| 3 | CSR Report | `385:147` | `/sponsor/csr-reports` | `views/sponsor/csr-reports/index.php` | Totals (donated, points generated, purchases this year, aid grants funded) + quarterly breakdown rows (receipt no., pool split, amount). Figures must reconcile with the public Transparency Dashboard. |
| 4 | Branding Upload | `387:19` | `/sponsor/branding` | `views/sponsor/branding/edit.php` | Logo upload, display name, optional tagline, "tag funded bonuses & aid grants" toggle, live sponsor-wall preview. |
| 5 | Sponsor Notifications | `387:75` | `/sponsor/notifications` | `views/sponsor/notifications/index.php` | Same list pattern as the member/admin notification screens (unread dot, relative timestamp). |
| 6 | Disaster — Connect with Moderator | `387:137` | `/sponsor/disasters/{id}` | `views/sponsor/disasters/show.php` | Division disaster status, moderator-on-the-ground card (name, division, quote), "make an offer" form (amount only — disaster contributions default to 100% Aid Pool). |
| 7 | Disaster Alert — Modal | `387:189` | *(not a route)* | `partials/sponsor/disaster-alert-modal.php` | Frame 6 with a dismissible "Disaster Mode activated" modal over a scrim, shown once per new `disaster_events` row for the sponsor's division. A partial layered onto screen 6 / the dashboard, not its own page. |

## Route table additions

Mirrors the `// ── Admin ──` / `// ── Moderator ──` sections already in
[public/router.php](public/router.php):

```php
// ── Sponsor ──
'/sponsor'                 => 'sponsor/dashboard/index',
'/sponsor/dashboard'       => 'sponsor/dashboard/index',
'/sponsor/purchase-points' => 'sponsor/purchase-points/create',
'/sponsor/csr-reports'     => 'sponsor/csr-reports/index',
'/sponsor/branding'        => 'sponsor/branding/edit',
'/sponsor/notifications'   => 'sponsor/notifications/index',
'/sponsor/disasters/{id}'  => 'sponsor/disasters/show',
```

## Naming (CONVENTIONS.md §4)

- Actor prefix `sponsor` throughout: view folder, routes, and the git branch
  (`sponsor/short-feature-name` — this work sits on `sponsor/sponsor-screens`).
- Nav labels match the Figma header exactly: Dashboard, Purchase Points, CSR Reports,
  Branding, Notifications.
- View files follow `feature/action.php` (§3): `create` for the one-shot purchase form,
  `edit` for branding (it always edits the one row for that sponsor), `index`/`show`
  elsewhere, matching the existing Items/Moderator modules.

## Data model already in place

No new tables needed — the screens read/write rows that already exist:

- `sponsors` ([app/Models/Sponsor.php](app/Models/Sponsor.php)) — `company_name`,
  `branding_path`, `total_injected`, `active`. Add `contact_name`/`tagline` fields here if
  the Branding screen needs them beyond `branding_path`.
- `sponsor_purchases` — one row per purchase request: `cash_amount`, `points_credited`,
  `sponsor_pool_pct`/`aid_pool_pct` (the allocation slider), `receipt_number`,
  `recorded_by` (the liaison), `recorded_at`. Backs both screen 2 (create) and screen 3
  (quarterly breakdown).
- `aid_grants` — "aid grants funded" tile and CSR report's Aid Pool share.
- `notifications` — screens 1 and 5, same `user_id`/`type`/`payload`/`read_at` shape as
  every other actor's notification list.
- `disaster_events` — screens 6 and 7 (`gn_division_id`, `started_at`, `ended_at`).

## Open questions for whoever builds this module

- Branding: `finfo` MIME check + GD re-encode + storage outside web root, served via the
  photo proxy (§7.5) — same as item/condition photos.
- Purchase Points and the disaster "make an offer" form both only *create a request*; the
  actual point-ledger movement happens when the Sponsor Liaison records it (mirrors
  `admin/pools/sponsor-ledger`). Confirm which service owns that write.
- Modal (screen 7) needs a "seen" flag per sponsor per `disaster_events` row so it doesn't
  reappear every page load.
