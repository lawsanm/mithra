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
  goes away once `public/index.php` dispatches real routes.
