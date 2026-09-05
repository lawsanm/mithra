# Explaining Mithra at the interim

## A 30-second introduction

"Mithra helps people in a community lend and share items. Members use points for lending. We built it with plain PHP and MySQL, with HTML, CSS and JavaScript for the interface. The Items module has working database operations. The other screens demonstrate the wider system, and some read live data while others show sample content."

## The five parts to explain

| Part | Simple explanation | Example |
| --- | --- | --- |
| Route | Chooses which code runs for a URL | `/items/browse` calls `ItemController::browse()` |
| Controller | Reads the request and prepares the page | Reads the category filter |
| Service | Applies business rules before changing data | Checks item ownership before editing |
| Model | Reads or writes the database | `Item.php` runs prepared SQL |
| View | Displays the result | `views/items/browse.php` shows item cards |

A read-only page may call a model directly. A write uses the service when it needs business rules or several related operations. This keeps SQL, business rules and HTML in predictable places.

## Trace one page in the code

1. Open `http://localhost/mithra/items/browse`.
2. Apache sends the URL to `public/index.php` using `public/.htaccess`.
3. `index.php` starts the session and gives the request to `Router`.
4. The GET entry in `app/routes.php` selects `ItemController::browse()`.
5. The controller reads filters and asks the Item model for matching rows.
6. The controller prepares the values used by `views/items/browse.php`.
7. The view includes the shared header and footer and returns HTML to the browser.

For an edit, the browser submits a POST. Router first checks the CSRF token. The controller validates the input, the service checks ownership and rules, and the model saves the data. The browser then returns to the listing page.

## A short demonstration

1. Show the dashboard at `localhost/mithra` and explain the community points idea.
2. Open Browse and try a category or search filter.
3. Open an item and explain how its ID selects a database row.
4. Open My Items, then edit an item owned by the demo member. Explain validation and ownership checks. Saving this changes the local database.
5. Open the create-listing wizard to explain how details and photos are collected. Do not claim a new submission is immediately approved; it goes to moderator review.
6. Show an admin screen as the intended administrative interface. Explain that its write actions are still pending.

## What works and what is still pending

| Area | Current state |
| --- | --- |
| Items | Database-backed reads and create/edit/archive/pause/resume actions; photo processing and ownership checks |
| Member dashboard and gifts list | Read database data; sending gifts is not implemented |
| Bookings | Database-backed borrower/lender lists and record-specific details; lifecycle actions remain pending |
| Admin | Many database-backed read screens; actions such as approvals and settings saves remain pending |
| Other member, moderator, sponsor and liaison screens | Demonstration templates; many contain sample data |
| Shared interface | Consistent navigation, account menus, local Inter font, responsive rules and Figma logo/bell assets; browser visual verification remains pending |
| Identity | A configured demo member, not real login or role-based access enforcement |
| Points transfers, booking lifecycle and scheduled jobs | Do not present these as completed end-to-end features |

Seeded accounts have the development password `password`, but there is no login screen to authenticate them yet. Changing the demo member setting is not authentication.

Unimplemented submissions are marked as previews and disabled. Sponsor liaison search, filters, detail links and approved-grants CSV operate on sample records. See [the UI fix report](UI_FIXES.md) for verification and remaining limits.

## Common questions

**Why no framework?** The project requirements use plain PHP and JavaScript. It also lets us show the full request flow ourselves.

**Why PDO?** It connects PHP to MySQL and supports prepared statements, which keep submitted values separate from SQL instructions.

**What does `e()` do?** It escapes values before displaying them in HTML.

**What is CSRF?** A session token helps the server check that a submitted form belongs to the current browser session.

**Why is `public/` the web root?** The browser needs the entry point and assets. Database configuration, application source and uploaded originals stay outside that folder.

**Why does typing localhost/mithra work?** Apache serves the `mithra` folder in htdocs, which is a junction to this project's public folder. Apache and MySQL still need to be running; `run.cmd` starts them.

## What was cleaned up

- Replaced the large front controller with a short bootstrap and a small Router.
- Put URL definitions in one route table; removed duplicate Items routes.
- Removed the separate preview entry point and public preview-data file.
- Moved the active demo/admin data code into controllers and replaced the unused admin implementation.
- Removed unused model objects and redundant helper wrappers.
- Reused the existing error view instead of building duplicate HTML in routing code.
- Replaced separate setup/start instructions with one launcher that preserves existing data.
- Added routing regression checks without adding a test framework.

The existing screens, models, service rules and migrations were retained because they are still used or describe the project's planned modules.
