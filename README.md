# FreeBooks

FreeBooks is an open-source ERP/accounting web application for small businesses, based on PhreeBooks 3.7.1 and updated for modern PHP and MariaDB.

## Deployment

FreeBooks can be deployed directly to any PHP 8.x-capable web server (Apache or Nginx) with a MySQL/MariaDB database — no Docker required. Deploy the `trunk/` directory as the web root, then visit `/install/` in a browser to run the installer.

For a containerised setup, see the [freebooks-docker](https://github.com/billj9000/freebooks-docker) repository.

## Direct (thermal) label printing — QZ Tray

FreeBooks supports printing shipping labels and POS receipts directly to thermal printers (Zebra, Eltron, etc.) without a print dialog. This requires the **QZ Tray** desktop application to be installed on each workstation that needs direct printing.

QZ Tray runs as a background service and accepts print jobs from the browser over a local WebSocket connection. Download it from [qz.io](https://qz.io/download/).

**Setup:**

1. Download and install QZ Tray on the workstation.
2. Launch QZ Tray — it runs in the system tray and starts automatically on login.
3. The first time a page attempts to print, the browser may ask permission to connect to QZ Tray. Allow the connection.
4. For unsigned local deployments (no certificate), open QZ Tray preferences and enable **Allow unsigned**.

**Printer name configuration:**

Each shipping method (FedEx, Endicia, UPS) and the POS till configuration has a **Printer Name** setting. Set this to the exact name of the printer as it appears in your operating system's printer list (e.g. `ZDesigner ZT230-203dpi ZPL`).

**Fallback behaviour:**

If QZ Tray is not running, direct print buttons will show an error and fall back to the browser's built-in PDF/print dialog via the report system. No data is lost.

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
