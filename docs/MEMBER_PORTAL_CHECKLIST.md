# Member portal — completion checklist

The member portal is finished when every member action in Plan §6.1 works end to end
with a real backend, and no member screen still carries a `data-demo-form`,
`preview-action` or "Preview only" note.

Checked against the code at commit `94b5e92` (2 Oct 2026). Line numbers in the issue list
refer to that commit.

## How to use this file

- Tick a box when the work is merged into `main`, not when it is written.
- Every known issue has an id (I1–I31) and names the section that fixes it. The fix items
  carry the same id, so tick both together.
- One branch and one pull request per module, named `member/<feature>` (for example
  `member/booking-request`). Commit messages use `type: description`
  (`Rules/CONVENTIONS.md` §12).
- Build every module the way Items is built: thin controller → service for rules and
  transactions → model for SQL → view (`Rules/CONVENTIONS.md` §6).
- A migration takes the next free number when its branch starts (018 is next today)
  and is never edited after it is merged.
- Plan §20 places the 3.x modules in group 3 (Transactions) and the 4.x modules in
  group 4 (Points and Gifting). Agree with the team who builds each one before starting
  it. Every section below names its plan group.

## Progress at a glance

| Module | Plan | Group | Create | Read | Update | Delete |
| --- | --- | --- | --- | --- | --- | --- |
| Account and profile | 1.1 | 1 | ✅ | ✅ | ✅ | ✅ |
| Item listing | 2.1 | 2 | ✅ | ✅ | ✅ | ✅ |
| Temporary community | 1.2, §6.5 | 1 | ⬜ | ✅ profile panel | ⬜ | ⬜ |
| Availability calendar | 2.4 | 2 | ⬜ | ⬜ | ⬜ | ⬜ |
| Saved searches | 2.3 | 2 | ⬜ | ⬜ | ⬜ | ⬜ |
| Donation | 2.5 | 2 | ✅ listing, ⬜ request | ✅ request list | ⬜ | ⬜ |
| Booking request | 3.1 | 3 | ⬜ | ✅ | ⬜ | ⬜ |
| Handover photos | 3.2 | 3 | ⬜ | ⬜ | ⬜ | ⬜ |
| Return photos | 3.3 | 3 | ⬜ | ⬜ | ⬜ | ⬜ |
| Damage claim | 3.4 | 3 | ⬜ | ⬜ | ⬜ | ⬜ |
| Rating and review | 3.5 | 3 | ⬜ | ✅ | ⬜ | ⬜ |
| Dispute (member side) | 3.6 | 3 | ⬜ | ⬜ | ⬜ | ⬜ |
| Gifting | 4.5 | 4 | ⬜ | ✅ | ✅ receive setting | n/a, irreversible |
| Aid grant (member side) | 4.4 | 4 | ⬜ | ✅ status page | ⬜ | ⬜ |
| Notifications | 4.6 | 4 | by the system | ✅ | ⬜ | ⬜ |
| Trust score | 1.6 | 1 | n/a | ✅ score, ⬜ breakdown | ⬜ recalculation | n/a |

Already live and read-only by design: Dashboard, Wallet (4.1), Transparency (4.1).

## Build order

1. **Phase 0 — shared groundwork.** Several modules depend on it.
2. **Phase 1 — communities and items** (groups 1 and 2): temporary community,
   availability calendar, saved searches, donation.
3. **Phase 2 — booking lifecycle** (group 3), strictly in order: request → handover →
   return → damage claim → rating → dispute.
4. **Phase 3 — points features** (group 4): gifting, aid grant, notifications. Can run
   alongside Phase 2.
5. **Phase 4 — trust score** (group 1). Needs ratings, returns, claims and donations.
6. **Phase 5 — finishing pass** across the whole portal.

---

## Known issues

Found while checking the code for this plan. Each issue says where it is, what it breaks
and which section of this checklist fixes it.

### Bugs in existing code

- [ ] **I1 · Point movements lose their links.** `PointLedger::record()`
  (`app/Models/PointLedger.php:104`) never writes `booking_id`, `gift_id` or
  `aid_grant_id`. The wallet's "Held in escrow" figure joins on `booking_id`
  (`app/Models/Wallet.php:49`), so it will show 0 for every new hold, and wallet activity
  loses item titles and gift reasons (`Wallet.php:84–86`).
  *Fix:* Phase 0 › Points ledger.
- [ ] **I2 · Gift recipients ignore divisions.** `User::giftableExcept()`
  (`app/Models/User.php:223`) offers every active member who accepts gifts, from any
  division; §11.1 says the same division. It also leaves out moderators
  (`r.code = 'member'`, line 230), although a moderator is a verified member (§16.3).
  *Fix:* Phase 3 › Gifting.
- [ ] **I3 · New booking notifications would be mislabelled.**
  `Notification::displayForMember()` (`app/Models/Notification.php:77`) titles every
  borrower notification that carries a `booking_id` as "Return due" or "… accepted your
  request". A "declined" or "cancelled" notification would read as an acceptance.
  *Fix:* Phase 0 › Conventions to keep in mind.
- [ ] **I4 · Booking status label uses a value the schema doesn't have.**
  `BookingController::status()` (`app/Controllers/BookingController.php:92`) handles
  `declined`; the `bookings.status` column uses `rejected`
  (`migrations/001_create_schema.sql:251`). *Fix:* Booking request › Read.
- [ ] **I5 · The item page offers borrowing across divisions.** `can_borrow`
  (`app/Controllers/ItemController.php:149`) ignores the viewer's division. Browse hides
  other divisions, but any live listing opens by its URL. Harmless while borrowing is
  disabled; it has to be fixed together with the booking request.
  *Fix:* Booking request › Create.
- [ ] **I6 · A rejected aid grant counts as active.** `AidGrant::activeForMember()`
  (`app/Models/AidGrant.php:67`) only skips `closed` and `expired`. Account closure
  filters again, so it is safe there, but the one-active-grant rule must not reuse it as
  is. *Fix:* Aid grant › Create.
- [ ] **I7 · Public profiles open for pending and closed accounts.** `/members/{id}`
  (`app/Controllers/ProfileController.php:112`) loads any account with a home membership,
  whatever its status. *Fix:* Phase 5 › Public member profile.

### Missing pieces that block member flows

- [ ] **I8 · Donations cannot start.** Nothing in `app/` creates a `donations` row, and
  only `views/donations/handover.php:54` links to the request list.
  *Fix:* Donation › Create and Read.
- [ ] **I9 · Two popups are never shown.** No page includes
  `partials/modal-rate-review.php` or `partials/modal-damage-claim.php`.
  *Fix:* Rating and review; Damage claim.
- [ ] **I10 · The photo proxy would refuse booking photos.** `public/photo.php:97` falls
  back to the item-photo check, so files in `handover-photos/`, `return-photos/` and
  `damage-evidence/` would return 404. *Fix:* Phase 0 › Input, uploads and photo access.
- [ ] **I11 · No ledger reason for refunding a cancelled booking.**
  `point_ledger.reason` (migration 004) has `buffer_refund`, but nothing for returning
  the rental charge (§10.1). *Fix:* Phase 0 › Points ledger.
- [ ] **I12 · No date validation.** `Validator` (`app/Core/Validator.php`) has no date
  rule, and every booking and calendar form needs one.
  *Fix:* Phase 0 › Input, uploads and photo access.
- [ ] **I13 · No scheduled job has ever run.** `scripts/` holds only
  `check-database.php` and `migrate.php`, and `CronRun` only reads. The runs shown on the
  transparency and admin cron pages are seed rows (migrations 002 and 016). Nothing
  expires, auto-cancels, charges late fees or recalculates.
  *Fix:* Phase 0 › Scheduled jobs, plus each module's job.
- [ ] **I14 · The trust score never changes.** Nothing writes `users.trust_score`, the
  `/trust` breakdown is empty (`app/Controllers/DemoController.php:230`), and no table
  records past suspensions, one of the three penalties. *Fix:* Phase 4.
- [ ] **I15 · Temporary communities only exist on paper.** The moderator queue reads home
  applications only (`app/Models/UserDivision.php:76`, `87`), and Browse and new listings
  always use the home division (`app/Controllers/ItemController.php:65`, `294`).
  *Fix:* Temporary community.

### Blocked by other portals

- [ ] **I16 · Moderator portal.** Aid vouching (`views/moderator/aid-vouching/index.php`)
  and damage cases (`views/moderator/cases/show.php`) are previews, so an aid request or a
  contested claim cannot get past the moderator.
- [ ] **I17 · Sponsor Liaison portal.** The aid-grant decision form is disabled
  (`views/sponsor-liaison/aid-grants/show.php:93`) and no POST route exists, so no grant
  can be approved.
- [ ] **I18 · Admin portal.** Dispute rulings are previews
  (`views/admin/disputes/show.php`).

### Screen and form problems

- [ ] **I19 · Upload pickers offer PDF.** `views/community/create.php:52` and
  `views/aid-grants/create.php:91` accept `application/pdf`, but `PhotoStore` takes JPEG,
  PNG and WebP only (`app/Services/PhotoStore.php:24`).
  *Fix:* Phase 0 › Input, uploads and photo access.
- [ ] **I20 · The temporary-community form would send division names.**
  `DemoController::community()` passes names, not ids
  (`app/Controllers/DemoController.php:333`). *Fix:* Temporary community › Create.
- [ ] **I21 · Cancel on the temporary-community page goes to Settings**
  (`views/community/create.php:62`), though the page is now opened from My profile.
  *Fix:* Temporary community › Create.
- [ ] **I22 · Lists without pages.** My Bookings (`Booking::forMember()`), Notifications
  (`Notification::forMember()`) and gift history (`Gift::forMember()`) have no limit, and
  wallet activity stops at 20 with no next page (`app/Models/Wallet.php:74`).
  `Rules/CONVENTIONS.md` §9 asks for 20 a page.
  *Fix:* Booking request › Read; Gifting; Notifications › Read; Phase 5 › Wallet.
- [ ] **I23 · Help's "Contact moderator" is a dead button**
  (`views/help/index.php:95`), and no module plans it. *Fix:* Phase 5 › Help.

### Schema gaps for planned features

- [ ] **I24** `bookings` has no column for the request message or a decline reason.
  *Fix:* Booking request › Database.
- [ ] **I25** `damage_claims.evidence_path` holds one file; the claim popup takes several.
  *Fix:* Damage claim.
- [ ] **I26** `ratings` has no column for the popup's quick tags, and nothing stops a
  member rating the same record twice. *Fix:* Rating and review.
- [ ] **I27** `aid_grants` has no column for the request details, the Liaison's question
  or the member's reply. *Fix:* Aid grant.
- [ ] **I28** `donations` records one `handover_at`, but §13.1 needs both sides to
  confirm. *Fix:* Donation › Database.
- [ ] **I29** `user_divisions` has no rejection reason and no place for renewal proof.
  *Fix:* Temporary community › Database.

### Comments that don't match the code

- [ ] **I30** `app/Models/Notification.php:8` says payloads are written by
  `Notifier::push()`; the method is `Notification::push()`. *Fix:* Phase 5.
- [ ] **I31** `app/Models/Gift.php:6` says the model covers `gift_usage_counters`; no code
  reads or writes that table, and the caps come from summing `gifts`. *Fix:* Phase 5.

### Decisions the team needs to make

- [ ] Who builds the group 3 and group 4 member modules (Plan §20 against the portal
  split).
- [ ] The late buffer for monthly-only items (Booking request › Rules).
- [ ] The donation scale in the trust score (Phase 4).
- [ ] Where past suspensions are recorded (Phase 4, I14).
- [ ] Whether moderators can receive gifts (I2).
- [ ] Keep or drop `gift_usage_counters` (Gifting, I31).
- [ ] Whether the unused `accepted` booking status stays (Booking request › Rules).

---

## Phase 0 — Shared groundwork

### Points ledger

- [ ] `PointLedger::record()` writes `booking_id`, `gift_id` and `aid_grant_id`, so every
  movement can be traced to its booking, gift or grant (I1).
- [ ] `LedgerService::poolToMember()` and `memberToPool()` accept those links (an
  optional `array $links = []`).
- [ ] `LedgerService::memberToMember()` for gifts, late fees and damage penalties. Lock
  both wallets in id order, the way `lockPools()` orders pool locks.
- [ ] A `LedgerService` shortfall helper: take what the member has, and the Reserve Pool
  pays the rest to the lender as `shortfall_cover`, linked to the booking (§7.7).
- [ ] Migration `NNN_add_booking_refund_reason.sql`: add `booking_refund` (In-Flight →
  borrower when a booking is cancelled before handover, §10.1) to `point_ledger.reason`,
  and to `PointLedger::GROUPS['escrow']` (I11).
- [ ] `tests/ledger.php`: inside a rolled-back transaction, every movement leaves
  `SUM(point_pools.balance)` unchanged, and the `member_wallets` pool still equals
  `SUM(member_wallets.balance)`.

### Input, uploads and photo access

- [ ] `Validator::date()` accepts a real `Y-m-d` date (I12). Range rules (not in the past,
  end on or after start) stay in the services.
- [ ] `PhotoStore` can stamp the upload time and the uploader's id onto the image with GD
  (§10.1). Handover and return photos use it.
- [ ] `public/photo.php` gets rules for the new folders, placed before the item-photo
  fallback (I10):
  - `handover-photos/`, `return-photos/`, `damage-evidence/`: the booking's lender and
    borrower, the moderator of the item's division, the Admin.
  - `aid-evidence/` (new folder with a `.gitkeep`): the member, their division's
    moderator, the Sponsor Liaison, the Admin.
- [ ] Upload pickers ask for images only, matching what `PhotoStore` accepts (I19).

### Membership

- [ ] `UserDivision::activeDivisionIds(int $userId)` returns the home division plus any
  active temporary one. Browse, borrowing, donation requests and gift recipients all
  check it ("a borrower must be an active member of that division", §6.5).

### Scheduled jobs

- [ ] `CronRun::start()` and `CronRun::finish()`. Every script in `scripts/` logs one row,
  is safe to run twice and exits non-zero on failure (`Rules/CONVENTIONS.md` §8) (I13).
- [ ] Locally, run a job with `C:\xampp\php\php.exe scripts\<job>.php` or from Windows
  Task Scheduler. List every job and its schedule in the README.

### Conventions to keep in mind

- [ ] `Router::match()` reads one `{id}` per URL, so a nested record gets its own address:
  `/availability-blocks/{id}`, not `/items/{id}/blocks/{blockId}`.
- [ ] Every new form has `method="post"`, `novalidate` and `<?= csrf_field() ?>`, and no
  `required` or `maxlength` attributes (CI check `server-side-rules`).
- [ ] Every new notification type goes into `Notification::GROUPS` with its own wording in
  `displayForMember()`, so it is not titled as an acceptance (I3).
- [ ] Bookings and damage claims keep their allowed status changes in one transition map,
  as `Rules/DESIGN_PRINCIPLES.md` plans for damage claims, so adding a state is one line.
- [ ] Every module adds `tests/<module>.php` with pure rule checks (like `tests/items.php`)
  and a line in the rule-checks step of `.github/workflows/ci.yml`.

---

## Phase 1 — Communities and items

### Temporary community — Plan 1.2, §6.5 (group 1)

**Today:** `/community/temporary` is a preview (a string route to
`DemoController::community()`). My profile shows the home and temporary communities. The
moderator queue reads home applications only (I15).

Database

- [ ] Migration `NNN_add_temporary_membership_review.sql` on `user_divisions`:
  `decision_reason VARCHAR(255) NULL`, `renewal_proof_path VARCHAR(255) NULL`,
  `renewal_requested_at DATETIME NULL` (I29).
- [ ] Keep `uk_ud_user_division (user_id, gn_division_id)` in mind: applying again to a
  division you were rejected from or left must reuse that row.

Create — apply

- [ ] Routes `GET /community/temporary` → `CommunityController::createForm` and
  `POST /community/temporary` → `store`, replacing the string route.
- [ ] `CommunityService::apply()` checks:
  - the account is active and has an active home;
  - the division is active and is not the home division;
  - there is no other temporary community that is pending, active or paused (one at most,
    §19);
  - the proof type comes from a fixed list (enrolment letter, lease or rental agreement,
    employer letter, other);
  - the proof image is stored in `identity-documents/`, so `photo.php` already shows it to
    that division's moderator through `documentReviewers()`.
- [ ] Save the row as `membership_type = 'temporary'`, `status = 'pending'`, and notify
  that division's moderator.
- [ ] `views/community/create.php` becomes a real form (`enctype="multipart/form-data"`).
  Its options carry division ids, not names (I20); it gains a proof-type select; Cancel
  goes back to My profile (I21); and the preview note goes.

Read

- [x] "My communities" panel on My profile (home and temporary, status, expiry).
- [ ] The community page shows the current application: pending, or rejected with the
  moderator's reason and a form to resubmit within 7 days (§19).

Update

- [ ] Moderator queue (part of the verification code): `UserDivision::queueForDivision()`
  and `countPendingForDivision()` include temporary applications, with a "Temporary"
  filter pill (I15). `views/moderator/verifications/show.php` says which kind it is.
- [ ] `VerificationService` approving a temporary application sets `active`,
  `verified_by`, `verified_at` and `expires_at` (today + 6 months). No `markActive()` and
  no welcome bonus. Rejecting needs a reason. Notify the member either way.
- [ ] Extend: the member uploads fresh proof while active or within the 14-day grace
  period (`renewal_proof_path`). The moderator approves → `expires_at` + 6 months, counted
  from today if it had lapsed, status `active`. Add `renewal_proof_path` to
  `documentReviewers()` and `isDocument()` so the moderator can open it.
- [ ] Promote to home: `POST /community/promote`, wiring
  `partials/modal-community-promotion.php`. One transaction:
  - the temporary row becomes `home`, with no expiry;
  - the old home row becomes `temporary` with status `deactivated`. That keeps exactly one
    home row (`User::findWithDivision()` joins on `membership_type = 'home'`) and lets the
    member rejoin the old home later by reusing that row;
  - the member's listings in the old home are paused;
  - the member is sent to the address-change form for the new address.

Delete

- [ ] Leave or withdraw: `POST /community/temporary/leave`. A pending application is
  withdrawn; an active membership becomes `deactivated` and its listings are paused.
  Refused while a booking in that division is still open, as account closure does.

Using the temporary community

- [ ] Browse gets a Home / Temporary switch (`?community=temporary`) when the member has
  an active temporary community. `ItemController::browse()` checks the membership, then
  passes that division to `Item::browse()` (I15).
- [ ] The listing wizard asks which community to list in. `ItemController::store()` always
  uses the home division today (I15).
- [ ] Borrowing, donation requests and gifts check `activeDivisionIds()`.

Scheduled job — `scripts/expire_temporary_memberships.php`

- [ ] 14 days before expiry: notify the member.
- [ ] At expiry: `active` → `paused` (grace period), and the member's listings in that
  division are paused. Open bookings still run to completion (§6.5).
- [ ] 14 days after expiry with no renewal: `paused` → `expired`. The member has to apply
  again.

Tests — `tests/community.php`

- [ ] Refusals: same as home, a second temporary community, an archived division,
  missing proof.
- [ ] Dates: 6-month expiry, 14-day grace, extension counted from the expiry date or from
  today.

Done when

- [ ] A member applies → that division's moderator approves → the member can browse, list
  and borrow there → expiry pauses their listings → extend, promote and leave all work.

### Availability calendar — Plan 2.4 (group 2)

**Today:** the `item_availability_blocks` table exists; no code uses it.

- [ ] Model `ItemAvailabilityBlock`: `forItem()`, `create()`, `update()`, `delete()`,
  `overlaps()`.
- [ ] `AvailabilityService` rules: owner only; real dates; end on or after start; not in
  the past; a block cannot cover an accepted or in-progress booking.
- [ ] Create: `POST /items/{id}/availability` blocks a date range, with an optional note.
- [ ] Read: the owner sees the blocks on `views/items/edit.php`; borrowers see
  "Unavailable" and "Booked" ranges on `views/items/show.php`. A plain list is enough; a
  month grid in a new `public/js/calendar.js` is optional.
- [ ] Update: `POST /availability-blocks/{id}`.
- [ ] Delete: `POST /availability-blocks/{id}/delete`, behind the existing confirm dialog
  (`public/js/confirm.js`).
- [ ] Booking requests refuse blocked dates (3.1).
- [ ] Tests: the date-overlap rule shared with bookings (touching ranges, same-day ranges,
  one range inside another, one around another).

### Saved searches — Plan 2.3 (group 2)

**Today:** the `saved_searches` table exists and the Browse filters work; nothing saves a
search.

- [ ] Model `SavedSearch`: `forMember()`, `create()`, `rename()`, `delete()`, all limited
  to the owner's `user_id`.
- [ ] Create: "Save this search" on Browse → `POST /saved-searches`. Name 1–80 characters;
  filters limited to `q`, `category`, `type` and `community`; up to 10 per member.
- [ ] Read: saved searches listed on `views/items/browse.php` as links that rebuild the
  filters.
- [ ] Update: rename with `POST /saved-searches/{id}`.
- [ ] Delete: `POST /saved-searches/{id}/delete`.
- [ ] Tests: unknown filters are dropped; the 10-search limit holds.

### Donation — Plan 2.5, §13 (group 2)

**Today:** donation listings work through the wizard, and the donor's request list
(`/donations/{id}`) reads live data. But nothing creates a `donations` row, no page links to
the request list (I8), and "Request this donation", "Choose recipient" and "Confirm
handover" are disabled.

Database

- [ ] Migration `NNN_add_donation_confirmations.sql`: `donor_confirmed_at` and
  `recipient_confirmed_at` on `donations` (§13.1 step 4 needs both) (I28).

Create

- [x] Donation listing (wizard, listing type "Donation").
- [ ] Create the `donations` row when a donation listing is approved
  (`ListingReviewService`) (I8).
- [ ] Request: `POST /donations/{id}/requests` with an optional message. Rules:
  - not your own item;
  - you are an active member of its division;
  - the listing is live;
  - one request per member (`uk_dr_once`).

  In first-come mode, the first request is selected straight away.

Read

- [x] The donor's request list (`views/donations/index.php`).
- [ ] My Items → Donations links to it ("View requests") (I8).
- [ ] The member who asked sees their request status on the item page.

Update

- [ ] The donor turns first-come on or off: `POST /donations/{id}/mode`.
- [ ] The donor chooses a recipient: `POST /donations/{id}/select`. The chosen request
  becomes `selected`, the rest `declined`, the donation `recipient_selected`. Notify
  everyone who asked.
- [ ] Handover on `views/donations/handover.php`: the donor confirms the transfer and the
  recipient confirms receipt. Once both have confirmed:
  - the donation is `completed` and the item `donated`;
  - both can rate each other (3.5);
  - it counts toward the donor badge and the trust-score contribution factor.

Delete

- [ ] A requester withdraws: `POST /donation-requests/{id}/withdraw`.
- [ ] The donor cancels before handover: donation `cancelled`, open requests declined,
  listing archived.

Tests

- [ ] First-come selection, one recipient only, no request on your own item.

Done when

- [ ] A donation goes listed → requested → recipient chosen → both confirm → the item
  shows as donated and both can rate.

---

## Phase 2 — Booking lifecycle (group 3)

Build these in order; each needs the one before it.

### Booking request — Plan 3.1, §8, §18.3

**Today:** My Bookings and the booking page read live data (`BookingController::index()`
and `show()`). The Request to Borrow modal is disabled.

Database

- [ ] Migration `NNN_add_booking_request_fields.sql`: `message VARCHAR(255) NULL`,
  `decline_reason VARCHAR(255) NULL`, `cancelled_by INT UNSIGNED NULL` (foreign key to
  `users`) (I24).

Rules in a new `BookingService`

- [ ] `quote()`, static and pure:
  - days counted inclusively;
  - a daily total, and a monthly total when the item has a monthly rate;
  - the cheaper option flagged, and requests over 27 days nudged toward monthly (§8.4);
  - the late buffer is one daily rate (§7.6). For a monthly-only item, decide the daily
    equivalent (for example the monthly rate ÷ 30, rounded up) and write it down.
- [ ] Transition map:
  - `requested` → `awaiting_handover`, `rejected`, `cancelled`, `auto_cancelled`
  - `awaiting_handover` → `in_progress`, `cancelled`, `auto_cancelled`
  - `in_progress` → `awaiting_return`, `pending_moderator` (unreturned after 7 days)
  - `awaiting_return` → `completed`, `pending_moderator`, `escalated`
  - `pending_moderator` → `completed`, `escalated`
  - `escalated` → `completed`

  Acceptance goes straight to `awaiting_handover`. `accepted` stays unused, since both
  already show as "Handover pending".

Create

- [ ] `GET /items/{id}?from=&to=&basis=` shows the quote worked out on the server, so the
  form works without JavaScript.
- [ ] `POST /items/{id}/borrow` → `BookingController::store()` →
  `BookingService::request()`:
  - the item is active, a rental and not yours;
  - you are an active member of its division;
  - the dates are real, start today or later, and overlap no open booking and no blocked
    range;
  - your balance covers the rental charge plus the buffer. If not, explain how to earn
    points or request aid (§19). No points move yet;
  - set `moderator_involved = 1` when the division's moderator is the lender or the
    borrower (§16.5);
  - save it as `requested` and notify the lender.
- [ ] The item page shows the borrow form only to active members of the item's division
  (I5).
- [ ] Wire `partials/modal-request-borrow.php` (dates, rate choice, message) and the inline
  form on `views/items/show.php`.

Read

- [x] My Bookings and the booking page.
- [ ] The booking page shows the message, the quote and the "Awaiting response" state
  (Figma "Booking — Awaiting Response").
- [ ] `BookingController::status()` handles `rejected`, the schema's value, instead of
  `declined` (I4).
- [ ] My Bookings shows 20 bookings a page (`Rules/CONVENTIONS.md` §9) (I22).

Update — the lender decides

- [ ] Accept: `POST /bookings/{id}/accept`, by the lender only, while `requested`, within
  24 hours. In one transaction:
  - lock the item's open bookings and check again for overlaps;
  - lock the borrower's wallet and check the balance again;
  - move the rental charge (`rental_charge`) and the buffer (`buffer_hold`) to In-Flight,
    linked to the booking;
  - set the status to `awaiting_handover` and fill `accepted_at`;
  - decline every other request for overlapping dates.

  Notify the borrower.
- [ ] Decline: `POST /bookings/{id}/decline` with an optional reason → `rejected`. Notify
  the borrower.

Delete — cancel

- [ ] `POST /bookings/{id}/cancel`:
  - the borrower can cancel while it is `requested`; nothing to refund;
  - either party can cancel while it is `awaiting_handover`; everything in In-Flight goes
    back to the borrower (`booking_refund`, §10.1).

  Either way, both can rate the cancellation (3.5).

Scheduled job — `scripts/expire_booking_requests.php`

- [ ] Requests with no answer after 24 hours become `auto_cancelled`, and both members are
  notified (Figma "Booking — Auto-Cancelled").

Tests — `tests/bookings.php`

- [ ] Quote: daily against monthly, the 27-day nudge, the buffer, a one-day booking.
- [ ] Overlaps, and the transition map (who may move a booking from which status to
  which).

### Handover photos and acceptance — Plan 3.2, §10.1

**Today:** the booking page shows only notes and photo counts. `handover_records` exists,
one row per booking.

- [ ] Create: `POST /bookings/{id}/handover/photos`. Each side uploads 1–5 stamped photos to
  `handover-photos/` with a condition note. The first upload creates the
  `handover_records` row.
- [ ] Read: both sides' photos (`partials/photo-grid.php`) and notes on the booking page,
  and who has accepted so far (Figma "Booking — Handover Step").
- [ ] Update: replace your own photos or note until you accept. Accept with
  `POST /bookings/{id}/handover/accept`, only once your photos are in. When both sides
  have accepted, in one transaction:
  - the record is locked as the baseline, with no further edits;
  - the rental charge moves from In-Flight to the lender (`rental_payout`);
  - the booking becomes `in_progress` and the item `borrowed`;
  - `handover_at` is filled.
- [ ] Delete: cancelling at handover is `POST /bookings/{id}/cancel` (3.1), with no penalty
  and a full refund to the borrower.
- [ ] Scheduled job `scripts/auto_cancel_handovers.php`: when the two sides have not both
  decided within 48 hours of the start date, the booking becomes `auto_cancelled`, the
  borrower is refunded and both are notified.
- [ ] Optional: `GET /bookings/{id}/handover-status` returns JSON for a new
  `public/js/polling.js`, so each side sees the other's acceptance without reloading
  (§21.1).
- [ ] Tests: 1–5 photos; you must upload before accepting; both accepting locks the record;
  no edits once locked.

### Return photos and acceptance — Plan 3.3, §10.2, §7.6

**Today:** nothing. `return_records` exists.

- [ ] Create: `POST /bookings/{id}/return/photos`. Each side uploads 1–5 stamped photos to
  `return-photos/` with a note. The booking becomes `awaiting_return`, and the first upload
  fills `return_at`.
- [ ] Read: the return photos next to the handover baseline (Figma "Booking — Return Step").
  The overdue warning already exists (Figma "Booking — Overdue").
- [ ] Update: the lender accepts the condition (`POST /bookings/{id}/return/accept`) or
  raises a claim (3.4). Accepting runs one transaction:
  - returned on time, by the end of `end_date` (the same rule `User::profileStats()` uses):
    the buffer goes back to the borrower (`buffer_refund`);
  - 1–24 hours late: the buffer goes to the lender (`late_fee`);
  - 25–72 hours late: the buffer, plus one daily rate per extra 24 hours taken from the
    borrower's wallet, goes to the lender (`late_fee`). The Reserve covers any shortfall;
  - the booking becomes `completed` (with `closed_at`) and the item `active` again;
  - both trust scores are recalculated (1.6), and both members are asked to rate.
- [ ] Delete: replace your own return photos until the lender decides.
- [ ] Scheduled job `scripts/flag_overdue_returns.php`: more than 72 hours late, flag the
  item as not returned and notify the moderator. Seven days late, open a total-loss claim
  on the moderator path (§7.6).
- [ ] Tests: the late-fee calculator (on time, 1–24 h, 25–48 h, 49–72 h, over 72 h) and the
  shortfall split.

### Damage claim — Plan 3.4, §10.3–10.6

**Today:** `partials/modal-damage-claim.php` exists, but no page includes it (I9).
`damage_claims` already has the two-track columns from migration 004.

- [ ] Migration: `evidence_photos JSON NULL` on `damage_claims`. The modal takes several
  photos; `evidence_path` holds one (I25).
- [ ] Create: the lender raises a claim at return with `POST /bookings/{id}/claims`, from
  `partials/modal-damage-claim.php` on the booking page (I9): severity, proposed penalty,
  description, 1–5 photos to `damage-evidence/`.
  - Simple path when it is Minor and the penalty is at most 20% of the declared value,
    rounded in the lender's favour (§7.1). Otherwise the moderator path.
  - A booking with `moderator_involved` goes to the Admin instead (§10.5 step 7).
  - The claim becomes `awaiting_borrower` or `pending_moderator`; on the moderator path
    the booking becomes `pending_moderator` too.
  - Notify the borrower.
- [ ] Read: a claim panel on the booking page with the baseline, the return photos and the
  evidence side by side (Figma "Booking — Pending Moderator").
- [ ] Update, borrower: Accept or Contest within 48 hours
  (`POST /damage-claims/{id}/accept`, `POST /damage-claims/{id}/contest`).
  - Accept on the simple path with enough points: the penalty moves from borrower to
    lender (`damage_penalty`), the buffer is settled, the booking is `completed` and the
    claim `closed`.
  - Accept without enough points: the claim moves to the moderator path (§19).
  - Contest: the moderator path.
- [ ] Update, sign-off on the moderator path: each side clicks "I agree with this
  resolution" (`POST /damage-claims/{id}/sign-off`). Once the lender, the borrower and the
  moderator have all signed, the recorded points move (the Reserve covers any shortfall)
  and the booking closes (§10.5). The moderator records the outcome on
  `views/moderator/cases/show.php`, in the moderator portal (I16).
- [ ] Delete: the lender withdraws before the borrower answers
  (`POST /damage-claims/{id}/withdraw`). The claim closes and the return counts as
  accepted.
- [ ] Scheduled job `scripts/escalate_unanswered_claims.php`: no answer within 48 hours →
  moderator path.
- [ ] Tests: choosing the track, rounding the 20% cap, the not-enough-points case, the
  transition map.

### Rating and review — Plan 3.5

**Today:** the ratings given and received read live data. The rate button is disabled, and
`partials/modal-rate-review.php` is not included anywhere (I9).

- [ ] Migration on `ratings`: `tags JSON NULL` (the modal has quick tags) and `updated_at`;
  unique keys on `(rater_id, booking_id)`, `(rater_id, donation_id)` and
  `(rater_id, gift_id)` (I26).
- [ ] Create: `POST /ratings`. Rules:
  - you are a party to a booking that is `completed`, `cancelled` or `auto_cancelled`,
    to a `completed` donation, or to a gift;
  - once per record;
  - 1–5 stars;
  - a comment of up to 500 characters.

  Then recalculate the ratee's trust score.
- [x] Read: the ratings given and received.
- [ ] Read: a "Waiting for your rating" list on `views/ratings/index.php`, and a prompt on
  the booking page.
- [ ] Update: edit your own rating within 7 days (`POST /ratings/{id}`).
- [ ] Delete: remove your own rating within 7 days (`POST /ratings/{id}/delete`), then
  recalculate.
- [ ] Put `partials/modal-rate-review.php` on the booking page and the ratings page, and
  remove the "Rating submission unavailable" button (I9).
- [ ] Tests: who may rate what, once per record, the 7-day window.

### Dispute (member side) — Plan 3.6, §10.4, §19

**Today:** members have no way to raise a dispute, and the admin dispute screens are
previews (I18).

- [ ] Create: `POST /bookings/{id}/disputes` with a reason. You must be a party to the
  booking, and raise it within 7 days of a simple-path acceptance (§10.4) or after
  refusing a moderator resolution (§19). One open dispute per claim. Notify the Admin.
- [ ] Read: the dispute's status and the Admin's ruling on the booking page.
- [ ] Update: edit the reason while it is `open` and no Admin has picked it up.
- [ ] Delete: withdraw while open → `closed`.
- [ ] The ruling itself is in the admin portal (`views/admin/disputes/show.php`) (I18).
- [ ] Tests: the 7-day window, one open dispute per claim.

---

## Phase 3 — Points features (group 4)

### Gifting — Plan 4.5, §11

**Today:** gift history and limits read live data, and the receive-gifts setting saves.
"Send a gift" is a disabled modal on Gifts and on Wallet.

- [ ] Create: `POST /gifts` → `GiftService::send()`, in one transaction. Rules:
  - the sender and the recipient are both active;
  - the recipient is not you, shares a division with you and accepts gifts;
  - the amount is at least 1 and stays within 200 a day and 2,000 a year. Check it after
    locking the sender's wallet, with the existing `Gift::sentToday()` and
    `sentThisYear()`; the lock stops two gifts slipping past the cap together.
    (`gift_usage_counters` from §21.4 is then not needed. If you keep it, update it in
    the same transaction.)
  - the reason is 1–100 characters;
  - the sender has no pending claim, open dispute or overdue return;
  - the recipient has no pending claim;
  - the sender's balance covers it.

  Then save the gift, move the points (`gift`, linked by `gift_id`) and notify the
  recipient. `displayForMember()` already shows `gift_received` notifications.
- [ ] `User::giftableExcept()` lists only members who share a division with the sender
  (I2). Decide whether moderators can receive gifts; today they are left out.
- [ ] Wire `partials/modal-send-gift.php` on Gifts and Wallet, and add "Send a gift" to the
  public member profile with the recipient already chosen (§18.4).
- [x] Read: sent and received history, with the limits.
- [ ] Read: gift history shows 20 a page (I22).
- [x] Update: the receive-gifts setting.
- [ ] Delete: none. Gifts cannot be reversed (§11.1); say so if the CRUD rule comes up.
- [ ] Scheduled job `scripts/detect_gift_patterns.php`: three round trips (A → B → A)
  within 30 days → notify the division's moderator (§19).
- [ ] Tests: daily and yearly caps, reason length, the blocking conditions.

### Aid grant (member side) — Plan 4.4, §12

**Today:** the status page reads live data. The request form is disabled, and moderator
vouching and Liaison approval are previews (I16, I17).

- [ ] Migration on `aid_grants`: `details TEXT NULL`, `evidence_photos JSON NULL`,
  `info_request VARCHAR(255) NULL`, `member_reply TEXT NULL`,
  `decision_reason VARCHAR(255) NULL` (I27).
- [ ] Create: `POST /aid-grants` → `AidGrantService::request()`. Rules:
  - you are an active member with no active grant. `AidGrant::activeForMember()` counts
    rejected grants as active, so this check needs its own status list (I6);
  - at least 60 days have passed since your last grant;
  - the amount fits within what is left of your 500 points this year;
  - the purpose comes from the list;
  - evidence photos go to `aid-evidence/`;
  - the grant belongs to your home division.

  Notify that division's moderator.
- [x] Read: the status page.
- [ ] Read: the cooling-period message (Figma "Aid Grant — Empty / Cooling").
- [ ] Update: edit while `requested`, or reply while `info_requested`
  (`POST /aid-grants/{id}/reply`).
- [ ] Delete: withdraw while `requested` or `info_requested` → `closed`.
- [ ] Needed from the other portals before a grant can finish:
  - moderator vouch or reject (`views/moderator/aid-vouching/index.php`) (I16);
  - Liaison approve, adjust, reject or ask for information
    (`views/sponsor-liaison/aid-grants/show.php`), moving points from the Aid Pool to the
    member (`aid_grant`) (I17);
  - `scripts/expire_aid_grants.php`, returning half of any unused amount after 30 days
    (`aid_return`).
- [ ] Tests: the yearly cap, one active grant, the 60-day cooling period.

### Notifications (member side) — Plan 4.6

**Today:** the list and its filters read live data. "Mark all as read" is disabled, and the
list is not split into pages (I22).

- [ ] Create: every module above sends its own types through `Notification::push()`.
- [ ] Read: 20 a page, and an unread count on the bell (I22).
- [ ] Update: opening one posts to `POST /notifications/{id}/read`, which marks it read and
  goes to its link. A GET must never change data (`Rules/CONVENTIONS.md` §7). Add
  `POST /notifications/read-all`.
- [ ] Delete: dismiss with `POST /notifications/{id}/delete`.
- [ ] Optional: `GET /notifications/unread-count` returns JSON for `public/js/polling.js`,
  polled every 8 seconds (§21.4).
- [ ] Note: sponsors share this page (`DemoController::notifications()` builds it for
  both), so test both roles.
- [ ] Tests: a member can only read or change their own notifications.

---

## Phase 4 — Trust score — Plan 1.6, §6.3 (group 1)

**Today:** the score shows on the dashboard, profiles and listings. The breakdown on
`/trust` is empty (`factors` is `[]`), and nothing recalculates `users.trust_score` (I14).

- [ ] `TrustScoreService::compute()`, static and pure:
  - weighted score: 0.40 R + 0.20 V + 0.20 L + 0.10 T + 0.10 C;
  - blend: (10 × 50 + n × weighted score) ÷ (10 + n);
  - penalties: −5 per upheld claim, −10 per past suspension, −5 per shortfall cover in the
    last 12 months;
  - clamp to 0–100 and round.
- [ ] Factor queries in the models:
  - average stars;
  - completed transactions;
  - on-time returns (rentals only);
  - months since verification;
  - donation contribution. Decide the scale, for example 20 points per completed
    donation, capped at 100.
- [ ] Past suspensions: no table records them yet (I14). Decide where a suspension is
  logged when the Admin suspends someone. Until then the term is 0, and the breakdown says
  so.
- [ ] `TrustScoreService::recalculate()` runs after every completed booking, donation and
  damage resolution, and whenever a rating changes.
- [ ] Scheduled job `scripts/refresh_trust_scores.php`, nightly: tenure and the 12-month
  window change without any event.
- [ ] `/trust` shows the five factors and the penalties. Fill `factors` in
  `DemoController::trust()`, or move the page to its own `TrustController`.
- [ ] Optional: a context line on profiles and listings, such as "★ 78 overall · 12
  completed in this division · 3 in temporary community" (§6.3.5).
- [ ] Tests: no transactions gives 50; worked examples; each penalty; clamping.

---

## Phase 5 — Finishing the member portal

- [ ] Dashboard: a "Needs your action" panel listing requests to answer, handovers to
  accept, claims to answer and ratings to give.
- [ ] Public member profile: open only active accounts (I7); add "Send a gift", the donor
  badge and the trust context line.
- [ ] Help: replace the disabled "Contact moderator" button with the division moderator's
  name and phone (I23). `User::homeModeratorName()` finds the name; extend it to return
  the phone.
- [ ] Wallet: split the activity into pages (I22) and add filters by
  `PointLedger::GROUPS`.
- [ ] Account closure: `AccountClosureService::blockers()` already refuses open bookings,
  pending claims and active aid grants. Test it again once those exist, and add open
  disputes and unfinished donation handovers.
- [ ] Correct the `Notification` and `Gift` docblocks (I30, I31).
- [ ] Remove the last preview markers. `grep -rnE "data-demo-form|preview-action" views partials`
  lists what is left (help, item page, ratings, notifications, donations, aid grants,
  community).
- [ ] Demo data: a new seed migration with one record in each new state (requested,
  awaiting handover, in progress, overdue, pending moderator, completed, donation
  requested, aid info requested, temporary pending).
- [ ] Update `docs/INTERIM_GUIDE.md` ("What works and what is still pending") and the
  README.
- [ ] End-to-end run with two members and the moderator: list → approve → request → accept
  → handover → return → rate, then a gift, a donation, an aid request and a temporary
  community. Extend `tests/ui-navigation.py` to walk the new pages.

---

## Definition of done (every module)

- [ ] Follows `Rules/CONVENTIONS.md`: thin controller, rules in a service, SQL in a model,
  escaped output, a CSRF token on every form, server-side validation.
- [ ] Every point movement goes through `LedgerService` inside one transaction, with
  wallets locked before balances are checked.
- [ ] `tests/<module>.php` and `php tests/router.php` pass, and CI runs both.
- [ ] No preview notes are left on the module's screens.
- [ ] The issues it fixes (I1–I31) are ticked.
- [ ] Another team member has reviewed the pull request
  (`.github/PULL_REQUEST_TEMPLATE.md`).
