#!/usr/bin/env bash
#
# Assert that the container is running the code in the working tree.
#
#   provenance.sh            check, and print what differs
#   provenance.sh --quiet    say nothing unless something is wrong
#
# WHY. The image is a COPY of a build context that is kept in step with trunk by
# hand, so it drifts. A suite that cannot say which code it ran is a suite whose
# green means nothing; this is the check that makes the claim.
#
# It stays useful after the bind mount: the mount is a convenience, this is the
# assertion. If someone runs the stack without the override, or a path is missing
# from it, this says so instead of the results quietly being wrong.
#
# Files present in the container but not in trunk are NOT failures: fifteen
# optional modules, two carriers, one payment method and the Zen Cart overrides
# are copied in from extensions/ to activate them. They are counted, not judged.
set -uo pipefail

APP=${FREEBOOKS_APP_CONTAINER:-freebooks-docker-php-app-1}
WEBROOT=/var/www/html
TRUNK="$(cd "$(dirname "$0")/../../trunk" && pwd)"
quiet=false; [ "${1:-}" = "--quiet" ] && quiet=true

say() { $quiet || echo "$@"; }

cd "$TRUNK" || { echo "cannot find trunk at $TRUNK" >&2; exit 2; }

# vendor/ is thousands of third-party files that no one edits by hand; ext/ and
# jodit/ likewise. The interesting surface is the application's own PHP.
list=$(find includes modules cron -type f -name '*.php' 2>/dev/null | sort)
count=$(printf '%s\n' "$list" | grep -c . || true)
[ "$count" -gt 0 ] || { echo "no PHP files found under $TRUNK" >&2; exit 2; }

say "comparing $count PHP files: working tree vs $APP"

host_sums=$(printf '%s\n' "$list" | xargs md5sum 2>/dev/null | awk '{print $1, $2}' | sort -k2)
cont_sums=$(printf '%s\n' "$list" \
  | docker exec -i "$APP" sh -c "cd $WEBROOT && xargs md5sum 2>/dev/null" \
  | awk '{print $1, $2}' | sort -k2)

missing=$(comm -23 <(printf '%s\n' "$host_sums" | awk '{print $2}') \
                   <(printf '%s\n' "$cont_sums" | awk '{print $2}'))
differ=$(join -j2 <(printf '%s\n' "$host_sums") <(printf '%s\n' "$cont_sums") \
         | awk '$2 != $3 {print $1}')

n_missing=$(printf '%s\n' "$missing" | grep -c . || true)
n_differ=$(printf '%s\n' "$differ"  | grep -c . || true)

# A module directory in the container that is in neither trunk nor extensions is
# the phreeform fault: a module deleted from the repository, surviving in an image
# layer, still being loaded, and still emitting warnings a month later. Comparing
# only the files trunk HAS cannot see it, which is why this check exists too.
known=$( (ls -1 "$TRUNK/modules"; ls -1 "$TRUNK/../extensions/Modules" 2>/dev/null) | sort -u)
have=$(docker exec "$APP" sh -c "ls -1 $WEBROOT/modules" 2>/dev/null | sort)
stale=$(comm -13 <(printf '%s\n' "$known") <(printf '%s\n' "$have"))
n_stale=$(printf '%s\n' "$stale" | grep -c . || true)

if [ "$n_missing" -eq 0 ] && [ "$n_differ" -eq 0 ] && [ "$n_stale" -eq 0 ]; then
  say "PROVENANCE OK - every file matches, and no stale modules"
  exit 0
fi

echo "PROVENANCE FAILED - the container is not running the working tree" >&2
[ "$n_differ" -gt 0 ] && {
  echo "  $n_differ file(s) differ:" >&2
  printf '%s\n' "$differ" | head -20 | sed 's/^/    /' >&2
  [ "$n_differ" -gt 20 ] && echo "    ... and $((n_differ - 20)) more" >&2
}
[ "$n_stale" -gt 0 ] && {
  echo "  $n_stale module(s) in the container belong to neither trunk nor extensions:" >&2
  printf '%s\n' "$stale" | sed 's/^/    /' >&2
  echo "    (deleted from the repository but surviving in an image layer - rebuild)" >&2
}
[ "$n_missing" -gt 0 ] && {
  echo "  $n_missing file(s) missing from the container:" >&2
  printf '%s\n' "$missing" | head -20 | sed 's/^/    /' >&2
  [ "$n_missing" -gt 20 ] && echo "    ... and $((n_missing - 20)) more" >&2
}
echo "  Fix: docker compose up -d in freebooks-docker (the override bind-mounts the tree)," >&2
echo "       or rebuild if a file was deleted from the repo but survives in the image." >&2
exit 1
