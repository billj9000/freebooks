# FreeBooks

FreeBooks is an open-source ERP/accounting web application for small businesses, based on PhreeBooks 3.7.1 and updated for modern PHP and MariaDB.

## Deployment

FreeBooks can be deployed directly to any PHP 8.x-capable web server (Apache or Nginx) with a MySQL/MariaDB database — no Docker required. Deploy the `trunk/` directory as the web root, then visit `/install/` in a browser to run the installer.

For a containerised setup, see the [freebooks-docker](https://github.com/billj9000/freebooks-docker) repository.

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
