---
name: help-manual-topic
description: How to add or update a topic in the FreeBooks phreehelp manual — where topic files live, the required head meta tags, locale fallback, the reindex requirement, and context-sensitive help wiring. Use when writing or editing user-facing documentation.
---

# Documenting in the Help Module

User-facing documentation lives in the **phreehelp** manual.

- **Content location:** `modules/{module}/language/en_us/manual/{chNN-Chapter}/{NN-Topic}.html` — plain HTML topic files grouped into chapter subdirectories (`ch01-Introduction` … `ch20-Glossary`). Put a topic in the module it belongs to (e.g. login/accounts → `phreedom/…/ch06-UsersSecurity`).
- **Required `<head>` meta tags:** `doc_pos` (dotted table-of-contents position, e.g. `06.04`; a topic nests under its parent, so `06.04` sits under the chapter landing page whose `doc_pos` is `06`) and `doc_title`. Optional: `doc_index_1`/`doc_index_2` (left-hand index, `Group.Label`) and `doc_glossary_N` (`term:definition`). The `<title>` and the stripped body text feed manual search.
- **Model new topics on an existing sibling** (e.g. `ch06-UsersSecurity/03-Passkeys.html`) for meta layout, tone, and the Related Topics footer. Keep `doc_pos` unique, and add a link from the chapter landing page's Related Topics list.
- **Locale fallback:** the scanner uses `language/{locale}/manual` for a module if that directory exists, else falls back to `en_us` for that module's *entire* manual. In practice only `en_us` has content — add topics there; do **not** create partial per-locale manual dirs (that would hide all the en_us topics for that module in that locale).
- **A reindex is required to see changes.** The TOC and search are indexed into the phreehelp table (`TABLE_PHREEHELP`) by `synchronize()`, which runs only when the `PHREEHELP_FORCE_RELOAD` runtime config is `1`; a new/edited HTML file does **not** appear until then. Fresh installs and module updates set the flag automatically; otherwise set `PHREEHELP_FORCE_RELOAD=1` (it resets to `0` after the next Help open reindexes). The module-update half of that was broken until the `write_configure()` fix — both callers passed the constant instead of its name, so a falsy flag wrote nothing at all. Applies on every deploy target, including production.
- **Context-sensitive help:** a page wires its toolbar Help button with `$toolbar->add_help('NN.NN')`, where the argument is the target topic's `doc_pos`. Pre-auth pages (login/password reset) have no toolbar, so their topics are reached via browse/search only.
- **Manual HTML files are content, not source code** — they do **not** take the FreeBooks copyright header block (see File Header Policy); match the existing topic files.
