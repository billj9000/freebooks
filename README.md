# FreeBooks

FreeBooks is an open-source ERP/accounting web application for small businesses, based on PhreeBooks 3.7.1 and updated for modern PHP and MariaDB.

## Deployment

FreeBooks can be deployed directly to any PHP 8.x-capable web server (Apache or Nginx) with a MySQL/MariaDB database — no Docker required. Deploy the `trunk/` directory as the web root, then visit `/install/` in a browser to run the installer.

For a containerised setup, see the [freebooks-docker](https://github.com/billj9000/freebooks-docker) repository.

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
