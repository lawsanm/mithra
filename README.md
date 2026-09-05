# Mithra

A community lending and sharing project built with plain PHP, MySQL, HTML, CSS and JavaScript. No framework or package installation is needed.

## Run on Windows

1. Install XAMPP with PHP 8.1 or newer. The default location is `C:\xampp`.
2. Double-click **run.cmd** in this folder.
3. Open **http://localhost/mithra/**.

The launcher creates your local config if missing, configures Apache, starts Apache and MySQL, and imports the schema and demo data only when the database is empty. Existing data is kept. `setup.cmd` is a shortcut to the same launcher.

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
  -> app/Core/Router.php    matches the URL and checks form tokens
  -> Controller            reads input and prepares the response
  -> Service               checks business rules for item changes
  -> Model                 runs prepared SQL queries through PDO
  -> View + partials       display the HTML page
```

Items supports database-backed listing, browsing, creation, editing, pausing, resuming and archiving. The member dashboard, gifts list, booking lists/details and many admin screens read database data. Other screens still use sample data. Actions without a backend are visibly disabled or marked as previews. Login and role enforcement are not implemented; the member identity comes from `demo_member_id` in the local config. This is an interim demonstration, not a finished deployment.

See [the UI fix report](docs/UI_FIXES.md) for the changes across all five roles, verification results and remaining visual checks.

## Useful files

| File or folder | Purpose |
| --- | --- |
| `app/routes.php` | All GET/POST route registrations |
| `app/Controllers/ItemController.php` | Working Items requests |
| `app/Controllers/BookingController.php` | Read-only member bookings and record-specific details |
| `app/Controllers/SponsorLiaisonController.php` | Filtering and navigation for liaison sample records |
| `app/Services/ItemService.php` | Item validation, ownership and state changes |
| `app/Models/` | Database queries |
| `app/Controllers/DemoController.php` | Member dashboard/gifts data and other demo pages |
| `app/Controllers/AdminController.php` | Read-only admin page data |
| `views/`, `partials/` | Pages and shared header/navigation/modals |
| `public/css/main.css`, `public/js/` | Styling and small browser interactions |
| `config/config.php` | Local database credentials; excluded from Git |
| `migrations/` | Database schema and sample data |

## Checks

```cmd
C:\xampp\php\php.exe tests\router.php
C:\xampp\php\php.exe .github\scripts\conventions-check.php
```

The router checks run without MySQL. They check every route's target, numeric IDs, exact-route priority, unsupported methods and invalid form tokens.

With the local application and database running at `http://localhost/mithra/`, run these read-only HTTP checks (Python required):

```cmd
python tests/ui-navigation.py
python scripts/audit-ui.py
```

These check routes, links, assets, selected records, filters and HTML interaction wiring. They do not render pages or simulate browser interactions. The audit writes `docs/UI_AUDIT_RECHECK.json`.

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
