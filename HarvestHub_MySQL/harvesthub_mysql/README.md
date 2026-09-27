# HarvestHub — Phase 3 Prototype (MySQL)

A working slice of HarvestHub covering public registration (with admin
approval), login, all three role dashboards (Administrator, Garden
Coordinator, Community Gardener), and the Produce Exchange Board — now
running on a real MySQL database, matching Section IX of the paper, so
it's ready to deploy on real hosting.

The visual design (forest-green + earthy brown palette, Fraunces/Inter
font pairing, hairline-divider cards instead of shadowed boxes) takes
cues from [pacificag.com](https://pacificag.com/) and is applied
consistently across every page.

## Setting up the database

The schema and demo data live in **`schema.sql`** at the project root.
You import this **once** — after that, `db.php` just connects to the
database that already exists; nothing is created or migrated at
runtime anymore.

### XAMPP (local)
1. Start Apache **and MySQL** from the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. Click **Import** → choose `schema.sql` → click **Go**.
   This creates the `harvesthub` database with all 11 tables and the
   demo accounts/sample data.
4. `db_config.php` already matches XAMPP's defaults (host `localhost`,
   user `root`, empty password) — no editing needed for local use.

### Updating an existing database
The coordinator inventory records require additional transaction columns.
Run this once in phpMyAdmin's SQL tab for an existing installation; fresh
installations receive the columns from `schema.sql` automatically:
```sql
ALTER TABLE RESOURCE_TXN
  ADD COLUMN PltID INT NULL AFTER ResourceID,
  ADD COLUMN ApprovedAt DATETIME NULL AFTER RequestedAt,
  ADD COLUMN ReturnRequestedAt DATETIME NULL AFTER ApprovedAt,
  ADD COLUMN ReturnedAt DATETIME NULL AFTER ReturnRequestedAt,
  ADD CONSTRAINT fk_resource_txn_plot FOREIGN KEY (PltID) REFERENCES PLOT(PltID);
```

### Real hosting (later)
1. Create a MySQL database through your host's control panel (cPanel,
   Plesk, etc.) and note the host, database name, username, and
   password they give you.
2. Import `schema.sql` through their phpMyAdmin (or
   `mysql -u USER -p DBNAME < schema.sql` if you have shell/SSH access).
   Note: most hosts auto-prefix database/user names — if so, remove
   the `CREATE DATABASE` / `USE` lines at the top of `schema.sql` and
   import directly into the database they created for you.
3. Edit **`db_config.php`** with the real host/dbname/user/password.
4. Upload everything and point your domain at the `public/` folder
   (this is your document root — `db.php` and `schema.sql` should sit
   one level above it, not inside it).

## How to run it locally

### Option A — PHP's built-in server
```
cd public
php -S localhost:8000
```
Open `http://localhost:8000/login.php`.

### Option B — XAMPP
1. Copy the whole `harvesthub` folder (not just `public`) into
   `htdocs` (e.g. `C:\xampp\htdocs\harvesthub`).
2. Complete the database setup above.
3. Start Apache from the XAMPP Control Panel.
4. Open `http://localhost/harvesthub/public/login.php`.

## Demo accounts

All demo accounts use the password `demo1234`.

| Role | Email |
|---|---|
| System Administrator | admin@harvesthub.test |
| Garden Coordinator | coordinator@harvesthub.test |
| Community Gardener | maria@harvesthub.test (has a plot) |
| Community Gardener | jun@harvesthub.test (has a plot) |
| Community Gardener | liza@harvesthub.test (no plot yet — good for testing "apply for a plot") |

New accounts can also be created via **"Create an Account"** on the
login page — these sit as a Pending signup request until an
Administrator approves them from the Admin dashboard.

## Pages

| Page | Role | What it does |
|---|---|---|
| `login.php` | Public | Role-tabbed login (Gardener / Coordinator / Admin) + link to registration |
| `register.php` | Public | Sign-up form; creates a pending request, not an account directly |
| `admin_dashboard.php` | Administrator | System stats, pending sign-up approvals, manage gardener/coordinator accounts, create coordinator accounts |
| `staff_dashboard.php` | Garden Coordinator | Overview of pending requests, available plots, and resources |
| `staff_plots.php` | Garden Coordinator | Review plot applications and unassignment requests; create/delete plots and view their status on the plot map |
| `staff_inventory.php` | Garden Coordinator | Approve/reject resource requests and view inventory and borrowers |
| `staff_records.php` | Garden Coordinator | Review the chronological resource borrow and return timeline |
| `customer_dashboard.php` | Community Gardener | View/apply for/unassign a plot, log crops, request resources, the Produce Exchange Board (search/filter/sort, post & claim listings) |

## What changed in this version

- **Database engine**: SQLite → MySQL. `db.php` now just opens a PDO
  connection using the credentials in `db_config.php`; all table
  creation lives in `schema.sql`, imported once via phpMyAdmin.
- **Fixed a install-breaking bug**: the old `db.php` had a stray line
  outside any `CREATE TABLE` statement that caused a fatal SQL syntax
  error on any brand-new database — confirmed by testing a fresh
  install before this fix. Not an issue anymore since `schema.sql`
  replaces that runtime table-creation logic entirely.
- **Fixed a validation mismatch**: `register.php`'s city dropdown
  included all 17 Metro Manila cities/municipalities, but the
  server-side whitelist in `api.php` was missing "Pateros," causing a
  false rejection. Added.
- **Fixed MySQL-incompatible SQL**: the `all_resources` query used
  SQLite's `||` string concatenation and a two-argument `GROUP_CONCAT`
  call — neither works on MySQL. Rewritten with `CONCAT()` and
  MySQL's `GROUP_CONCAT(... SEPARATOR ...)` syntax. Also replaced
  `datetime('now')` (SQLite-only) with `NOW()`.

## Security notes

- Passwords are hashed with `password_hash()` / verified with `password_verify()`.
- Every write action re-validates on the server, independent of client-side checks.
- All SQL uses prepared PDO statements; sort order and city whitelists come from server-side constants, never interpolated user input.
- Every dashboard and every sensitive API action checks the session role (`requireRole()` for pages, `requireJsonRole()` for API actions).
- Output is escaped with `htmlspecialchars()` before storage/render to guard against XSS.
- `db_config.php` holds a real database password once you deploy — keep it out of any public repo.

## Verification

Every API action was tested end-to-end against a real MySQL instance
during development: all three role logins, listing creation/claiming,
plot application/approval/unassignment, crop logging, resource
request/approval (including the fixed `all_resources` query), plot
creation/deletion (with its occupied-plot safeguard), account
registration with every NCR city including Pateros, signup approval,
and account deletion. Data was also confirmed to persist correctly
across a database restart.
