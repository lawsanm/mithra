# Mithra UI and navigation audit

> Historical baseline, before fixes. The source and navigation issues below have since been addressed; see [the UI fix report](UI_FIXES.md) and [the latest HTTP results](UI_AUDIT_RECHECK.json). Browser visual and interaction verification remains pending.

Reviewed 17 September 2026 against the running application at `http://localhost/mithra/` and [the supplied Figma file](https://www.figma.com/design/m9EZDG6TpxnaBCFbHCiyQn/Design).

The UI does not yet fully match the design system or navigate reliably. Shared tokens are largely aligned, but two roles cannot load their stylesheet, several navigation destinations are broken, and many visible forms lack implemented actions.

## Scope and limitations

- Figma's file-level listing initially returned only **Design System** (`7:2`), but the user's direct page links successfully exposed all five role pages. Retrieved metadata for **101 top-level screen/state frames**: Admin 30, Member 41, Sponsor 7, Sponsor Liaison 12, Moderator 11. All enumerated top-level frames are 1440 × 1024. See the [full screen inventory](FIGMA_SCREEN_INVENTORY.md).
- Visually inspected Figma screenshots of all five dashboards and the shared navigation component, comparing their structure with the corresponding templates and CSS. Full application rendering and Figma prototype interactions remain unverified.
- Requested all 84 registered GET routes, substituting ID `1` for parameterized routes. 83 returned HTTP 200. `/items/1/edit` returned 403; an ownership restriction is not itself a navigation defect.
- Extracted and requested 215 unique local link/asset targets from those successful pages. 66 returned 404; one additional linked edit page returned 403. Counts include query-string variants, asset URLs, and link fallbacks; they are not counts of independent root causes.
- Found 72 rendered form occurrences targeting 51 distinct method/URL combinations with no registered action. These include repeated forms and documented demo functionality. Forms were inspected, not submitted.
- Existing router checks passed for all 89 GET/POST registrations. The existing conventions check passed with 14 advisory notes. Those checks do not validate the rendered links against their destinations.
- Browser control was unavailable. No rendered application screenshot comparison, responsive viewport test, keyboard test, or interactive modal test was completed. Findings below distinguish source/HTTP evidence from risks requiring browser verification.
- Application code and Figma were not changed. Only this report and its [supporting results](UI_AUDIT_DATA.json) were added.

## Findings, in priority order

### 1. Sponsor and liaison pages request missing CSS and navigate outside the application

**High — confirmed by HTTP and source.**

`partials/header-sponsor.php` and `partials/header-sponsor-liaison.php` load `/css/main.css`, which returns 404. Their navigation partials load `/img/logo-deep-slate.svg`, also 404. Links point to `/sponsor/...` and `/sponsor-liaison/...`, while this installation lives under `/mithra`.

Examples: opening the sponsor dashboard directly succeeds, but its Dashboard link requests `http://localhost/sponsor/dashboard` and returns 404. Liaison navigation has the same problem. Consequently these role pages cannot receive the shared styling from the linked stylesheet.

Use `base_url()` consistently in both headers, both navigation partials, and the sponsor/liaison views. Some detail targets also lack routes, so correcting the prefix alone is insufficient.

### 2. Moderator list links use slugs that the router rejects

**High — confirmed 404s.**

Examples:

- `/mithra/moderator/verifications/akalvily-a`
- `/mithra/moderator/listing-approvals/pressure-washer`
- `/mithra/moderator/cases/grinding-drill`

These appear in moderator dashboards and lists. `app/Core/Router.php` accepts positive numeric IDs for `{id}`, while the templates use names/slugs. The related detail templates also contain slug-keyed sample data. Align the routes, view data and link identifiers together.

### 3. Admin selection and member action links have no destination

**High — confirmed 404s.**

`views/admin/moderators/index.php` links to `/admin/moderators/appoint?division=...&member=...`, but the registered GET route is `/admin/moderators/appoint/{id}`.

Booking detail links to `/bookings/1/cancel` and `/messages/new` return 404. Help's `/messages/new?to=moderator` also returns 404. Moderator disaster report and relief links have no matching route.

Give these controls valid destinations, or make their interim unavailability clear before the user follows them.

### 4. Most action forms are presentation only

**High for end-to-end flows — confirmed from rendered forms and route definitions.**

The application registers five POST routes, all for Items. Examples with no POST handler include borrowing requests (`/bookings`), sending gifts (`/gifts`), profile updates (`/profile`), account/preferences settings, moderator decisions, admin approvals, and sponsor purchases.

This is partly documented interim scope, but successful page loading does not mean those user journeys work. Unsupported submissions resolve to 404 or 405 according to whether the path exists for another method. Implement the actions or explicitly disable/label demo controls. No submissions were made during this audit.

### 5. Booking navigation can show the wrong sample content

**Medium — confirmed source and HTTP evidence.**

`/bookings?role=borrower` and `/bookings?role=lender` returned identical HTML. `views/bookings/index.php` always supplies the same sample records and keeps the borrower tab active.

`views/bookings/show.php` defaults to the same drill booking regardless of the route ID and hard-codes several actions to booking `1`. Selecting the tent or projector can therefore display the drill details. Load data by the selected ID and derive tab state from the requested role.

### 6. Figma avatar menus are missing

**Medium — confirmed component/source mismatch.**

Figma's [member avatar menu](https://www.figma.com/design/m9EZDG6TpxnaBCFbHCiyQn/Design?node-id=794-80) includes Profile, Public Profile, Trust Score, Ratings, Wallet, Gifting History, Donations, Aid Grants, Community, Notifications, Settings, Transparency, Help and Log out.

The member avatar instead links directly to `/profile`; admin links directly to settings; sponsor and liaison avatars are noninteractive spans. No equivalent menu is implemented. This removes the designed common entry point to secondary screens. The shared Figma navigation component shows notification bells for sponsor and liaison and a different sponsor company name/avatar order. However, the individual dashboard frames omit those bells and use the implementation's sponsor order. This is a conflict within the design reference as well as a component-consistency gap; choose the canonical navigation before changing it.

### 7. Typography does not consistently use the compact app scale

**Medium — confirmed CSS/specification mismatch.**

The Figma design-system page specifies app page titles at **Inter Bold 22px**. Both `.page-intro__title` and `.page-header__title` use `--text-h1`, which is **32px**, despite the existing `--text-page-title: 22px` token. However, the individual dashboard frames also contain larger titles. The source conflicts with the compact scale, but blindly reducing all headings would not reproduce every supplied frame. Resolve the distinction between dashboard greetings and standard page titles first.

Inter is named in the font stack but no font file or `@font-face` is supplied. Devices without Inter installed will use a system fallback, changing text widths and wrapping. Supply the font locally if consistent Figma typography is required.

### 8. Responsive layout and wide-screen sizing require verification

**Medium risk — source evidence, not visually verified.**

The 1440px layout token is declared but unused by the page shell. Navigation has nonwrapping labels and fixed spacing; the main stat grid keeps four columns. The only media query adjusts `.two-col--wide-main`. There is no mobile navigation adaptation or general responsive adjustment for these components. Test at 1440px, tablet and mobile widths before claiming uniform responsive behavior. The supplied Figma reference specifies desktop dimensions, not a mobile design.

### 9. Dashboard structure and controls differ from the role frames

**Medium — confirmed Figma screenshot and template differences.**

- **Admin (`426:4`):** Figma places Cron job health inside one white bordered panel, with simple rows inside. `views/admin/dashboard/index.php` renders a bare section with independently bordered `.list-row` cards. The passed invariant appears in Figma as a small green pill inside a white card; code uses a full success notice. These produce a different grouping and hierarchy even with identical colors.
- **Moderator (`349:13`):** Figma shows Review and Approve buttons beside verification and listing-approval rows. The template renders only Review. The three non-first statistic values are blue in Figma but use default charcoal styling in code. Review links also suffer from the slug-routing defect above.
- **All role headers:** dashboard metadata uses 28px logo marks, whereas `.nav__logo` is 32px. Small individually, but a consistent source of horizontal/vertical misalignment.
- **Member:** the main dashboard sections and listing wizard stages are represented in the templates. Booking role tabs and record details still use sample content as described above. Content counts/names legitimately differ when supplied from the database and are not treated as visual defects by themselves.
- **Sponsor and liaison:** the main dashboard sections correspond to Figma, but the confirmed missing stylesheet and broken paths prevent these pages from presenting the intended experience in the current subfolder installation.

### 10. The Figma reference itself is not fully uniform

**Confirmed metadata/screenshot differences.**

- Sponsor dashboard navigation (`385:12`) is 64px high; the other reviewed dashboards and shared navigation component are 68px.
- Shared navigation includes notification bells absent from several role dashboard frames. Sponsor avatar treatment/order also differs between the shared component and the sponsor dashboard.
- Dashboard stat cards vary in height: Member 101px, Admin 102px, Sponsor/Liaison 108px, Moderator 111px. Metadata also shows different vertical padding/text heights. Decide which differences are intentional before standardizing.
- The compact title guidance and the larger dashboard greetings need an explicit rule rather than treating all supplied frames as one exact specification.

## What matches

- Core palette: primary `#2D6CA3`, accent `#E9A23B`, background `#F7F5F1`, surface `#FFFFFF`, text `#1C2733`, muted text `#5E6A74`, border `#E5E3DD`.
- Semantic status colors and extended brand scales are represented in the shared stylesheet.
- Page padding of 40px top / 48px sides / 64px bottom; 24px section spacing; 68px navigation height.
- Shared card, row and stat-card padding/radii broadly follow the documented components.
- All modal triggers found in the sampled rendered pages had matching element IDs. This verifies wiring references only, not click, focus or dismissal behavior.

## Remaining verification

The role screen references are now accessible. After fixing the confirmed defects and choosing canonical components where Figma conflicts with itself, use an available browser session to compare application screenshots with those frames, then exercise each role's navigation, tabs, forms, modals, focus states and narrow-screen layouts. A full screen-by-screen rendered/prototype match remains unverified because browser control is unavailable in this session.

## Independent recheck — 17 September 2026

The current recheck retrieved all five supplied Figma pages again and visually inspected their five dashboard screenshots, saved under `docs/audit-reference/`. These are Figma reference images, not screenshots of the running application. The 101-frame inventory remains unchanged. The Admin panel grouping and Moderator missing Approve buttons are confirmed against the current templates.

Re-ran the HTTP audit with `python scripts/audit-ui.py`; fresh evidence is in [UI_AUDIT_RECHECK.json](UI_AUDIT_RECHECK.json). All 84 GET route patterns were requested with numeric ID 1 where needed: 83 returned 200 and one owned-item edit request returned 403. Of 215 unique rendered link/asset destinations, 148 returned 200, 66 returned 404, and one returned 403. These counts include repeated query variants and assets, not 66 independent defects.

The recheck identified 72 unsupported POST form occurrences across 51 distinct destinations, plus two GET filter forms whose liaison actions omit `/mithra`. Thus the broader count including GET forms is 74 occurrences across 53 method/URL destinations. No forms were submitted and no application data was changed.

Both existing checks passed again: router checks covered 89 registrations, and the conventions check reported no hard-rule violations with 14 advisories. Browser control again reported that no browser was available, so application rendering, mobile layouts, keyboard access, modal interactions and Figma prototype transitions remain unverified. The application implementation was not edited during this recheck.
