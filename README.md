# FreeBooks

FreeBooks is an open-source ERP/accounting web application for small businesses, based on PhreeBooks 3.7.1 and updated for modern PHP and MariaDB.

See the [freebooks-docker](https://github.com/billj9000/freebooks-docker) repository for Docker deployment instructions.

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
