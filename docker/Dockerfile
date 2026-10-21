# FreeBooks application image: Apache + PHP + cron, serving trunk/.
#
# Build from the repository root, which is the build context:
#
#   docker build -f docker/Dockerfile -t freebooks .
#
# The image holds exactly the committed trunk/ - vendor/ included, so no
# dependency is resolved at build time. Optional modules from extensions/ are
# not in it; add them in a derived image (see the freebooks-docker README).
#
# Runtime state lives in two volumes: config/ (configure.php) and my_files/
# (company data). A platform with a single volume mounts it at fly_data/
# instead - see entrypoint.sh.

FROM php:8.5.12-apache-bookworm

RUN apt-get update && apt-get install -y libbz2-dev libzip-dev libpng-dev libjpeg-dev cron && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-jpeg && docker-php-ext-install pdo pdo_mysql zip bz2 gd

WORKDIR /var/www/html

RUN a2enmod rewrite

# Honour the application's per-directory .htaccess access controls. The base
# php:apache image ships AllowOverride None, which silently ignores the
# .htaccess files under my_files/, includes/, modules/ and themes/ that deny
# direct HTTP access to PHP internals, database backups and company uploads.
# It also enables directory indexing (Options Indexes), which would let anyone
# browse and download the backup archives written to my_files/backups/.
RUN printf '<Directory /var/www/html/>\n    AllowOverride All\n    Options -Indexes +FollowSymLinks\n    Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/freebooks-hardening.conf \
    && a2enconf freebooks-hardening

# upload_tmp_dir is set explicitly so the installer's ini_get() check passes;
# TCPDF also takes its image cache directory from it.
RUN echo "upload_tmp_dir = /tmp" > /usr/local/etc/php/conf.d/uploads.ini
# Company backup restore accepts a single archive (SQL + files) up to 1GB;
# post_max_size is padded above upload_max_filesize to cover multipart overhead.
RUN printf "upload_max_filesize = 1024M\npost_max_size = 1088M\nmemory_limit = 256M\n" > /usr/local/etc/php/conf.d/restore_uploads.ini
# The Forms designer submits large forms.
RUN echo "max_input_vars = 5000" > /usr/local/etc/php/conf.d/input_vars.ini
# FreeBooks uses neither; both are paths to CVE-2026-109619 (issue #125).
RUN printf "session.upload_progress.enabled = 0\nfatal_error_backtraces = 0\n" > /usr/local/etc/php/conf.d/hardening.ini
RUN printf "display_errors = Off\nerror_reporting = E_ALL & ~E_NOTICE\nlog_errors = On\nerror_log = /var/log/apache2/php_errors.log\n" > /usr/local/etc/php/conf.d/errors.ini

COPY trunk/ ./

# Developer and maintenance tools (help reindex, language checks), kept outside
# the web root so they are not reachable over HTTP.
COPY docs/tools/ /opt/freebooks/tools/

# configure.php lives in its own volume directory and is symlinked into
# includes/. PHP's file_exists() returns false on a broken symlink, so the
# installer runs until the file is written.
RUN mkdir /var/www/html/config && \
    chown www-data:www-data /var/www/html/config && \
    ln -s /var/www/html/config/configure.php /var/www/html/includes/configure.php
RUN chown www-data:www-data includes/ my_files/
# Pristine copies of config/ and my_files/ seed a single-volume platform on
# first boot (see entrypoint.sh). Docker Compose mounts the two directly.
RUN cp -a /var/www/html/config /var/www/html/.defaults-config && \
    cp -a /var/www/html/my_files /var/www/html/.defaults-my_files

COPY --chown=root:root docker/crontab /etc/cron.d/freebooks
RUN chmod 0644 /etc/cron.d/freebooks
COPY --chown=root:root docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ARG FREEBOOKS_VERSION=dev
ARG FREEBOOKS_REVISION=unknown
LABEL org.opencontainers.image.title="FreeBooks ERP" \
      org.opencontainers.image.source="https://github.com/billj9000/freebooks" \
      org.opencontainers.image.licenses="GPL-3.0-or-later" \
      org.opencontainers.image.version="${FREEBOOKS_VERSION}" \
      org.opencontainers.image.revision="${FREEBOOKS_REVISION}"

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
