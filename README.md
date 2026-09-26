# FreeBooks

FreeBooks is an open-source ERP/accounting web application for small businesses, based on PhreeBooks 3.7.1 and updated for modern PHP and MariaDB.

## Deployment

FreeBooks can be deployed directly to any PHP 8.x-capable web server (Apache or Nginx) with a
MariaDB database — no Docker required. Deploy the `trunk/` directory as the web root, then visit
`/install/` in a browser to run the installer.

The installer does not create the database; create it first, and name the collation when you do:

```sql
CREATE DATABASE freebooks CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Every table FreeBooks creates names its own collation, so this only governs tables created without
one — but left unnamed, MariaDB picks `utf8mb4_uca1400_ai_ci`, which MySQL does not have, and a dump
of such a table will not restore there. The General Settings → System Information tab reports the
collation the database is actually on.

**Use MariaDB. MySQL 8 is not supported yet**, though it is a goal for the 1.0.0 release. The
schema itself now builds there, but MySQL enables `ONLY_FULL_GROUP_BY` by default and some of the
application's queries do not yet satisfy it, so pages would fail after a successful install. This
note will say otherwise when that work is finished.

For a containerised setup, see the [freebooks-docker](https://github.com/billj9000/freebooks-docker) repository.

## Upgrading from PhreeBooks

FreeBooks upgrades a **PhreeBooks 3.7.1** database. Deploy `trunk/` over the existing installation,
keep the existing `includes/configure.php` and `my_files/`, and log in: the schema is brought up to
date on the first login, and the modules record the new version as they go.

Take a backup first. The upgrade is designed to be safe to re-run if it stops part way — every step
checks whether it has already been applied — but a backup is the only thing that undoes a decision
you would rather not have made.

PhreeForm is replaced by **Forms**, which the upgrade installs. Existing reports and forms carry
over as they are. Anyone who could use PhreeForm can run and write reports in Forms; permission to
define datasets — which tables a report may join — is new, and an administrator grants it under
Company → Roles.

Older PhreeBooks releases are not supported directly. Upgrade to 3.7.1 with PhreeBooks' own
updater first, then to FreeBooks. The code to migrate a 2.x or 1.3.x database is still present and
may well work, but it has never been tested here and is not something to attempt on data that
matters.

## Direct (thermal) label printing — QZ Tray (optional)

By default, FreeBooks prints shipping labels, POS receipts, and inventory labels through the browser's normal print dialog — no extra software is required.

**QZ Tray** is an optional desktop application that lets FreeBooks send print jobs straight to a thermal printer (Zebra, Eltron, etc.), skipping the print dialog entirely. It's only needed on workstations that print directly to thermal hardware; everyone else can ignore this section.

QZ Tray runs as a background service and accepts print jobs from the browser over a local WebSocket connection. Download it from [qz.io](https://qz.io/download/).

**Setup:**

1. Download and install QZ Tray on the workstation.
2. Launch QZ Tray — it runs in the system tray and starts automatically on login.
3. The first time a page attempts to print, the browser may ask permission to connect to QZ Tray. Allow the connection.
4. For unsigned local deployments (no certificate), open QZ Tray preferences and enable **Allow unsigned**.

**Printer name configuration:**

Each shipping method (FedEx, Endicia, UPS) and the POS till configuration has a **Printer Name** setting. Set this to the exact name of the printer as it appears in your operating system's printer list (e.g. `ZDesigner ZT230-203dpi ZPL`).

**Without QZ Tray:**

QZ Tray is entirely optional. If it isn't installed, or isn't running, FreeBooks automatically falls back to printing through the browser's normal print dialog — labels and receipts still print, no data is lost, and no configuration is needed to enable this fallback; it's the default behaviour.

## Maintenance scripts

`trunk/cron/` contains standalone CLI scripts, each documented in full in its own header comment (usage, flags, and behavior):

- **`zencart_sync.php`** — imports new ZenCart orders as FreeBooks Sales Orders. Scheduled every 15 minutes.
- **`update_currency_rates.php`** — updates currency exchange rates from the ECB daily feed. Scheduled daily.
- **`backfill_vendor_sku.php`** — one-off tool to populate `vendor_sku` on inventory items from existing vendor descriptions, optionally stripping the part number back out of the description. Not scheduled; run manually with `--apply` when needed.

Run any script with `php trunk/cron/<script>.php [company_name]` — see each file's header comment for its full set of flags and options.

## Logs

FreeBooks writes three logs to `my_files/`: `errors.log` (PHP warnings and fatals), `sql_errors.log` (statements the database refused) and `empty_column_writes.log` (blank values written to columns that cannot hold one).

Each rotates at 8 MB: the file becomes `<name>.1`, replacing any previous `.1`, so a log occupies at most 16 MB and the newest entries are the ones kept. To change the size, add `define('LOG_MAX_BYTES', <bytes>);` to `trunk/includes/configure.php`; set it to `0` to let a log grow without limit.

## Post-install security

After completing installation, **remove the `install/` directory** from the web root. Leaving it accessible allows anyone to re-initialise the database.

The installer will refuse to run if it detects that the application is already configured, but the directory should still be removed:

**Docker deployment:**
```bash
docker compose exec php-app rm -rf /var/www/html/install
```

**Direct deployment (Apache/Nginx):**
```bash
rm -rf /path/to/webroot/install
```
