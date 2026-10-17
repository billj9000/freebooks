#!/bin/sh
# +-----------------------------------------------------------------+
# |                          FreeBooks ERP                          |
# +-----------------------------------------------------------------+
# | Copyright(c) 2026 Bill Jones                                    |
# +-----------------------------------------------------------------+
# | This program is free software: you can redistribute it and/or   |
# | modify it under the terms of the GNU General Public License as  |
# | published by the Free Software Foundation, either version 3 of  |
# | the License, or any later version.                              |
# |                                                                 |
# | This program is distributed in the hope that it will be useful, |
# | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
# | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
# | GNU General Public License for more details.                    |
# +-----------------------------------------------------------------+
#  Path: /docs/tools/test_module_install.sh
#
# THIS WRITES TO THE DATABASE. Install and uninstall each module through the
# General Settings page, over HTTP, as a logged-in user.
#
# The module admin page is the only route by which a module is ever installed,
# and nothing else exercises it: the page crawl only GETs, and the form robot
# posts a save rather than an install_<module> action. So a module whose
# installer is broken stays broken until somebody tries to add it.
#
# Each module is taken through remove, install, remove - ending uninstalled.
# The middle step is the one under test; the first clears the way for it, and
# the last leaves the company as it was found. What counts as a failure is a
# message on the page, a new line in sql_errors.log, or a table the installer
# said it would create and did not.
#
#   docs/tools/test_module_install.sh <company> <user> <password> [module ...]
#
# Refuses any company whose database is not on the throwaway server, because
# removing a module drops its tables.

set -u
COMPANY="${1:-}"; USER="${2:-}"; PASS="${3:-}"
[ -n "$COMPANY" ] && [ -n "$USER" ] && [ -n "$PASS" ] || {
  echo "usage: test_module_install.sh <company> <user> <password> [module ...]" >&2; exit 2; }
shift 3

APP=${FREEBOOKS_APP_CONTAINER:-freebooks-docker-php-app-1}
BASE=${FREEBOOKS_URL:-http://localhost}
TEST_DB_HOST=${FREEBOOKS_TEST_DB_HOST:-fb-testdb}
LOG=/var/www/html/my_files/sql_errors.log

host=$(docker exec "$APP" sh -c "grep -oP \"DB_SERVER_HOST','\\\\K[^']*\" /var/www/html/my_files/$COMPANY/config.php" 2>/dev/null)
[ "$host" = "$TEST_DB_HOST" ] || {
  echo "REFUSING: '$COMPANY' is on '${host:-?}', not the throwaway server '$TEST_DB_HOST'." >&2
  echo "Removing a module drops its tables. Make a copy with test_company.sh." >&2; exit 2; }

JAR=$(mktemp)
trap 'rm -f "$JAR"' EXIT

login=$(curl -s -c "$JAR" "$BASE/index.php?module=phreedom&page=main")
tok=$(printf '%s' "$login" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
# The selector shows each company by its <name>_TITLE, which need not contain the
# directory name at all, so the title is read from the same config.php the
# application reads it from rather than guessed from the directory.
title=$(docker exec "$APP" sh -c "grep -oP \"${COMPANY}_TITLE','\\K[^']*\" /var/www/html/my_files/$COMPANY/config.php" 2>/dev/null)
co=$(printf '%s' "$login" | grep -oP "<option value=\"\K[0-9]+(?=\">[^<]*\Q${title}\E)" | head -1)
[ -n "$co" ] || co=$(printf '%s' "$login" | grep -oP "<option value=\"\K[0-9]+(?=\">[^<]*$COMPANY)" | head -1)
[ -n "$tok" ] && [ -n "$co" ] || { echo "could not reach the login page for '$COMPANY'" >&2; exit 1; }
curl -s -b "$JAR" -c "$JAR" -o /dev/null \
  -d "csrf_token=$tok&admin_name=$USER&admin_pass=$PASS&company=$co&language=en_us" \
  "$BASE/index.php?module=phreedom&page=main&action=validate"
csrf=$(curl -s -b "$JAR" -c "$JAR" "$BASE/index.php?module=phreedom&page=admin" \
       | grep -oP 'name="csrf-token" content="\K[a-f0-9]+' | head -1)
[ -n "$csrf" ] || { echo "login failed" >&2; exit 1; }
echo "logged in to $COMPANY as $USER"
echo

# An action, then the page again: messageStack keeps its messages in the session
# and renders them on the next request, so the POST's own response does not
# carry them.
act() {
  curl -s -b "$JAR" -c "$JAR" -o /dev/null -d "csrf_token=$csrf&action=$1" \
       "$BASE/index.php?module=phreedom&page=admin"
  curl -s -b "$JAR" -c "$JAR" "$BASE/index.php?module=phreedom&page=admin" \
    | grep -oE 'alert-(danger|warning)[^>]*>[^<]*' | sed 's/.*>//' | sed 's/&nbsp;//g' \
    | sed 's/^[[:space:]]*//' | grep -v '^$' | head -3
}

modules=${*:-$(ls "$(dirname "$0")/../../extensions/Modules")}
pass=0; fail=0
for m in $modules; do
  docker exec "$APP" sh -c ": > $LOG"
  act "remove_$m" >/dev/null 2>&1
  said=$(act "install_$m" 2>&1)
  status=$(docker exec fb-testdb mariadb -uroot -ptestroot -N -e \
    "select ifnull((select configuration_value from $COMPANY.configuration
      where configuration_key='MODULE_$(echo "$m" | tr '[:lower:]' '[:upper:]')_STATUS'),'not set')" 2>/dev/null)
  sqlerr=$(docker exec "$APP" sh -c "wc -l < $LOG" 2>/dev/null | tr -d ' ')
  if [ -n "$said" ] || [ "$status" = "not set" ] || [ "${sqlerr:-0}" != "0" ]; then
    fail=$((fail+1)); printf "  %-14s FAILED   status=%s\n" "$m" "$status"
    [ -n "$said" ]            && printf '%s\n' "$said" | sed 's/^/      /'
    [ "${sqlerr:-0}" != "0" ] && docker exec "$APP" sh -c "head -2 $LOG" | cut -c1-150 | sed 's/^/      sql: /'
  else
    pass=$((pass+1)); printf "  %-14s installed (%s)\n" "$m" "$status"
  fi
  act "remove_$m" >/dev/null 2>&1
done
echo
echo "$pass installed cleanly, $fail with something to say. All left uninstalled."
