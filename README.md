# Mithra

Community Lending, Sharing & Caring Platform — vanilla PHP + CSS + JS, no
frameworks or libraries (see `Rules/CONVENTIONS.md`).

## Requirements

- [XAMPP](https://www.apachefriends.org/) (PHP 8.x + MySQL 8.x), or any local
  PHP and MySQL install.

## Quick start (Windows)

1. Clone the repo.
2. Open the XAMPP Control Panel and start **MySQL**.
3. Double-click **`setup.cmd`** — copies the config template and loads the
   database schema + demo data. Run it once per machine.
4. Double-click **`run.cmd`** — starts the dev server and opens
   <http://localhost:8123/> in your browser.

## Serving it from Apache at <http://localhost/mithra>

Prefer XAMPP's Apache to the built-in dev server? Do steps 1–3 above, then:

1. Double-click **`xampp-link.cmd`** — links `C:\xampp\htdocs\mithra` to this
   repo's `public/` folder. Once per machine; Apache then serves the working
   copy directly, so edits show on refresh.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open <http://localhost/mithra/>.

Both ways run the same front controller. Views never hard-code the prefix —
every link is written `href="<?= base_url() ?>/items"`, which renders as
`/items` on the dev server and `/mithra/items` behind Apache.

## Manual setup

If the scripts don't fit your machine (custom MySQL password, PHP outside
XAMPP), do the same steps by hand:

```cmd
copy config\config.example.php config\config.php
```

Edit `config\config.php` if your MySQL credentials differ, then load the
database (no mysql CLI needed — this uses PHP's PDO driver and the config
file's credentials):

```cmd
C:\xampp\php\php.exe scripts\migrate.php
```

Start the app from the project root:

```cmd
C:\xampp\php\php.exe -S localhost:8123 -t public public/router.php
```

## Notes

- `config/config.php` is **git-ignored** (`Rules/CONVENTIONS.md` §12) — every
  machine keeps its own copy and credentials are never committed. Until you
  create one, the app falls back to `config/config.example.php`, which holds
  stock XAMPP defaults only.
- The seed data is dev/demo only; every seeded account shares the password
  `password`.
- `public/router.php` is a temporary dev router for PHP's built-in server; it
  only serves static files and hands everything else to `public/index.php`,
  which does the same job Apache's `public/.htaccess` does.
- `public/index.php` is a stand-in front controller: it maps routes onto view
  files and reads through `public/preview-data.php`. It gets replaced by the
  real Router + controllers, at which point the preview files are deleted.
- Screens that don't exist yet (write actions such as `/bookings/1/cancel`)
  answer with a styled "no screen for this route" page, not a blank 404.
