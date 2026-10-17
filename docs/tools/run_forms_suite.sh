#!/bin/bash
# +-----------------------------------------------------------------+
# |                          FreeBooks ERP                          |
# +-----------------------------------------------------------------+
# | Copyright(c) 2026 Bill Jones                                    |
# +-----------------------------------------------------------------+
# | This program is free software: you can redistribute it and/or   |
# | modify it under the terms of the GNU General Public License as  |
# | published by the Free Software Foundation, either version 3 of  |
# | the License, or any later version.                              |
# +-----------------------------------------------------------------+
#  Path: /docs/tools/run_forms_suite.sh
#
# Every verification tool, against one company.
#
#   docker exec <c> bash /opt/repo/docs/tools/run_forms_suite.sh [company]
#
# Run from INSIDE the container, like the php tools beside it. Exit status is 0
# only if every tool finished and none reported a failure.
#
# ## Why this is not `for f in verify_*.php; do php $f; done`
#
# Three things that shell would get wrong, each learned by getting it wrong:
#
#   1. **Exit status is not enough.** TCPDF's Error() prints a message and
#      die()s — with status ZERO. A tool that dies inside a renderer is
#      indistinguishable, to `$?`, from one that finished. So a run counts as
#      finished only if it printed a TERMINAL MARKER of its own; the tools use
#      several spellings and all of them are listed below.
#   2. **Not every tool is a check.** The directory also holds tools that WRITE
#      — the migration, the capture, the generators — and tools that need
#      arguments. Running those as part of a suite would be somewhere between
#      useless and destructive.
#   3. **`verify_forms_*` is the wrong glob.** verify_security_ids and the
#      verify_paper_* tools do not match it, and the deletion of PhreeForm broke
#      two of them without anything noticing, precisely because the pattern
#      everybody used stopped just short.
#
# The memory limit is raised because verify_forms_prefix copies every table a
# dataset touches into memory; on a company of any size the default is not
# enough, and PHP's OOM message is not one of the markers below.

set -u
COMPANY="${1:-}"
HERE="$(cd "$(dirname "$0")" && pwd)"

# Tools that are not checks: they write, or they need arguments.
SKIP=""

# Tools whose first argument is NOT a company. Passing one to these is not a
# harmless extra — verify_report_page_widths reads argv[1] as a paper name and
# refuses a company name — so they are run bare. They still check something; they just
# check it for the whole shipped set rather than for one company.
# verify_page_javascript opens no database at all: it checks source, not data.
NO_COMPANY="verify_report_page_widths verify_page_javascript"

failed=0; ran=0; skipped=0
for tool in "$HERE"/verify_*.php; do
  name="$(basename "$tool" .php)"
  case " $SKIP " in *" $name "*) continue;; esac

  args="$COMPANY"
  case " $NO_COMPANY " in *" $name "*) args="";; esac
  out="$(php -d display_errors=1 -d memory_limit=1024M "$tool" $args 2>&1)"
  status=$?

  # A failure the tool reported itself, or a death it did not.
  bad="$(printf '%s\n' "$out" | grep -cE '\[FAIL\]|Fatal error|Parse error|TCPDF ERROR')"
  # Terminal markers, in every spelling the tools use. A run with none of these
  # stopped early, whatever its exit status says.
  done_="$(printf '%s\n' "$out" | grep -ciE '^PASS|^FAIL|all checks passed|passed, 0 failed|check\(s\) failed|^  \[--\]')"

  if [ "$status" -eq 0 ] && [ "$bad" -eq 0 ] && [ "$done_" -gt 0 ]; then
    printf '  ok    %s\n' "$name"
    ran=$((ran + 1))
  elif [ "$done_" -eq 0 ]; then
    printf '  DIED  %s (no terminal marker, exit %s)\n' "$name" "$status"
    printf '%s\n' "$out" | tail -3 | sed 's/^/          /'
    failed=$((failed + 1))
  else
    printf '  FAIL  %s (exit %s)\n' "$name" "$status"
    printf '%s\n' "$out" | grep -E '\[FAIL\]|Fatal error|Parse error|TCPDF ERROR' | head -6 | sed 's/^/          /'
    failed=$((failed + 1))
  fi
done

printf '\n%d tool(s) passed, %d failed' "$ran" "$failed"
[ "$skipped" -gt 0 ] && printf ', %d skipped' "$skipped"
printf '\n'
exit $([ "$failed" -eq 0 ] && echo 0 || echo 1)
