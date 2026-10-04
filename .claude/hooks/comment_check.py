#!/usr/bin/env python3
"""After an edit to shipped code, flag new comment lines that read as history.

The comment rule (CLAUDE.md, "Comments"): a comment states what the code does and why,
in the present tense; how and when something was found, what the code used to do and
what a fault cost belong in the commit message. This checks only the unmistakable
markers, so it is a safety net, not the rule: most history carries no marker word.

Runs as a Claude Code PostToolUse hook on Edit, MultiEdit and Write. Reads the tool call
as JSON on stdin; exits 2 with the offending lines on stderr, which is shown to Claude,
or 0 when there is nothing to say. Never blocks or fails an edit.
"""
import json, os, re, subprocess, sys

SHIPPED = re.compile(r'^(trunk|extensions)/')
THIRD_PARTY = re.compile(r'(^trunk/(vendor|ext|jodit)/|ZenCartFiles/|/jstree/|qz-tray)')
SOURCE = re.compile(r'\.(php|js|css)$')
COMMENT = re.compile(r'^\s*(//|#(?!\[)|\*|/\*)')
RELEASE_LINE = re.compile(r'^\s*//\s*\d+(\.\d+)*\s*=>')    # module release history in config.php

MARKERS = [
    (re.compile(r'\b(it|this|they|that|which|we|he|she|there)\s+used to\b|\bused to be\b', re.I), 'what the code used to do'),
    (re.compile(r'\bpreviously\b', re.I), 'what the code used to do'),
    (re.compile(r'\bbefore (this|that|it|the \w+) (existed|was added|was fixed)\b', re.I), 'what came before'),
    (re.compile(r'\bthe (first|original|old) (version|attempt|draft|code)\b', re.I), 'an earlier version'),
    (re.compile(r'\b(found by|was found|were found|turned out|was discovered|went unnoticed|had been (broken|wrong|silent))\b', re.I), 'how a fault was found or what it cost'),
    (re.compile(r'\bTRIAL\b'), 'a trial or diagnostic label'),
    (re.compile(r'\b20\d\d-\d\d-\d\d\b'), 'a date'),
]

def new_text(tool, inp, rel):
    """The lines this edit introduced, as (old, new) text pairs."""
    if tool == 'Edit':
        return [(inp.get('old_string', ''), inp.get('new_string', ''))]
    if tool == 'MultiEdit':
        return [(e.get('old_string', ''), e.get('new_string', '')) for e in inp.get('edits', [])]
    if tool == 'Write':
        try:
            old = subprocess.run(['git', 'show', 'HEAD:' + rel], capture_output=True, text=True,
                                 cwd=os.environ.get('CLAUDE_PROJECT_DIR', '.')).stdout
        except Exception:
            old = ''
        return [(old, inp.get('content', ''))]
    return []

def main():
    try:
        call = json.load(sys.stdin)
    except Exception:
        return 0
    tool, inp = call.get('tool_name', ''), call.get('tool_input', {}) or {}
    path = inp.get('file_path', '')
    root = os.environ.get('CLAUDE_PROJECT_DIR') or call.get('cwd') or ''
    rel = os.path.relpath(path, root) if root and path.startswith(root) else path
    if not SHIPPED.match(rel) or THIRD_PARTY.search(rel) or not SOURCE.search(rel):
        return 0
    found = []
    for old, new in new_text(tool, inp, rel):
        before = set(l.strip() for l in old.splitlines())
        # Consecutive new comment lines are read as one passage: a phrase such as
        # "before the setting existed" is as likely as not to wrap.
        passages, run = [], []
        for line in new.splitlines() + ['']:
            text = line.strip()
            if text and text not in before and COMMENT.match(line) and not RELEASE_LINE.match(line):
                run.append(text)
            elif run:
                passages.append(run); run = []
        for run in passages:
            joined = ' '.join(re.sub(r'^(//+|#+|/?\*+/?)\s?', '', t) for t in run)
            for pattern, why in MARKERS:
                m = pattern.search(joined)
                if m:
                    start = max(0, m.start() - 60)
                    found.append(('...' + joined[start:m.end() + 60] + '...', why))
                    break
    if not found:
        return 0
    print(f'Comment rule (CLAUDE.md, "Comments"): new comment lines in {rel} read as history.', file=sys.stderr)
    for text, why in found:
        print(f'  [{why}] {text[:160]}', file=sys.stderr)
    print('Rewrite each as the rule it states, in the present tense; the history goes in the commit message.', file=sys.stderr)
    return 2

if __name__ == '__main__':
    sys.exit(main())
