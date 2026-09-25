# Mithra

A community lending and sharing project built with plain PHP, MySQL, HTML, CSS and JavaScript. No framework or package installation is needed.

## Run on Windows

1. Install XAMPP with PHP 8.1 or newer. The default location is `C:\xampp`.
2. Double-click **run.cmd** in this folder.
3. Open **http://localhost/mithra/**.

The launcher creates your local config if missing, configures Apache, starts Apache and MySQL, and applies any migration this database has not seen yet. Existing data is kept. `setup.cmd` is a shortcut to the same launcher.

If your database was created before September 2026, `run.cmd` adopts it and applies `003_add_identity_constraints.sql` on the next start. Signing in by mobile number needs that migration.

After startup, you can close the command window and just type `localhost/mithra` in your browser. Apache and MySQL must remain running. After restarting Windows, double-click `run.cmd` again, or start both services in XAMPP. Stop them using the XAMPP Control Panel.

The launcher links `C:\xampp\htdocs\mithra` to this project's `public/` folder. The normal XAMPP page stays at `localhost`; Mithra lives at `localhost/mithra`. Application source, configuration and uploads remain outside the web root. If you used the earlier root-site launcher, its Apache override is removed after making a backup.

For a different XAMPP location:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-local.ps1 -XamppRoot D:\xampp
```

## Understand the code

Read [the interim presentation guide](docs/INTERIM_GUIDE.md) for a short explanation, a walkthrough and the current feature limits.

```text
Browser URL
  -> public/index.php       starts the session and dispatches the request
  -> app/routes.php         maps each URL to its action or demo view
  -> app/Core/Router.php    matches the URL, then runs Auth -> RBAC -> CSRF -> session checks
  -> Controller            reads input and prepares the response (all extend app/Core/Controller.php)
  -> Service               checks business rules before data changes
  -> Model                 runs prepared SQL queries through PDO
  -> View + partials       display the HTML page
```

Working modules:

- **Identity** — registration, moderator verification, sign-in, password reset and change, profile, address changes and account closure.
- **Items** — listing, browsing, a four-step create wizard, editing, pausing, resuming and archiving, with the moderator's declared-value review.
- **Divisions (Admin)** — create, edit and archive GN divisions.
- **Sponsors (Sponsor Liaison)** — list, search, sort and filter sponsors; onboard, view, edit, deactivate and reactivate them.

The member dashboard, gifts list, bookings and the admin screens read live data. Screens for modules that are not built yet are design previews with their own sample content, and their actions are visibly disabled. Every route outside the sign-in pages needs a session, and each path is limited to the roles declared in `app/routes.php`. This is an interim demonstration, not a finished deployment.

## Useful files

| File or folder | Purpose |
| --- | --- |
| `app/routes.php` | All GET/POST route registrations |
| `app/Controllers/AuthController.php` | Sign-up, sign-in and sign-out |
| `app/Services/RegistrationService.php` | What a valid application is, and the pending account it creates |
| `app/Services/VerificationService.php` | The moderator's approve/reject of a new member |
| `app/Middleware/AuthMiddleware.php`, `RbacMiddleware.php` | Who may reach which routes |
| `app/Core/Controller.php` | What every controller shares: render, flash, redirect, refusal pages |
| `app/Controllers/ItemController.php` | Working Items requests |
| `app/Controllers/AdminController.php` | Admin screens and the division CRUD |
| `app/Services/DivisionService.php` | Division rules: unique names per district, archiving |
| `app/Controllers/BookingController.php` | Read-only member bookings and record-specific details |
| `app/Controllers/SponsorLiaisonController.php` | The liaison's sponsor CRUD, and a design preview of contributions and aid grants |
| `app/Services/SponsorService.php` | Sponsor rules: contact formats, unique company names, deactivating |
| `app/Controllers/DemoController.php` | Member dashboard and gifts data, and the design-preview pages |
| `app/Services/ItemService.php` | Item validation, ownership and state changes |
| `app/Models/` | Database queries |
| `views/`, `partials/` | Pages, the one shared header/navigation, and modals |
| `public/css/main.css`, `public/js/` | Styling and small browser interactions |
| `config/config.php` | Local database credentials; excluded from Git |
| `migrations/` | Database schema and sample data |

## Checks

```cmd
C:\xampp\php\php.exe tests\router.php
C:\xampp\php\php.exe tests\identity.php
C:\xampp\php\php.exe tests\items.php
C:\xampp\php\php.exe tests\sponsors.php
C:\xampp\php\php.exe tests\disaster-relief.php
C:\xampp\php\php.exe .github\scripts\conventions-check.php
```

These test files run without MySQL. The router checks cover every route's target, numeric IDs, exact-route priority, unsupported methods, invalid form tokens and which paths a signed-out visitor may reach. The identity checks cover mobile and NIC normalisation, password handling and the refusals behind sign-in. The listing checks cover the declared-value proof tiers, and the sponsor checks cover how an onboarding or edit form is stored and which contact details are refused. The disaster relief checks cover how a relief form is stored and which types, household counts and dates are refused.

With the local application and database running at `http://localhost/mithra/`, run these read-only HTTP checks (Python required):

```cmd
python tests/ui-navigation.py
```

This checks routes, links, assets, selected records, filters and HTML interaction wiring. It signs in first — as the demo member, and as the moderator for the verification screens — because every screen is behind the sign-in check. It does not render pages or simulate browser interactions.

## Troubleshooting

- **MySQL unavailable:** check XAMPP and the credentials in `config/config.php`. The supplied migrations create the database named `mithra`.
- **Apache already running after a configuration change:** stop and start Apache in XAMPP, then run `run.cmd` again.
- **Port 80 in use:** stop the conflicting web server before starting Apache.
- **Access denied writing Apache config:** run `run.cmd` as administrator if your XAMPP directory requires it.
- **Page fails:** inspect `C:\xampp\apache\logs\error.log`.
- **htdocs/mithra already exists elsewhere:** the launcher will not overwrite another project. Resolve that folder conflict before running again.
- **The old root-site setup was installed:** the launcher removes only its own marked Apache block and keeps a timestamped backup beside `httpd-vhosts.conf`. Restart Apache if prompted.

Optional development server (start MySQL first):

```cmd
C:\xampp\php\php.exe -S localhost:8123 -t public public/router.php
```

This alternative uses http://localhost:8123/ and does not require changing Apache.
