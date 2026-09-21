#!/usr/bin/env bash
#
# Make and destroy a disposable copy of a company, for tests that provoke writes.
#
#   test_company.sh make <name> [--from <company>] [--server <container>]
#   test_company.sh drop <name> [--server <container>]
#   test_company.sh list
#
# WHY A COPY. The strict-mode suite works by provoking writes - posting journals,
# adjusting stock, printing labels - so it must not run against a company anyone
# cares about. The ledger tables are InnoDB and would roll back, but 24 others are
# MyISAM and would not, so "wrap it in a transaction" is not an answer.
#
# WHY ANOTHER SERVER. The application's own database user holds ALL on four named
# databases and nothing global, so it cannot CREATE DATABASE. fb-testdb (MariaDB,
# strict) and fb-mysql8 (MySQL 8, its own defaults) are throwaway servers whose
# root passwords we set, on the application's docker network so the app reaches
# them by hostname.
#
# BOTH HALVES. FreeBooks builds its company list by scanning my_files/, so a
# database without its directory is invisible and a directory without its database
# appears in the company selector and fails at login. make creates both; drop
# removes both.
#
# Loading a MariaDB dump into MySQL 8 is EXPECTED to fail today - zero-date
# defaults are refused at CREATE TABLE. That failure is a measurement, not a bug
# in this script, and it is reported rather than hidden.
set -uo pipefail

APP=freebooks-docker-php-app-1
SRC_DB=freebooks-docker-db-1
MY_FILES=/var/www/html/my_files

from=demo; server=fb-testdb
action="${1:-}"; [ $# -gt 0 ] && shift
# `list` takes no name, so only consume one if the next argument is not a flag.
name=""
if [ $# -gt 0 ] && [ "${1#--}" = "$1" ]; then name="$1"; shift; fi
while [ $# -gt 0 ]; do
  case "$1" in
    --from)   from="$2";   shift 2 ;;
    --server) server="$2"; shift 2 ;;
    *) echo "unknown option: $1" >&2; exit 2 ;;
  esac
done

# MySQL ships `mysql`, MariaDB ships `mariadb`; same arguments otherwise.
client() { case "$1" in fb-mysql8) echo mysql ;; *) echo mariadb ;; esac; }
CLI=$(client "$server")

# Filters the client's unavoidable password warning while KEEPING its exit status.
# Piping straight through grep loses it - and worse, grep exits 1 when it filters
# out every line, which under `pipefail` reads as the command having failed. That
# reported a perfectly good copy as broken.
target() {
  local out status
  out=$(docker exec -i "$server" "$CLI" -uroot -ptestroot "$@" 2>&1); status=$?
  printf '%s\n' "$out" | grep -v "Using a password" | grep -v '^$' || true
  return $status
}

case "$action" in
  list)
    echo "companies in the app container:"
    docker exec "$APP" sh -c "ls -1 $MY_FILES | while read d; do [ -f $MY_FILES/\$d/config.php ] && echo '  '\$d; done"
    for s in fb-testdb fb-mysql8; do
      docker ps --format '{{.Names}}' | grep -qx "$s" || { echo "$s: not running"; continue; }
      echo "$s databases:"
      docker exec -i "$s" "$(client $s)" -uroot -ptestroot -N -e 'show databases' 2>/dev/null \
        | grep -vE '^(information_schema|performance_schema|mysql|sys)$' | sed 's/^/  /'
    done
    ;;

  make)
    [ -n "$name" ] || { echo "usage: test_company.sh make <name>" >&2; exit 2; }
    docker ps --format '{{.Names}}' | grep -qx "$server" || {
      echo "$server is not running - start it first (docker start $server)" >&2; exit 1; }

    echo "==> creating database '$name' on $server"
    target -e "DROP DATABASE IF EXISTS \`$name\`;
               CREATE DATABASE \`$name\` DEFAULT CHARSET=utf8mb4;
               CREATE USER IF NOT EXISTS 'fbtest'@'%' IDENTIFIED BY 'fbtest';
               GRANT ALL ON \`$name\`.* TO 'fbtest'@'%'; FLUSH PRIVILEGES;"

    echo "==> copying '$from' into it"
    # Credentials come from the source company's own config, never retyped. Read
    # with sed rather than PHP: the values are wanted here in the shell, and a
    # php -r one-liner nested inside a command substitution inside a quoted eval
    # is three levels of quoting for no gain.
    cfg=$(docker exec "$APP" cat "$MY_FILES/$from/config.php")
    field() { printf '%s\n' "$cfg" | sed -n "s/.*define('$1'[^']*'\([^']*\)'.*/\1/p" | head -1; }
    SRC_HOST=$(field DB_SERVER_HOST)
    SRC_USER=$(field DB_SERVER_USERNAME)
    SRC_PASS=$(field DB_SERVER_PASSWORD)
    [ -n "$SRC_USER" ] || { echo "could not read credentials from $from/config.php" >&2; exit 1; }

    if ! docker exec "$SRC_DB" mariadb-dump -h"$SRC_HOST" -u"$SRC_USER" \
            -p"$SRC_PASS" --single-transaction --routines "$from" 2>/dev/null \
         | target "$name"; then
      echo "!! the copy reported errors - see above. On MySQL 8 that is expected today." >&2
    fi

    src_t=$(docker exec "$SRC_DB" mariadb -h"$SRC_HOST" -u"$SRC_USER" -p"$SRC_PASS" -N \
              -e "select count(*) from information_schema.tables where table_schema='$from'" 2>/dev/null)
    dst_t=$(target -N -e "select count(*) from information_schema.tables where table_schema='$name'" | tail -1)
    echo "    tables: source $src_t, copy ${dst_t:-0}"

    # A copy inherits the source company's credentials, and those reach real
    # services: the Zen Cart store, carrier and gateway APIs, the mail account.
    # A test company is for driving the application hard - page crawls, the form
    # robot posting every save three times - and none of that may leave the
    # container. Blanked here rather than in the tools, so it holds however the
    # copy is later used.
    echo "==> blanking outbound credentials in '$name'"
    target "$name" -e "
      UPDATE configuration SET configuration_value = '' WHERE configuration_value <> '' AND (
           configuration_key LIKE 'ZENCART!_%' ESCAPE '!'
        OR configuration_key LIKE 'EMAIL!_%'   ESCAPE '!'
        OR configuration_key LIKE '%!_API!_KEY'    ESCAPE '!'
        OR configuration_key LIKE '%!_API!_SECRET' ESCAPE '!'
        OR configuration_key LIKE '%!_ACCESS!_TOKEN%' ESCAPE '!'
        OR configuration_key LIKE '%!_CLIENT!_SECRET' ESCAPE '!'
        OR configuration_key LIKE '%!_ACCOUNT!_NUMBER%' ESCAPE '!'
        OR configuration_key LIKE '%!_REFRESH!_TOKEN' ESCAPE '!'
        OR configuration_key LIKE '%LOOKUP!_API%' ESCAPE '!'
        OR configuration_key LIKE 'MODULE!_PAYMENT!_%!_LOGIN' ESCAPE '!'
        OR configuration_key LIKE 'MODULE!_SHIPPING!_%!_USERID' ESCAPE '!'
        OR configuration_key LIKE 'MODULE!_SHIPPING!_%!_PASSWORD' ESCAPE '!');" 2>/dev/null
    left=$(target "$name" -N -e "SELECT COUNT(*) FROM configuration WHERE configuration_value <> '' AND (configuration_key LIKE '%API!_KEY' ESCAPE '!' OR configuration_key LIKE 'ZENCART!_%' ESCAPE '!');" 2>/dev/null)
    echo "    outbound credentials still set: ${left:-?}"

    echo "==> writing $MY_FILES/$name/config.php"
    docker exec "$APP" mkdir -p "$MY_FILES/$name"
    # The heredoc is expanded out here and piped in, rather than nested inside an
    # sh -c string where its quoting would have to survive two shells.
    docker exec -i "$APP" sh -c "cat > $MY_FILES/$name/config.php" <<PHPCONF
<?php
// Disposable test company - created by docs/tools/test_company.sh. Safe to delete.
// The _TITLE constant is not optional: the company selector calls
// constant("\$name_TITLE") for every directory under my_files, and a missing one
// is an uncaught Error that ends the request with an empty 200 - no page, no
// message, nothing in the browser to say what happened.
define('${name}_TITLE','Test copy (${name})');
define('DB_SERVER_USERNAME','fbtest');
define('DB_SERVER_PASSWORD','fbtest');
define('DB_SERVER_HOST','$server');
PHPCONF
    docker exec "$APP" chown -R www-data:www-data "$MY_FILES/$name"

    echo "==> done. Company '$name' is on $server."
    [ "${dst_t:-0}" = "${src_t:-1}" ] || echo "    NB table counts differ - the copy is incomplete."
    ;;

  drop)
    [ -n "$name" ] || { echo "usage: test_company.sh drop <name>" >&2; exit 2; }
    case "$name" in
      demo|production|production_archive|test)
        echo "refusing to drop '$name' - that is not a disposable company." >&2; exit 1 ;;
    esac
    echo "==> dropping database '$name' on $server"
    target -e "DROP DATABASE IF EXISTS \`$name\`;"
    echo "==> removing $MY_FILES/$name"
    docker exec "$APP" sh -c "rm -rf $MY_FILES/$name"
    echo "==> done. Both halves removed."
    ;;

  *)
    sed -n '3,9p' "$0"
    exit 2 ;;
esac
