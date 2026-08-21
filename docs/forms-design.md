# Forms — Design

**Status:** Partially implemented — the seam, catalog, providers and both renderers are in
`trunk/modules/forms/`. See §12 for measured status and what remains.
**Supersedes:** the `phreeform` module.
**Last updated:** 2026-08-13

Forms is a new module that replaces PhreeForm's data and authoring layers, and rebuilds its
rendering behind an explicit interface. It is a breaking change and therefore lands **at 1.0.0**,
before the first public release.

---

## 1. Why replace PhreeForm

PhreeForm is inherited from PhreeBooks 3.7.1. Three structural problems make it unfixable in place.

### 1.1 Any report author is a database-wide read primitive

- `CreateTableList()` (`phreeform.php:1220`) is a bare `SHOW TABLES` — the report designer offers
  **every table**, including `users` (password hashes), `data_security` (encryption values),
  `configuration` (SMTP/OAuth secrets), `webauthn_creds`, and `audit_log`.
- Join conditions are free-form SQL: the `<relationship>` field is concatenated straight into the
  query. So is the criteria min/max value in `build_criteria()`.
- Report *authoring* and report *running* share one permission (`SECURITY_ID_PHREEFORM`, id 3) with
  **no minimum level check** — `validate_user()` admits any level 1–4. Since printing an invoice
  routes through `popup_gen`, everyone who prints documents can also author arbitrary SQL.

### 1.2 There is no data/render interface

The render classes reach into the data layer through PHP globals:

```php
// report_generator.php, form_generator.php, html_generator.php, letter_generator.php
global $report, $Heading, $Seq, $FieldValues, $posted_currencies;
```

There is no contract to refactor — the coupling is ambient. Additional consequences:

- **The payload is a tagged 2D array.** `BuildDataArray()` returns `$Data[row][0]` = `'d'` |
  `'g:<label>'` | `'r:<title>'`, with `[1..n]` as columns. Row semantics are magic string prefixes.
- **Values are pre-formatted in the data layer.** `ProcessData()` returns `'$ ' . number_format(...)`
  for `dlr` and a locale string for `date`, *before* rendering. A field formatted for a readable PDF
  therefore exports to CSV as `$ 1,234.50` and `08/10/2026` — unusable by an importer. You must
  choose one output format per field, or maintain duplicate reports.
- **Template and data are the same mutated object.** `BuildPDF()` writes results back into the
  definition: `$report->fieldlist[$key]->text = $TextField;` and `$TableObject->data = $data;`.
- **Transport is fused into rendering.** `Generate*File()` sets HTTP headers and calls `exit()`.
- **Hidden side effects.** `setprintedfield` fires a DB `UPDATE` from inside the PDF builder.

### 1.3 Vestigial surface

`letter_generator.php` is **never loaded** anywhere in `trunk/`. Zero letter documents exist. The
only reference is a "new letter" button in the designer that creates a type nothing renders.

---

## 2. Architecture

Five concerns, one hard seam:

```
Catalog  →  Data  →  [ DOCUMENT MODEL ]  →  Render  →  Deliver
(trusted    (query    ←  THE SEAM  →         (bytes)    (HTTP /
 defs)      + rows)                                      email / file)
```

| Layer | Responsibility | May know about |
|---|---|---|
| **Catalog** | Datasets, field definitions, role grants | Schema only |
| **Data** | Definition + runtime params → `Document` | DB, catalog |
| **Document** | **The interface.** In-memory, renderable structure | Nothing above or below |
| **Render** | `Document` → bytes | Document only — *never* the DB |
| **Deliver** | bytes → download / inline / email / disk | HTTP, mailer |

Splitting **Deliver** from **Render** is what makes the render layer replaceable, and lets the same
document go to a download *and* an email attachment without duplicated logic.

### 2.1 Rules that keep the seam honest

1. **Plain value objects, zero globals.** Everything a renderer needs arrives as arguments.
2. **Typed values, not formatted strings.** Formatting happens at render time.
3. **No DB access below the seam.** A renderer that cannot query cannot be coupled to the data layer.
4. **No HTTP below the seam.** Renderers return bytes; delivery is a separate call.
5. **No side effects below the seam.** Post-render hooks are declared on the document and executed
   by the caller.

### 2.2 Renderer interface

```php
interface FormsRenderer {
    public function supports(Document $doc): bool;   // CSV supports Table, not Layout
    public function render(Document $doc, RenderOptions $opts): RenderedOutput;
}

final class RenderedOutput {
    public string $bytes;
    public string $mimeType;
    public string $filename;
}
```

Because renderers sit *below* the interface, replacing one is **not a breaking change** — it alters
no stored definition, no permission, and no API. The 1.0.0 freeze binds the catalog, the definition
format, and the permission model; it does not bind renderer implementations.

---

## 3. Security model

### 3.1 The core split

PhreeForm conflated two privileges that must be separate:

| Act | Frequency | Risk | Tier |
|---|---|---|---|
| Run / print a document | Constant | None | **Run** |
| Write a report (fields, filters, layout) *within* a dataset | Common | None | **Author reports** |
| Define which tables join, and how | Rare | **This is the dangerous one** | **Author datasets** |

Report authors get unlimited freedom *inside* a dataset. Only dataset authors define join graphs.

### 3.2 Dataset tiers

| Tier | Who | May define | May reference |
|---|---|---|---|
| **Shipped** | Developer (PHP file, version-controlled) | Structured joins **and** arbitrary SQL expressions / computed fields | Any table, including sensitive |
| **Custom** | Admin UI, permission-gated, DB-stored | Structured joins, plain columns | Any table **except** the sensitive set |

Beyond the structured builder, you escalate to filesystem access. That is the boundary.

### 3.3 The rule that makes the admin UI safe

**A dataset author picks identifiers; they never supply SQL syntax.**

The join builder collects: left table, right table, join type (`INNER`/`LEFT` enum), left column,
right column — each **validated against the live schema** via `SHOW TABLES` / `SHOW COLUMNS`. The
system then assembles `LEFT JOIN b ON a.col = b.col` from validated parts.

There is **no free-form join-condition textbox**. That textbox is PhreeForm's `<relationship>` field
and re-introducing it would restore the vulnerability.

Resulting failure mode: a careless dataset author can expose *a table they shouldn't* — a data
exposure problem, auditable and reversible. They **cannot** inject SQL, stack queries, `UNION`, or
reach arbitrary tables, because they never supply syntax.

Sensitive set (blocked in the admin UI, allowed in shipped datasets):
`users`, `users_profiles`, `webauthn_creds`, `data_security`, `configuration`, `audit_log`.

> The shipped `audit_log`+`users` report is handled naturally: it ships as a code-defined dataset,
> so the stock report works while the UI can never produce an equivalent.

### 3.4 Filters

Filters are **parameterised**: field key + operator (enum) + value → *bound parameter*. Never
concatenated.

`queryFactory` is already PDO-backed (`includes/db/mysql/query_factory.php:33`) but exposes only a
string-SQL `Execute()`. Adding a bound-parameter execute method is a small, additive change — so
real prepared statements are available, not aspirational.

---

## 4. The document model (the seam)

Two document shapes, because reports and forms are genuinely different. Both implement `Document`.

### 4.1 `TableDocument` — reports

Columns, rows, group breaks, totals, page setup. Replaces the tagged 2D array plus `$Heading`/`$Seq`
globals with an explicit structure.

### 4.2 `LayoutDocument` — forms

Validated against `Invoice.xml` and the element vocabulary of **all 17 shipped forms**.

```php
final class LayoutDocument implements Document {
    public PageSetup $page;        // size, orientation, margins
    public Template  $template;    // positioned elements — carries NO data
    public array     $records;     // Record[] — one rendered form per record
    public Context   $context;     // COMPANY_* vars, locale
    public array     $afterRender; // explicit side-effect hooks
}

abstract class Element {
    public Box        $box;      // x, y, w, h (mm)
    public ?Style     $style;    // font, size, colour, align, border, truncate
    public Visibility $visible;  // ALL_PAGES | LAST_PAGE_ONLY | CONTINUED_ON_OVERFLOW
}

final class StaticText extends Element { public string $text; }
final class Field      extends Element { public Binding $bind; public ?Format $format; }
final class FieldBlock extends Element { public array $parts; }   // [{Binding, separator}]
final class Table      extends Element { public string $source; public array $columns;
                                         public Style $headerStyle; }
final class Line       extends Element { public string $lineType; }
final class Rect       extends Element {}
final class Image      extends Element { public Binding|string $src; }
final class PageNumber extends Element { public string $pattern; }
final class Barcode    extends Element { public Binding $bind; public string $symbology; }

final class Binding { public Scope $scope;   // RECORD | CONTEXT | AGGREGATE
                      public string $name; }

final class Record {
    public array     $values;   // name => TypedValue — header fields AND totals, one flat namespace
    public array     $tables;   // sourceName => TypedRow[]
    public ?Currency $currency; // per-record posted currency
}

final class TypedValue { public mixed $raw;      // int|float|string|DateTimeImmutable|null
                         public string $type; }  // money|date|int|decimal|string|bool
```

### 4.3 Vocabulary mapping (14 → 9)

Four PhreeForm pairs are the same shape with a different data source, so the model shrinks without
losing expressiveness:

| PhreeForm | LayoutDocument | Note |
|---|---|---|
| `Text` | `StaticText` | |
| `Data` + `CDta` | `Field` | `CDta` only differs by reading a `COMPANY_*` constant → binding *scope* |
| `TBlk` + `CBlk` | `FieldBlock` | Both are "ordered fields joined by separators" (`AddSep()`) |
| `Tbl` | `Table` | |
| `TDup` | *(eliminated)* | Same table at new coordinates → a second `Table` on the same source |
| `Ttl` | `Field` + `AGGREGATE` binding | |
| `Img` + `ImgLink` | `Image` | Static vs. bound filename → `src` is literal-or-binding |
| `Line`, `Rect`, `PgNum`, `BarCode` | `Line`, `Rect`, `PageNumber`, `Barcode` | |

Element counts across all 17 forms: `Text` 290, `Data` 194, `Line` 58, `CDta` 46, `TBlk` 24,
`Rect` 18, `Tbl` 17, `CBlk` 14, `Img` 13, `PgNum` 10, `Ttl` 8, `TDup` 1, `BarCode` 1, `ImgLink` 1.

### 4.3.1 The `processing` vocabulary splits three ways

PhreeForm treats "processing" as one concept. It is three, and only two of them belong below the
seam. Established by reading every `pf_process_*` hook, not by inspecting names — four hints that
look like database lookups turn out not to be:

| Group | Hints | Where it belongs |
|---|---|---|
| **Presentation** | `date`, `posted_cur`, `null_pcur`, `def_cur`, `null_dcur`, `rnd_dec`, `rnd_pre`, `rnd2d`, `dlr`, `null-dlr`, `euro`, `uc`, `lc`, `neg`, `yesBno`, `printed`, `blank`, `n2wrd`, `terms`, `ship_name`, plus the separators `sp`, `2sp`, `comma`, `com-sp`, `nl`, `semi-sp`, `del-nl` | Renderer (`Formatter`) |
| **Code → label** | `j_desc`, `coa_type`, `cvt_iso2`, `cvt_cntry`, `NewUsed` | Renderer, via an **injected lookup map** |
| **Resolution (queries the DB)** | `rep_id`, `branch`, `avg_cost`, `avg_total`, `ordr_qty` | **Provider** |

The middle group reads a constant or a static array (`$coa_types_list`, `$arrLocales`) — no query at
all — so it is presentation. But those tables live in *other modules*, so they are handed to the
`Formatter` as a `lookups` array rather than reached for directly; the render layer never learns that
`$coa_types_list` came from `phreebooks/defaults.php`.

The third group is data lookups wearing a formatting costume, and it violates §2.1 rule 3 outright.
`rep_id` is the sharp one: it calls `pb_get_user_name()`, which **queries the `users` table** — one of
the sensitive tables the whole security model in §3 exists to keep away from report authors — and it
is used by `pf_73` (Purchase Order). Under Forms these become fields the provider resolves, so the
document arrives carrying the name and the renderer never queries anything.

A group key carries a `processing` hint like any other field and gets the same treatment: four
shipped reports group by a contact id with the `branch` hint, and without re-pointing them the break
is right but the heading reads `Group Total For: 128` where a customer name belongs. `ReportConverter`
re-points the group key, and the provider breaks on the **id** while labelling with the **name** —
breaking on the name would fold every unresolved id together, since they all resolve to the company
name, along with any two contacts sharing a `short_name`.

`Formatter` therefore refuses the resolution hints: it passes the value through and reports them via
`unresolvedHints()`, so the conversion utility (§8.2) can list exactly which definitions still need a
provider-side field instead of silently printing an id where a name belongs.

Two dead hints surfaced while doing this: `idx` (phreepos) calls an undefined `do_something()`, and
`neg`/`n2wrd`/`null-dlr` hard-fatal on PHP 8 for non-numeric input because they lack the `is_numeric`
guard their neighbours have. Forms guards them; `verify_forms_formatter.php` records the three
resulting divergences as intended, since they occur only on input that crashes the legacy engine.

### 4.4 Two findings that shaped the model

- **Totals are ordinary record bindings.** In `Invoice.xml`, `invoice_subtotal`, `sales_tax`,
  `freight`, `total_amount`, `total_paid`, `balance_due` are plain `Data` elements sharing a flat
  namespace with `post_date` and `rep_id`. No separate totals concept is required.
- **The document is page-agnostic.** `BuildPDF()` delegates pagination to `$pdf->FormTable()` and
  redraws static elements via TCPDF's `Header()`/`Footer()`. `LayoutDocument` therefore describes a
  *logical* layout and the renderer paginates — so a future renderer is not bound to TCPDF's
  pagination model.

---

## 5. Custom providers

PhreeForm's `special_class` bypasses `BuildSQL()` and returns a pre-formatted array. In Forms this
becomes a `DocumentProvider` returning a `Document`.

This is strictly better: a provider gets **every renderer for free**. Write one provider and it emits
PDF, HTML, and CSV; today a special class is welded to whatever format it was built for.

`entry_builder` (the Invoice) already has close to the right interface, and it is the port template:

```php
load_query_results($tableKey, $tableValue)  // header record  → Record::$values
load_table_data($fields)                    // line items     → Record::$tables
load_total_results($Params)                 // totals         → Record::$values (flat)
load_text_block_data($Params)               // text blocks    → resolved FieldBlock values
```

---

## 6. Catalog inventory

Derived by parsing every shipped definition under
`modules/*/language/en_us/reports/` and `extensions/Modules/*/language/en_us/reports/`
(7 modules, 56 files; **56/56 parsed, 0 errors**).

> Every figure in this section is generated by `docs/tools/analyse_phreeform_defs.py`
> (read-only, stdlib only). **Re-run it before revising this section** — the shipped
> definitions change, and these numbers drive the scope estimate in §7:
>
> ```sh
> docs/tools/analyse_phreeform_defs.py            # report to stdout
> docs/tools/analyse_phreeform_defs.py --json inventory.json
> ```

### 6.1 Join graph → candidate datasets

**Zero reports span more than one component** — the library was already organised into domains;
PhreeForm merely never enforced it. Only **10 distinct join edges** exist in total.

| Component | Tables | Docs |
|---|---|---|
| D1 | `journal_main` + `journal_item` + `contacts` + `address_book` + `chart_of_accounts` + `chart_of_accounts_history` | 44 |
| D2 | `inventory` + `inventory_history` + `wo_main` + `wo_steps` + `wo_task` | 9 |
| D3 | `audit_log` + `users` | 1 |
| D4 | `assets` | 1 |
| D5 | `wo_journal_main` | 1 |

A connected component is the *maximal* graph, not necessarily the right dataset. **Datasets are
curated, possibly overlapping views onto the join graph.** D1 should ship as two views for
usability — "Transactions" (`journal_main`+`journal_item`+`contacts`+`address_book`) and
"General Ledger" (`chart_of_accounts`+`chart_of_accounts_history`+`journal_item`+`journal_main`).

### 6.2 Field catalog

- **117** distinct `table.column` references — the entire field catalog.
- **9** distinct computed expressions, all simple arithmetic
  (`inventory_history.unit_cost * inventory_history.remaining`,
  `journal_item.debit_amount / journal_item.qty`), plus two `greatest(...)` calls in the Trial
  Balance. All occur in *shipped* reports → they live in code-defined datasets, so the admin UI
  never needs a formula box.
- **14** processing hints become the typed-value format vocabulary: `posted_cur`, `date`, `rnd2d`,
  `null_pcur`, `null_dcur`, `def_cur`, `coa_type`, `j_desc`, `yesBno`, `blank`, `avg_cost`,
  `avg_total`, `branch`, `NewUsed`. Several are module-supplied via the existing
  `pf_process_{module}` hook, so the vocabulary is already extensible per module.
- "Bare" identifiers resolve into two groups: `COMPANY_*` constants (→ `Context`, not dataset
  fields) and special-class output fields (`balance_0`, `purchase_invoice_id`). 366 of 426 bare uses
  are inside special-class docs.

---

## 7. Scope

| | Query-driven | `special_class` |
|---|---|---|
| Reports (`rpt`) | 30 | 9 |
| Forms (`frm`) | 9 | 8 |
| **Total** | **39** | **17** |

**The project is dominated by porting 9 providers, not by the dataset catalog.** The 17
special-class documents are the business-critical ones:

`entry_builder` (Invoice ×4) · `statement_builder` (×2) · `pos_builder` · `wo_builder` ·
`balance_sheet` · `is_budget` (×3) · `aged_receivables` (×2) · `aged_summary` (×2) · `acct_recon`

| Workstream | Size |
|---|---|
| Dataset + field catalog | Small — largely derivable from §6 |
| 39 query-driven conversions | Mostly mechanical |
| **9 `DocumentProvider` ports** | **Critical path** |
| 2 renderers (Table, Layout) + CSV/HTML | Moderate |
| Dataset admin UI, report designer, converter, diff harness | Moderate each |

---

## 8. Migration

- New module at `trunk/modules/forms/`. **PhreeForm keeps running until parity.**
- Coexistence is at the **document** level: an unported document renders through PhreeForm
  end-to-end; a ported one uses Forms end-to-end. Each page's print button switches as its document
  is ported. No adapter or shim is required.
- **Regression protection is a side-by-side diff harness**: render the same source document through
  PhreeForm and through Forms and compare output. Do not port a form until its output matches. This
  *verifies* correctness rather than preserving it by carrying legacy code forward.
- PhreeForm is removed only when nothing references it.

### 8.1 Baseline capture (step 0)

Baselines are rendered from **PhreeForm on the test container** and kept **local, not committed**
(they are generated fixtures). Each capture snapshots two artefacts per record: the resolved data
crossing into the renderer (the semantic baseline) and the PDF bytes (for positional/visual diff).

Captured 2026-08-10 — Invoices `pf_91` records 349 (18 lines, EUR @1.225), 89 (12, USD @0.757),
110 (12, GBP), 507 (4), 430 (5); Purchase Order `pf_73` record 516 (211 lines → 8 pages).

Four things the capture harness must handle — each one silently produces wrong or empty results:

1. **Rendering an Invoice mutates data.** `pf_91` carries `setprintedfield = journal_main.printed`
   and `BuildPDF` fires `UPDATE journal_main SET printed = printed + 1` per record. **Null
   `setprintedfield` before rendering** so the counter never moves and captures are re-runnable;
   assert `printed` is unchanged afterwards. (`pf_73` has no such field.) This is also why the
   Forms `afterRender` hook must fire on *delivery*, never on *render* — otherwise a preview or a
   diff run increments the counter, which is arguably the existing bug.
2. **The stored `datedefault` is `'c'` (Today)**, which selects zero rows for historical records and
   returns `PHREEFORM_NOROWS`. Override to `'a'` (All) — this is exactly what the real print button
   does (`…&date=a&xfld=journal_main.id&xcr=EQUAL&xmin=<id>`).
3. **The container runs `display_errors = Off`**, so CLI fatals are silent and a failing capture
   looks like an empty result. Run with `php -d display_errors=1`.
4. **Bootstrap must mirror `application_top.php`'s order**: Composer autoload → `phreedom`/
   `phreebooks` menu language → `phreedom/config.php` → per-module language + config → module
   default language for `phreeform`. Loading them out of order fails on undefined
   `SECURITY_ID_*` / `MENU_HEADING_*` / `PF_FONT_*` constants. `trunk/cron/*.php` is the working
   template for the rest of the CLI bootstrap. This lives once, in
   `docs/tools/bootstrap_freebooks.php`, which every Forms tool requires.
5. **Mirroring the order is not enough — the SCOPE has to match too.** `application_top.php` runs at
   file scope, so every `$var` in the files it includes is a global. A bootstrap that includes the
   same files from inside a *function* makes them function locals that vanish on return, and the
   legacy engines read those arrays back with `global`. Nothing errors; the answer just changes.
   `$CritChoices` going missing this way made `build_criteria()` emit no criteria at all and cost a
   day of chasing a "parity gap" that was never in either engine (§8.5). `fb_require_globals()`
   restores file scope in both directions — globals in before the include, whatever it defined
   promoted out after — and must be the only route by which those files are loaded, since
   `require_once` on an already-loaded file defines nothing.

Use `BuildForm($report, 'S')` — delivery method `S` returns the bytes instead of emitting HTTP
headers and calling `exit()`.

Two companies are named throughout, because the suite is run against both and neither alone is
sufficient: **`demo`** is a stock installation with the shipped chart and demonstration data —
broad in feature coverage, small in volume; **`production`** is a real installation on a
`DB_PREFIX` database — large in volume, narrow in feature use, and the only one carrying
customised definitions. Substitute your own; the pairing is the point.

A sixth trap sits outside the harness: **company auto-detection**. A baseline taken from the wrong
database still renders plausible-looking output and exits 0 — an early run silently captured `demo`
instead of `production`. The bootstrap always echoes the company it resolved and warns when it guessed.

The tools, all under `docs/tools/` and all run inside the container with `-d display_errors=1`:

| Tool | Purpose |
|---|---|
| `bootstrap_freebooks.php` | Shared CLI bootstrap (trap 4) + the Formatter's lookup tables |
| `capture_phreeform_baseline.php` | Step 0 — snapshot legacy data + PDF bytes |
| `verify_forms_delivery.php` | Delivery hygiene (§2): header injection, path traversal, overwrite, staged-attachment cleanup, refusal after output has started | no | **PASS — 45 checks** |
| `verify_forms_dataset_map.php` | Every shipped report resolves to the dataset a passing row-set run proved | yes | **PASS — 33 mappings, 0 wrong** |
| `verify_forms_app.php` | The application's own path — route, render, deliver — over every definition in the catalog | yes | **PASS** — `demo` 47 documents, `production` 40, both as PDF and CSV |
| `verify_forms_custom_datasets.php` | The admin-defined tier (§3.2, §3.3): hostile input refused by name, sensitive tables unreachable, stored rows re-validated on load, tier limits enforced in the constructor | yes | **PASS — 40 checks** (`demo`; needs the `forms_datasets` table) |
| `verify_forms_pages.php` | Renders each Forms page as index.php would, parses the markup it emits, follows a rendered run link through to a delivered PDF, and fails on any warning from Forms' own files | yes | **PASS** (skips a company where Forms is not installed) |
| `verify_report_page_widths.php` | Every shipped report definition fits the page it declares, or is reported as needing more paper than exists | no | **PASS — 39 definitions, 9 listed as scaled** |
| `diff_forms_provider.php` | Semantic-diff a provider against the baseline |
| `verify_forms_seam.php` | Assert the seam holds (no DB needed) |
| `verify_forms_formatter.php` | Assert `Formatter` parity with `ProcessData()`/`AddSep()` |
| `render_forms_document.php` | Render a form through Forms, for diffing against the baseline |
| `verify_forms_catalog.php` | Attack the catalog's safety guarantees (no DB needed) |
| `verify_forms_datasets.php` | Check every shipped dataset against the real schema |
| `verify_forms_prefix.php` | Run every dataset against a DB_PREFIX'd schema and compare |
| `verify_forms_report.php` | Run one report through both engines and compare |
| `make_forms_proving_templates.php` | Create/remove the scratch multi-page + `Ttl` templates (§8.3) |
| `analyse_phreeform_defs.py` | Regenerate the §6 catalog inventory |

### 8.2 Conversion utility

The XML format is fully mechanical (56/56 parsed clean) and carries labels, widths, alignment,
totals, and processing hints that map directly. But conversion is a **mapping** problem, not a
translation problem: `<relationship>` must map to a *dataset*, and `fieldname` to a *catalog key*.

Two facts make this tractable:

1. The definitions are overwhelmingly **product-shipped** (~56 files across 7 modules; the set on
   a live installation is essentially the stock set). The bulk of conversion is a one-time authoring job we
   do at the source — those become the new module's shipped definitions.
2. **The existing definitions are the specification for the catalog** (§6), so the shipped set
   converts by construction.

The user-facing converter therefore handles only genuinely *customised* installs, detectable by
diffing `my_files/{company}/phreeform/pf_N` against the shipped default. Its hard limit: a report
whose join set matches no dataset **cannot be auto-converted** — the converter must report that
clearly rather than guess a mapping.

### 8.3 Verifying the renderer

PDF bytes never match — TCPDF regenerates `file_id` and the creation date per run — so the renderer
is compared two ways: extracted text (`pdftotext -layout`) and rendered pixels (`pdftoppm` at 100dpi
then ImageMagick `compare -metric AE`). Text catches wrong or missing values; pixels catch what text
cannot see, which is most of a form's furniture — rules, boxes, column widths, alignment.

That second check earned its place. With text already identical, the pixel diff still found three
real defects: table borders were not drawn at all, the heading box was a rule too tall (drawing a
doubled line under every heading), and the boxes around `Data` totals vanished from every page but
the last.

**Pagination cannot be proven from the library as it stands.** The only multi-page record (PO 1234,
211 lines) belongs to a query-driven form, and no `entry_builder` form is long enough to break a
page — the largest sales invoice is 18 lines. So the same real record is made to paginate in *both*
engines by shrinking the item table on a copy of `pf_91` (`make_forms_proving_templates.php`): same
data, same geometry, two renderers. Those scratch definitions live in `my_files/`, are not committed,
and must be removed afterwards or they appear in the reports UI.

Results (2026-08-11, against the `production` company):

| Form | Records | Pages | Text | Pixels |
|---|---|---|---|---|
| `pf_91` Invoice | 349 (EUR, 14), 110 (GBP), 89 (USD), 507, 430 | 1 | identical | 2 per page |
| `pf_9991` paginating | 349 | 6 = 6 | identical | 2 per page |

The residual 2 pixels are two greys one level apart (108/109, 168/169) — antialiasing rounding.

### 8.4 Verifying the catalog

Two things are being claimed, and they need different evidence.

**That the safety guarantees hold** is structural, so `verify_forms_catalog.php` attacks them rather
than demonstrating them — 66 assertions, no database required. Identifiers carrying SQL are refused
rather than escaped; a Custom dataset refuses expressions and every table in the sensitive set,
while a Shipped one may use both; filter values appear only as bound parameters; unknown fields are
refused in select, filter and sort position. Three real injection strings were also fired at the live
database through `ExecutePrepared()` — `' OR '1'='1`, a `UNION SELECT` against `users`, and a stacked
`DROP TABLE` — each returning zero rows with `journal_main` intact.

One assertion is there purely to stop drift: `Formatter::RESOLUTION_HINTS` and
`TemplateConverter::PROVIDER_RESOLVED` are two lists in two layers describing one decision, and a
hint the renderer refuses but the converter fails to re-point prints a raw id on a customer-facing
document. That is exactly the defect it caught — the Purchase Order's Buyer box printing `1`.

**That it reproduces real output** is empirical. PO 1234 (`pf_73`, 210 lines) renders through
`QueryDocumentProvider` at 8 pages, matching the legacy engine's 8:

| Comparison | Differing pixels (8 pages) |
|---|---|
| First working render | 740,384 |
| After fixing heading-style inheritance, computed-field grain, `ship_name`, user resolution | 121,578 |
| Excluding the unmapped Vendor SKU column | **6,498** (~810/page) |

The remaining ~810 per page is the heading row's column rules landing about a pixel off; the rest is
antialiasing.

The Vendor SKU column is not a defect but the design working. This install's `pf_73` is customised
(our own vendor-SKU work) and selects
`IF(inventory_purchase_details.vendor_sku <> '', …, SUBSTRING_INDEX(…))` — raw SQL embedded in a form
definition, which is precisely what §3.2 confines to the shipped tier. The converter reports it
through `unmappedFields()` instead of silently rendering blanks, which is the behaviour §8.2 demands.
Rendering that column requires a shipped dataset carrying it as a named computed field.

Two findings from doing this that the earlier sections did not anticipate:

- **Compound joins are real.** All 11 shipped relationships are simple `a.b = c.d`, but the
  customised Purchase Order joins on both `sku` and `vendor_id`. `Join` therefore takes a *list* of
  conditions. A single-pair model would have failed to express it, and the pressure would then have
  been to re-introduce the free-form condition box that caused the original vulnerability.
- **A computed field still needs a table.** Not to build the SQL — the expression says where values
  come from — but to state its *grain*, i.e. whether it varies per line or per record. Without it the
  row-folding code cannot classify the field, and the Unit Price column renders empty.

### 8.5 The reports path

Two claims, and both now hold on the `transactions` dataset.

**The export defect is fixed.** PhreeForm formatted in the data layer, so a money column reached the
CSV writer as `£ -0.68` and a date as `25/07/2026`. The same report through Forms exports `-0.68`
and `2026-07-11`. One definition now serves both a readable PDF and an importable file, which is
what makes "export invoices with payment details to another system" an ordinary report rather than a
second copy maintained by hand. `verify_forms_report.php` asserts the paired claim over both files
in full — legacy carried display formatting *and* Forms strips it — so the test fails if either side
stops behaving. It skips the assertion, visibly, on reports with no money or date column, rather
than passing on the absence of evidence.

**Row-set parity holds: 13 reports, every data row and every cell.** The comparison puts the Forms
side through the same `Formatter` a PDF renderer would use, because the two engines are not
comparable raw — legacy formats during `BuildDataArray()`, Forms at render time. It compares
multisets, since neither engine defines a row order unless the report sets a default sort. Group
breaks are checked separately: a report can have every data row right and still break its groups in
the wrong places.

Three divergences are asserted rather than failed, because in each the legacy engine is the broken
one:

| Legacy behaviour | Cause | Forms |
|---|---|---|
| Cannot group by a column the report does not display | `BuildDataArray()` sets its break key inside `if ($DataFields->visible)`, so `$GrpField` stays `''`, `$myrow['']` is null on every row, and nothing ever breaks | Selects the group field whether or not it is shown. Customer Ledger and Vendor Ledger go from one lump total to 118 and 13 real groups |
| Emits a spurious group total on reports with *no* grouping | `$GrpField = ''` then `isset($GrpField)`, which is true of an empty string; the closing `if ($GrpWorking !== false)` then fires once | Emits no group total when nothing is grouped |
| Merges the SKUs `'0001'` and `'1'` | the break test is `<>`, and PHP compares two numeric strings numerically | Compares as strings — but still collapses `NULL` with `''`, which is one blank group, not two |

**The bug that produced the earlier "parity gap" was in the harness, not in either engine.** PHP
evaluates an include in the scope of the code that ran it, so `require`ing `phreeform/defaults.php`
from inside `fb_bootstrap()` made every `$var` in it a function local. `build_criteria()` then read
back `global $CritChoices`, found nothing, fell through to its `default:` case and emitted **no
criteria at all** — so Sales By SKU returned every journal in the table (4,707 rows) instead of the
two its own `IN_LIST` filter names (647). An absent global is not an error in this codebase; it is a
silently different answer. `fb_require_globals()` now restores file scope in both directions: it
copies globals in before the include (`phreeform/defaults.php` guards its hook loading with
`isset($loaded_modules)`) and promotes whatever the file defined back out afterwards.

**Definitions are consumed as they are read.** `BuildDataArray()` finds the default group with
`while ($t = array_shift($report->grouplist))` and `build_criteria()` writes each filter's resolved
mode back onto the shared object. Handing the same `$report` to both engines showed Forms a report
with no grouping at all — which reads as a Forms bug and is not one. Anything comparing the two must
clone, and the clone must go to *Forms*: `prefixTables()` resolves table names off `global $report`,
so the object the legacy engine mutates has to stay the one in global scope.

**Found while doing this: pruning must be decided by reference, not by join type.** A dataset is
deliberately wider than any one report, so joining everything it declares changes the answer —
`address_book` is one-to-many and doubled the rows of a report that never referenced it. Keeping
every INNER join looks like the cautious choice, but it is the more damaging one: `transactions`
joins `journal_item` INNER because most documents are line-grain, and Open Purchase Orders selects
only header columns, so the unreferenced line join turned its 55 orders into 955 rows. Legacy had no
such problem because each report named its own tables. An unreferenced join contributes exactly two
things — row multiplication when it is one-to-many, and silent exclusion of parents with no children
— and a report naming no column from that table asked for neither. A join that genuinely is part of
the dataset's meaning says so with `required: true`.

**Coverage: every non-special shipped report now converts.** Seven datasets (Transactions, General
Ledger, Inventory, Audit Trail, Contacts, Work Orders, Assets) and 214 fields cover all 30 report
definitions with nothing unmapped. 26 pass end to end; the other four — Employee List, both Work
Order reports and the Asset List — have no rows in the test company, so they convert and query
cleanly but prove nothing.

Filling the datasets out turned up four things worth recording:

- **A filter's value can be other COLUMNS.** The Re-order Worksheet asks for items where
  `quantity_on_hand < (minimum_stock_level + quantity_on_sales_order - quantity_on_order)`;
  PhreeForm decides between "a value" and "an expression" with `testTables()` and splices the latter
  in as SQL. Binding it as a parameter is not a safe failure — MySQL coerces the string to 0, so the
  report silently becomes "items with negative stock" and returns 251 rows instead of 986.
  `FieldExpression` restores the capability without restoring the injection: an expression is a list
  of tokens that are each either a dataset field KEY or one of six fixed symbols, and the keys
  resolve through the dataset exactly as a selected field does. Anything the parser cannot account
  for — a digit, a quote, a function call — is refused, and the filter is reported rather than
  approximated.
- **A resolution hint can be served two ways, and the dataset should win.** `avg_cost` and
  `avg_total` are now dataset expressions, so the valuation reports resolve them inside the one
  query instead of PhreeForm's two lookups per row (several thousand round trips to print a page).
  `branch` — which resolves a CONTACT id to a short name, despite the name — stays provider-side and
  batched, because its `COMPANY_ID` fallback for an absent or unknown id is application behaviour,
  not arithmetic. `ReportConverter` prefers a dataset field and falls back to the provider, warning
  only when neither can serve it.
- **A dataset's shape has to match the report's grain.** The Daily Cash Report reads as a General
  Ledger report but is journal-grain: its base table is `journal_main`, and running it against the
  account-grain dataset returned two extra rows — cash accounts with no postings, which that
  dataset's LEFT join keeps and legacy's INNER join dropped. It belongs on Transactions, which now
  reaches `chart_of_accounts` so a report can select by account type.
- **An unhinted boolean must print its raw value.** `Formatter::plain()` rendered a Boolean as
  "Yes"/"" — which reads better and is wrong. With no hint `ProcessData()` returns the value
  untouched, and `yesBno` is how a definition asks for Yes/blank, so the conversion silently changed
  every unhinted boolean column in every converted report: the Vendor List's Inactive column of 0s
  and 1s became blanks.

`verify_forms_datasets.php` now checks the datasets against the real schema — `Identifier::check()`
proves a name is syntactically an identifier, never that the column exists. It caught
`journal_main.dept_rep_id`, added because a shipped report filters on it; the column is on
`contacts`, and the legacy definition only ever worked because that filter defaulted to ALL and was
never emitted.

**What is not yet done.** `datedefault` / `gen_build_sql_date()` is unported — the tool neutralises
it with `datedefault = 'a'` on both sides, so date-ranged reports are untested. The Table PDF
renderer does not exist, so reports are proven as data and as CSV, not as printed pages. The seven
special-class reports (aged receivables/payables and their summaries, the three income statements)
reference computed columns — `balance_0`, `current_ytd`, `budget_cur` — that are not table columns
at all; those are the deferred provider ports, not dataset work.

**Table prefixes.** FreeBooks supports a table-name prefix set at install time and baked into
`configure.php` as `DB_PREFIX`; every install here uses an empty one, which is how the catalog got
away with emitting bare names. Rather than prefix every *reference* — what PhreeForm's
`prefixTables()` does, by string-replacing `"table."` throughout — a table is introduced once as
`<prefix><table> AS <table>` and referenced by the bare name everywhere else. Field references, join
conditions, `ORDER BY` and the raw SQL inside computed expressions then need no rewriting at all,
and a dataset reads identically on every install.

The one case aliasing cannot cover is a subquery that opens a table *again*: `(SELECT avg_cost FROM
inventory_history h_avg …)` is a second, independent instance and needs the real prefixed name. A
dataset writes that as `FROM {{inventory_history}}`, and the braces are deliberately unlike anything
SQL uses, so a missed substitution is a syntax error rather than a query against the wrong table.

Proven by copying every table the datasets touch into session `TEMPORARY` tables named `pb_*` and
running each dataset's full SELECT twice — unprefixed against the real tables, prefixed against the
copies — then comparing the row sets. Temporary tables are session-scoped, so no company database is
touched. Note the comparison must not use `LIMIT`: with no `ORDER BY` an indexed table and a heap
copy return *different* 200-row windows, which reads as a mismatch when the row sets are identical.

---

## 9. Decisions

| # | Decision | Rationale |
|---|---|---|
| A | Shipped datasets as version-controlled PHP files; **custom datasets admin-UI-creatable**, DB-stored, permission-gated | Keeps PhreeForm's flexibility USP for non-developer power users, without giving report authors join-definition rights |
| B | DB + JSON definitions; conversion utility scoped to customised installs | Removes the `PF_DIR_MY_REPORTS` file-write path and its error handling |
| C | Reports **and** forms both in 1.0.0 | No breaking changes after the first public release |
| D | Fresh renderers for both document types, protected by a side-by-side diff harness | Coexistence already handles transition; an adapter would be throwaway work that constrains the model |
| E | Letters dropped | Dead code, zero instances (§1.3) |
| F | Three-tier privilege model: run → author reports → author datasets | Replaces the conflated `SECURITY_ID_PHREEFORM` check |

---

## 10. Open risks

Not yet validated; resolve during implementation.

1. **`Ttl` "Continued" is pagination-aware content — semantics now confirmed, implementation still
   to do.** On multi-page forms, total fields render as "Continued" on non-final pages: content
   that depends on page position. The renderer must pass a `PageContext` when resolving bindings.
   Modelled as the `CONTINUED_ON_OVERFLOW` visibility variant, and it remains the one place the
   seam is not perfectly one-directional.

   **Verified 2026-08-10 against real output** (PO 1234, `pf_73`, 211 lines → 8 pages): pages 1–7
   render "Continued", page 8 renders the real total (6,800.17). The behaviour is exactly as
   modelled, so the residual risk is implementation rather than unknown semantics.

   ⚠️ **No provider-backed form can exercise this, and the reason is stronger than first recorded.**
   `pf_91` has no `Ttl` (§4.4) — but neither does any other `entry_builder` form, and none can:
   PhreeForm's `entry_builder::load_total_results()` reads `$Params['Seq']` while `BuildPDF()` passes
   an `objectInfo`, so a `Ttl` on **any** `special_class` form is a hard fatal ("Cannot use object of
   type objectInfo as array"). Confirmed 2026-08-11 by adding one. The legacy `Ttl` path works only
   for query-driven forms, all six of which (`SalesOrder`, `PurchaseOrder`, `CustomerQuotes`,
   `CustomerCreditMemo`, `VendorCreditMemo`, `DepositSlip`) await the step-5 catalog.

   **Implemented and structurally proven 2026-08-11.** The renderer places deferred content in an
   explicit second pass over the pages of each record — placeholder on every page but the last, value
   on the last — rather than PhreeForm's reliance on when TCPDF happens to call `Footer()`. Verified
   on a six-page proving form: "Continued" on pages 1–5, the resolved total on page 6. What remains
   unverified is the *value* a query-driven `Ttl` computes, since there is no working legacy
   behaviour to compare against until step 5.

   A `Ttl` also sums **several** fields (`foreach ($Params['Seq'] as $f) $total += $this->$f`), so an
   `Aggregate` binding carries a field list, not one name; keeping only the first silently
   under-reports the total.
2. **`TDup` carries a known PhreeForm limitation** — its own comment says it "only works if each
   form is contained in a single page". Modelling it as a second `Table` may remove that limit;
   confirm the intended use (two-part remittance forms) before assuming that is desirable.
3. **Provider shape is proven for `entry_builder` only.** The element vocabulary is proven across
   all 17 forms, but the record/table/totals shape has been validated against the Invoice alone.
   `statement_builder` and `pos_builder` may want different provider methods.
4. **Multi-currency** must reach the formatter per record (`posted_cur` depends on the record's
   posted currency) — hence `Currency` on `Record`, not on the document.
5. **~60 bare-identifier uses in query-driven docs** are unclassified and need review during
   conversion.

---

## 11. First implementation steps

**Two proving documents, not one.** They cover the two architectural paths, and neither alone is
sufficient — the Invoice has no `Ttl` and never paginates; the Purchase Order has no provider.

| Proving document | Path | Proves |
|---|---|---|
| **Invoice** (`pf_91`, 12–18 lines, EUR/USD/GBP) | `DocumentProvider` | `LayoutDocument` expressiveness, `Record`/`TypedValue`, per-record currency (`posted_cur`) |
| **PO 1234** (`pf_73`, 211 lines → 8 pages) | query-driven | Pagination, `Ttl` "Continued" (risk 1), the dataset/catalog path |

1. **Step 0 — baseline (done 2026-08-10).** Captured from PhreeForm on the test container: five
   invoices plus PO 1234, each snapshotting the resolved data crossing into the renderer *and* the
   PDF bytes. Kept local, not committed. See §8.1.
2. **Step 1 — the seam (done 2026-08-10).** `Document`, `LayoutDocument`, `Record`, `TypedValue`,
   `Binding`, `FormsRenderer`, `RenderedOutput` — pure value objects, no behaviour. Guarded by
   `verify_forms_seam.php` (20 assertions, runs with no database).
3. **Step 2 — Invoice provider (done 2026-08-10).** `entry_builder` ported to
   `EntryDocumentProvider`; `diff_forms_provider.php` reports 0 differences against the step-0
   baseline for records 349 (EUR, zero-rated) and 110 (GBP, 20% VAT).
4. **Step 3 — the renderer.** In progress.
   - a. **`Formatter` (done 2026-08-11).** The value-formatting half, shared by every renderer.
     `verify_forms_formatter.php` proves parity with `ProcessData()`/`AddSep()` across the hint grid
     and asserts the five DB-backed hints are refused. See §4.3.1.
   - b. **The TCPDF Layout renderer (done 2026-08-11).** `LayoutPdfRenderer` plus `PdfCanvas` and
     `TemplateConverter`, which maps stored PhreeForm geometry so real forms render unchanged.
     Verified against the legacy engine on all five baseline invoices (EUR/GBP/USD, 1–14 lines):
     **text identical, 2 differing pixels each** — two greys one level apart, i.e. antialiasing.
     Pagination verified on a six-page proving form: identical page count, identical text, 2 pixels
     per page. See §8.3.

     PO 1234 could **not** be used: `pf_73` is query-driven, so no provider can supply it until the
     step-5 catalog exists. Neither could any `Ttl` be diffed — see risk 1.
5. **The dataset + field catalog (done 2026-08-12).** Four shipped datasets (Transactions, General
   Ledger, Inventory, Audit Trail), parameterised filters, a query builder, `ExecutePrepared()` on
   `queryFactory`, and `QueryDocumentProvider` — which unblocks the query-driven half of the
   library. `rep_id`/`admin_id` are resolved provider-side as promised. Verified by
   `verify_forms_catalog.php` (66 guarantees) and against PO 1234; see §8.4.
6. **The reports path (data + CSV done 2026-08-12).** `ReportDocumentProvider` folds a dataset
   into a `TableDocument` with group breaks and totals; `ReportConverter` maps a legacy report
   definition; `CsvRenderer` renders it. **Row-set parity holds across 13 reports** — every data
   row, every cell and every group break — with three divergences asserted as legacy defects. Only
   the `transactions` dataset is proven; the other three are registered but thin. See §8.5.
7. **Dataset coverage (done 2026-08-12).** Seven shipped datasets, 214 fields, every non-special
   report converting with nothing unmapped; 26 of 30 verified end to end, 4 untestable for want of
   data. Added `FieldExpression` (column-to-column filters) and schema verification. See §8.5.
   *(Superseded: those 4 were measured on `production` only. All four pass on `demo` — see §12.1.)*
8. **Table PDF renderer (done 2026-08-13).** `TablePdfRenderer` draws a `TableDocument`, reaching
   legacy layout parity on furniture, column widths and stacking, fills, borders and the footer.
   `ReportStyle` was added to the seam to carry the report author's band styling, and
   `ReportConverter` gained `convertStyle()` and `convertPageSetup()`. Three deviations are
   deliberate — see §12.3.
9. **Date ranges (done 2026-08-13).** `DateRangeResolver` replaces `gen_build_sql_date()`,
   resolving all thirteen preference codes to a half-open date pair — or, for `z`, to an
   accounting period — with the calendar behind a `FiscalCalendar` interface so the arithmetic is
   testable off the clock. `ReportConverter::convertDateFilters()` turns the result into bound
   filters. Verified by `verify_forms_dates.php` (72 checks) and end to end on 15 reports.
10. **The three other form providers (done 2026-08-13).** `StatementDocumentProvider`,
    `PosDocumentProvider` and `WorkOrderDocumentProvider` complete the `LayoutDocument` half of the
    special-class set. All three match a PhreeForm baseline field for field and line for line on
    both companies; the statement provider also matches across date ranges, which is the only path
    that exercises its prior-balance carry-forward. Risk 3 is **closed**: the `entry_builder` shape
    held for all three, and no new provider method was needed. Two things the ports settle:
    - The statement takes a `DateRange` rather than reading the report definition, so §11.9's
      resolver is what supplies the period — a provider never touches `gen_build_sql_date()`.
    - `record_ids` is not always a journal id. A statement is per contact and a work order is its
      own journal, which the baseline tooling had assumed away.
11. **The aged pair (done 2026-08-13).** `aged_receivables` and `aged_summary` are gone, and
    nothing replaced them: the six pseudo-columns they computed are now ordinary computed fields on
    the Transactions dataset, and both reports run through the normal query-driven path. All four
    shipped reports (`pf_46`–`pf_49`) match the legacy engine on both companies. What this removes:
    - **The provider.** `ReportDocumentProvider` already did group breaks and running totals, which
      is the ~70 lines each class duplicated from the engine.
    - **The `GROUP BY`.** The summary is the detail report grouped by account with the rows beneath
      hidden, so it needed a display setting rather than an aggregating query builder.
    - **The N+1.** `aged_receivables` ran three queries per row; the `ttl` amount and the payments
      sum are subqueries in the one statement.
    - **The ceiling.** The buckets are fields, so any transactions report can sort, filter or total
      on "Age 31–60" — which the legacy field picker advertised but only four reports could use.

    Two additions made it possible: `{{param:name}}` in a computed expression, which binds a value
    the dataset supplies rather than pasting it into the SQL (the cutoffs are today's date less an
    admin setting, so no static expression can hold them); and identity columns on a totals-only
    break row, without which an aged summary lists balances against no customer name.
12. **Income Statement and Balance Sheet (done 2026-08-13).** `is_budget`, `balance_sheet` and the
    `income_statement` helper the latter existed partly to call become
    `IncomeStatementDocumentProvider` and `BalanceSheetDocumentProvider`, sharing one `FiscalWindow`
    that resolves the four period bounds all three derived separately. `pf_50`, `pf_65` and `pf_66`
    match the legacy engine row for row on both companies.

    These are the first reports that are genuinely *structural*: their rows are sections in a fixed
    order — revenues, cost of sales, gross profit, expenses, net income — not a query result grouped
    by something. No filter or sort a report author could write would produce a balance sheet, so
    unlike the aged pair they keep a provider. `ReportConverter::convertStructuralColumns()` maps
    their columns without a dataset.

    One correctness fix travelled with the port. `is_budget` advanced four result cursors in
    lockstep with `MoveNext()`, which holds only while every one returns the same accounts in the
    same order; an account with no history row in the prior year drops out of that cursor and every
    row after it reads its neighbour's figures. The port keys by account id, which cannot misalign.
13. **Account Reconciliation (done 2026-08-14).** `acct_recon` becomes
    `AccountReconciliationDocumentProvider`, the ninth and last of the special-class ports. It is
    structural like the two statements and shares their base class, which needed one change: a row
    value may now be handed over already typed, so the reconciliation's post date is a date rather
    than a column of currency.

    **The report was dead.** `$dep_in_transit += $dep_amount` adds an int to `''` — the class sets
    the unused side of each pair to the empty string so it prints blank — and PHP 8 raises
    `Unsupported operand types` on the first payment row. `$dep_cleared` and `$chk_cleared` are
    never initialised at all, so `null += ''` kills the second half the same way. The Account
    Reconciliation has produced a fatal error rather than a report since the PHP 8 upgrade. Fixing
    it (four lines) was a precondition of having anything to port against.

    **And once it ran, it under-reported.** `acct_recon` collected its transactions into an array
    keyed by `journal_main.id` while accumulating the totals separately, so a journal entry with
    more than one line against the account kept only whichever line came back last. On `demo` this
    printed a reconciliation listing **two of its six cleared transactions** beneath totals covering
    all six — a bank reconciliation that does not show the transactions it is totalling. The port
    lists one row per line; the totals are unchanged, and the harness asserts exactly that (no
    legacy row lost or altered, and every surplus row is an uncaptioned transaction line).

    Two smaller things also stop: the GL account is a constructor argument rather than
    `$report->filterlist[0]->min_val` — "assumes that the gl account is the first criteria", so
    reordering a report's filters silently reconciled the wrong account — and both queries bind it
    instead of interpolating a value that arrives from the run dialog.

    `verify_forms_report.php` gained `--period=` and `--gl=`, which stand in for the run dialog, and
    now exits non-zero when the legacy side returns nothing: an empty baseline agrees with any
    provider at all, and a forgotten `--gl` previously read as a pass.
14. **The seam gains three row kinds, the delivery layer, and a module surface (done
    2026-08-14).** Three pieces, in that order, because each is a precondition of the next.

    **RowKind grows from three to six.** `SectionHeading`, `Separator` and `Subtotal` join `Data`,
    `GroupTotal` and `ReportTotal`. PhreeForm emitted all six as `'d'`, which is why no renderer
    could set "Gross Profit" differently from the account line above it. Ask
    `isData()`/`isTotal()`/`isBreakTotal()` rather than comparing cases: `!== RowKind::Data` used to
    mean "this is a total", and a consumer that still reads it that way draws a group caption band
    over a section heading. Both renderers were updated accordingly; the comparison against
    PhreeForm still treats all six as data rows, because that is what the legacy produced.

    **The delivery layer.** `HttpDelivery` (attachment or inline), `FileDelivery` and
    `EmailDelivery`, each taking its sink as an argument so the headers, the staged attachment and
    the failure paths can all be asserted without an HTTP request or a mail server. Splitting it
    from Render is what makes "the same report as a download and as an email attachment" one code
    path; PhreeForm's `BuildPDF()` chose the output method, set the headers and echoed the body
    itself.

    Most of what the layer does is hygiene, and it is not incidental. PhreeForm built
    `Content-Disposition` by concatenating a user-editable report title, unquoted and unescaped — a
    title with a newline injects response headers. `Filename` reduces a name to one safe path
    component and emits an RFC 6266 `filename*` alongside so an accented name survives rather than
    becoming underscores. `HttpDelivery` refuses outright once output has started, rather than
    warning and appending a document to a half-rendered page: that is the exact failure mode that
    made the Work Order form silently unprintable (§12.3.1).

    **A module surface.** Until now `trunk/modules/forms/` held only `classes/`, and every run in
    this document had been driven from `docs/tools/`. It now has `config.php`, `defaults.php`,
    `language/en_us`, an installer, and a Bootstrap page at `?module=forms&page=main` that lists
    every definition, says what serves it, and runs it to PDF or CSV.

    Two things worth naming. The module creates **no tables**: it reads the existing PhreeForm
    catalog, so enabling it makes every stored definition runnable through the new engine with no
    migration and without touching the old one, which is what lets the two coexist (decisions C and
    D). And it takes **three security ids** — `SECURITY_ID_FORMS_RUN`, `_AUTHOR`, `_DATASETS` —
    implementing decision F, where PhreeForm had one key that granted all three.

    `DocumentRunner` is the only place that knows the three routes a definition can take (dataset,
    provider, or honestly declined). `DatasetResolver` answers the question the tools had been
    answering by hand — and getting wrong: `pf_84` is the Audit Log on `audit_trail`, `pf_62` is the
    General Ledger report but reads `transactions`. The mapping is now code, and
    `verify_forms_dataset_map.php` asserts all 33 proven answers.
15. **Custom datasets in the admin UI (done 2026-08-14).** The last step, and the one that
    reintroduces the capability PhreeForm was replaced for having — so it is worth being exact
    about what is and is not given back.

    `SchemaInspector` reads the live schema in one `information_schema` query and is the only
    source of the names the UI offers: unprefixed, sensitive tables already removed, with a value
    type guessed from each column's SQL type so a money column does not silently default to text.
    `CustomDatasetStore` validates a submitted definition against that inspector and stores it as
    JSON in `forms_datasets`. The editor at `?module=forms&page=datasets` is three dependent
    selects per join — left table, left column, right column — plus an `INNER`/`LEFT` enum. There
    is no textbox anywhere on it that accepts a fragment of SQL, because that box is PhreeForm's
    `<relationship>` field and it is the whole of §1.1.

    Four properties hold, and `verify_forms_custom_datasets.php` asserts each adversarially:

    - **Refused by name, not escaped.** `id' OR '1'='1`, `journal_main; drop table contacts`,
      `id union select password from users`, a qualified `journal_main.id`, a `-- ` comment — all
      rejected at the boundary because they are not names the schema has.
    - **The sensitive set is unreachable** as a base table and as a join, and the check is in
      `Dataset`'s constructor, so no caller can bypass it. The inspector also filters them from
      every listing, which is convenience rather than the guarantee.
    - **A stored row is untrusted on the way out.** Loading re-validates exactly as saving does,
      because a row is reachable by anything with database access and by a restore of an older
      backup. Forged definitions — a sensitive base table, a column carrying SQL, unreadable JSON —
      are skipped with a named reason, and one bad row does not take the catalog down with it.
    - **A stored row cannot shadow a shipped dataset**, which would be a way to redefine a trusted
      dataset, with its expression powers, from the database.

    Two structural rules stop definitions that are valid SQL but not valid datasets: a join's left
    side must be a table already in the dataset (otherwise a join can quietly widen the FROM
    clause), and a join with no conditions is refused rather than becoming a cross product.

    The residual risk is the one §3.3 predicted and accepted: a careless author can expose a table
    they should not have. That is a data-exposure problem — auditable, reversible, and gated behind
    its own security id.
16. The nine `special_class` ports, the catalog, both renderers, the delivery layer, the module
    surface, the dataset authoring UI and the print path from the documents that own the forms are
    done, and §8.2 migration has its first worked case (`pf_73`, §12.4). What is left before 1.0.0
    is **the designer** — see §13, which supersedes this step and carries its own plan. PhreeForm
    is retired by that work rather than separately: deleting it would otherwise take the only
    editor with it.

---

## 12. Measured status

Every figure below was **measured on 2026-08-12**, and §12.3 on 2026-08-13, not quoted. Re-run the suite (§12.2) after any
change to the seam, catalog or renderers; these numbers are the regression baseline.

### 12.1 Verification suite

| Tool | Proves | Needs DB | Result |
|---|---|---|---|
| `verify_forms_seam.php` | The seam holds: immutability, no template/data fusion, typed row kinds, renderer routing, CSV emits raw numbers | no | **20 passed, 0 failed** |
| `verify_forms_catalog.php` | Catalog safety (§3): no free-form SQL reaches the builder, filters bind parameters, expression parameters bind rather than interpolate, sensitive tables unreachable | no | **PASS — 103 guarantees** |
| `verify_forms_formatter.php` | `Formatter` parity with `ProcessData()`/`AddSep()` | no | **PASS** — 16 separators, 24 lookup hints matched; 5 DB-backed hints correctly refused |
| `verify_forms_datasets.php` | Every dataset field and join resolves against the live schema | yes | **PASS — 7 datasets, 220 fields** |
| `verify_forms_report.php` | Per-report row-set parity against PhreeForm, plus the intended CSV divergence | yes | **30 of 30 verified**, 0 failing (26 on `production`, 4 on `demo`); also drives the four structural reports (`pf_45`, `pf_50`, `pf_65`, `pf_66`), which take `--period=` and `--gl=` in place of the run dialog. `--dates[=prefs]` re-runs any of them with a date range applied to both engines — 15 pass so far |
| `verify_forms_dates.php` | `DateRangeResolver` parity with `gen_build_sql_date()`, and boundary arithmetic on pinned days | yes | **PASS — 72 checks** |
| `verify_forms_prefix.php` | Datasets behave identically on a `DB_PREFIX` install | yes | **PASS** — `production` 188,195 rows; `demo` 106,146 rows |
| `diff_forms_provider.php` | Every ported `DocumentProvider` matches a PhreeForm baseline, field for field and line for line | yes | **0 differences** across 4 forms / 16 records |
| `verify_forms_native.php` | Every definition converts to the native format, re-encodes byte-stably, loads back as native, and renders **identical PDF bytes** through it (§13.7 step 1) | yes | **PASS both companies** — 56 of 57 convert, 56/56 byte-identical on `demo`, 51/51 on `production` |
| `verify_forms_designer.php` | Opening a report in the editor and saving it untouched returns the same definition and prints the same bytes; a field the dataset does not declare is refused; the original is kept on first save (§13.7 step 2) | yes | **PASS both companies** — 38 of 38 reports survive an untouched save |
| `verify_forms_canvas.php` | The geometry the canvas overlay rests on, read back out of the PDF content stream: page size, absolute (not margin-relative) placement, both orientations, five paper sizes; and that a form opened and saved untouched is unchanged (§13.7 steps 3–4) | yes | **PASS both companies** — 61 checks; found `Letter` rendering as A4 |

**Run the suite against both companies — neither alone is sufficient.** `production` carries the
transaction volume; `demo` is the only company with employees, assets and work orders. Four reports
(`pf_35` Employee List, `pf_86` Asset List, `pf_89`/`pf_90` work orders) return **no rows at all on
`production`**, so the legacy engine produces nothing to compare and the tool reports SKIP. All four
pass on `demo`. Combined, **all 30 shipped non-special reports are verified, none failing**.

The same gap silently weakened the prefix check: on `production` the `work_orders` and `assets`
datasets compared **0 rows** and still reported `[ok]` — a vacuous pass. The `demo` run gives both
real coverage. All seven datasets are now non-vacuously verified.

> A worked example of why data coverage matters, worth keeping in mind for the remaining work.
> `pf_90` failed on `demo` while it held a single `wo_task` row. Its assertion is
> `count($formsGroupLabels) > 1` — legacy groups by `wo_task.id`, a column it never displays, so it
> degenerates to one unlabelled total, and the check exists to prove Forms groups where legacy
> cannot. With one task, Forms could only emit one group and the divergence was unobservable. It
> was never a defect. Three tasks across four work orders made it pass.

### 12.1.1 A defect the suite could not see

`Tools -> Documents (Forms)` was fatal on first open — `Undefined constant "PF_DIR_MY_REPORTS"` —
while every tool in the table above reported green. The reason is worth recording, because it is a
property of the harness rather than of the code.

`get_report_details()` reads report definitions out of `PF_DIR_MY_REPORTS`, which
`phreeform/defaults.php` defines. Every tool bootstraps through `fb_bootstrap()`, which loads that
file for its own reasons; the page did not, and `application_top.php` does not load it either. So
the tools were exercising a path the application could not reach — including
`verify_forms_app.php`, which was written specifically to drive "the application's own path" and
still missed it, because it inherited the harness's bootstrap instead of a page's.

That was the first of three, and the first two fixes each revealed the next — which is the real
lesson. A missing `PAGE_TITLE`, which `template_index.php` echoes unguarded. Then a toolbar entry
created by assigning into `$toolbar->icon_list['new']`, leaving it without the `order` key that
`build_toolbar()` feeds to `array_multisort()`: `ValueError: Array sizes are inconsistent`. Both
live in the page BODY, so no amount of checking a page's *requires* would have found either.

`verify_forms_pages.php` therefore does what `index.php` does — sets `DIR_FS_WORKING`, includes
`pre_process.php`, `template_main.php` and `js_include.php`, and fails on anything raised. Four
things it had to get right to be worth having, each found by testing the test:

- **One page per process.** The first version replayed both pages into one interpreter and asserted
  at the end, so the datasets page satisfied assertions the main page's deletion should have broken
  — it passed against code known to be broken. A constant cannot be undefined once set, so the
  isolation has to be a process boundary.
- **Warnings attributed by file.** Rendering a Forms page walks through PhreeForm and three
  modules' language files, which carry 17 warnings of their own that predate this work. Failing on
  those makes the output something to skim rather than act on, so only warnings from
  `modules/forms/` fail; the rest are reported as inherited.
- **What the page NAMES, not what it runs.** The datasets page calls `get_report_details()` only
  when deleting, so its list view rendered perfectly with the PhreeForm bootstrap missing and would
  have broken on the first delete. Rendering alone gave a false pass; the check now scans the page
  for the symbols it references and asserts each is defined.
- **Scanned over tokens, not text.** That scan first flagged `PF_FONT_*` out of a comment
  explaining why `PF_FONT_*` matters. Comments and string literals are not references.

A fourth defect then arrived that all of the above still missed, because it was not a fatal: the
page rendered perfectly and emitted

```
onclick="formsRun("http://localhost/index.php?...", 'pdf', false)">
```

`json_encode()` produces double quotes, the attribute is delimited by double quotes, and the browser
discards the handler at the first one. The result is a button that highlights and does nothing. So
"it rendered" is not the claim worth making; the output is now PARSED, and three things asserted of
it — no malformed attribute, no inline handler truncated by its own quoting, and one run control per
runnable document each carrying a valid URL and format. The fix removes the hazard rather than
escaping it: the URL moved to a `data-` attribute with a single delegated listener, where HTML
escaping is sufficient and cannot break out.

That check immediately found a second, quieter fault in the same markup — `html_pull_down_menu()`
already emits `id="<name>"`, so the `id=` passed through its `$parameters` produced a duplicate on
every select.

Finally, the suite follows a run link end to end: it takes a `data-url` out of the rendered page,
replays it as `$_GET`, and asserts a PDF comes back. Getting that honest took two goes, both of
which looked like the page failing and were the harness: `HttpDelivery` refuses to deliver once
output has started — correctly, since a document appended to a half-rendered page is a corrupt
download — and in a CLI the harness's own chatter counts as output. Buffering now starts before the
bootstrap prints anything.

The PhreeForm require must also be at FILE scope: `phreeform/defaults.php` guards its hook-loading
loop with `isset($loaded_modules)`, so from inside a function it would quietly skip every module's
`pf_process_*` hook — a wrong report rather than an error.

One thing observed and deliberately not changed: `index.php` dispatches to any module page whose
file exists, without consulting `MODULE_<NAME>_STATUS`. On a company where Forms is not installed,
`?module=forms&page=main` therefore reaches the page and dies on an undefined `SECURITY_ID_FORMS_RUN`
rather than refusing politely. Nothing leaks — `validate_user()` is never reached — and the
behaviour is identical for every uninstalled module, so it is a framework question rather than a
Forms one.

### 12.1.2 Reports wider than the page they declare

Twenty-eight of the forty-three shipped dataset reports ask for more column width than their paper
holds — Vendor Ledger wants 287 mm on a 190 mm portrait body, Customer List 275 mm. PhreeForm let
the bands run past the right margin and TCPDF clipped whatever fell outside, so those reports have
been losing the tail of their last column on every print since they were written. It surfaced only
because a three-letter currency code lost its third letter, which is visible in a way a truncated
customer name is not.

The fix is in two halves, because report definitions live per company in
`my_files/{company}/phreeform/pf_NN`, seeded at install — so editing the shipped XML reaches new
installations only.

- **At render time**, `TablePdfRenderer` chooses the orientation: the declared one if the columns
  fit, landscape if that is what it takes, and only then scaling. The designer's WIDTHS are never
  overridden to achieve this — turning the paper is a change to the paper, not to the report. A
  report that declares landscape keeps it even if it would fit portrait, because that was a choice.
  On `demo`, 28 of 47 documents now print landscape and every one fits between its margins.
- **In the shipped definitions**, twenty-two reports were set to `<orientation>L</orientation>` so a
  fresh install is right rather than rescued. Nine already declared landscape and it was the
  per-company copies that had drifted, which is exactly why the render-time half is needed.

Nine remain wider than Letter landscape (263 mm printable), by 2–24 mm. Those are scaled at render
time to 92–99%, which is mild, and `verify_report_page_widths.php` lists them every run rather than
passing over them: each is fixed properly by a column width or a larger sheet, and that is a
judgement about paper stock rather than something to decide in code.

`verify_forms_app.php` asserts the drawn table ends inside the right margin for every document, and
`verify_report_page_widths.php` reads the shipped XML directly — no database, no company — so the
artefact in the repository is checked rather than one installation's copy of it.

### 12.2 Reproducing it

The tools resolve the seam **relative to their own location** (`../../trunk/modules/forms/`), so
they expect the repo layout — not a flat directory. Inside the container:

```sh
docker exec <c> mkdir -p /opt/repo/docs/tools /opt/repo/trunk/modules
docker cp docs/tools/.          <c>:/opt/repo/docs/tools/
docker cp trunk/modules/forms   <c>:/opt/repo/trunk/modules/
docker exec <c> sh -c 'cd /opt/repo/docs/tools && php -d display_errors=1 verify_forms_seam.php'
```

Four traps, each of which produces a wrong or misleading result rather than a clear error:

1. **Always pass the company explicitly, and run both.** Auto-detection silently picks the *first*
   company directory (`demo`), so a run you believed covered `production` may not have. Use
   `FREEBOOKS_COMPANY=<company>` or the positional argument, and run the DB-backed tools against
   **`production` and `demo`** — they cover disjoint data (§12.1), and a single-company run leaves
   whole datasets either untested or vacuously passing.
2. **`verify_forms_prefix.php` needs `-d memory_limit=2G`.** It compares whole row sets in memory
   and dies at the default 256 MB partway through `general_ledger` — which looks like a failure of
   the prefix work rather than of the harness.
3. **`display_errors` is Off in the image**, so CLI fatals are invisible. Always
   `php -d display_errors=1`.
4. **`verify_forms_report.php` takes the dataset name, and naming the wrong one still runs.** Every
   column comes back `UNMAPPED`, the range is dropped, and the tool reports a row-count mismatch in
   the hundreds of thousands — which reads exactly like a broken converter. `pf_84` is the Audit
   Log on `audit_trail`, not a `general_ledger` report, and `pf_62` (General Ledger) is on
   `transactions` despite its name; check §6 before believing a failure of this shape. Both were
   mis-invoked here first.
5. **An empty legacy side is not a pass, and the tool now exits 2 to say so.** A structural report
   run without `--gl=`/`--period=`, or a report whose data lives only in the other company, returns
   nothing from PhreeForm — and nothing agrees with any provider at all. Exit 0 means compared and
   matched; exit 2 means there was nothing to compare.

The prefix check copies each table into session `TEMPORARY` tables, so it creates, alters and drops
nothing in any company database — safe against `demo` and `production` while both are live.

### 12.3 Where the Table PDF renderer deliberately differs

`TablePdfRenderer` reaches the legacy engine's layout to the point — heading bands, column widths
and stacking, fonts, zebra fill, the total-border state machine, and the page-number footer all
line up. Four differences remain, and all four are intentional. They are recorded here because
each one looks like a bug in a side-by-side diff.

**1. The page bottom is the real sheet.** 55 of the 57 shipped definitions declare
`Letter:216:282`. TCPDF does not resolve that mixed-case name and falls back to A4, so *both*
engines print on a 297 mm sheet — but PhreeForm computes its page bottom from the **declared** 282
mm, leaving roughly 23 mm of dead space at the foot of every page. Forms measures from the actual
page height, so it fits about three more rows per page and pagination will not match legacy.
Compare content, never page counts.

**2. Totals sum the data, not the printed text.** PhreeForm accumulated totals by formatting each
value for display and then parsing the formatting back off
(`clean_value(ProcessData(...))`), so its total is the sum of the *rounded* values. Forms sums the
raw typed values. On `pf_56` (Customer Payments, `production`, all dates) the difference is 4p, and the
database settles which is right:

| Source | Total |
|---|---|
| `journal_main.total_amount`, summed raw | **5854.5876** |
| GL `pmt` credit postings — what reaches AR and the bank | **5854.5876** |
| Per-row rounded, then summed — PhreeForm's figure | 5854.63 |

Forms prints £5,854.59 and agrees with the journal, the payment and the ledger; PhreeForm's
£5,854.63 agrees only with its own printed column. 43 of those 92 journals genuinely carry
sub-penny amounts, and the GL rows carry them too (`ref_id` 311 posts `dr=30.216 / cr=30.216`) —
so the fractions are already in the account balances, and a report that hides them is the one
misreporting. Note separately that sub-penny values in **GBP at rate 1.0** suggest tax arithmetic
posting unrounded upstream; that is a data question, not a rendering one.

**3. No phantom group total.** PhreeForm's `if ($GrpWorking !== false)` fires even when the report
has no group selected, emitting a duplicate of the report total labelled `Group Total For:` with a
blank name. Forms emits a group total only where a group is configured.

**4. Totals are formatted like the column they total.** PhreeForm's totals are floats it has already
stripped the formatting from, and it prints them as-is: on `pf_62` the money columns read `£ 216.77`
down the page and then `216.77` on the total line. Forms carries a total as a `Money` `TypedValue`
like any other, so the same formatter runs over it and the currency stays on.

### 12.3.1 PhreeForm bugs found while proving this

All are in the legacy engine, all were found by driving the shipped set through it, and each is
fixed only where noted. The pattern is worth naming: every one of them was invisible until
something outside PhreeForm asked the same question twice.

**`RW_DEFAULT_COLUMN_WIDTH` was defined nowhere.** `report_generator.php` read it for any visible
column with no `columnwidth`. The name is a typo for the `PF_DEFAULT_COLUMN_WIDTH` admin setting
(default 25 mm); before PHP 8 an undefined constant degraded to its own name and a notice, and
since PHP 8 it is a fatal — so 13 of the shipped reports died on PDF print, among them General
Ledger, Sales Report, Inventory List, Vendor List and Audit Log. **Fixed**, along with the
adjacent `$CellXPos[$col]` read that warns on every field of every report.

**Grouping silently collapses when the group field is not also a displayed column.** `BuildDataArray()`
derives its break key by scanning `fieldlist` for the group's field name; when it is absent
`$GrpField` stays `''`, `$myrow['']` is constant, and every row falls in one group. Customer Ledger
prints one group total where there are 118 customers. **Not fixed** — PhreeForm is being retired
and Forms groups these reports correctly.

**"By period" dies on any report that is not built on a journal.** `gen_build_sql_date()` case `z`
emits a bare `WHERE period = N`, unqualified and unconditional. The run dialog offers that choice on
every report, so picking it on the Audit Log builds
`SELECT ... FROM audit_log JOIN users WHERE period = 14` — neither table has a `period` column, the
query fails, and `queryFactory::set_error()` prints its Database Error page and `die()`s. The same
applies to every inventory, contact and asset report. **Not fixed**, for the same reason; Forms
warns and drops the range instead, because a report that quietly covers all time while its header
claims one period is worse than one that says it could not comply.

**The Work Order form could not be printed at all.** `BuildPDF()` decides whether to load posted
currency values with `strpos($report->sqlTable, TABLE_JOURNAL_MAIN) !== false` — a substring test
where an identity test was meant. `'journal_main'` is a substring of `'wo_journal_main'`, so with
multi-currency enabled and no `DB_PREFIX` the Work Order form asked `wo_journal_main` for
`currencies_code`, the query failed, and `gen_redirect()` sent the user to the crash page before a
single page was drawn. A prefixed install escapes it by accident: `pb_journal_main` is not a
substring of `pb_wo_journal_main`. **Fixed** with a word-boundary match, which is correct either
way. This one blocked the port outright — no legacy baseline could be captured until it was fixed.

**Aged Payables Summary aged its buckets by the receivables periods.** `aged_summary` computes its
three cutoffs once from `AR_AGING_PERIOD_*`, but serves both the receivables and the payables
summary; `aged_receivables`, its near-twin, correctly picks `AP_AGING_DATE_*` per row for journals 6
and 7. AP and AR aging are independently configurable in admin, so the two reports disagree wherever
an installation has set them differently. **Fixed** by choosing the cutoffs per row, as the twin
does. Latent in both `demo` and `production`, which run the 30/60/90 default on both sides.

**The Work Order form printed at most its order-level reference documents.** `wo_builder::build_ref_lists()`
read `ref_specs` from a query that selected `ref_spec`, so order-level specifications were always
empty; and it guarded the per-step merge with `count(is_array($docs > 0) ? $docs > 0 : [])`, where
comparing an array to an integer yields `true`, `is_array(true)` is `false`, and `count([])` is 0 —
so no step ever contributed a reference. The intent was plainly `count($docs) > 0`. **Not fixed** in
PhreeForm; `WorkOrderDocumentProvider` gets it right, which makes it the one place a Forms document
deliberately carries *more* data than its baseline.

**Both aged classes got the sign wrong, in opposite directions.** The data settles it without
argument: a customer invoice and a vendor credit memo both carry their `ttl` amount as a DEBIT,
while a vendor bill and a customer credit memo both carry it as a CREDIT. Which side a document
sits on follows the ledger, not the document type — so the rule that makes every base document
positive and every credit memo negative is to negate the payables journals (6 and 7) and leave the
receivables journals (12 and 13) alone. `aged_receivables` negated {6, 13}, reading a vendor credit
memo as a positive payable. `aged_summary` negated {7, 13}, reading a vendor BILL as negative.
**Not fixed** in PhreeForm, which is being retired; Forms negates by side, and the divergence is
asserted by `verify_forms_report.php` as "identical up to sign" rather than skipped — no row may
appear or vanish and no magnitude may change.

**The shipped Income Statement has never run.** `pf_64` names `special_class` `income_statement_au`,
which exists nowhere in the codebase — not in `trunk`, not in `extensions`. `find_special_class()`
returns false, `BuildDataArray()` returns false, and the report produces nothing at all. `pf_65`
(Income Statement w/Budget) and `pf_66` (Income Stmt - 2 Years) use the real `is_budget` class and
work. **Not fixed**: repointing it at `is_budget` would change what a shipped report prints, and
Forms serves the same three definitions regardless.

**One PHP 8 warning that was only ever a warning.** `is_budget::load_report_data()` emits a row
built from `$temp`, a local of `add_income_stmt_data()` that is undefined in that scope. Every cell
came out null, and the `def_cur` processor passes non-numerics straight through, so the row rendered
blank — which is exactly the separator the surrounding code wanted. Recorded because it was first
reported as wrong output and is not: the Income Statement was always correct. **Fixed** as an
`add_heading_line()` call, same output, no warning.

**The Account Reconciliation has been fatal since PHP 8, and wrong before that.** Two defects, in
that order. `acct_recon` blanks the unused side of each transaction (`$dep_amount = ''` on a
payment) and then adds it to a running total, so `0 + ''` raises `Unsupported operand types` on the
first payment line; `$dep_cleared`/`$chk_cleared` are never initialised, so the cleared half fails
identically. **Fixed** — four lines, because nothing could be captured against a class that dies.
Underneath was the real bug: the class keys its transaction list by `journal_main.id` while
totalling every line, so an entry with several lines against the account contributes all of them to
the total and prints only the last. `demo` listed two of six cleared transactions. **Not fixed** in
PhreeForm; the provider lists one row per line and the harness asserts that no legacy row was lost
and every surplus is a transaction line. Two lesser habits also stop in the port: the GL account
came from `$report->filterlist[0]->min_val` on the assumption it is the first criterion, and went
into both queries unbound.

This makes **two shipped reports found dead rather than merely wrong** — this one and `pf_64` above.
Both had been unrunnable long enough that nobody noticed, which is the strongest argument in the
document for driving the whole shipped set through a harness rather than spot-checking it.

**A report has never shown the currency a record was posted in.** `BuildDataArray()` sets
`$posted_currencies` to the HOME currency once, at the top, and never per row — so a `posted_cur`
column on a report prints the home amount with the home symbol, and a payment taken in EUR reads as
sterling. Only the forms path sets it per record.

That is not the arithmetic error it first looks like, and the difference matters: the stored
`total_amount` IS the home-currency figure, so £ 46.62 is correct and € 46.62 would not be — the
posted-currency equivalent is € 57.11. The column is also usually totalled, and a total of mixed
currencies means nothing, so home-currency amounts are what make it addable. The vocabulary does
distinguish the two (`def_cur` is home, `posted_cur` is posted; nine shipped reports use the former
and nineteen the latter), so the designer did ask for something the engine never delivered.

**Not fixed, deliberately.** Forms briefly appended a Currency column automatically and it was
reverted: redesigning a shipped report is not the engine's decision. The information is one field
away — `journal_main.currencies_code` is in the transactions dataset and selectable in PhreeForm's
designer — so anyone who wants that column can add it to the definition both engines read. What
Forms owes here is faithful rendering and the field being available, and both hold.

One deliberate divergence remains: a **bound that is not in database format** is dropped rather
than guessed. `gen_db_date()` substitutes TODAY for anything it cannot parse, so a mistyped date
silently reports the wrong month instead of failing.

Everything else about an unresolvable range fails *narrow*, which is the rule the resolver is built
around: an undefined accounting period keeps its `period = N` filter and loses only the dates its
header would have shown, and a quarter whose closing period is undefined still applies the opening
bound. Dropping the filter would turn "period 999" into "all time" — the one wrong answer that
looks like a working report.

### 12.3.2 Three harness bugs the form ports exposed

The provider diff had been run against one form. Three of its assumptions were really assumptions
about the Invoice, and each produced a *wrong result* rather than an error — worth recording,
because the same shape will recur as the report providers land.

**Positional re-keying took only `Data` elements.** `BuildForm()` sequences `Data`, `BarCode` and
`ImgLink`, and `wo_builder` returns all three. Taking `Data` alone left the name list shorter than
the value list, so every Work Order field compared against its neighbour: six fields reported wrong
when the data was right and only the alignment was off. An image filename showing up under `id` is
the tell.

**A `serialform` baseline captured almost nothing.** `BuildSeq()` renders by `array_shift()`ing
`$FieldValues`, so by the time it returns there is nothing left to snapshot — the same
consume-as-you-go hazard already recorded for `BuildDataArray()`. The POS receipt is the only
serial form in the set. Its baseline now drives the special_class directly, which still tests the
real legacy data layer; what it no longer proves is that the engine can drive it.

**A record with nothing in it passed.** A date range that excludes every row of a record leaves the
baseline empty, and an empty baseline agrees with any provider at all. This is the third appearance
of the vacuous-green failure mode (after `pf_90` and the prefix check over 0 rows), so the tool now
says `VACUOUS` and counts it separately instead of printing a tick.

Two further findings were **not** harness bugs and are kept as behaviour: PhreeForm returns `null`
for a header field its special_class never sets — the statements bind `rep_id` and `terms` that way
— and a provider that declines to invent them agrees; and a table may hold rows of more than one
shape, as the POS receipt's payment lines do, so a missing key is not a missing value.

### 12.4 What remains

Per §11, step 16: **the designer, and with it the retirement of PhreeForm** — planned in §13.
Printing forms from the documents that own them is done, and migrating a customised install has its
first worked case (`pf_73`, below).

**The forms path now runs, and has a page (2026-08-14).** `?module=forms&page=print` answers on
PhreeForm's own popup contract — `gID`/`rID`, `xfld`/`xcr`/`xmin`/`xmax`, `date` — so pointing a
Print button at Forms is a URL change and nothing else. It names the form and the records it is
about to print, then offers View, Download and Email; email goes through `EmailDelivery`, which
stages to a temp file and always removes it. `verify_forms_pages.php` gained two modes, `print`
(the screen) and `printrun` (the delivered bytes), and drives them from a (form, record) pair
`verify_forms_forms.php` has already proven renders.

**The Print buttons can now be pointed at Forms, per company (2026-08-15).** Not repointed —
*pointable*, which is the difference §11.16 asks for. All eleven call sites (orders, bills, status,
bulk_bills, three in the POS, the Square Terminal return, and the work order button in the
`work_orders` extension) call one helper, `gen_print_form_url()` in `common_functions.php`, and it
opens Forms only when three things are true:

- Forms is installed. Otherwise the page does not exist, and a hard repoint would have broken
  printing outright on every install that has not enabled it — which is why this is a helper and
  not a URL edit.
- `MODULE_FORMS_PRINT_BUTTONS` is `1`. It ships `0`, so installing Forms to *look* at it does not
  silently retire PhreeForm. It is set on the module's own settings page
  (`?module=forms&page=admin`, reached from Module Administration), and flipping it back is a
  pull-down rather than a deploy.
- The person clicking holds `SECURITY_ID_FORMS_RUN`. Printing a document and running one were never
  the same permission — a warehouse user who prints packing lists may well not have the second —
  and without this check they would get a popup that redirects to the dashboard. They keep printing
  on PhreeForm until their role is granted the key, and the settings page says so, because a
  per-user fallback nobody mentions is just a mystery.

`verify_forms_print_buttons.php` holds both halves. It exercises the permission gate in both
directions (the one an admin cannot see, since it depends on who clicked), asserts `xmin` is the
last parameter so a caller can append the record id in JavaScript, and then does the part that
makes flipping the switch *safe* rather than merely reversible: it harvests every form group the
source can send — from `POPUP_FORM_TYPE` and from the literals passed to the helper, so a Print
button added later is covered without anyone remembering — and checks each one resolves to a form
Forms will serve. On both companies, eleven groups, none declined. `bnk:rcpt` holds no form on
either, which is a gap on *both* engines and predates this.

Two defects it caught immediately, each of which would have shipped as "the Print button does
nothing":

- `FILENAME_DEFAULT` is `'index'`, not `'index.php'` — `html_href_link()` is where the extension
  normally gets added, and a helper building its own relative URL has to do the same. Every button
  would have 404'd.
- The `work_orders` extension has an eleventh Print button that names no `POPUP_FORM_TYPE` and
  writes `gID=inv:wo` into the URL directly. Grepping `trunk/` for the constant would have missed
  it; the tool scans the **deployed** tree for the engine's page name instead, which is also what
  keeps it honest about what the web server is actually running.

Forms answers two things it did not before, both for parity with what the buttons already send:
`&autoprint=1` (Square Terminal, so the till operator gets the receipt without a dialog) presses
View once the page has resolved a single form — where the group holds several, the chooser stays
up, because printing the wrong document unasked is worse than waiting for a click; and
`rEmail`/`rName` prefill the email field where the journal lookup cannot reach, which is the work
order case, since `wo_journal_main` is not `journal_main`.

A harness bug surfaced in passing and is fixed: the (form, record) fixture `verify_forms_forms.php`
publishes for `verify_forms_pages.php` was named without the company, so running two companies in
sequence printed one company's record against the other's database. It reported the print path
broken when it was not — and could as easily have reported it working when it was not.

`FormRunner`
routes a form definition to one of the five providers and builds its `LayoutDocument`;
`RecordSelector` translates the four URL parameters every Print button already sends
(`xfld`/`xcr`/`xmin`/`xmax`) into the record ids a provider takes. `verify_forms_forms.php` drives
both over the whole catalog: on `demo` **all 18 forms** run and render as PDF (9 by provider, 9 by
dataset); on `production` 12 render, 5 have no data, and 1 is declined — see below. What is missing is
the page that calls them, and the change to the Print buttons that would point at it.

Three things were found by making it run, and all three are recorded because each was invisible
until a form was actually built:

- **A form's `record_ids` are not always primary keys.** The Deposit Slip breaks on
  `purchase_invoice_id`, so handing it `journal_main.id` values selects nothing and returns an
  empty document rather than an error. `FormPlan::$recordKey` now states the column, and a caller
  that ignores it gets silence — hence the field, and hence the test.
- **A legacy filter value can be a LIST.** The Deposit Slip's journal filter holds the single
  string `"2, 18, 19"`. A first cut converted every form filter with `Filter::equals`, which
  matched nothing; worse, in MySQL comparing an integer column to that string casts it to `2`, so
  the *fixture* query silently selected from the wrong journal. `ReportConverter::convertFilters()`
  already handled every legacy mode, so forms now use it rather than a second, weaker copy — it
  needed only to build its field map when called without `convertColumns()` first.
- **A customised definition can be genuinely unservable — and that is how §8.2 migration works.**
  The `production` company's Purchase Order (`pf_73`) joins `inventory_purchase_details` and prints a field whose
  *name* is `IF(inventory_purchase_details.vendor_sku <> '', …, SUBSTRING_INDEX(…))`. That is §1.1
  exactly — the report author writing SQL — and Forms declined it rather than executing it.

  **Resolved 2026-08-15 by converting the customisation, not by running it.** A new shipped dataset
  `purchasing` is `transactions` plus a LEFT JOIN to `inventory_purchase_details` on both
  `(journal_item.sku, journal_main.bill_acct_id)`, carrying the same arithmetic as a computed field
  with the legacy string in `legacyNames`. The definition needs no edit: `DatasetResolver` picks the
  narrowest dataset covering its tables, so `pf_73` lands on `purchasing` and every other document
  keeps `transactions`. Confirmed: only `pf_73` on `production` uses it, the `demo` company's uncustomised Purchase
  Order still resolves to `transactions`, and `production` now declines nothing.

  A separate dataset rather than extending `transactions`, because that one is behind 44 documents
  and `(sku, vendor_id)` is **not unique** — `production` has a duplicate pair — so the join would have
  put an extra row into unrelated reports to serve one form. The provider's de-duplication on
  `item_id` absorbs the duplicate; `verify_forms_vendor_sku.php` asserts that, and compares the
  computed field against the legacy SQL line by line: **40 purchase orders, 797 lines, zero
  mismatches**. Removing the fallback branch from the expression makes it fail, which is what says
  the comparison is real.

  The pattern generalises: a customisation that reaches for SQL is migrated by naming its arithmetic
  in a shipped dataset, keyed to the legacy string, so the stored definition converts untouched.

Forms is now reachable from the menu and runs the shipped catalog end to end. `verify_forms_app.php`
drives the application's own path over every definition: on `demo` 47 documents run and render as
both PDF and CSV (43 by dataset, 4 by provider, 10 honestly declined), on `production` 40 (six more
select no rows there, which is data coverage rather than a defect).

**All nine `special_class` ports are done.** Nothing in `modules/phreebooks/classes/` or
`modules/phreeform/` is now on the critical path.

Reconciliation coverage is thinner than the rest and worth stating plainly. `production` has no
reconciled journal items at all, so its runs exercise the in-transit and GL-movement halves (87 and
57 rows on two cash accounts, matching exactly) but never the cleared sections. Only `demo` has
reconciled items — 11 lines across two accounts and two periods — and it is also the only place the
`journal_main` collapse is observable. Re-run against a company that reconciles regularly when one
exists.

`is_budget` is now fully verifiable: `demo` carries budget figures on five P&L accounts across
periods 13 and 14, and postings in periods 1, 2, 13 and 14, so all six of its columns — current,
YTD, last-year current, last-year YTD, and both budget columns — hold distinct non-zero values for
Income. Cost of Sales has a current and YTD actual; Expenses has budget figures only, which is
acceptable because `add_income_stmt_data()` runs identical arithmetic for both (`negate=false`).

Dataset depth is uneven, though every dataset now has real coverage. `transactions` is proven
deeply (14 reports, 28,832 rows prefixed). `general_ledger`, `inventory`, `contacts` and
`audit_trail` are verified across several reports and substantial row counts. `work_orders` (5
rows) and `assets` (3 rows) pass every check but on small `demo` fixtures — treat those two as
smoke-tested rather than proven, and re-run when a company with real work-order or asset volume
exists.

`transactions` gained six computed aging fields, so it is now the only dataset whose SELECT binds
parameters; `verify_forms_catalog.php` asserts that every one of them is a value the dataset
declared and that the count matches the placeholders, which is a stronger guarantee than the
"parameterless SELECT" it replaced.

The same caveat now applies to two of the form providers. The POS receipt and the Work Order are
`demo`-only — `production` has no journal-19 receipts and no work orders at all — and the four work
orders there share one task each, so the routing table is proven at one row. The statements are the
best covered of the three: five contacts across both companies, customer and vendor, plus the same
records re-run under three date preferences. **Neither company holds a single non-zero budget
row**, so when `is_budget` is ported, one of the three reports it serves (`IncomeStatementw_Budget`)
can only be verified structurally until budget figures exist.

---

## 13. The designer

PhreeForm's `popup_build` is the last thing Forms does not replace. Everything else — the nine
providers, both renderers, delivery, the catalog, the print path — is done and measured above, but
the definitions themselves are still authored in the old engine, and both engines read the same
files precisely so that they can be. That is why retiring PhreeForm has stayed open-ended: not
because printing is unproven, but because deleting it would take the only editor with it.

`popup_build` is 2,422 lines across twelve files and does four jobs in one modal: pick tables and
joins, pick criteria, pick fields, set the page. Forms needs three of them. The first is gone —
join graphs belong to the Datasets page, behind `SECURITY_ID_FORMS_DATASETS` (§3.2, decision A) —
which is why this is a smaller tool than the one it replaces rather than a port of it.

### 13.1 Two editors, not one

Measured on `demo`, 2026-08-15:

| | Definitions | Shape | Size |
|---|---|---|---|
| Reports (`rpt`) | 39 | ordered column list (`columnwidth`, `align`, `total`) | avg 9 columns, max 20 |
| Forms (`frm`) | 18 | positioned boxes (`abscissa`/`ordinate`, mm) | avg 42 elements, max 72 |

Only the second wants a canvas. A report is a column list with grouping, sorting and filters;
dragging boxes around one would be theatre. It gets a grid and the same live preview, rendered
through `TablePdfRenderer` on the rows its own filters return.

Element types actually in use across all 18 forms, which is the vocabulary the canvas must handle:
`Text` 322, `Data` 213, `Line` 64, `CDta` 51, `TBlk` 26, `Rect` 19, `Tbl` 18, `CBlk` 15, `Img` 14,
`PgNum` 11, `Ttl` 8 — and exactly one each of `TDup`, `ImgLink` and `BarCode`. The long tail is
three elements in the entire shipped set, which is worth knowing before anyone builds a general
solution for it.

### 13.2 The page underneath is the real page

The requirement is that an author sees how the form will look while editing it. Three ways to do
that, and the difference between them is where the fidelity comes from:

| | Approach | Cost |
|---|---|---|
| A | Absolutely-positioned HTML in mm | Cheap, draggable — but browser font metrics are not TCPDF's, so text wrap and overflow are wrong exactly where it matters |
| B | Edit boxes on one side, PDF preview on the other | Honest, but the author compares two pictures by eye |
| **C** | **Render the real PDF through `LayoutPdfRenderer`, rasterize it in the browser, and put the element boxes on a transparent layer above it** | **The author drags against the true output** |

C, and the constraint that decides *how* C is done: the container has **no Imagick, no Ghostscript
and no `pdftoppm`** (checked 2026-08-15; only `gd`). Server-side rasterizing would mean a new
system dependency on every deployment target. Rasterizing in the browser needs none, which makes
vendoring **pdf.js** into `trunk/ext/pdfjs/` the route — self-contained, no build step, alongside
`bootstrap5` and `htmx.min.js` exactly as those are.

The preview binds to a **real record** via `RecordSelector`, and falls back to generated sample
values — visibly marked as such — where the form's table is empty on this company. A fresh install
with no invoices must still be able to lay out an invoice. Those values are invented but believable
in LENGTH, which they were not at first and which turns out to be the whole point of them: see §13.18.

Rulers, grid, margin box and inspector all read in **mm**, because that is the unit the definition
stores and the unit paper is sold in. No pixel reaches the file.

### 13.3 One format, one migration

Forms reads PhreeForm's XML today and would keep working if it kept doing so. It will not, because
a format Forms cannot extend is a ceiling on every feature after 1.0.0.

The naive reading of that is a permanent dual-format world: read either, write native, convert each
definition when someone first edits it. That was the plan for about an hour, and it is worse than
it looks — a format discriminator, a format-aware loader, a mixed catalog that never resolves, and
a per-document backup file, all carried forever to serve a distinction no user can perceive. A
definition nobody edits prints the same either way; a definition somebody edits needs a new file
regardless.

**So the conversion is bulk, once, in the database upgrade, and PhreeForm is deleted in the same
release.** Afterwards there is exactly one format and the loader is a file read. `TemplateConverter`
stops being a runtime path.

Three conditions on that, each of which is the difference between a migration and a data loss:

1. **It refuses to run while any definition is unconvertible, and names them.** Not hypothetical:
   The `production` company's `pf_73` was exactly that until the `purchasing` dataset was added for it (§12.4).
   Converting 56 of 57 and dropping one is unacceptable once no engine can read the leftover. Check
   all, block, list, let the admin fix or discard, then run.
2. **Legacy files are moved, not deleted** — `my_files/{co}/phreeform/` becomes
   `phreeform.pre-forms/`, kept for one release. Nothing reads it. It exists for the one customer
   whose customised invoice converted subtly wrong, and it costs a rename.
3. **It ships in the release that has the designer.** The moment PhreeForm goes, `popup_build` goes
   with it. Shipping the migration first would leave an installation that prints everything and can
   edit nothing.

The converter survives in one place: **"Import a PhreeForm definition"** in the designer. Same
code, no longer a loader branch, and it is the upgrade path for a definition that turns up on a USB
stick two years from now.

A consequence worth recording so it is not mistaken for a permanent feature: `MODULE_FORMS_PRINT_BUTTONS`,
the PhreeForm branch of `gen_print_form_url()` and the row on the Forms settings page are all
**scaffolding for the transition**, and are deleted in the same release. There is nothing to switch
to once PhreeForm is gone.

### 13.4 What the palette may offer

The field palette is the whole authoring vocabulary, and it is drawn from the resolved dataset:
`Record`, line-item, `Context` and aggregate scopes, each listing the fields that dataset declares.
There is no table picker and nowhere to type an expression.

That is not a simplification, it is §1.1 and §3 restated as a UI. PhreeForm's designer opened with
a bare `SHOW TABLES` and let an author put SQL in a field *name*, which is why any report author
was a database-wide read primitive. An author who can only pick declared identifiers cannot reach a
table the dataset does not name.

Gated on `SECURITY_ID_FORMS_AUTHOR` (id 9) — defined in `forms/config.php` since 1.00, used
nowhere, reserved for this. It is the middle tier of decision F, and it has been empty until now.

### 13.5 A new company ships native

52 `.xml` definitions ship under `modules/{contacts,inventory,phreebooks,phreedom,phreepos}/language/en_us/reports/`
and are copied per company by `admin_add_reports()` → `ImportReport()`. A new install or new
company gets native definitions from the start, which means generating native equivalents once,
committing them, and teaching the installer to seed those instead.

Three traps in that, all of which fail silently:

- **`ImportReport()` is where the paper size is stamped** (§11 paper-size work, commit `5db3ccd`).
  A native seeding path that does not do the same loses the installer's Letter/A4 choice on exactly
  the installs that just made it. **Re-stamp has the same hole**: it is a textual substitution on
  XML and would skip every native file.
- **Seeding is locale-aware** — `language/{locale}/reports/` with an `en_us` fallback. Native files
  follow that convention or a non-US-English install seeds nothing.
- **A fresh install has nothing PhreeForm can read**, so on a fresh install
  `MODULE_FORMS_PRINT_BUTTONS` defaults **on** while an upgrade keeps it **off**. The installer
  sets it differently; no new mechanism is needed, which is what that switch was built for.

This is why 13.5 is late in the order below despite being the first thing anyone asks for: seeding
native before the designer exists gives a fresh install documents nobody can edit.

The optional modules under `extensions/Modules/` ship native definitions too, decided 2026-08-18 —
see §13.25 for what that added and for the trap it uncovered.

### 13.6 Decisions

Continuing §9.

| # | Decision | Rationale |
|---|---|---|
| G | Two editors — a canvas for forms, a column grid for reports | The two document types have different shapes; 39 reports averaging 9 columns do not want dragging |
| H | The real rendered PDF as the canvas underlay, rasterized client-side by a vendored pdf.js | The only zero-dependency route to true fidelity: no Imagick or Ghostscript on the deployment targets |
| I | Read legacy XML, write native — and convert everything **in bulk** at upgrade, deleting PhreeForm in the same release | A permanent dual-format world buys a distinction no user can perceive |
| J | The migration blocks on any unconvertible definition rather than skipping it | After it, nothing can read what was skipped |
| K | New installs and new companies seed native definitions; upgrades convert | An upgrade is one event, not an ongoing state |
| L | The converter survives as an **Import** feature, not a loader branch | Keeps the upgrade path without keeping the second format |

### 13.7 Steps

1. ~~**Native format, converter, format-aware loader.**~~ **DONE — see §13.9.** A JSON
   serialization of `Template` — already `readonly` value objects, so close to mechanical — plus
   the proof that carries everything after it: all 57 definitions on both companies convert and
   render byte-comparable to the legacy path. Nothing user-visible ships here.
2. ~~**Report editor.**~~ **DONE — see §13.10.** Columns, group, sort, filter, page setup, live
   preview through the existing `DocumentRunner` → `TablePdfRenderer`. The smaller half, and it
   exercises load and save end to end before any canvas exists.
3. ~~**Form canvas, read-only.**~~ **DONE — see §13.11.** pdf.js underlay with element outlines
   drawn on top from the `Template`. No dragging. This proves the mm-to-pixel alignment, page size,
   margins and orientation — the part most likely to be subtly wrong, and the cheapest point at
   which to abandon approach C if it is.
4. ~~**Form editing.**~~ **DONE — see §13.12.** Drag, resize, snap, add and delete, z-order, and an
   element inspector for font, size, alignment, colour, border and binding.
5. ~~**Native shipping set.**~~ **DONE — see §13.15.** Generated, committed, seeded by the installer,
   with paper-size stamping and a re-stamp that understands the native format.
6. **Migration, and delete PhreeForm** — including `MODULE_FORMS_PRINT_BUTTONS`, the helper's
   PhreeForm branch, the settings row, and the four render-to-string call sites below.

During steps 2–5 the loader tries native then legacy, so development and testing work against a
mixed tree. Step 6 deletes that branch; it is a few lines, not an architecture.

#### After step 6

Work that Forms needs but PhreeForm's removal does not wait on. Nothing here is started, and nothing
here blocks step 6 — which is the distinction that keeps step 6 finishable.

7. ~~**Editing a table's columns on the canvas.**~~ **DONE — see §13.30**, which went further than
   this entry asked: a block's parts are editable too, and all nine element kinds can be added, so a
   form created from nothing can become an invoice. §13.31 then made the format a list rather than
   free text. The entry was added on 2026-08-17 after adding a single column to a single shipped
   form took an XML edit, a regeneration and a developer (§13.20); that is now a job for whoever
   owns the form.
8. **Updating a company's copy of a shipped document.** There is no route at all: `admin_add_reports()`
   refuses a definition whose file already exists, deliberately, because replacing it would discard
   whatever the company has done to it. So a fix to a shipped form reaches new installs only, and an
   existing company keeps what it was seeded with for ever. Added 2026-08-17, after the Vendor SKU
   column (§13.20) had to be grafted into a company's own Purchase Order by hand.

   **The blunt version is the wrong one.** That company's copy carried 54 geometry changes — a whole
   form moved down 20–25 mm and its table reshaped — so overwriting it with the new master would have
   thrown a session's tidying away to gain one column. What the hand-run did instead was graft the
   one thing that was new.

   Generalising that is the work, and most of it exists already: `adopt_forms_layouts.php` computes
   precisely the needed diff in precisely the needed categories — what the company changed against
   the master it was seeded from, split into geometry, appearance and content. An update action is
   that diff read in the other direction: take the master's content changes, keep the company's
   geometry, show both before writing, and keep a backup. Where the two have touched the same thing
   there is no honest automatic answer, and saying so per element beats guessing.

#### ~~Step 6 must also move the four render-to-string call sites~~ — DONE

**All four were switched in `cf8c94d2` and the receipt work that followed it** (§13.26, §13.28).
Each now tries `forms_render_to_string()` first and falls back to `get_report_details()` /
`BuildForm()`, so step 6's job here is to delete the fallback rather than to write the branch.

**The standing caution below is therefore lifted**: opening a configured form in the Forms editor no
longer stops its call site producing a document. The section is kept because the trap it describes
is the reason the call sites are written the way they are, and because a reader meeting
`forms_render_to_string()` deserves to know what it was for.

The original entry follows.

`gen_print_form_url()` switches a Print BUTTON, which is a choice about a URL. Four places instead
render a form to PDF **bytes in-process**, and every one of them calls `get_report_details()` and
`BuildForm($report, 'S')` — PhreeForm, unconditionally, with no Forms branch and no reference to
`MODULE_FORMS_PRINT_BUTTONS`:

| Call site | What it prints |
|---|---|
| `extensions/…/zencart/functions/zencart.php` | the VAT invoice emailed to a customer, from the sync cron |
| `trunk/modules/payment/ajax/paypal_email_link.php` | an invoice emailed with a PayPal link |
| `trunk/modules/phreepos/ajax/save_main.php` | the POS receipt, on save |
| `trunk/modules/phreepos/ajax/print_previous.php` | a POS reprint |

**This is a live trap, not merely untidiness.** `get_report_details()` refuses a native definition
by design (§13.9) — the alternative was a fatal from a perfectly valid file. So the moment one of
these configured forms is opened and saved in the Forms editor, its call site stops producing a
document: the ZenCart cron logs "configured form (id N) could not be loaded" to STDERR, skips the
email and returns false, and the order still posts. It fails safe and it fails **silently**, which
on the two emailing paths means a customer simply never receives their invoice.

A company can already be in the state that makes this easy to walk into: `MODULE_FORMS_PRINT_BUTTONS`
on, so the invoice prints through Forms from the screen and through PhreeForm from the cron. An
author would edit the form, watch it print correctly, and never learn the emails had stopped.

What step 6 needed was the render-to-string counterpart of the URL helper — `FormRunner` →
`LayoutPdfRenderer` → bytes, which the print page already did behind an HTTP delivery — and these
four switched onto it. That is `forms_render_to_string()`, and it is done. The caution that stood
here — *do not open a form named in one of these settings in the Forms canvas* — no longer applies.

#### Step 6 must also give Forms a way to CREATE and DELETE a document

Recorded 2026-08-16. Forms has three actions — `email`, `save`, `view`. Both editors open an
existing `rID` and nothing else. There is **no New, no Copy, no Delete, and no Rename for a form**
(the report editor has an editable title field, so a report can be renamed incidentally; the canvas
posts the form's title as a hidden field, so a form cannot).

All of it lives in PhreeForm today: `popup_build` creates and edits, `doc_operation.php` deletes,
and its main page carries Copy, Rename and Export. So the current answer to "how do I make a new
form?" is "copy one in PhreeForm, then edit the copy in Forms".

**That makes it a blocker for step 6 rather than a nice-to-have**: deleting PhreeForm removes the
only way to create or delete a document, and a report engine that cannot add a report is not a
replacement. What is needed:

- **Copy** — the valuable one, and nearly free: duplicate a definition under a new title and open it
  in the right editor. Starting from a blank page is the rare case; starting from the invoice that
  is nearly right is the normal one.
- **New** — a catalogue row plus a definition. For a report, a chosen dataset; for a form, an empty
  template.
- **Delete** — the catalogue row, the definition, and its `.pre-forms` backup, at level 4. It needs
  a guard against deleting a form that a SETTING names — the ZenCart VAT invoice form, the POS
  receipt form — or it breaks exactly the call sites above.
- **Rename a form** — the canvas's hidden title becomes a field.

§13.14 settles where these belong: New and Copy on the Reports page for reports, on Form Layout for
forms, each beside the list of the thing it creates.

### 13.8 Open risks

Not yet validated.

1. ~~**mm-to-pixel alignment between pdf.js and `LayoutPdfRenderer`.**~~ **SETTLED — §13.11.** The
   conversion is exact and is now pinned by `verify_forms_canvas.php` against the PDF's own content
   stream. One millimetre is 72/25.4 points, y is measured down from the page top, and placement is
   absolute rather than margin-relative — measured, not assumed.
2. **The three long-tail element types.** `TDup`, `ImgLink` and `BarCode` appear once each in the
   shipped set. `TDup` was *eliminated* rather than mapped when the vocabulary was collapsed to
   nine (§4.2), so its single instance is the one definition most likely to block the migration
   under condition 1.
3. ~~**Editing a definition served by a provider.**~~ **SETTLED — §13.12.** A provider declares
   nothing, so it is asked the only way it can be: the form is run once and the keys are read back
   off the `Record`. `entry_builder` supplies 76 names, `pos_builder` 70, `statement_builder` 26,
   `wo_builder` 25. Measured, not declared, and it is the same contract the renderer resolves
   against.
4. ~~**pdf.js weight.**~~ **MEASURED: 1.8 MB**, not the 1 MB estimated — `pdf.min.mjs` 504 KB and
   `pdf.worker.min.mjs` 1.3 MB, both from the `legacy` build of pdfjs-dist 6.2.108. Still the
   largest single front-end asset in the repository by some margin. Accepted.

### 13.9 Step 1, measured

Built, and measured against both companies on 2026-08-15.

| | |
|---|---|
| The format | JSON, in the same `my_files/{co}/phreeform/pf_<id>` slot the XML occupies |
| Detection | by CONTENT — a native definition is a JSON object, a PhreeForm one is XML |
| Converted | **56 of 57** on each company; `pf_64` is declined, and the legacy runner declines it too |
| Re-encode | byte-stable for all 56 — opening and closing an unedited definition leaves no diff |
| Rendered | **56 of 56 byte-identical** on `demo`, **51 of 51** on `production` |

The classes are `modules/forms/classes/definition/`: `Definition` (the value object), `DefinitionCodec`
(the format), `DefinitionBuilder` (PhreeForm XML in, native out) and `DefinitionLoader` (the
format-aware read). `DocumentRunner` and `FormRunner` each gained `planDefinition()` and
`runDefinition()` **alongside** the legacy pair rather than in place of it — that is what lets one
definition be rendered both ways in one process and held to a byte comparison, and step 6 deletes
the older half.

**What a Definition is:** already converted. Its fields, filters, sorts and bindings are dataset
field KEYS, its elements are the nine document types, and its route is stated rather than inferred
from a table list. Two things deliberately stay out, because they are not properties of the
definition: the resolved date RANGE (`dateDefault` is a preference, and what "this quarter" means
depends on today), and the Context (a form's company block is read back off its own template at run
time, so a stored `COMPANY_NAME` cannot go stale).

`DateRangeFilters` in `catalog/date_range.php` is new and shared: the legacy converter and the
native runner must turn the same `DateRange` into the same filters, and two implementations of that
would be two different reports. It takes the date field as a callable, because the by-period branch
never reads it and the legacy caller warns when it cannot map the stored name — resolving eagerly
would emit that warning on reports where the field is irrelevant.

#### Three things the byte comparison caught that reading the code did not

1. **An all-default `Style` is not the same as no `Style`.** The encoder omitted empty objects, as
   an encoder should. But `Table` resolves its heading row as `$element->headerStyle ?? $element->style`,
   so a headerStyle specifying *nothing* still wins over the element's own style where a null one
   falls back to it. Collapsing the two changed the heading font of every converted table — 68
   bytes per form, on 14 forms, and invisible in the object graph. The format now writes `{}`.
2. **TCPDF is not a function of its document.** Every render stamps six varying fields: the Info
   dictionary's `/CreationDate` and `/ModDate`, a `/ID` derived from them, a random uuid in the XMP
   packet as DocumentID *and* InstanceID, a `/LastModified` on **every page**, and a `/M` on every
   link annotation. The per-page one is the instructive one: it only differs when two renders
   straddle a second boundary, so missing it failed the four LARGEST documents and passed the rest
   — which reads exactly like a defect in whatever those four have in common. They had nothing in
   common except taking over a second to render. The tool now renders one document twice **a second
   apart** as a control, and separately proves that a document which really differs still compares
   different: a normaliser that strips too much passes everything, including the broken.
3. **`serialize()` is the wrong comparison.** It encodes object identity as well as value, so two
   structurally identical documents compare unequal purely because one shared a `Style` instance
   between elements where the other built two. It reported differences on 14 definitions that had
   none. The tool walks the graph instead.

#### Two things now visible that were not

- **Five definitions carry conversion warnings**, so §13.3 condition 1 would block the migration on
  them today: `pf_51` needs a second `Table` on the same source (the single `TDup` — open risk 2,
  now confirmed rather than suspected) and names two fields no dataset covers; `pf_57`, `pf_77` and
  `pf_80` each want `credit_amount / qty`. These are not new — the same converters produce the same
  warnings at every run today — but nothing was reading them. The §8.2 pattern applies: name the
  arithmetic in a shipped dataset, as `pf_73` was fixed.
- **The application's report path drops the page setup and the band styling.** `DocumentRunner::runDataset()`
  passes neither to `ReportDocumentProvider`, though `render_forms_report.php` does and every
  definition carries them — so a report renders on the `PageSetup` defaults rather than on its
  declared paper. A `Definition` carries all three, and `runDefinition()` deliberately drops them
  too, because step 1's whole claim is that the two paths are byte-identical. Passing them is an
  improvement, it moves every report onto its declared paper size, and it wants its own change and
  its own verification rather than being smuggled in as a refactor.

### 13.10 Step 2, measured

The report editor, built and measured against both companies on 2026-08-15.

`?module=forms&page=design&rID=<id>`, reached from an **Edit** button on the document list, gated on
**`SECURITY_ID_FORMS_AUTHOR`** — defined since 1.00 and unused until now. It is the middle tier of
decision F, and the reason it exists: running a document, authoring one and defining which tables
may join are three different privileges, and PhreeForm granted all three with one key.

| | |
|---|---|
| Edits | title, description, columns (order, heading, width, alignment, format, total, column break), grouping, totals-only, sorting, filters, paper, orientation, margins, date preference and field, the six style bands and both title lines |
| Empty | a report returning no rows is filled with SAMPLE rows in the real layout, marked as such in the filter band (§13.2) — a fresh install has no data and must still be able to lay a report out |
| Palette | the resolved dataset's fields, and nothing else (§13.4) |
| Preview | the real PDF, through `DocumentRunner::runDefinition()` → `TablePdfRenderer`, of the definition **as edited** rather than as stored, over a date range chosen for the preview alone |
| Saves | the native format, keeping the PhreeForm original as `pf_<id>.pre-forms` |

**`DefinitionForm` is the whole of it**, and the reason it is a class rather than page code: the
preview endpoint and the save must build the definition the same way. A preview assembled by
different code is a preview of a different report, and it would agree often enough to be trusted.
Being a class also means it can be held to a round trip, which is what `verify_forms_designer.php`
does — **all 38 reports survive an untouched save unchanged, and print identical bytes afterwards.**
That property is the one an editor most easily loses: anything it cannot represent disappears on the
first save, silently.

The preview posts the editor's own form through `formaction`/`formtarget` into an iframe. Not a
second form, and not an XHR: a second form cannot be guaranteed to carry the same fields, and a PDF
cannot be swapped into the DOM.

**Every field name is checked against the dataset on the way in**, because the selects on the page
are a convenience and the POST is whatever the browser chooses to send. The harness attacks it:
an undeclared column, SQL in a column name, a filter and a sort on tables the dataset does not
reach, and a hand-edited field expression are each dropped and reported — with a control proving an
allowed change still applies, since an editor that ignores everything would pass the other five.

#### Three defects found while building it

1. **The datasets page could never save.** Its form was written as a hand-rolled `<form method="post">`
   rather than through `html_form()`, so it carried no `csrf_token` — and `index.php` answers a POST
   without one with a bare 403. It had never been caught because `verify_forms_pages.php` rendered
   the datasets LIST, and the form lives in the editor branch. Fixed, and the suite now renders that
   branch (`datasetsnew`) and asserts a token on **every posting form on every page**, which is the
   shape of the defect rather than the instance.
2. **The preview endpoint would have been fatal on its last line.** A module's `defaults.php` is not
   loaded for you — a page requires its own, and an ajax op has no `DIR_FS_WORKING` to require it
   from — so `FORMS_DEFAULT_COLUMN_WIDTH` was undefined *after* the report had already been queried
   and built.
3. **Group and report totals have always printed in English.** `main/pre_process.php` guarded on
   `TEXT_GROUP_TOTAL` and `TEXT_REPORT_TOTAL`; the constants are `TEXT_GROUP_TOTAL_FOR` and
   `TEXT_REPORT_TOTAL_FOR`, which every locale defines. The guard could only ever take its English
   fallback. Found by a scan the new tool runs for a different reason — an undefined constant is
   fatal on first open — which now reports guarded constants that exist nowhere as a separate,
   softer finding, since a guard that can never succeed is a translation that can never be reached.

#### Four more defects the first browser session found

The editor rendered, parsed and passed every check before anyone opened it. Then it was opened.

1. **`SECURITY_ID_FORMS_AUTHOR` could not be granted by anyone.** phreedom's roles page builds
   itself by walking `$mainmenu`, so a security id with no menu entry appears nowhere on it — and
   the editor is reached from an Edit button, not a menu. The permission was ungrantable, so the
   page was unreachable for every user including the one who installed the module, and it failed as
   a redirect to the dashboard, which reads like a missing page. It now has a menu entry (which is
   also a usable report chooser), and the suite asserts every Forms permission is grantable.
2. **Double-encoded links.** `html_href_link()` already separates parameters with `&amp;`;
   wrapping it in `htmlspecialchars()` encodes that again, so the browser requested a parameter
   named `amp;page`, `page` was absent, and `index.php` defaulted to the document list. The **Print**
   link had shipped with this too. The old check counted links matching `page=print` — and a
   double-encoded href still *contains* that text, so counting could never see it. The suite now
   parses every href and `data-url`.
3. **A form control named `page`.** `index.php` picks the file to run from `$_POST['page']` before
   it reads the query string, and the page-setup group was `page[size]`, `page[margin][top]` — so
   `$_POST['page']` was an ARRAY and the router looked for `pages/Array/pre_process.php`. It broke
   the preview and the save together. Renamed to `paper[…]`; the suite now rejects any control named
   `module` or `page` (but not `action`, which is the framework's own submit idiom).
4. **A missing control silently flattened every report.** The columns of the Sales Report each carry
   `columnbreak='1'` — "end this column, start the next across the page" — and the template rendered
   no control for it. An unchecked box posts NOTHING, so an untouched save cleared the flag on every
   column and all five collapsed into one stacked band.

Defect 4 is the one worth keeping, because of what it says about the harness.
`verify_forms_designer.php` round-trips `DefinitionForm::toRequest()` through `apply()` and asserts
the definition comes back — and it passed, because `toRequest()` had the value and the **form** did
not. That is the class checked against itself. The page suite now builds the POST **from the
rendered controls**, the way a browser does — an unchecked box contributes nothing, a select
contributes its selected option — and requires that to rebuild the definition that was loaded.

The general lesson, and it cost four rounds to learn: **anything asserted by counting or by
substring is a check that will pass a broken page.** Every one of these rendered correct-looking
markup and failed somewhere downstream of it — in a browser decoding an entity, in a router reading
POST before GET, in a form omitting a field. Each fix moved a check from "does the output contain
this" to "does the output still mean this when something else reads it".

#### The report path was printing less than the definition said

Two of the above were mine. The comparison against PhreeForm exposed two that were not, both in the
shipping report path and both recorded in §13.9 as known-but-deferred:

- **`DocumentRunner` passed columns, filters and sorts and nothing else.** No `Context`, so the
  company-name band was blank; no `ReportStyle`, so the titles and the filter line never drew; no
  `PageSetup`, so every report printed on the defaults rather than its own paper and margins. Both
  run paths now pass everything the definition carries, which is what keeps them byte-identical to
  each other. The filter line is generated from the definition — grouping, sorting and the resolved
  range — and reads the column HEADINGS, because "Grouped by: post_date" is the right sentence
  written in the storage layer's language.
- **A phantom band in `TablePdfRenderer`.** When the LAST column breaks — the ordinary side-by-side
  grid, so most of the library — `bandEdges()` added a closing edge for a band nothing occupied, one
  default width wide. The table then measured wider than it was and the scaling step shrank every
  band to fit a width nothing used. On the Sales Report: 7 edges and a 92% scale, against 6 edges
  and none.

#### What it deliberately does not do

- **Field expressions are carried, not edited.** A filter comparing a column to other columns — the
  re-order worksheet's `quantity_on_hand < (minimum + on order)` — is shown as read-only and passed
  through as its token list, re-validated by `FieldExpression` on the way back in. An expression
  editor is not step 2, and dropping the filter instead would change which rows the report returns.
- **The dataset cannot be changed.** Repointing a report at another dataset is a different operation
  from editing one, and every column, filter and sort would have to be re-resolved.
- **No new reports.** This edits what the catalog holds. Creating one needs a group, a document type
  and a catalog row, which is its own piece of work.

### 13.11 Step 3, measured

The form canvas, read-only. Built and measured on 2026-08-16.

`?module=forms&page=canvas&rID=<id>`, reached from a **Layout** button beside each form on the
document list or from **Tools → Form Layout**, behind `SECURITY_ID_FORMS_AUTHOR`. The real rendered
PDF, rasterized by pdf.js, with every element outlined on a transparent layer above it — colour per
type, an element list beside it, hover in either direction, and the outlines switchable off so the
page can be seen on its own. Zoom and page controls, and no save: this step proves the alignment
before anything can be moved.

#### Open risk 1 is settled, by measurement

The overlay converts with one line — `pixels = mm × scale × 72/25.4` — and it carries no page size,
no margins and no orientation. That is deliberate: those are three things to get wrong, and not
having them means there is nothing to get wrong. What makes it legitimate is that a `Box` is
millimetres from the page's **top-left**, which is what `LayoutPdfRenderer` hands TCPDF's `SetXY`
and `Rect` — absolute page coordinates. pdf.js rasterizes at `scale` pixels per point from the same
corner with y pointing the same way, so the two agree by construction.

`verify_forms_canvas.php` does not take that on trust. It renders documents and reads the numbers
back out of the **PDF content stream** — inflating the compressed page streams, because reading the
bytes as text finds nothing and would make every assertion vacuously true. A Rect at (20, 30, 50 × 10) mm
on A4 emits

```
56.692913 756.850630 141.732283 -28.346457 re
```

on a MediaBox of 595.276 × 841.890 pt: 20 mm across, 841.890 − 30 mm up from the bottom, 50 × 10 mm
in size. Checked on A4 portrait and landscape, Letter, Legal and A5 landscape — a wrong axis passes
on a square page and on nothing else — plus a control proving a 25 mm margin does **not** move an
element, which is the failure that would look like a form drawn slightly low rather than like a
defect.

#### And it immediately found one: every `Letter` document was printing on A4

`TCPDF::getPageSizeFromFormat()` looks the paper name up in a table keyed in **UPPERCASE** and,
finding nothing, returns **A4 without complaint**. Definitions store `Letter` — that is what
PhreeForm's `Letter:216:282` reduces to, and it is what every shipped definition says — so every
Letter form and report rendered by Forms came out 210 × 297 mm instead of 215.9 × 279.4: 6 mm
narrower and 18 mm longer. `A4` and `A5` were right by accident, being uppercase already, which is
why a European install would never have noticed.

Fixed in `PdfCanvas::paperFormat()`, in front of all three canvas constructions. It is worth being
clear about how this was missed for so long: **every byte-comparison in the suite compares Forms
against Forms**, and both sides were equally wrong. Only a check against an external truth — the
declared page size in millimetres — could see it, and this is the first such check.

An unknown paper name still falls back to A4 inside TCPDF. That is its choice and this does not
second-guess it, but a definition naming a size nobody has heard of prints on A4 in silence.

#### The rest of what step 3 pins

- **763 elements across 18 forms** all become boxes, none unknown, none with a negative position or
  size: text 322, field 272, block 41, line 64, rect 19, image 15, table 18, pagenum 11, barcode 1.
- **A `Line`'s box is not a rectangle.** `convertLine()` stores the END POINT as a signed offset, so
  a line drawn upward has a negative height — and a browser renders a negative-size box as nothing,
  silently, for exactly the elements that happen to point that way. 64 of the shipped elements are
  lines. `CanvasLayout` normalises them and the tool checks both directions.
- **A text element's box is one LINE, not the whole element.** `drawText()` goes through TCPDF's
  `MultiCell`, whose height argument is the height of each line rather than of the cell, so the text
  grows downward and the box does not describe where it ends — an address block stores 4.5 mm and
  prints six lines. Raised by the first person to look at the canvas, which is the point of building
  it read-only first. The stored box is still what an author positions, so that is what is drawn,
  but with a **dashed lower edge** and a note; how far the text really reaches depends on the record
  and changes with it, which is why it cannot simply be drawn larger.
- **An image stores a WIDTH and no height** — every logo in the shipped set — because TCPDF works
  the other side out from the file's aspect ratio. Passing that 0 to the browser drew the logo as a
  two-pixel sliver: not a wrong box so much as an absent one, on the element an author most wants to
  see the edges of. Raised by the same first look as the block question. Unlike text this really is
  knowable — it depends on the FILE, not the record — so `CanvasLayout` measures it, and the tool
  checks the answer against the `cm` transform in the content stream rather than against the
  arithmetic that produced it. What genuinely cannot be measured (a missing file, or an `ImgLink`
  whose name is data) is dashed all round and said to be indicative.
- **A form with no printable record** falls back to invented values (§13.2) so there is text to
  align against — a form rendered against an empty record draws its labels and its rules and
  nothing else, which is precisely the case where an author cannot judge a box. Company constants
  are still resolved for real: "Sample COMPANY_NAME" would misjudge the width of the one block
  whose width is fixed.
- **pdf.js is 1.8 MB**, vendored at `trunk/ext/pdfjs/` with its Apache-2.0 licence and a README
  recording version and provenance. It keeps its own licence banner and takes no FreeBooks header.

#### What is left for step 4

Dragging, resizing, snapping, z-order and an element inspector. The alignment they all depend on is
now measured rather than hoped for, which was the point of doing this first.

### 13.12 Step 4, measured

Editing on the canvas. Built and measured on 2026-08-16; module version 1.04.

Drag to move, drag to resize (the bottom-right corner at first; every side and corner from §13.17),
snap to 0.5/1/5 mm or off, click to select, an inspector
for exact position, size, text, binding, font, size, colour, alignment, border and visibility,
add (text, value, line, box), delete, and forward/backward for z-order. Saving writes the native
format and keeps `pf_<id>.pre-forms`, exactly as the report editor does.

**`TemplateForm` is the whole of it**, and it keeps DefinitionForm's two rules for the same two
reasons. The base matters: a `FieldBlock`'s parts, a `Table`'s columns and source, a bound image
filename, a `Line`'s type and a `Barcode`'s symbology are carried across untouched, because the
canvas draws no control for them and a form that could only submit what it can draw would delete
them on the first save. And an author names only what exists.

**All 18 forms survive an untouched save and print identically afterwards** — the property an editor
loses first, since anything it cannot represent disappears silently.

#### Open risk 3 is settled, by asking the provider

Nine forms are built by a `DocumentProvider` and a provider **declares nothing**, so there was no
palette to offer. It turns out the contract is observable: run the form once and read the keys off
the `Record` it produced. Measured — `entry_builder` 76 names, `pos_builder` 70,
`statement_builder` 26, `wo_builder` 25 — and it is the same contract the renderer will resolve
against, which is what makes it the right list rather than merely a list. A query-driven form uses
its dataset's fields as before, and in both cases the template's own bindings are always allowed, or
a form would lose a field the moment its record table went empty.

#### Geometry is submitted as STORED, not as drawn

The two findings from step 3 are what make this possible at all. A `Line`'s sides are a **signed
offset** to its end point; an `Image` usually stores a **width and no height**. So the canvas
carries both boxes — the drawn one it positions with, and the stored one it edits by the same delta
and submits. An untouched save then returns exactly what was loaded, **nulls included**, rather than
a normalised approximation of it.

That is not theoretical. Reading a blank height as `0` would pin every logo to nothing on the first
save of a form nobody meant to change, and normalising a line's box would rewrite 64 elements across
the shipped set. Both are asserted.

A text element's height box is labelled **Line height** in the inspector, because that is what
`MultiCell` takes and calling it "height" would be the same misstatement the dashed edge exists to
correct.

#### A check that could not fail

Step 3 asserted the canvas was read-only with `!str_contains($output, 'tb_main_save')`. The toolbar
numbers its icons — `tb_main_0` — so that string was never in the output and the assertion passed
without testing anything. It is replaced by checks against things that exist: the form, the hidden
field holder, the serializer the Save button calls, the snap control, ten inspector controls, and
that every name the palette offers is one the form can supply.

That is the fourth time in this work a check phrased as counting or substring-matching has proved
vacuous. The pattern is now explicit: **assert against something that must be present, never against
something that must be absent.**

The add buttons moved to a data attribute and one delegated listener for the same reason the
document list did — an inline handler carrying a quoted string renders as `&quot;text&quot;`, which
is correct and unsearchable, so the check had to read the DOM rather than the markup.

#### What step 4 deliberately does not do

- **Undo and redo**, by whole-state snapshot rather than a reversible command per action. The state
  is two small arrays — 72 elements at the largest — so a snapshot is cheap and cannot drift, where
  a command that knows how to reverse itself is one more thing to get wrong every time an edit is
  added, and getting it wrong leaves the form in a state the author never made. One snapshot per
  DRAG, taken as it begins, not one per pointermove. Bounded at 60 and cleared on leaving: this
  undoes edits, not saves. Deferred at first with "leaving without saving is the way back", which
  was too glib once a long edit was possible — one wrong move should not cost the session.
- **No editing of sub-structure.** A block's individual parts and a table's columns are carried, not
  edited; adding is limited to the four kinds that need none.
- **The underlay redraws on every completed change**, with a per-browser switch and the button kept
  for a slow machine. Three things make that safe: it fires on drag END rather than on pointermove
  (a page under the pointer is not worth a PDF per pixel); a burst of typing is coalesced into one
  render; and a superseded request is ABANDONED, because a slow answer landing after a fast one
  paints an older page over a newer and looks like a rendering bug rather than a race.
- **No group selection, and only the bottom-right corner resizes.** Both were asked for on
  2026-08-16 and both are **now built — see §13.17.**
- **Redraw the page** remains, on demand.
  Live re-rendering on every drag would mean a PDF per pointer move; without any redraw at all the
  picture goes stale on the FIRST change and every change after that is judged against a page
  further out of date, which the first person to use it said within minutes. The endpoint applies
  the posted canvas through `TemplateForm` — the class the save uses — so the picture and the save
  agree by construction, and anything the redraw refuses is reported in a header and shown, rather
  than discovered when the author saves.

### 13.13 Adding an image

Built and measured on 2026-08-16. The canvas could point an image element at a file and never put
one there: the only way into `my_files/{co}/phreeform/images/` was the file system, which the person
laying out an invoice does not have. `ImageStore` and one ajax op close that.

It was held back from step 4 on purpose. A file arriving from a browser is the only input on the
page that the server keeps and later hands back, so it is the one place where being wrong is not a
cosmetic problem.

**The extension is the check that matters.** The type is read out of the bytes with
`getimagesize()`, and the extension the file is STORED under comes from that type — not from what
was uploaded. A name is the only part of an upload a web server acts on, so the name is not the
uploader's to choose: `logo.php` carrying a valid GIF header comes to rest as `logo.gif`. The rest
of the name is reduced to letters, digits, dash, underscore and dot and capped at 60, which is what
disposes of `..`, of directory separators, of a NUL and of a name long enough to break the file
system. `.htaccess` survives as nothing at all, because `pathinfo()` reads a leading dot as all
extension and no name — the right answer for it.

**Nothing is overwritten.** The folder is shared by every form in the company, so a name already
taken gets a number rather than new contents; replacing it would change a document whose author
never opened it.

**One class decides what is written AND what is listed.** The page used to glob for six extensions
of its own; a lister and a writer that disagree leave the folder holding files nobody can choose.
`names()` reads each candidate with `getimagesize()` too, so a `README.txt` in there is not offered.

**The gate is level 3, the level that saves** — not level 1, which is all that
`validate_ajax_user()` proves. Note what that function returns with no argument: `1`, for anybody
signed in. An endpoint that asked IT for the level would refuse everybody, which fails safe only by
accident, so the level is read from the session directly.

**The uploaded-file test is substitutable, and separately pinned.** There is no upload in a command
line, so `is_uploaded_file`/`move_uploaded_file` sit behind one closure the harness can replace —
whole, never half, because half a gate reads as a check. `verify_forms_image_upload.php` supplies
its own for the naming and collision tests and then holds the DEFAULT to refusing a file that was
never uploaded, so substituting it cannot quietly remove it. The permission gate is tested by
RUNNING the endpoint in a child process at level 1 and at level 3 — an ajax op ends in `die()`, so
it cannot be exercised in the same process as anything after it — rather than by searching the
source for the constant, which would pass on a file that named it in a branch that never ran.

**An upload changes the drawn height**, because an image stores a width and no height (§13.11). So
the endpoint answers with the stored name and the pixel size, and the canvas re-measures the outline
with the same rule `CanvasLayout::imageSize()` uses. A second rule would put the outline where the
ink is not.

**The file input carries no `name`.** It sits inside the urlencoded form that saves the whole
canvas, and a named file input posts its filename as a stray field on every save.


#### An image becomes addable

Uploading answered "how do I change this logo", not "how do I give this form one". `ADDABLE` was
`text`, `field`, `line`, `rect` — the kinds whose whole content the canvas can supply — and an image
was excluded because its one piece of sub-structure, the filename, had no control that could set it.
The upload IS that control, so `image` joins the list and a form that shipped without a logo can be
given one.

Two details decide whether it works rather than merely appears:

- **A new image is created with a NULL height**, not the 5 mm the other kinds default to. The shape
  has to come from the file (§13.11), and a height fixed at the moment of adding would squash the
  first picture put into it.
- **It is kept with an empty `src`.** `drawImage()` already returns early on an empty or missing
  source — deliberately, so a missing picture cannot abort a document — so an element added now and
  filled in a moment later draws nothing in between rather than breaking the page it is being placed
  on. That early return is what made this a small change instead of a staged one.

The filename gate is the same `imageFile()` an existing image goes through: a name that is not in
the folder is refused and the element keeps none, so a new image cannot be pointed at a path by a
request that simply names one. A bound image — filename from the record — stays unaddable; it needs
a path prefix and a record value, neither of which the canvas asks for.

The add-button check in `verify_forms_pages.php` walks `ADDABLE`, so it picked the new kind up on its
own — which is what that loop was for.

#### And a name to call it by

Adding an image made a gap in step 4 visible: `Element::$label` was read and written by `apply()`
and `create()` from the start, and `toRequest()` emitted it, but the inspector had **no control for
it** — so nothing could ever set one. A new element showed as "New element" while it was being
placed, and as **"image"** the moment the form was reopened, because that is what
`CanvasLayout::describe()` falls back to.

For most kinds the fallback reads well: text describes itself by its words, a field by its binding,
a table by its source. For a picture it does not describe anything — a form with a logo and a
signature lists two elements both called "image", and the only way to tell them apart is to click
each one and watch which outline lights up.

So the inspector gained a **Name** field. It is UI only; the model, the codec and both `TemplateForm`
paths already carried it. Two things it has to get right:

- **Blank is NO name, not an empty one.** `trim() ?: null` was already correct in `TemplateForm`;
  what is asserted now is that it stays that way, because an empty string would leave the row in the
  Elements list with nothing in it while `describe()` was standing by with a perfectly good answer.
- **Clearing it restores the server's description, not a second guess at one.** The canvas keeps
  `CanvasLayout::describe()`'s output per box as `described` when the boxes arrive, and falls back to
  that. Reimplementing `describe()` in JavaScript would be a second rule for the same question, and
  the two would drift the first time either changed.


### 13.14 Reports and forms are two lists, not one

Built 2026-08-16. `?module=forms&page=main` listed all 57 documents ordered by `doc_group` — an
internal code — and printed that code as the Group column. So a page that WAS ordered looked
unordered, and "Employees" sorted between "General Ledger" and "Inventory" because its code is `hr`.

The first instinct was a tree, as PhreeForm had. What the data showed instead is that the depth was
an artefact of mixing two things:

|  | Actions | Count | Depth once separated |
|---|---|---|---|
| Report | PDF · CSV · Email · Edit | 39 in 9 categories | one level |
| Form | Print… · Layout | 18 in 12 named folders | one level |

**They share no action at all**, and the template already forked completely on `$plan->isForm()`.
Worse, neither of a form's two buttons was worth having here: **Print… could never work**, because
the print page needs a record and a list has none — it always landed on "a form is opened from the
document it belongs to" — and **Layout duplicated the canvas's own chooser**, which is already a
forms list, already in the menu as Form Layout. So the forms half of this page had no action that
both worked and was not already somewhere better.

The result is subtraction: Documents becomes **Reports (Forms)**, and the canvas's chooser becomes
the **forms catalogue**, author-gated (a run-only user loses nothing, because every action on a form
is either authoring or belongs on the document that owns it). The catalogue is now rendered twice for
its two real purposes rather than four times.

#### The names were in the data all along

The folder titles are rows in the same table — `doc_type = '0'`, which the list explicitly excluded.
`doc_ext = '0'` is a category (`cust` → "Customers"), `'fr'` its reports folder, `'ff'` a named forms
folder (`cust:inv` → "Invoices/Packing Slips"). A report is filed under its CATEGORY, because every
report in one shares a folder called "Reports" and a page of headings reading "Reports" says nothing;
a form is filed under its own folder, whose name already identifies it. That asymmetry is in the
data, not invented. `forms_folder_titles()` and `forms_folder_for()` are the whole mechanism — no
tree, no jsTree, no selection state.

Headings are driven by the DOCUMENTS, so a folder holding nothing gets none; five of the twelve are
empty in a stock install.

**It found a gap in the shipped catalogue.** Group `asset` holds the Asset List report and has no
folder row at all, on either company — so the fallback shows the raw code, because inventing "Assets"
would be a guess. `verify_forms_pages.php` reports it as a `[--]` note rather than failing: the fix
is a row of data, and it belongs with the shipping set in step 5.

#### Cancel went to the wrong place, in both editors

Both hardcoded `page=main`, so Cancel always landed on Documents — right only if that is where the
author came from, and wrong from Tools → Form Layout, which threw them out of the section they were
working in. Cancel now returns to the list of things that editor edits.

#### Two checks that failed on correct code first

Both worth recording, because both are the same mistake in different clothes — a check that looked
right and tested something else:

- the Cancel check matched `location\.href[^"\']*page=canvas`, and the handler is written
  `onclick="location.href = '…'"`. The character class stops dead at the opening quote, so a correct
  page reported as broken. Anchored on the icon instead.
- the coverage check compared TITLES against the whole page text, and reported four forms as leaking
  onto the reports page — because a report named "Purchase Orders by Vendor" contains the form title
  "Purchase Order". It now reads the title CELL of each row, so values compare whole.

Coverage is the property that matters when a list is split in two: 39 + 18 = 57, each on exactly one
page, checked against the catalogue itself on both companies.

### 13.15 Step 5, measured

The native shipping set. Built 2026-08-16; module version 1.07.

**56 definitions ship in both formats** — `.json` beside `.xml` under
`modules/{module}/language/{locale}/reports/` — generated by
`docs/tools/generate_forms_shipping_set.php` and committed. Not 52: that count was `en_us` only, and
there are four `nl_nl` copies. Optional modules from `extensions/` still ship xml alone and the
seeder falls back to it, which is why the rule the verifier enforces is consistency **within a
module** rather than across the tree.

#### Three defects the generation exposed

Converting our own shipped set found what converting customers' definitions never could, because
**every byte comparison in the suite is Forms-native against Forms-legacy, both through the same
converter** — a gap in it agrees with itself. All three are in `b46f321e`:

- **A blank where a price belongs.** Sales Order, Customer Quotes and Vendor Credit Memo ask for
  `journal_item.credit_amount / journal_item.qty`, which mapped to nothing. `item_unit_price` divides
  the DEBIT side and would print `0.00` on a credit-side line (qty 8, debit 0, credit 20.40), so
  `item_unit_price_credit` joins it exactly as `item_net_debit` sits beside `item_net`.
- **A cheque missing its stub.** `TDup` carries a position and NOTHING else — no columns, no size —
  so it means "draw that table again here". §4.3 dropped the concept; the shape was always
  expressible, and `LayoutPdfRenderer` already draws every `Table` rather than the first. **§13.8
  risk 2 is settled, not by accepting the loss but by finding the assumption wrong.**
- **Two undeclared but real columns**: `journal_item.serialize_number` exists and was never declared,
  and credit + debit is what a line is worth whichever side it sits on.

**All 56 shipped definitions now convert with no warnings, and both companies convert 57 of 57
clean** — the condition decision J puts on the migration ever running.

#### Seeding is a copy, a stamp and a row

`forms_seed_native()` (`modules/forms/functions/seed.php`) loads **no Forms class**, and needs none:
it decodes the JSON as a plain array, stamps the paper, inserts the catalogue row and writes the
file. That matters because it runs during INSTALL, before the module is registered, and because
decoding through `DefinitionCodec` would round-trip bytes that are already exactly what the loader
wants. It is the same reasoning `ImportReport()` uses when it edits the size in the XML.

The catalogue row needs nothing the definition lacks: `doc_title`, `doc_group` and `doc_ext` are
`title`, `group` and `type` — `type` is literally `rpt`/`frm`. The other two columns carry no
information, measured across every row on two companies: `doc_type` is always `'s'` and `security`
always `'u:0;g:0'`.

`admin_add_reports()` prefers the native file **per file**, and only where `MODULE_FORMS_STATUS` is
defined — a company without Forms cannot read one, and seeding it a catalog nothing can open would
be worse than seeding legacy.

#### The two spellings of a paper

The legacy format stores `A4:210:297`; the native one stores `A4`. TCPDF looks a paper up **by
name**, so writing the legacy token into a native file gives every document a size no renderer
resolves — and it silently falls back to A4, which is the one failure that looks like success on the
installs that asked for A4. Both `forms_seed_native()` and `forms_restamp_native()` take
`explode(':')[0]`, and the verifier asserts the stored value has no colon in it.

#### A fresh install turns the Print buttons ON

`MODULE_FORMS_PRINT_BUTTONS` ships **off** so that installing Forms on an existing company changes
nothing. A fresh install is the opposite case: it has only native definitions, which PhreeForm
refuses by design, so leaving the buttons pointed at PhreeForm would give a new company a Print
button that errors on every document it owns. The installer writes `1` after seeding — no new
mechanism, the same per-company switch set differently at the one moment the difference is knowable.

#### The re-stamp could not reach a native file

Report Settings' re-stamp substitutes the size textually on XML, so a native definition matched
nothing and was counted as **skipped**. Every document is native after the migration, which would
have left the feature applying to none of them. `forms_restamp_native()` returns `null` for an xml
file so the caller keeps its own rule, and the two formats coexist until step 6.

#### What the verifier is for

`verify_forms_shipping_set.php` re-runs the conversion and compares against the committed files: a
generated artefact that is committed goes stale the moment its generator changes, and that is the
only claim worth making about it. `verify_paper_seeding.php` gained the check that matters most and
was missing — **which format was written**. Every paper assertion in it would pass unchanged if the
seeder quietly fell back to xml, shipping the very thing step 5 exists to replace.

### 13.16 A serial form is not a page, and Forms cannot render one

Found 2026-08-16 by opening the POS Receipt Slip on the canvas: every one of its 19 elements sat at
0,0. The conversion is faithful — the definition carries **no coordinates at all**.

A `serialform` is character-cell text for a receipt printer. PhreeForm routes it away from the PDF
builder entirely:

```php
if ($report->serialform) $output = BuildSeq($report, $delivery_method);
else                     $output = BuildPDF($report, $delivery_method);
```

`BuildSeq()` composes lines with `formatReceipt($value, $width, $align)`, where **`width` counts
columns, not millimetres**, and `rowbreak` ends a line. There are no abscissa/ordinate tags because
there is no page. **Forms has no reference to `serialform` anywhere** — so it reads a text form as a
positioned one and lays every element at the origin.

One definition in the shipped set is like this (the POS Receipt Slip, in two locales), and it is
excluded from the native shipping set: seeded natively, a NEW install could print a receipt with
**neither** engine — Forms drawing nonsense and PhreeForm unable to read the format. As xml it keeps
working through the fallback, and `verify_forms_shipping_set.php` asserts that no serial form ships
native.

**This is a step 6 blocker, and the largest of them.** Deleting PhreeForm removes the only renderer
that can produce a POS receipt. Forms needs a text/receipt renderer — a fourth output alongside PDF,
CSV and the delivery layer — before the migration can run. The seam already supports it: a serial
form is a `Document` whose renderer emits text rather than bytes of PDF, and the flow model
(`width` in columns, `rowbreak`) is simple enough to express as its own document kind rather than
being forced into `Template`.

### 13.17 Selecting several, and taking hold of any side

Asked for on 2026-08-16 and 2026-08-17, and built on 2026-08-17. Two requests, one piece of work:
they meet in the pointer handler and in the same drag state.

**Why the group drag matters more than it sounds.** It is the tool the form-tidying work needs. A
layout fix almost never moves one element — it moves a *block*: a label and its value, an address
and its heading, the six elements of a cheque stub. Moved singly they drift out of alignment with
each other, so a tidy-up creates exactly the untidiness it set out to remove.

Shift-, control- or command-click adds an element to the selection or takes it out again; a drag
across empty page sweeps up everything the band **wholly encloses**. The same modifiers work on the
Elements list, which is the only way to reach an element sitting underneath another one.

Enclosing was the second answer. The band first swept up everything it *touched*, on the reasoning
that a form is full of rules and full-width headings and containment would mean dragging the length
of the page to catch a line you can see is covered. In use that reasoning inverts: a form is a
**dense** thing, so a band drawn across the middle of one brushes the rule above it, the rule below
it, the heading it passes under and the table border it happens to end inside — and the author gets
a selection they did not ask for, whose extent they cannot see, and which then moves as a block.
Containment costs a longer drag on a wide element and buys an exact answer, which on a canvas whose
next job is nudging blocks by a millimetre is the better trade. Changed on 2026-08-17 at the user's
request, after the touching version had been used.

#### One delta, snapped once

This is the finding, and it is not obvious until it is written down: **rounding each member
separately is what pulls a group apart.** Two elements 0.3 mm apart, dragged 2.4 mm on a 1 mm grid,
land on 22 and 23 — the gap between them changed from 0.3 mm to 1 mm, and the block whose spacing
the author was preserving is precisely what got bent.

So the delta is snapped **once**, against the element the author actually grabbed, and then applied
to every member unrounded. The grid stays meaningful, because the thing under the pointer lands on
it; the group stays rigid, because everything else moves by the same amount.

The same change fixed a fault the single-element drag already had. A drag used to be applied
incrementally — current position plus the delta since the last pointer event — and with snapping on,
an increment smaller than the step rounds away to nothing. An author dragging carefully across a
1 mm grid moved the element **not at all**, and only a fast flick crossed a step. Measured from the
position the drag *began* at, the same slow drag crosses each step exactly once.

Two smaller consequences of holding the origin: the undo snapshot moved from the press to the first
movement (a click that only selects is not a change, and was filling the history with states nobody
made), and the pointer is now captured by the **layer** rather than by the box. The box is destroyed
and rebuilt by `place()` on every move, which silently released the capture it was holding.

#### The conventional cursors, and what the sides mean

Every side and every corner resizes: `nwse-resize` and `nesw-resize` on the diagonals, `ns-resize`
and `ew-resize` on the sides, `move` in the middle. The grip is 6 px, never more than **a third** of
the side it sits on, and **nothing at all** on an axis with fewer than 8 px to give. A horizontal
rule is drawn two pixels tall: a grip at each end of that swallows the middle, so the element could
be resized and never moved — and on a `Line` it is worse than an annoyance, because the stored
height is the y offset of its *end point*, so the drag the author meant as a move would turn the
rule into a diagonal. All four corners now carry a mark, drawn as four inset box-shadows on the same
element, so the affordance matches the rule.

**The moving edge is what gets snapped; the fixed edge is left exactly where it was.** Snapping the
*width* instead would slide the far side about while the author was adjusting the near one, on every
element that does not already start on the grid — which, after twenty years of hand-typed
coordinates, is most of them.

#### Resizing works on the drawn box and derives the stored one

Dragging a north or west side changes the origin **and** the size in one gesture, which is where
§13.11's two findings bite. A `Line`'s sides are a signed offset to its end point, so on a line drawn
leftward the drawn *west* edge is the stored origin and the drawn *east* edge is the end: adding the
delta to the stored width there stretches the wrong end, and the outline parts company with the ink.

So `resize()` computes the new **drawn** rectangle and reads the stored box back off it, keeping the
sign. The direction is then preserved by construction and there is no case to remember. Two things
follow from getting this wrong quietly rather than loudly, and both are now asserted in
`verify_forms_canvas.php`: that a side drag can move the origin and the size in one save, and that a
**negative** side survives that save — a clamp at zero would flatten a leftward line to nothing, and
the author would watch it vanish rather than be told.

An image is the other half of §13.11: it stores a width and no height, because TCPDF works the rest
out from the file. Dragging its left or right edge is therefore not silence about the height — it
*changes* the height, by the file's aspect ratio — so the outline follows, while the stored side
stays blank. Nothing the author did said anything about it. Dragging a side that IS the blank one
makes it explicit at that moment and not before, which is the rule step 4 already used.

#### What it deliberately does not do

- **The inspector stays single.** It describes the anchor — the element last clicked, ringed on the
  page so a group of six otherwise identical outlines says which one the numbers belong to. A mixed
  selection has no position, no font and no binding, so the fields would have to blank themselves;
  and a typed change applying to every member is a far larger promise than "these move together",
  which is what was asked for. Everything that *is* shared — the drag, Delete, the order arrows —
  acts on the whole set, and the inspector says so in a sentence when there is more than one.
- **A group does not scale from one corner.** A resize acts on the element whose handle was grabbed.
  Scaling a group asks its own questions — what happens to a text element's line height, to a line's
  direction — and answering them by guess inside a pointer handler is the wrong place.
- **No alignment or distribution helpers.** The obvious next thing once a group can be selected, and
  not asked for.

#### The modified click did nothing, and three reasons why

Reported the moment it reached a browser: the band worked, the group drag worked, shift-control-click
did not. The handler was right — `chosen` gained the element every time. Three other things buried it.

1. **A shift-click's own meaning is "extend the text selection."** Left unrefused it paints a blue
   swathe across the canvas, which is what the author sees instead of a selection. Fixed in both
   halves: `event.preventDefault()` on the modified click, and `user-select: none` on the stack and
   on the Elements list. Nothing on the stack is text worth selecting — it is a rasterized picture
   with outlines over it.
2. **A selection was drawn as one extra pixel of border.** Every outline on the page is already
   coloured and 1 px, so "2 px instead of 1" is not a signal anyone reads at a glance — and a
   selection an author cannot see is one they will not believe they made. It is now a ring: solid on
   the anchor, dashed on the rest. Everything ringed is selected; the solid one is what the inspector
   describes.
3. **Being selected raised the box above its neighbours** (`zIndex: 2`), which changes what a click
   lands on. On a form where elements overlap — the cheque does — the second shift-click keeps
   hitting the first element and toggles it straight back off, which reads exactly like a modifier
   that does nothing. The z-index now follows the HOVER only.

A fourth thing was wrong without being reported: **no button check**, so a right-click began a drag
or a band that the context menu then interrupted, leaving the canvas mid-gesture. Primary button
only.

The lesson is not about any of the three. It is that **the handler being correct is not the feature
working**, and none of the three could have been caught by anything in this suite — the first two are
what a browser does with the event afterwards, and the third is a hit-test on overlapping geometry.
All four are now asserted, but they were found by using it.

#### The frame in front took every click

Reported from use on 2026-08-17, on the sales invoice: clicking any of the sixteen fields inside the
frame drawn around them selected **the frame**, every time. There was no way in from the page at all.

The cause is two facts meeting. Every box on the overlay is a filled surface, not an outline —
`place()` gives each one `background: colour + '14'` so it can be grabbed anywhere — and the array
order is the drawing order, so a frame added after the fields it surrounds is drawn over all of them.
`event.target` is the **uppermost** element at a point, and uppermost is the wrong answer on a form: a
container is essentially never what someone is pointing at when they point inside it.

So the hit-test is now `boxAt()`, which takes the **smallest** box containing the point. It reads the
intent directly and costs the frame nothing, because a frame is mostly empty — it is still grabbed
anywhere no smaller box covers, and always from the Elements list. Stacking no longer decides
anything, which incidentally makes the hover `z-index` from the fix above safe: it is now purely
something to look at.

One exception, or the fix would take away a gesture it had just given: **a box already selected keeps
the pointer while the pointer is on one of its handles.** Without it, selecting a frame and then
reaching for its corner hands back whichever field stands in front of that corner, and a resize
becomes a drag of something else.

Two consequences that were not obvious, and are the reason this was more than one line:

- **The cursor is shaped by one box and written on another.** The zone, and so the arrow, belong to
  the box that *would* be resized; but the cursor has to be set on the **topmost** box, because that
  is the one the browser consults. Written on the box behind, the frame's own `cursor: move` goes on
  showing and the pointer describes a gesture nobody can make.
- **The hover highlight had to move off `mouseover`.** That event fires on entering a *div*, and the
  div entered is the frame: inside one frame the pointer crosses a dozen fields without a single
  `mouseover`, so the highlight would name the frame throughout. It is driven from `pointermove` now,
  guarded on a change of index because `highlight()` walks every box and every row in the list.

The Elements list stays the way to reach an element **completely** covered by another, which is what
it was already for.

#### A script with no engine to check it

There is no JavaScript engine on the development machine or in the container, and the canvas is the
one page in Forms whose whole behaviour lives in a script. A mis-nested brace there raises nothing
anybody sees: the module fails to parse, every listener goes unbound, and the editor comes up as a
picture of a page that cannot be edited — which reads as a rendering problem rather than a syntax
one. `docs/tools/render_canvas_js.php` renders the script as the page emits it and checks that every
language constant resolved, that no PHP notice landed in the middle of it, and that its delimiters
balance. It is a scanner rather than a parser, and a guard rather than a proof.

The gesture checks in `verify_forms_canvas.php` can only assert that what the browser needs is
**present**, which is worth having for the same reason: each names a thing whose absence is silent —
a cursor that never changes, a selection that quietly collapses to one — rather than a thing that
throws.

#### Two checks that failed on correct code, for the fourth and fifth time

`verify_forms_pages.php` asserted that a drag is remembered inside the `pointerdown` handler, and
that the release handler calls `touched(0)` within 400 characters of `'pointerup'`. Moving the
snapshot to the first movement broke the first; growing the release handler by one branch broke the
second. Both were bounded by a character count or by "the next `addEventListener`", which is the
same fragility in two disguises.

They are now bounded by `handlerBody()`, which brace-matches the handler's own body, and phrased
against what must be **present**: the guard `if (!dragging.moved)`, and exactly one `remember()` in
the move handler. The pattern set out in §13.12 holds and now has a corollary — **never bound a
check by counting characters**, because the thing being measured is the one thing an edit changes.

**And a sixth, from a third direction.** `handlerBody()` skipped quoted strings so that a brace in a
CSS literal could not close a body early — but it did not skip *comments*, and it treated `'` as a
quote wherever it found one. An ordinary English possessive in a new comment (`the frame's own`)
opened a string that ran to the next quote in the source, the brace count went wrong, the returned
body ran on into the handler below, and the release-handler check failed on correct code. The fix is
in the tool, not in the prose: comments are now skipped, because a comment is not code and an
apostrophe in a sentence is not a syntax error. Rewording the comment would have been the cheaper
answer and the wrong one — the next sentence anybody writes has the same apostrophe in it.

### 13.18 Adopting a tidied layout back into the shipping set

Asked for on 2026-08-17, once the canvas was good enough to tidy a form on. The shipped layouts were
inherited from PhreeBooks and were never revisited. Nobody is going to fix a layout by editing
coordinates in XML, so the work is done on the canvas, in a company, and has to travel back. This is
the return leg: `docs/tools/adopt_forms_layouts.php`.

#### What the canvas shows where there is no data, and what it used to show

A form previewed with no record behind it is filled by `SampleData` (§13.2), and until 2026-08-17
every text value was **the field's own name** plus a line number — `ship_state_province 6` drawn
where *Devon* would be. The reasoning was that a value naming its own field tells an author which box
they are looking at. It does, and it also lies about the one thing a layout is judged on: a field
name is longer than the data it stands for, near enough always, so a narrow box bound to a long name
wrapped in the preview and would not have wrapped in life. A text element's stored height is one line
and it grows downward, so the overflow landed on whatever sat below and the form looked broken.

**The cheque is the worked example, and it cost this document a wrong claim.** An earlier draft
recorded its stub as overlapping the line-item table "in the source definition". Measured:

| | declared |
|---|---|
| the three stub rows | y = 8, 12, 16, each 5 mm tall — a 1 mm nominal overlap between rows, which is only the line height being taller than the spacing |
| the table below them | y = 25, i.e. **9 mm clear** of the last row |

Nothing collided. What collided was one field's *text*: `purchase_invoice_id 1` measures **35.1 mm at
10 pt in a 30 mm box**, so it wrapped onto `post_date` 4 mm below. A real invoice number — `INV-1043`
— measures 14.9 mm and leaves half the box empty. Reported by the user before starting the tidying,
and confirmed by measurement rather than taken on trust.

So the sample values are now **invented but believable in length**: the kind is guessed from the
field name (email, phone, postcode, city, county, address, sku, description, terms, reference,
company, person) and each kind carries a SPREAD of lengths, so a few rows show what a long entry
does. That is strictly more informative than a column of identical field names — the near-worst case
is visible, and it is a real one rather than an artefact of how the field was named. Everything is
fictional, using the reserved `example.com` domain and Ofcom's reserved `01632 960xxx` range. The
cheque now fits in every box.

Report columns changed with them, where the old behaviour was worse rather than better: the value was
the length of the **heading**, which is what the author is trying to judge the column against, and a
heading is usually *shorter* than the data beneath it — so a column looked comfortable and overflowed
in life. Identification survives in both places: a report draws its headings, and the canvas outlines
every box, colours it by kind, names it in a tooltip and in the Elements list, and describes it in
the inspector.

**The rule that outlives all of this:** judge an overlap by whether the BOXES overlap, not by whether
the preview looks wrong, and leave a form alone when the only fault is invented text. The adopt tool
reports such a form as `unchanged` and commits nothing for it, which is the correct outcome rather
than a missed one. No sample can tell you what a long customer name will do to a narrow box; only
real records can.

#### The procedure

1. **Tidy** in a company whose definitions are stock, on the paper the masters ship
   (`?module=forms&page=canvas`, or Forms → Form Layout from the menu, which lists every `frm`).
   Saving is the whole handoff: there is nothing to export.
2. **See what would change**, which writes nothing:

       docker exec <c> php /opt/repo/docs/tools/adopt_forms_layouts.php <company>

   Read any `[CONTENT]` line before reaching for `--allow-content`.
3. **Write the masters** with `--write`, narrowing with `--only="<Title>"` to land one at a time.
4. **Copy them into the repository** — the files the tool names, and `docs/forms-adopted.json` with
   them. Without the manifest the generator reverts the work on its next run and the verifier fails
   the file as stale.
5. **Verify**: `verify_forms_shipping_set.php` reports the adopted files as sound rather than stale,
   and `generate_forms_shipping_set.php` says `[adopted] … left alone`.
6. **Prove the seeding.** `verify_paper_seeding.php` drives the real `admin_add_reports()` into a
   temporary directory, and that is the installer's own function — it prefers the native file, so a
   pass means a new install gets the tidied layout.
7. **Commit** the masters and the manifest. The xml is deliberately not touched.

**Adoption needs nothing copied back INTO the container**, and a first draft of this had a step for
it. The deployed tree already holds the written files, because that is where the tool writes them;
the manifest is already under `/opt/repo/docs/`, because that is where the tool keeps it. Only the
repository needs feeding.

`/opt/repo` does now carry the report directories of both trees — `trunk/modules/*/language/*/reports`
and `extensions/Modules/*/language/*/reports` — but for a different purpose: the drift check added in
§13.25 compares what is installed against what the repository ships. That copy is a *reference*, not
an input to anything, and it wants refreshing whenever a master changes, or the check is comparing
against a stale idea of the repository.

Rehearsed end to end on one form before any real tidying (2026-08-17): two elements moved and saved
through `DefinitionLoader::save()`, the same call the page makes. The master's diff was **four lines
of a 54 KB file** — the two coordinates and nothing else — and seeding it through
`admin_add_reports()` into a temp directory read back the moved boxes, identical to the master. Then
reverted.

#### The two things it puts back, and the one it refuses

**The paper goes back to the master's.** A company carries the installer's Letter/A4 answer; the
shipping set carries its own. Adopting the company's would re-paper the whole set for every future
install, silently, from whichever company happened to be used that day.

It does not follow that the paper can be ignored while tidying, and this is the trap worth naming:
**Letter is 6 mm wider than A4 and 18 mm shorter.** Elements are positioned absolutely from the top,
so a layout fitted to the foot of an A4 page falls off the foot of a Letter one — and Letter is what
the masters ship. The test company was re-stamped to Letter before any tidying started, which is also
why `page.size` then needs no restoring at all.

**Everything that is not geometry is refused unless asked twice.** Moving and resizing is what this
is for. A change to a text, an image, a binding or a column heading changes what the document *says*,
and the likeliest reason for one is that the definition came from a company that had customised it —
which is precisely how a real installation's branding and field choices would end up in a public
repository. The classification is deliberately wide, because a false positive costs one flag on the
command line and a false negative costs a private name in git history for ever. `--allow-content` is
the second ask, and every such change is listed before it is made.

That guard is not hypothetical, and running it against a company cloned from a real installation is
what finished the classification. The first pass called four things "appearance" that are nothing of
the sort:

- an element **label** — free text, and the name someone gives a logo box is their company's;
- a report's **title line** — free text with substitutions in it, so it is what the document says;
- the top-level **description**;
- **page.orientation**, which turns the whole page and is a shipping decision like the paper, not a
  tidy.

With those moved, that company offers **nothing** adoptable without the flag: three definitions, all
refused, the other 58 still legacy XML and skipped with a reason. Its invoice alone carried customised
branding, a customised heading and an extra bound field, six elements more than the master.

#### The contract that had to change

Every shipped `.json` was a GENERATED artefact: the generator wrote it from the `.xml` and the
verifier proved, byte for byte, that it still matched — the only claim worth making about a committed
generated file. A hand-tidied layout cannot satisfy that claim and must not be reverted by the next
generator run, so adoption is recorded in `docs/forms-adopted.json`:

- the **generator** skips an adopted file and names it; `--force` overwrites, which is what
  abandoning a tidy looks like;
- the **verifier** drops the byte check for an adopted file and applies the checks that survive
  editing instead — it still decodes, it is still exactly what the editor would write, and it is
  still the same document as its XML (type, title, group, filename prefix, provider, dataset). A
  binding that disappeared is reported rather than failed, because a tidy may legitimately delete a
  duplicated label, but a field the document no longer prints wants reading.

Everything not listed keeps the byte check, so ordinary staleness is still caught. Both directions
were tested by hand: a mangled adopted file fails the canonical check, an adopted file with a changed
title fails the same-document check, and the generator leaves an adopted file alone.

The XML stays regardless. It is what Import reads, what a company without Forms seeds from, and the
record of where these definitions came from.

#### Two bugs the byte comparison uncovered

The whole approach rests on a claim `forms_seed_native()` had been making in a comment — that a
seeded file is byte-identical to one the editor would write. It was false, twice over, and both
would have been read as "the tidy changed something" by the tool that trusts it:

1. **`json_decode($s, true)` turns every `{}` in the file into `[]` on the way back out.** A
   definition with `"style": {}` was seeded as `"style": []`. Harmless to read — `DefinitionCodec`
   treats the two the same — and fatal to any comparison of bytes. Both the seeder and the re-stamp
   now decode to objects.
2. **`DefinitionCodec::encode()` ends with a newline and those two functions did not.** One byte, in
   every seeded and every re-stamped file.

The re-stamp is the sharper case: its own comment says it has no business rewriting the parts of a
definition it was not asked about, and it was rewriting a little of every one. Neither bug changed
what any document printed, which is why neither had been noticed — and neither would have been
noticed now, except that something finally needed the files to be equal.

#### What it does not cover

- ~~**Extension modules.**~~ **Covered since 2026-08-18** — `work_orders` and `assets` ship native
  masters in the repository, so their layouts adopt like any other. See §13.25, and note that the
  copy-out destination is `extensions/Modules/` rather than `trunk/modules/`.
- **Serial (receipt) forms.** XML only by design, no canvas, no renderer — §13.16.
- **Reports, partly.** A report's editable geometry is its column widths, and those adopt like any
  other; but a report has no canvas, so the tidying this was built for does not apply to it.

### 13.19 A restore leaves a company holding both formats

Asked on 2026-08-17, and the right question: a company is created and seeded with native `.json`
definitions, then someone restores a backup into it that contains only PhreeForm `.xml`. What breaks?

**The restore itself does not.** Traced through:

- extraction is an **overlay**, not a replacement — `restore_extract.php` runs `extractTo()` (or
  `tar -xjf`) into the company directory and nothing clears `phreeform/` first, so the backup's XML
  files land on top of the seeded native ones and any seeded file whose id is not in the archive
  survives;
- the SQL dump carries `DROP TABLE IF EXISTS` per table, so the catalogue is replaced wholesale by
  the backup's rows and its ids;
- `DefinitionLoader` tries native, falls back to PhreeForm XML and converts on the way past, so every
  restored document opens and prints. The company simply reverts to legacy definitions, converted on
  each load — the fallback the format was designed with, and what both legacy companies do daily.
  Save one in Forms afterwards and it is written back as native, so the company migrates piecemeal.

Two nuisances follow — orphaned native files at ids the backup never mentions, and, where a backup
holds a catalogue row whose file is missing from the archive, a leftover file being silently adopted
as that document. Neither is new with native definitions; the second is the old rows-without-files
hazard wearing a new coat.

#### The tool that did break

**Report Settings → Rebuild**, which is precisely what someone reaches for when a restore has left
the list wrong. It called `xml_to_object(file_get_contents($file))` on every `pf_*` — and given JSON
that function **hands the string straight back** rather than failing. Every field then read off it is
NULL, which on PHP 8.5 is a warning and not an error, so the tool ran to completion and reported
success while writing a catalogue row with an empty title, an empty group and an empty `doc_ext` for
every native definition, filing them under Miscellaneous and renaming each file to a fresh id.

Measured on a fresh install before the fix: **54 documents rebuilt, 54 of them nameless.** The files
survived — the ids did not, and neither did any way of telling one document from another.

It reads both formats now: a file beginning `{` is decoded and its `title`, `group` and `type` taken
from the JSON, with `u:0;g:0` for the security column, which is what that field means for a native
definition (decision F, and what `forms_seed_native()` writes). A file that is neither is **skipped
and counted** rather than turned into an empty row — the tool has just truncated the table those
documents were listed in, so a file it cannot read is a document that has vanished, and it says how
many.

Verified by driving the real page against a fresh install: 54 native definitions rebuilt with their
titles, groups and types intact; then with a legacy XML definition and an unreadable file dropped in
beside them, 55 documents rebuilt, none nameless, the XML one landing in `cust:inv` as a `frm`, and
the unreadable file left where it was.

The mirror-image risk is worth stating and is not fixable here: **a native company's backup restored
into a company without Forms** is dead, because PhreeForm cannot read a native definition at all.
That is an argument for step 6 rather than against seeding native.

### 13.20 The shipped Purchase Order prints the supplier's part number

Asked for on 2026-08-17: *"I want to add a Vendor SKU column to the Purchase Order table."* Worth
recording because almost none of the work was new — and because the one part that could not be done
in the UI is now the clearest gap in the canvas.

**The data was already built.** §8.2 added a `purchasing` dataset for exactly this: `transactions`
plus a LEFT join to `inventory_purchase_details` on **both** sku and vendor, carrying `vendor_sku`
(the supplier's number, falling back to the first word of the purchase description),
`vendor_sku_only` and `vendor_description`. It was written to migrate a customisation that already
existed, and it holds that customisation's SQL as a `legacyName`.

**So the change is the XML, and the generator does the rest.** Three edits to
`PurchaseOrder.xml`: a `<tables>` block naming `inventory_purchase_details` with the join condition,
a `<boxfield>` whose field name is that SQL expression, and Description giving up 28 mm so the
columns still total the table's 200. Then `generate_forms_shipping_set.php --write`, and the
conversion did the interesting parts by itself:

- `DatasetResolver` picked `purchasing` over `transactions`, because that is the narrowest dataset
  covering the tables the definition names;
- the SQL field name matched the `legacyName` and came out bound to **`vendor_sku`**, not left as raw
  SQL — which is the whole point of §1.1 and the reason those `legacyNames` are there.

Editing the XML rather than the JSON is deliberate: this change is expressible by the converter, so
both formats stay in step, the byte check still applies, and it needs no entry in
`docs/forms-adopted.json` (§13.18). Adopt the JSON only for what the converter cannot say — layout.

Proved by rendering: the shipped definition seeded into a company as a temporary document, a real
purchase order printed through `FormRunner`, and the column read back out of the PDF text between SKU
and Description with the supplier's part number in it. Catalogue row and file removed afterwards.

**The caveat, accepted deliberately.** That join is one-to-many on (sku, vendor_id) and those columns
are not unique, so a duplicate purchase record can duplicate a line on a printed order. It is a LEFT
join, so no line is ever dropped — the worse failure. This is why the fields were kept out of
`transactions`, where they would have reached 44 unrelated documents; on the purchase order, where
the supplier's number is the point, the risk is the user's to accept and was accepted.

An existing company keeps the purchase order it was seeded with. Nothing in the UI adds a column to
it, which is the next section.

### 13.21 Scope: editing a table's columns on the canvas

Not built. Scoped on 2026-08-17, when adding one column to one shipped form turned out to need a
developer, an XML edit and a regeneration. It is item 7 on the after-step-6 list in §13.7: wanted,
and not a thing PhreeForm's removal waits on.

**What is missing.** `TemplateForm::apply()` rebuilds a `Table` from `$original->source,
$original->columns` — the canvas moves it and resizes it and cannot touch what is inside it. So a
table is the one element on the page whose content is beyond the editor, and a table's column list is
exactly the sort of thing an author wants to change: add the supplier's part number, drop a column
nobody reads, widen a description.

**What already exists to build on.** The report editor (§13.10) edits a column list already — order,
heading, width, alignment, format, totals, column break — for reports. A form table's columns are
the same shape (`ReportColumn`), so this is the same control in a different host, not a new idea.

**The work, in the order it would be done:**

1. **The palette has to include line-grain fields.** `forms_binding_names()` returns what the
   document can supply, and a table column binds per LINE. The dataset knows which fields are
   line-grain (`FieldDefinition::$table` against the provider's line table) — that distinction
   already exists and is load-bearing, because naming the wrong table makes a per-line value a header
   value and every row shows the first row's. A column palette must offer only the line-grain half.
2. **A repeating control in the inspector.** One row per column: bind, heading, width, alignment,
   format, total. Add, remove and reorder. The report editor's markup is the model.
3. **`TemplateForm` has to accept columns**, which is where the care goes. Every submitted binding
   through the same gate as a field (`$allowed`, with a warning and the old value kept on a name the
   form cannot supply); widths validated as numbers; and the sum of the widths reconciled with the
   table's own width — a table whose columns total more than its box is how the legacy reports ended
   up clipped (§13.9).
4. **Changing the DATASET is a separate question, and the harder one.** A new column may need a field
   the current dataset does not have — which is exactly what the Vendor SKU needed. Resolution today
   is automatic and by tables named; letting an author change it by hand means letting them widen a
   join, which is the privilege §3.1 deliberately separated into `SECURITY_ID_FORMS_DATASETS`. The
   honest first version is: offer the fields the resolved dataset supplies, and say plainly when a
   wanted field is in another one. Not "silently join more tables".
5. **Verification.** A column added, saved and reloaded byte-identically; a bad binding refused with
   the old column intact; widths that overflow the table reported; and a rendered PDF showing the new
   column with data in it, which is the only proof that the palette's grain was right.

**Risks, in the order they will bite.** A column bound to a header field (silently repeating the
first row) — mitigated by the grain rule in step 1. Widths that no longer fit — mitigated by step 3.
And the dataset question in step 4, which is where this could quietly turn into "let an author write
a join" if nobody holds the line.

### 13.22 The document list asks its questions per document

Done 2026-08-18, on the user's direction after a review of `?module=forms&page=main`. Four things
were on the table; three were taken. Viewing a report inline instead of downloading it was offered
and declined, and the page's remaining dependence on `TABLE_PHREEFORM` and the PhreeForm bootstrap
was left where it belongs, in step 6.

#### The defect the review found

The page carried a page-wide options bar — date range, accounting period, GL account — above a list
of thirty-nine reports. The date select was rendered with `'a'` preselected, `TEXT_ALL`, and the
click handler sent it whenever it was non-empty. `DocumentRunner` only falls back to the
definition's own `dateDefault` when the parameter is **empty**, so every report run from this list
ran over all time. A report drawn as "this month" gave the whole ledger and looked like it had
worked. Measured on both companies, through the filter description the renderer prints on the page:

```
Customer Invoice History           stored=f
  no parameter : Report Filters: Sorted by: Invoice Date; Date range: 01/08/2026 to 31/08/2026
  forced 'All' : Report Filters: Sorted by: Invoice Date
```

That is the whole bug: the second line is what the page produced, and the report says so in its own
header, which is why it was never obviously wrong.

#### What replaced it

`RunPlan` already knew `needsPeriod` and `needsGlAccount`; it gains `needsDates`, true on the
dataset route when the definition has a `dateField` for a range to apply to. A definition without
one runs identically whatever a range says, and a control that changes nothing is worse than no
control. Two derived questions sit beside them: `hasOptions()`, and `mustAsk()` — the answer the
definition cannot supply for itself, which today is the bank reconciliation's account.

So each row declares what it can be asked, and the page acts on it:

- **PDF runs immediately**, sending no dates and no period, which is what lets the runner fall back
  to the definition's own answers. The common case costs no clicks and comes out as drawn.
- **A caret** beside it offers CSV, Email and — only where `hasOptions()` — Options.
- **The dialog** is headed with the document's name and shows only the controls that document needs.
  The date list's first entry is *As the report is set — This Month*, valued empty: choosing nothing
  sends nothing. The period has no such fallback (a structural provider cannot do anything with
  period 0), so the stored one is named outright, or the current one where none is stored.
- **`mustAsk()` opens the dialog instead of running.** Reconciling whichever account sorts first is
  a plausible-looking wrong answer.

The page-wide options bar and the email card are gone; the email fields moved into the same dialog,
which is what lets the subject default to the document's own title. The form now wraps the dialog
and nothing else. A refused send reopens the dialog on the document it was for — and a **successful**
one does not, which needs its own flag: `$_POST` still holds the message on the redraw, so
"was anything typed" cannot distinguish the two.

#### A required answer with no possible value

Found by the user on a company whose chart of accounts is empty. The bank reconciliation is the one
report that `mustAsk()`, and the dialog duly presented a mandatory GL account select — with no
options in it. Mandatory, unanswerable, and an error message that could never be satisfied.

The query and the filter are right: `account_type = 0` is cash and bank, and a company with a chart
lists its four. What was wrong was the page's behaviour when the answer set is empty. The dialog now
says there is nothing to reconcile and where to add an account, disables the empty control, and
**disables the run buttons** rather than demanding an answer that does not exist.

The general rule this is an instance of: a required control whose list is empty is not a validation
problem, it is a precondition that has not been met, and it should be reported as one.

#### Filtering, folding, and what a row says

A filter box narrows the list on name and group, in the browser, over rows that are already
rendered — no query, so the framework-wide `LIKE '%term%'` scaling problem does not arise here.
Folder headings fold. **A search looks through a folded group**: being told nothing matches, because
the group holding it happens to be shut, is the kind of answer that costs an afternoon.

The "Served by" column is gone. The dataset or provider a definition resolved to is diagnostic — the
answer to "why can this report not see that field" — so it sits under the title for the people who
can author, and nobody else. A document Forms *cannot* serve says so to everyone, with a warning
sign and the reason, because that is what the reader is looking for when a document they expect is
missing.

Both `alert()` boxes are gone. The GL account and the To address report on the control itself, which
is now in the dialog beside the field, rather than in a box that pointed at a collapsed panel.

#### Two things the markup had to get right

The row, not the button, carries `data-url` and the flags: one document has several ways to be run,
and repeating its URL on each of them is how they drift apart. And the table lost its
`.table-responsive` wrapper — two columns never need to scroll sideways, and the wrapper's
`overflow-x` makes a clipping context that cuts the row dropdowns off, which is a menu that opens
and is invisible.

#### Verification, and what it cannot cover

`verify_forms_pages.php` was extended rather than relaxed, and four of its checks had to be taught
the new shape. Three of those were reading the old markup and would have passed on a broken page:
the title-cell reader took `textContent`, which swept up the diagnostic line and reported every
non-runnable document as missing from its own list. It now reads the words a node shows *itself*,
ignoring child elements.

New checks: every runnable row names its document, its URL and all four flags as `0` or `1`; **no
run URL imposes a date range or a period**, which is the defect above, asserted; the dialog draws
`dates`, `period` and `gl_account`, and the first date option's value is empty; the filter box is
not inside the form (an `<input>` in a form takes Enter as "submit", and the only submit this page
has sends an email); and no `alert(` survives anywhere in the render.

There is still no JavaScript engine here, so the script cannot be exercised. Standing in for it:
**every id the page's script reaches for must exist in the render** (19 checked) and **every class
it delegates on must be in the markup** (4 checked). `getElementById` returning null is silent until
the line that uses it, which presents as a button that highlights and does nothing — the exact
failure this page had once before. That is a guard, not a proof.

The run harness also had to change. It followed the first `.fb-run` control it found, which on a
stock install is the Account Reconciliation — a report that properly refuses to run until it is told
which account. So it proved the refusal worked and reported a page that delivers documents perfectly
well as broken. It now follows the first row with `data-must-ask="0"`. Both companies pass.

#### The trap that made every button dead, and why the harness missed it

`template_index.php` emits `pages/{page}/js_include.php` inside the **`<head>`** — before `<body>`,
and long before the page's own markup. So the script runs when none of the elements it works on
exist. This page's first version read its elements at the top level and guarded on them:

```js
const dialog = document.getElementById('forms_run_dialog');
if (!dialog || !table || !form) return;      // always taken
```

Every one of those was null, the guard bailed, and nothing was wired: PDF did nothing, and neither
did anything on the caret. The version it replaced never met this, because it delegated on
`document` — which does exist in the head. Everything now sits inside a `DOMContentLoaded` handler,
which also makes `bootstrap` safe to name: the bundle is loaded with `defer`, and deferred scripts
are guaranteed to have run by then.

**The harness had modelled the page wrongly**, and that is the more useful half of this. It rendered
`pre_process`, then `template_main`, then `js_include`, and parsed the three as one document — so
every id the script reached for was "present", and the check that asserts exactly that passed on a
page whose every button was dead. It now renders them in the order `template_index.php` uses, script
first.

Order alone does not catch it, though: a parsed document has no notion of *when* a line ran. So
there is a check for the timing itself. It measures brace depth through each non-module `<script>`,
skipping strings and comments, and reports a `getElementById` or `querySelector` executed at the top
level — where "top level" is one brace deep when the file opens with an IIFE, because
`(function () { … })()` runs immediately despite the brace. A lookup inside a function *declared*
there is fine; declaring is not calling.

That detector is itself tested, on the four shapes it exists to tell apart — an IIFE that acts at
once, a `DOMContentLoaded` wrapper, a lookup inside a declared function, and the name appearing in a
string. A checker that answers "safe" to everything passes every page and protects none, and this
one is a hand-written scanner over JavaScript.

### 13.23 The report editor has no list of its own

Asked by the user on 2026-08-18, after cancelling an edit and landing somewhere they had not come
from: *"Do we need the Report Editor page now?"* Mostly, no.

**What it was.** With no `rID`, `?module=forms&page=design` answered with a chooser: every
`doc_ext <> 'frm'` document, with a Title and a Group column. That is the same population the
Reports page lists — and it printed the raw `doc_group` code, which §13.14 had deliberately stopped
doing on the Reports list because `cust:inv` beside every row made an ordered page look unordered.
Two lists of one thing, and the worse of them on the Tools menu.

**Why it was there, and why that no longer holds.** `config.php` said the menu entry was the only
way `SECURITY_ID_FORMS_AUTHOR` could be granted at all, because phreedom's roles page builds itself
by walking `$mainmenu` and drawing one row per entry that names a security id — an id no entry names
cannot be given to any role, and nothing errors; the feature is simply absent. That reasoning was
correct when written and is now stale: the form canvas was added afterwards and names the same id.
Measured before removing anything — with the entry gone, ids 8, 9 and 10 are still named by Reports,
Form Layout and Report Datasets respectively.

That invariant is now asserted rather than remembered: `verify_forms_pages.php` checks that every one
of the module's three security ids is named by some menu entry. Removing the canvas's entry would
now fail the suite instead of quietly ungranting every authoring privilege.

**What replaced it.** The page keeps its "nothing chosen" state — arriving with no report is not an
error and must not be reported as one — but it says which list to use and links to it rather than
building a second one. Cancel returns to the Reports list.

**The canvas is NOT following it, and that is the same rule rather than an exception.** Cancel means
"back to the list this document appears on". For a report that is the Reports list; for a form it is
Form Layout, because forms are kept off the Reports page on purpose (§13.14), so the canvas's
chooser is the only list a form appears on at all.

### 13.24 A heading has to look like a different kind of thing

Reported by the user on 2026-08-18: *"the category headings don't look much different from the
report names so they don't help the user much."*

The headings were `fw-bold text-muted` at the row's own size — bolder than a report name, but in the
same colour family and on the same ground, so the eye ran past them. Grouping that the reader does
not see is grouping that is not there.

They are a **band** now: their own background (`#c9e0f0`, distinct from both the `#E2E4FF` odd rows
and the white even ones), ruled `2px` above and `1px` below in the theme's `#4297d7`, with a small
uppercase letter-spaced label in `#0d4d69` — a colour no row uses. The rule above is dropped on the
first heading, where the table head already provides one.

Two things worth keeping in mind from doing it:

- **The look belongs in `freebooks.css`, not in utility classes on the markup.** `.text-muted` and
  its neighbours carry `!important`, so a colour of our own could not have won against them; the
  heading's classes came off the button and the styling went into `.fb-folder-toggle`.
- **The heading is a `<button>`** — it folds its group, so it has to be reachable from the keyboard
  and announce its state. None of that should show, so the button's own chrome is turned off in the
  stylesheet rather than by piling `p-0 border-0 text-decoration-none` onto the element.

`freebooks.css` is a static file, so a browser that has it cached will show the old headings until a
hard refresh — which is a deployment note, not a bug, but it is the first thing to check when a style
change appears not to have landed.

### 13.25 Optional modules ship native definitions too

Decided by the user on 2026-08-18: *"Extension modules should ship native definitions."* This had
been an open question since §13.18, where the Work Order form was the one shipped layout that could
not be tidied — the tidy is adopted back over a **master**, and `work_orders` had no native master to
adopt into.

`admin_add_reports()` chooses per FILE, not per directory, so a module shipping only `.xml` has
always been legal and still is: the seeder falls back to `ImportReport()`. What made it the wrong
default is what a fresh install now looks like. Every core document is seeded native (§13.5), and
after step 6 there is no PhreeForm to fall back to for the pages that render one — so a module
shipping xml alone would hand a new company a handful of documents converted afresh on every load,
in a system where nothing else is. Five files close that:

| Module | Definition | Type | Paper |
|---|---|---|---|
| `work_orders` | Work Order | form | Letter, landscape |
| `work_orders` | Work Order List | report, 3 columns | Letter, landscape |
| `work_orders` | Work Order Task Usage | report, 4 columns | Letter, portrait |
| `assets` | Asset List (`en_us`) | report, 8 columns | Letter, landscape |
| `assets` | Activa Lijst (`nl_nl`) | report, 8 columns | Letter, portrait |

All five convert without a warning, and all five bind to datasets the catalog already registers —
`work_orders`, `assets`, and the `wo_builder` provider for the form. Nothing new was needed to make
them native; they were simply never generated, because the generator globs the modules **installed**
in the container and nobody had checked what that missed.

**No module version was bumped, and that is deliberate.** `admin_add_reports()` runs from the
`install` branch of the module admin, not from `update`. A company that already has these documents
keeps them exactly as they are; a company that installs the module after this release gets native
ones. Bumping the version to advertise new shipped files would have implied an Update that does
nothing.

#### The trap: an optional module's installed copy is made once and never refreshed

Two of these five reports — Work Order List and Asset List — were turned **landscape** in the
repository on 2026-08-14, as part of the width work in §13.9. The container's copies still said
portrait four days later, because a core module is redeployed from `trunk/` constantly while an
optional one is copied out of `extensions/` once, when it is installed, and then never again.

Generating the native set at that moment would have produced, committed and shipped two definitions
converted from an xml **nobody ships**. It surfaced only because the two trees happened to be
diffed by hand first. That is not a repeatable safeguard, so it is now a check in both tools:

- `generate_forms_shipping_set.php` compares each installed `.xml` against its master under
  `/opt/repo/{trunk/modules,extensions/Modules}` and **skips** any that differ, naming them and
  exiting non-zero. Generating from the wrong source is worse than not generating.
- `verify_forms_shipping_set.php` makes the same comparison over both formats — a drifted `.xml`
  means the set was generated from the wrong source, a `.json` present here but absent from the
  repository means somebody generated and forgot to copy back out.

The masters reach `/opt/repo` by `docker cp`, not from the image, so both tools report their absence
rather than failing on it: a container without them still runs every other check.

#### Where a generated file goes back to

Inside the container both kinds of module live at `modules/{module}/language/{locale}/reports/`.
In the repository they do not:

| Installed as | Master in the repository |
|---|---|
| `modules/phreebooks/…` | `trunk/modules/phreebooks/…` |
| `modules/work_orders/…` | `extensions/Modules/work_orders/…` |

So the copy-out step of §13.18's route has two destinations, and the one it needs depends on whether
the module ships in trunk or in extensions. The build context at `freebooks-docker/php-app/modules/`
takes a copy either way, because that is the deployment.

`verify_forms_shipping_set.php` now reports **61 definitions in 7 modules**, of which `phreepos`
remains xml-only for its two serial receipt slips — correct, and for the reason in §13.16: Forms has
no renderer for a character-cell form, so a native copy would leave a new install unable to print a
receipt with either engine.

### 13.26 The render-to-string call sites, and the two that turned out not to be

Tackled 2026-08-18, continuing [[todo_forms_render_to_string_callsites]]. Four
places render a document to bytes in-process rather than through a page:
the ZenCart cron's VAT invoice email, the PayPal payment-link email's
invoice attachment, and the POS receipt on save and on reprint. All four
called `get_report_details()` + `BuildForm($report, 'S')` — PhreeForm,
unconditionally — and `get_report_details()` refuses a native (JSON)
definition outright, returning `false` with a page message that these
callers have no page to read.

**Only two of the four are actually the PDF-bytes problem the note
described.** The other two — `phreepos/ajax/save_main.php` and
`phreepos/ajax/print_previous.php` — render the POS Receipt Slip, which is
a SERIAL form: character-cell text for a receipt printer, not a page
(§13.16). `BuildForm()` returns a plain string for it, not a PDF array, and
Forms has no renderer for that shape at all — there is nothing to switch
these two onto. Re-reading the original note against the code it named was
what caught this; it had grouped all four under "need PDF bytes."

**What shipped for the two real cases** — `forms_render_to_string()`,
`modules/forms/functions/forms.php`. The same sequence
`pages/print/pre_process.php` runs for a page (load → plan → run →
`LayoutPdfRenderer`), without the HTTP delivery, the record selector, or a
`$messageStack` to write to; failures go to `error_log()` instead, because
there is no page to put them on. It reads whichever format the definition
is stored in, through the same `DefinitionLoader` the print page uses — the
one place a PhreeForm caller can go to stop caring, which is what actually
closes the trap.

`zencart_email_vat_invoice()` and `_paypal_build_invoice_pdf()` now try it
first when `MODULE_FORMS_STATUS` is defined, falling back to the existing
`get_report_details()`/`BuildForm()` path only when it returns null — so
Forms not being installed, or a definition Forms genuinely cannot serve,
degrades to exactly the old behaviour rather than a new failure mode.

**This was not a hypothetical.** On `demo`, the `cust:inv` group resolves
(`ORDER BY doc_title LIMIT 1`) to a document that is native — seeded back when
`phreebooks` first shipped native definitions (§13.5) — while `production`'s
same-titled document is still legacy XML. Measured before the fix:
`get_report_details()` on that id returned `false`; the PayPal payment-link
email had been silently sending with no invoice attached, with nothing
anywhere saying so. Measured after: `forms_render_to_string()` on the same id
returns a 160 KB PDF on `demo` (native) and a 160 KB PDF on `production`
(legacy) — the same document, either format.

**Superseded by §13.28**, which built the serial renderer and the receipt
editor these paragraphs describe as missing. What follows is left as written
because the guard is still there and still correct — the canvas still refuses a
receipt — and because the reasoning is what the editor was designed from.

**What shipped for the two receipt call sites** — a null guard matching the
one `get_report_details()` already documents needing: `save_main.php`
previously read `$report->title` with no check at all, so a native receipt
definition would not fail cleanly, it would cascade property-access
warnings through the filter-building lines below and hand `BuildForm()` a
`false`. Guarded, both now decline with a message instead. This does not
make the underlying document any less printable — nothing does, until
Forms has a serial renderer — it only stops a bad state from becoming worse
than "no receipt."

**What did NOT ship: a guard on the canvas.** Nothing in Forms recognises
`<serialform>` at all — the converter accepts a receipt's XML and produces
a definition with every element at 0,0 (§13.16's original finding), and
nothing stops it being opened and SAVED from the canvas, which is the one
way a receipt could actually become native and hit the code path just
guarded against. Preventing that at the source — refusing to load or save
a serial-form definition in the canvas — is a real, separate fix, not
covered here; the two ajax guards only stop the symptom from being a
warning cascade.

**A stray catalog row, and the lesson.** Verifying this touched off an
unrelated finding: an ad-hoc script written earlier in the same session to
test extension-module seeding (§13.25) had called `admin_add_reports()`
against `test` with a TEMP save path — and left a real catalog row behind.
`admin_add_reports()`/`ImportReport()` insert the TABLE_PHREEFORM row
unconditionally; only the FILE goes to the path given. `verify_paper_seeding.php`
already knows this and cleans up after itself (`$highWater` captured before,
everything above it deleted after); a one-off script written to answer a
quick question did not, and it sat unnoticed until it changed which
document `verify_forms_pages.php`'s self-test picked to run. **Any direct
call to `admin_add_reports()` outside that tool needs the same before/after
id bracket, even against a temp directory.**

See [[todo_forms_render_to_string_callsites]] and
[[project_phreeform_replacement]].

### 13.27 The receipt form, guarded — and why a canvas is the wrong tool for one

Built 2026-08-18, taking up the guard flagged at the end of §13.26. The
question that shaped it was the user's: *could* we give the receipt a
canvas, so it can be edited rather than merely refused? The format
answers that, and the answer is no.

**A serial form has no coordinates.** Not coordinates Forms mis-reads —
none. Measured on the shipped `ReceiptSlip.xml`, all 19 fields:

```
 0 type=Text  w=40 align=C brk=1  abs=''  ord=''  text='Sales/Invoice'
 5 type=Text  w=40 align=L brk=0  abs=''  ord=''  text='Order #'
 6 type=Data  w=40 align=C brk=1  abs=''  ord=''
10 type=Tbl   w=40 align=L brk=0  abs=''  ord=''
11 type=Text  w=40 align=L brk=0  abs=''  ord=''  text='Subtotal'
12 type=Data  w=40 align=R brk=1  abs=''  ord=''
```

`abscissa` and `ordinate` are empty on every one, and `BuildSeq()` never
reads them. Layout comes from three other things: sequence, `align`, and
`rowbreak`. Each output line is a 40-character string that successive
fields OVERLAY through `formatReceipt()` — left-pad, right-pad or centre
against the line built so far — until a field carrying `rowbreak=1`
flushes it. That is how "Subtotal" (align L) and its amount (align R)
occupy one line: two fields overlaid on the same 40 cells, not two boxes
at two positions.

So dragging boxes cannot express a receipt, and a canvas would have
nothing true to write back. The converter's "everything at 0,0" is not a
bug in the conversion — it is a faithful reading of coordinates that were
never there.

**The right editor, when it is built, is a row/cell grid** — rows in
order, each listing its overlaid cells with type, width, alignment and
binding, beside a live monospace preview of the actual 40-column output.
That would be better than PhreeForm's own serial editor, which is the
same field grid it uses for PDF forms and still shows X and Y columns
that do nothing. Building it needs two pieces that do not exist: a serial
renderer (character-cell output — `formatReceipt()` plus the
overlay/flush loop) and a serial layout model to replace `Template`'s
positioned boxes. The data side is already done; `pos_provider` serves
the receipt today. **This is required before Forms is finished**, because
PhreeForm is to be retired — it is not optional polish. Until it lands,
PhreeForm's designer edits the receipt, as it always has.

**What the guard is.** Five pieces, all small:

- `Definition::$serial` and `isSerial()` — the flag the format needs;
- `DefinitionBuilder::form()` reads `serialform` instead of dropping it,
  which also makes the conversion lossless;
- `DefinitionCodec` round-trips it. Emitted only when true, so not one
  stored byte of the 61 non-receipt definitions changes and the
  byte-identity check still passes;
- `FormRunner::planDefinition()` **and** `plan()` refuse a serial form,
  before the provider lookup;
- the canvas refuses to open one, `FORMS_CANVAS_IS_SERIAL`.

The canvas refusal is at LOAD, which is what makes it a guard rather than
a warning: the save branch is gated on the definition being non-null, so
one that never loads can never be written back. That mattered more here
than for a report — saving would have replaced the only working copy of a
receipt with a native file NEITHER engine can print, Forms having no
serial renderer and PhreeForm being unable to read native at all.

**Both plan entry points, not one.** `planDefinition()` takes a
Definition and `plan()` takes a legacy object, and the diff harness in
`verify_forms_native.php` calls the second. Guarding only the first made
it compare a legacy render against a native one that was no longer
allowed to happen, and it failed — correctly. Worth noting what that
check had been doing beforehand: it ran the receipt down both paths, got
identical bytes, and passed. Both sides were Forms, so both produced the
same everything-at-0,0 page. It proved the two FORMATS agreed, which is
all it ever claimed, but on this one document that agreement was between
two wrong answers.

**The one that would have bitten users.** `gen_print_form_url()` switches
Print buttons on a COMPANY setting, not per document, and `pos:rcpt` is
one of the 11 groups it covers — the POS pages call it with
`POPUP_FORM_TYPE`. So with `MODULE_FORMS_PRINT_BUTTONS` on, the till's
Print button already landed on the Forms print page, which rendered the
0,0 page. Adding the plan guard alone would have turned that from a wrong
receipt into NO receipt, which at a shop counter is worse. The print page
therefore hands a serial form straight back to `phreeform&page=popup_gen`
with the query string it already has — gen_print_form_url() built it, so
it is already in the shape popup_gen expects. The button keeps working,
on the engine that can still do the job. That redirect goes when the
serial renderer lands, and nothing else changes.

**Measured, three companies.** No receipt had already been saved native
anywhere (`serial-already-native=0` on all three), so there was no
pre-existing damage the guard would have hidden — worth checking first,
because the guard makes that state invisible rather than repairing it.
`demo` and `production` each carry one serial form, now planned unrunnable
with its reason and round-tripping the flag; a third, bare installation has
no POS module and so no receipt at all. Full verifier suite passes on `demo`
and `production`. The bare one still shows four pre-existing data-gap failures
in `verify_forms_native` and `verify_forms_designer` — confirmed
pre-existing by running the committed code against it and getting the
same four.

**Also found: the deployment build context held a stale, partial copy of
the Forms module** — 51 files against the repository's 74, missing
`classes/definition/` and `pages/canvas/` outright. The Dockerfile is
`COPY . .`, so that context IS the web root, and a deploy would have
shipped a Forms module without the definition layer everything now
requires. Synced. Harmless so far only because Forms is deliberately not
deployed yet; it would not have stayed harmless.

See [[todo_forms_canvas_serial_guard]] and §13.16.

### 13.28 The receipt editor, and a renderer that had to be wrong on purpose

Built 2026-08-18, at the user's instruction: *"Guard now, but add the receipt
editor before we finish the Forms module. PhreeForm will be retired."* §13.27
guarded the receipt; this makes it editable, which is what removes the last
reason PhreeForm has to exist.

**Why it is not a canvas, settled by measurement.** Every field of the shipped
receipt stores an EMPTY `abscissa` and `ordinate`, and `BuildSeq()` never reads
them. Layout is sequence, `align`, and `rowbreak`: each line is a string of the
paper's width that successive fields OVERLAY through `formatReceipt()` — left
pad, right pad, or centre against the line built so far — until a field
carrying `rowbreak` flushes it. "Subtotal" left and its amount right are two
fields written into the same 40 cells, not two boxes at two positions. No
arrangement of draggable boxes can say that, so the editor is a list of lines
and the cells written into each.

**The model composes rather than duplicates.** A receipt's four field kinds ARE
`StaticText`, `Field`, `FieldBlock` and `Table`, with the same bindings,
formats and separators as a page. So `SerialField` pairs an existing `Element`
with a `SerialCell` (width, align, row break), and `SerialTemplate` holds them
in order; `TemplateConverter::convertSerial()` calls the SAME `convertField()`
a page does. Nothing about a binding changes because the thing printing it has
no coordinates, and so nothing about the binding rules is restated. Two
behaviours the legacy applied at PRINT time are recorded in the model instead,
because they belong to the field and not to the run: a `Tbl` always breaks the
row (BuildSeq() used to set `rowbreak = 1` on the definition as it read it),
and a `TBlk` writes the whole line rather than overlaying it.

**The renderer is faithful where the legacy is wrong.** `SerialRenderer`
reproduces `BuildSeq()` including two visible faults: the table APPENDS to the
line under construction instead of flushing it, so a 40-column receipt prints
an 80-character line; and it then chops two characters with `substr($x, 0, -2)`,
eating the last row's final character along with its newline. Both are
deliberate. A definition must print the same before and after the engine
changes under it, or the switch to Forms becomes an unrequested change of
output that nobody can diff. An author who wants the fault gone now has a
place to fix it — and in the editor they can SEE it, which they could not
before. Where it does not copy the legacy is data: `BuildSeq()` pulls each
value with `array_shift($FieldValues)`, positionally, so inserting a field
silently re-points every field after it; values resolve by NAME here.

**Held to the byte.** `verify_forms_serial.php` renders the same point-of-sale
entry down both engines and compares. Measured on `demo`: 658 bytes, identical,
on both of the sales it has. It also asserts the properties an editor lives or
dies by — that a receipt opened and saved unchanged comes back byte-identical
through `SerialForm::toRequest()`/`apply()`, that the codec round-trips every
cell, and that the encoding is stable. `production` has the definition but no
POS module, so it checks the conversion there and says plainly that it compared
nothing.

Two faults the checks found on the way, both worth recording because neither
would have shown up by looking: the record feed is a per-record TERMINATOR
(`$output .= "\n\n\n\n"`, four blank lines that clear the printer's cutter),
not a separator between receipts — treating it as a separator was 4 bytes short
and would have cut the last line off every receipt; and `RenderOptions` carries
a NULL filename rather than an empty string, so the obvious emptiness test named
every download `.txt`.

**The editor.** `pages/receipt`, its own menu entry, sharing
`SECURITY_ID_FORMS_AUTHOR` with the canvas — which KIND of layout a document
has does not change who may change it. It shows the lines, and in each the
cells written into it, with the width, the position and the row break editable;
rows move and delete; literal text is editable in place and can be added. Beside
it, a live preview rendered through the real `SerialRenderer` against a real
sale, under a character ruler — because a cell width of 15 means nothing except
against the column it lands in, and the overlay behaviour is very hard to
predict and very easy to see.

What it deliberately will not do is invent a binding. A new field is literal
text only; adding a field that prints a VALUE needs the dataset machinery the
canvas has, and an editor that offered a half-completed binding would produce a
receipt that prints a blank where a total should be.

**One list, not two menu entries.** The receipt editor has no menu entry of its
own. The Form Layout chooser reads each definition rather than merely listing
catalog rows — a receipt is known by its stored layout and by nothing else,
since `pos:rcpt` is not reserved — marks the receipts it finds, and links them
straight to the receipt editor. Both editors also redirect: the canvas sends a
receipt here, this page sends a page form to the canvas and a report to the
report editor, and a request naming nothing goes to the one list. So a link to
either editor lands on the right one whatever it names, which is what lets the
list link directly without having to be certain, and keeps old links working.
The redirect is still the guard it replaced — it fires before anything reads a
template, and the save branch is gated on a definition being loaded.

Retiring the entry was safe for the reason the report editor's removal already
records: `SECURITY_ID_FORMS_AUTHOR` is still named by the canvas entry, so it
stays grantable on the roles page.

**The preview is live.** It renders the receipt AS EDITED through the same
`SerialForm` the save uses, posted by htmx from the editor's own form, so what
is shown is what would be stored. It matters more here than on any other Forms
page: fields overlay one another, so the effect of a width or an alignment is
genuinely hard to predict, and a preview that waited for a save would make an
author commit to a change in order to find out what it was. Row moves, deletes
and additions change what prints without changing any field's VALUE, so they
fire no `change` event — the row code announces them on a custom event instead,
from the renumbering every mutation already goes through.

Worth knowing what the preview makes visible: ticking "Ends line" on the field
before the items table turns the 80-character line into two 40-character ones.
The fault §13.28 reproduces on purpose is now one an author can see and fix in
about two seconds, which is the whole argument for reproducing it rather than
silently correcting it.

**The wiring, now that a receipt can be printed.** The §13.27 hand-back to
PhreeForm is gone: the print page renders a receipt itself, and both POS ajax
handlers try `forms_render_to_string()` first — which returns the receipt text
under the same `pdf` key, because what those callers do with it is identical.
`FormRunner::plan()`, the legacy-object entry point, still declines a serial
form: it converts to a page `Template`, and a receipt is served from a native
Definition. The canvas still refuses one, and now names the receipt editor
rather than PhreeForm.

**Also fixed here:** `contextFor()` took a Template and read `->elements`, which
a SerialTemplate does not have. It takes the element list now — and the change
caught a second call site that the first pass had missed, through the whole
form suite failing at once rather than quietly.

**What the first real save found**, 2026-08-19, and neither could have been
found without one — both need a definition that has actually been through the
encoder, which nothing but a save produces.

*The decoder gave a saved receipt BOTH layouts.* `template:` was built for every
form on the way back, so a native receipt decoded with an empty page Template
beside its character-cell one — the contradiction `Definition`'s own docblock
rules out. Harmless to print, because the serial layout wins wherever the two
are consulted, but anything asking "what kind of document is this?" by looking
at `$template` got the wrong answer, and the canvas check said so the moment a
saved receipt existed. The decoder now builds one only when there is no serial
layout, and `verify_forms_serial.php` asserts the invariant directly.

*The byte comparison stopped being possible.* A saved receipt is native, so
`get_report_details()` correctly refuses it — and the check that compares the
two engines was reading through that function. It now takes the PhreeForm XML
from wherever it still survives, which after a save is the `.pre-forms` copy the
editor keeps, and renders THAT down both engines. Comparing engine against
engine rather than stored-file against stored-file is what makes the guarantee
survive editing: an author changing their receipt can no longer turn the check
red, and it will keep working after the last legacy receipt has been converted
— which is when a byte guarantee is hardest to re-establish and most worth
still having. A separate check covers what a save actually raises: an unedited
receipt must print the same bytes after being saved as before.

Measured on the one real edit made through the editor — ending the line on the
payment reference, the field before the items table:

```
as shipped   9 |...............12/03/2026...............2..WIDGET-7...........£.9.99....£.19.98|80
as saved     9 |...............12/03/2026...............|40
            10 |2..WIDGET-7...........£.9.99....£.19.98|40
```
(a made-up sale; the figures are the LINE LENGTHS, which is all this shows)

18 of the 19 fields came back byte-identical and one changed, which is the
editor doing exactly what was asked and nothing else. The 80-character line
§13.28 reproduces on purpose is gone from that company's receipt, by a change
its author made and can see.

**And the shipped receipt carries the fix.** A new install should seed a
document that is a good example of one, so `ReceiptSlip.xml` now sets the row
break on the payment reference in both locales it ships in — one line changed
in each. Verified afterwards down BOTH engines and in both locales, because a
new install seeds XML and PhreeForm has to keep reading it: byte-identical
output, and no line wider than the paper.

What that leaves is worth naming, because it is the one artefact a definition
cannot express away. The items table is items followed by the payments that
settled them, and the last row loses its final character to the `substr($x, 0,
-2)` §13.28 reproduces — so the payment row prints as 39 spaces where 40 were
built. It reads as a blank separator before the totals, which is why it has
never been noticed, and it is invisible on the shipped form because payment
rows do not populate the item columns. It is a renderer and provider matter,
not a layout one, and no setting in the receipt editor reaches it.

Existing companies keep their own copy: seeding only affects a new install, and
`demo` has already made this change through the editor.

See [[todo_forms_canvas_serial_guard]], §13.16 and §13.27.

### 13.29 New, Copy, Rename and Delete — the last thing PhreeForm could do

Built 2026-08-19, closing [[todo_forms_create_delete]]. Both editors opened an
existing `rID` and nothing else, so the route to a new form was "copy one in
PhreeForm, edit the copy in Forms". A report engine that cannot add a report is
not a replacement, which is why this was a step 6 blocker rather than a
convenience.

**One class, not four page actions.** `DocumentStore` owns all of it, because a
document is TWO things that must agree — a catalogue row and a definition file
— and every operation touches both. Split across the pages that offer the
buttons, you get a row with no file (a document that lists and then cannot be
opened) or a file with no row (a document nobody can see and nobody deletes).

Ordering is chosen so a failure leaves the catalogue consistent. A write inserts
the row first because the FILENAME IS THE ID, and removes that row again if the
write fails. A delete removes the row first: of the two inconsistent states, a
file with no row is the one you can still find, because its name tells you which
document it was.

**What each does.** *New* is genuinely empty — a report with no columns, a form
with no elements. An author who wants something like an existing document should
copy it, and a "new" that guessed at content would only be a worse copy. *Copy*
is the valuable one: starting from the invoice that is nearly right is the normal
case. It writes the copy natively whatever the original was, exactly as the first
save of either editor does, and leaves the original untouched so PhreeForm can
still print it. *Rename* writes both places the name lives — the row every list
shows and the definition every printed document shows — because a rename that
moved one of them is how a report comes to be called two different things
depending on where you look.

**Renaming a form needed the canvas changed.** Its title was posted back as a
hidden field, which was correct when the canvas had no way to rename anything.
It is a text field now; `TemplateForm::apply()` had always read the key and
simply never saw a different value.

**Four refusals, and one warning.**

- A **duplicate title** is refused, and this is not tidiness. Several things
  resolve a document BY TITLE — `ImportReport()` upserts on it, and the invoice
  a payment link attaches is chosen with `order by doc_title limit 1` (§13.26).
  Two of one name makes which they find a matter of collation. All three editors
  now rename through the store rather than writing `doc_title` themselves, so
  none of them can create one.
- A **document a setting names** is refused, and the refusal names the setting.
  This is the §13.26 failure mode again: `ZENCART_VAT_INVOICE_FORM` holds a form
  id, and deleting that form leaves the setting pointing at nothing — which does
  not fail loudly, it silently stops emailing invoices.
- A **folder** is refused; deleting one here would orphan everything filed under
  it rather than delete it.
- A **name longer than the column** is refused rather than cut by the database
  into something its author did not choose.
- Deleting the **last document in its group** is allowed but says so first. The
  thing that breaks is a Print button on another page entirely, and nothing
  afterwards would connect the two — so it is said while there is still a
  decision to make. The list works it out per row while the row still exists,
  because after the delete there is nothing left to ask.

**Reached the FreeBooks way**, at the user's correction: New is an icon on the
TOOLBAR, not a panel under the list. The three answers it needs — name, folder,
and the dataset it is built on — are collected first, in a Bootstrap modal,
because all three are properties of the DOCUMENT rather than of its layout: an
editor that opened first would have to ask for them afterwards or guess. On the
canvas the icon appears only on the list, never with a form open, where a New
beside Save would read as "new element" on a page whose whole subject is
elements.

**Levels.** Level 3 writes a document, level 4 removes one: deleting is the only
action here that cannot be undone from inside Forms. The Reports page validates
on the RUN key, so the handler is passed the AUTHOR level explicitly rather than
the one that page happens to hold — decision F, and easy to get wrong precisely
because the page already has a `$security_level` in scope.

**A `.pre-forms` copy goes with its document.** It exists to make the first save
reversible, not to outlive what it belongs to; left behind, the next document
given that id would inherit it as its own history.

**Two faults the checks caught**, both the kind that reads as working software:

*The setting guard matched too loosely.* `FORM|DOCUMENT|REPORT|…` anywhere in a
key caught `MODULE_FORMS_PRINT_BUTTONS`, a boolean whose value of `1` happens to
equal a document id — so every company would have refused to delete document 1
for a reason making no sense. The pattern is anchored at the END now: a key that
NAMES a document ends in what it names. The verifier reads the store's own
constant rather than restating it, because a check that restates the rule passes
whenever the two drift together.

*A title broke out of its own attribute.* `json_encode()` alone emits the double
quotes that end `onclick="…"`, so every Copy button on a company whose documents
have ordinary names was truncated. `verify_forms_pages.php` has a check named
exactly that — "no inline handler was truncated by its own quoting" — and it
fired on the first render.

**Two faults that rendered perfectly and did nothing**, found by clicking and
not by any tool:

*The canvas's script is a `type="module"`.* A function declared inside a module
is scoped to it, so `onclick="formsDocNew()"` resolved to nothing — and an
inline handler naming an undefined function is only an error when it is
clicked, so the page looked right.

*And that file returns early when no form is open* — which is exactly and only
the state the New, Copy and Delete buttons appear in. So even with the scope
fixed, the handler was never emitted on the one page that needed it.

Both are gone rather than patched around: the buttons carry `data-fb-doc` and a
single DELEGATED listener handles them, from a classic `<script>` block emitted
before the early return. Nothing has to be global, and a title travels as a data
attribute — escaped once, rather than surviving `json_encode()` inside an HTML
attribute, which had already broken every Copy button once.

The check the suite lacked is the general one: **every trigger in a page must be
reachable by that page's own script.** It is asserted now for both lists, along
with the handler preceding any early return. Worth recording that the check's
own first version was wrong in the same family — it matched a `return;` inside
the JavaScript — so it strips script blocks before looking for a PHP one.

`verify_forms_documents.php` is the first tool in the suite that MUTATES the
catalogue, so it brackets its run by id and removes everything above the mark in
a `finally`, the discipline `verify_paper_seeding.php` already uses and an
ad-hoc script once failed to (§13.26). It works against the real catalogue
rather than a scratch copy on purpose: what it tests is whether a row and a file
stay in step, and a scratch directory with no table behind it cannot answer that.

See [[todo_forms_create_delete]] and §13.7 step 6.

### 13.30 The other four element kinds — what a new form could not become

`TemplateForm::ADDABLE` held five kinds: text, a value, a line, a box and an
image. The four it did not hold were `FieldBlock`, `Table`, `PageNumber` and
`Barcode`, and the reason was written into the class:

> A Table created here would arrive with no columns and a FieldBlock with no
> parts, and neither could be repaired on that page — so they are not offered.

That was a sound rule and it had an expiry date. It says *these are the kinds
the canvas cannot COMPLETE*, not *these are the unimportant ones* — and an image
had already left the list, the moment §13.13 gave the canvas somewhere to put a
file. The other four leave for the same reason and no other.

**What the gap actually cost.** The question that prompted this was whether a
newly created Form Layout could be given every element a form might use, and the
honest answer was no — in the way that mattered most. A blank form could be
given text, bound values, rules, boxes and a logo. It could not be given **line
items** or a **customer address block**. Those are not decoration; they are what
distinguishes an invoice from a letterhead. So §13.29's New button, which had
just made creating a document a first-class action, opened onto a canvas that
could not carry the creation through, and **Copy** remained the only route to a
substantive form. New promised more than it delivered.

Worse for the existing set: `rebuild()` carried a Table's columns and a
FieldBlock's parts across untouched, which meant that even on a form that HAD
them, they could be moved and resized but never edited. A shipped invoice's
column headings were unreachable from the editor that draws that invoice.

#### The rule that replaced "carry every structure"

The blanket carry existed to stop the editor deleting what it could not draw —
the classic way this kind of editor loses things, silently. Editing the
structures means each one is now exposed to exactly that failure, so the
protection is kept in a narrower form, **per key**:

> An **absent** key is carried. A **present** one is rebuilt.

A post that says nothing about the columns is not a post that says none of them.
A blank is treated as absent too, because a blank is not an instruction and a
browser that fails to fill the field sends one; the deliberate empty is written
as `[]`, which is what makes deleting the last column possible while an
accidental blank stays harmless. An unreadable value is carried and reported.

#### One field, not a nested array

A column list travels as JSON in a single canvas value — `element[i][columns]` —
rather than as `element[i][column][j][bind]`. The canvas writes its hidden fields
from a flat object and the live preview POSTs the same object through FormData;
a nested shape would mean teaching both of those, and undo's snapshot, to walk a
tree. Opaque to the transport, structured at both ends — the same trade the
report editor already makes for a filter expression.

#### The gate that is not a validation nicety

A `Binding` carrying `Scope::Context` resolves through `FormRunner::contextFor()`,
which is:

```php
if (defined($name)) $values[$name] = TypedValue::text((string)constant($name));
```

**Every PHP constant in the process** — `configure.php`'s `DB_SERVER_PASSWORD`
among them. A company block's parts ARE context bindings (PhreeForm's `CBlk`), so
a parts editor that let an author name one would hand back precisely the
database-wide read primitive §1.1 records as PhreeForm's defect, on the very page
built to remove it. An author would put the database password on an invoice.

So an author never *names* a non-record binding. They may *keep* one, which is
what lets a company block be reordered and rejoined. The test is membership of
what the element already had, scope and name together:

1. **Was it already here?** Handed back as ITSELF, with whatever `sumOf` it
   carries. This is `font()`'s rule — *what is already in the definition survives
   a save* — and it is checked first for that reason.
2. **Otherwise, is it a record value this form supplies?** The ordinary palette
   check.

Test 1 is not a courtesy. Written with only test 2, the first run emptied the
company block on three shipped documents: eight parts to zero. `forms_binding_names()`
had never collected FieldBlock part names, because parts were carried past the
canvas and so nothing had ever asked it about them. Both were fixed — the
function now offers them, and the binder no longer depends on it doing so.

#### Two smaller repairs, and one deliberate omission

- A column that HAD a style keeps one even when every setting in it is blank.
  `TemplateConverter::style()` returns a Style unconditionally, so every column
  converted from a PhreeForm table carries one and most carry nothing but
  defaults. Letting those collapse to null would have rewritten the stored file
  of every shipped form on its first save — a diff on 14 documents nobody asked
  for, which §13.24's byte-identity check would then report as drift.
- A separator outside `Formatter::separator()`'s vocabulary is refused rather
  than accepted. That method falls through its `default` on a name it does not
  know and joins with NOTHING, which reads as an address block that has lost its
  line breaks. The editor's list and the renderer's `match` are now held
  together by the suite.
- A form table column's `total` flag is carried and **not** offered. No renderer
  reads it: an Aggregate binding sums by field name across the line items and
  never consults the column list. A tick box for it would be a control that does
  nothing. It is carried because the format can express it.

#### A page number and a barcode

Nearly free, and worth having. A page number's whole content is one pattern, so
it is edited in place. A barcode gains a symbology picker — every converted form
uses `C128` because that is what `TemplateConverter` hardcodes, so this is a
setting the old format could not express at all. It is gated: TCPDF throws on a
type it does not know, from inside `write1DBarcode()` with the document
part-drawn, so an unknown name is refused where it can be reported. A symbology
already in a definition is kept regardless, for the font rule's reason.

Unlike a table and a block, a barcode with no binding is **refused** rather than
created blank. An empty table can be repaired in the grid beside it; an element
bound to nothing cannot be told apart, in the element list, from one whose
binding was lost.

#### What the suite gained

`verify_forms_canvas.php` now assembles a form from **nothing but the four kinds
that used to be unreachable**, stores it, reloads it and prints it — which is the
question the change was made to answer, asked directly. It also checks the carry
rule in all four of its states, the separator vocabulary against the renderer,
and both halves of the constant gate. The gate's checks were confirmed
non-vacuous by reinstating the fault: with the scope test removed, the assembled
form prints `DB_SERVER_PASSWORD`.

`verify_forms_pages.php` gained the check §13.29's afternoon deserved and did not
get: **every id the page script looks up must be one the page can emit.** A
`getElementById` that finds nothing returns null silently — the page renders, the
console stays quiet, and the control is simply dead, which is exactly how "none
of the new buttons do anything" happened. The comparison is against the page's
SOURCE as well as its output, because one render is one branch and the canvas
draws either the list or the editor, never both.

See [[todo_forms_element_kinds]] and §13.7 step 6.

### 13.31 The format box, which was free text over a closed vocabulary

The **Format** control — on a report's columns, a form table's columns and an
address block's lines — was a text input. It had been one since §13.4, copied
from the report editor to the two grids §13.30 added, and the question that
ended it was simply *why?*

`Formatter::format()` ends in `default => $this->plain($value)`. There is no
extension point on the class: the `pf_process_{module}` hook that made
PhreeForm's list open is PhreeForm's, and Forms does not call it — the
references to it inside the module are comments *about* the legacy engine.
So the vocabulary is **closed at thirty names**, fixed at compile time by that
`match`, and anything else is a guaranteed no-op.

That makes free text the wrong shape twice over. A misspelling and a decision
look identical — `posted_curr` prints the value unformatted, exactly as leaving
the box empty would, with no warning and nothing in any log. And a stored name
is not a label: `posted_cur` is something an author has to already know, so the
box could only be used by someone who did not need it.

`Formatter::CHOOSABLE` now names the 25 that can be honoured, grouped as the
editors present them, with labels in the language file. The remaining five are
`RESOLUTION_HINTS`, deliberately not offered: **a list is an offer**, and each
of those needs a database lookup the provider must have performed before the
value reaches the renderer (§4.3.1), so choosing `rep_id` would promise a
salesperson's name and deliver a number.

Not offered is not the same as not allowed. Whatever a definition already
carries is appended to the list and marked *already in use* — the rule the font
list has kept since §13.7 step 4 and the symbology list since §13.30, for the
same reason each time: **a select with no option for what is stored clears it on
the first save of a document nobody meant to change.** That covers the five
above and anything inherited from a PhreeForm install with its own hooks.

One helper serves all three controls. A format means the same thing in a report
as on a form, and two lists that could drift apart would be two lists to keep
right. `html_pull_down_menu()` could not draw it — it emits a flat option list
and twenty-five entries in one flat list is a list nobody reads to the end of —
so the markup is hand-written, as the separator column already was.

#### What the suite gained

The chain now has four links and all of them are checked, because none of them
fails loudly: **performed → offered → labelled → documented.**

- A format performed but not offered is unreachable.
- A format offered but not performed does nothing when chosen.
- An option with no label is a blank row nobody can report.
- A format in neither the offer nor the manual cannot be learned at all.

`verify_forms_formatter.php` holds the first three and `CHOOSABLE` to being
exactly what the `match` performs less the resolution hints, so adding an arm
without adding it to the list is a failure rather than a format nobody can
reach. `verify_forms_pages.php` checks the rendered markup — that no page has
quietly gone back to an `<input>`, that the groups are there, and that the empty
option is offered.

Writing the manual page first is what found `null-dlr` missing from it: the one
format whose name breaks the pattern its neighbours keep, a hyphen where
`null_pcur` and `null_dcur` take underscores, and so the one an author was least
likely to guess. That is the argument for the documentation check in a sentence.

See [[todo_forms_element_kinds]] and §13.7 step 6.

### 13.32 The migration, and what it revealed about the suite

`docs/tools/migrate_forms_definitions.php` converts a company's definitions from
PhreeForm XML to native, in bulk. Decisions I, J and K, with one refinement that
the survey forced.

It reports and changes **nothing** without `--write`, keeps every original beside
its replacement as `pf_N.pre-forms`, and has a `--rollback` that copies them
back. Nothing deletes an original, so a migration is reversible indefinitely.

#### Decision J, refined: three outcomes rather than two

J says the migration blocks on any unconvertible definition rather than skipping
it, because after PhreeForm is deleted nothing can read what was skipped. That
is right, and the survey found a case it does not cover: **one company had 56
catalogue rows and no `pf_` files at all.**

A missing file is unreadable by *both* engines already. Blocking on it would
make that company permanently un-upgradeable for a fault the migration neither
caused nor can repair, and deleting PhreeForm would lose nothing that is not
already lost. So there are three outcomes: **convertible**, **blocked** (the file
is there and cannot be converted — J exactly), and **already lost** (reported by
id, loudly, and not a blocker).

That company also had no Forms module installed, so registering custom datasets
fataled the tool that was supposed to be telling the operator what state it was
in. Conversion needs the shipped datasets and the loader, both of which are code;
the module's tables are not required, and the tool now says so and continues.

#### The bar the migration holds, and the one it does not

Every definition is converted, encoded, decoded and encoded again, and the two
encodings must match byte for byte. That is **total** — it needs no data, so it
covers every definition on every company — and it proves what the migration is
responsible for: the file written back holds exactly what the conversion
produced.

It does **not** prove the converted definition prints the same document. That
needs records, so it can only cover what a company has data for, and it already
lives in `verify_forms_native.php`. The tool says so rather than implying a
guarantee it has not checked.

#### What migrating actually broke: the suite went blind

Migrating `demo` converted 52 definitions and turned three verifiers red —
`verify_forms_native`, `verify_forms_forms` and `verify_forms_designer`. Not a
regression in the code. All three read the legacy side through
`get_report_details()`, which refuses a native definition **by design** (§13.9),
so on a migrated company they had nothing to read:

```
[FAIL] 0 of 0 rendered definition(s) are byte-identical
[FAIL] 0 form(s) ran and rendered as PDF
[FAIL] the stored original is PhreeForm XML to begin with
```

**This had already happened and been misdiagnosed.** The same three had been
failing on the freshly-seeded company for some time, and were written off as
"that company has no data". Half true — it has none — but the actual cause was
that it was seeded native and there was no legacy side at all. Two causes wearing
one symptom, and the wrong one was believed because it was plausible.

The fix was a pattern the suite had already invented and not generalised:
`verify_forms_serial.php` compares engines by reading the PhreeForm original out
of `pf_N.pre-forms` when the stored file is native. `legacyOriginal()` now lives
in `bootstrap_freebooks.php` and all three use it, so the comparison **survives
migration** — and covers more than before, because it now includes definitions
that were already native with an original kept. On `demo`: 52 compared before,
56 after.

Two smaller corrections came with it, both of the same kind:

- *Nothing compared* is a failure only when something could have been. With no
  data there was never a comparison to make, so the tool now says that in words
  instead of reporting a red run on a correct installation. A suite that goes red
  on a healthy company is a suite people learn to skim.
- The check *"the stored files still sniff as legacy"* asserted a state step 6
  exists to end. What has to hold permanently is narrower: **the sniff must
  discriminate** — every original reads as legacy, every stored file reads as
  what it actually is, cross-checked against a real `json_decode` rather than
  against the one-byte sniff alone.

#### The rollback restored too much, and cost five documents

The first `--rollback` restored **every** `pf_N.pre-forms` it could find. That
is not the set the migration wrote, and the difference is the whole safety of
the thing.

A definition saved in the Forms editor also has a `.pre-forms` — its PhreeForm
original, kept by the canvas since §13.3. On disk it is indistinguishable from a
migrated one: native file, XML original beside it. Restoring it does not undo a
migration; it discards whatever the author did in the editor, and overwrites the
only copy of it.

Run once against `demo` to prove the safety net worked, it reverted five
documents that had been edited that way. They are not recoverable. A sixth,
created natively through **New**, was unharmed for an incidental reason worth
knowing: the canvas's keep-original copies the *native* file as `.pre-forms`
when there was never a PhreeForm original, so restoring it was a no-op.

`--write` now leaves a manifest naming exactly what it converted, and
`--rollback` restores only those. With no manifest it **refuses** — the
originals are still there for a deliberate manual restore, which is the right
way round for something that cannot be undone. `demo` was migrated by the
pre-fix tool, so its manifest was reconstructed with the browser-saved Invoice
explicitly excluded.

`verify_forms_migration.php` pins it, and pins it against the real tool rather
than a re-implementation: it copies the report directory aside, builds the
fixture the incident needed — some definitions put back to XML, two left native
with an original beside them — runs the migration and the rollback for real, and
restores the directory from a shutdown function so a fatal cannot leave a
company half-converted. Reinstating the unscoped rollback fails it by name:

```
[FAIL] and left every definition it did not convert exactly as it was
       — reverted an editor save: pf_34, pf_35, pf_42, ...
```

The general lesson is not about manifests. **A tool that undoes an operation
must know which operation it is undoing**, and "restore every backup file I can
see" is a different and much larger action wearing the same name.

#### Still to do when PhreeForm goes

`verify_forms_forms` exercises `plan()`/`run()` over a legacy object — the route
step 6 deletes. Reading the kept originals keeps it working today and shrinks its
coverage to nothing on a company that never had them, which is every fresh
install and, after step 6, every company. It has to be repointed at
`planDefinition()`/`runDefinition()`. `verify_forms_designer`'s "saving keeps the
original, once" section tests a first conversion, which after step 6 is history;
it can retire with the loader branch it tests.

The suite now passes on all three states a company can be in — un-migrated,
migrated, and seeded native — which is the property that makes step 6 something
that can be done one company at a time rather than all at once.

### 13.33 Forms could not be left standing when PhreeForm went

Step 6 was written as a deletion: convert the definitions, then remove the
module. Checking what actually breaks when it goes turned that order round.

**Forms is not self-contained.** It reads PhreeForm's constants, calls
PhreeForm's functions, and every page bootstraps the module it replaces before
it can find its own files. Deleting PhreeForm first would take the loader, the
canvas, the print page, the datasets page and the migration tool with it — and,
worst of the lot, `legacyOriginal()`, which is how a `.pre-forms` original is
read. The rollback safety net would go the same day the thing it protects
against becomes possible.

So the deletion is last, and what comes first is moving each dependency across.

#### Counting them, rather than remembering them

A dependency here does not look like one. `PF_DEFAULT_ROWSPACE` in a renderer
looks like any other constant; `value_to_words_en_us()` looks like a helper;
`$GLOBALS['Fonts']` looks like a lookup. Searching the module for `phreeform`
finds the ten `require` lines and none of those, so the day the module goes each
becomes a fatal, one page at a time, found by whoever prints something.

`verify_forms_standalone.php` answers it by reading the vocabulary **out of
PhreeForm** — every constant its files define, every runtime setting its
`install.php` seeds, every function declared at file scope, every global its
`defaults.php` assigns — and then looking for those names in Forms, through the
tokeniser so that the module's own prose about PhreeForm does not count as a use
of it. What comes back is a list of 18, each with the reason it is still there.

Three narrowings decide whether that list is readable, and the first draft got
all three wrong:

- **PhreeForm's own files only.** `includes/fpdf/` is vendored FPDF, so
  scanning it made `Cell()` and `AddPage()` look like PhreeForm's — and every
  PDF renderer in Forms calls those, on a TCPDF object.
- **Column zero only.** `function` at an indent is a *method*, and method names
  are the commonest words in the codebase: `install`, `output`, `notes`, `list`.
- **Declared nowhere else.** A name core or another module also defines is not
  PhreeForm's to lend. `PAGE_TITLE` and the shared `TEXT_*` strings go out here.

Unnarrowed it reported 73 borrowings, most of them noise, which is the failure
mode that matters: a list nobody believes is a list nobody shrinks.

**Both directions fail.** A borrowing not on the list is a new coupling. A name
on the list Forms no longer uses is a stale entry, and the list has to shrink to
stay honest — which is what stops it quietly describing last month's module.
When it is empty, PhreeForm can be deleted without touching Forms at all.

#### The definitions directory

`PF_DIR_MY_REPORTS` was the first across, and the one that made the rest
visible: it was named in the loader, the canvas, the print page, three ajax
handlers and nine tools. `FORMS_DIR_DEFINITIONS` and `FORMS_DIR_IMAGES` now
carry it, from `forms/defaults.php`.

The **path does not change**. `pf_<id>` under `my_files/<company>/phreeform/` is
wired into the backup, the restore, and every definition file already on disk;
moving 57 files so a folder reads better is a migration with nothing to gain and
one an older backup would silently undo. What changes is who owns the constant.

One trap worth naming, because it is the kind that makes a harness weaker
without making it red. `bootstrap_freebooks.php` is where a CLI tool does what
`index.php` does for a page, so the new `require` belongs there — but
`verify_forms_pages.php` boots with that bootstrap deliberately **off**, so that
a page failing to load its own defaults has nothing to fall back on. Supplying a
constant the page is supposed to supply is exactly what let an empty chooser
pass once already. The require went inside the flag, not above it.

#### The fonts

`forms_font_names()` read `$GLOBALS['Fonts']`, which PhreeForm's `defaults.php`
assigns. Forms now names the four faces itself, with `FORMS_FONT_*` labels in
its own language file and `FORMS_DEFAULT_FONT` in its own defaults.

**The PDF_APP branch does not survive the move**, and that is the whole content
of the change. PhreeForm builds its list as three faces, adding `freeserif` and
setting `PDF_DEFAULT_FONT` only when the `PDF_APP` setting says `TCPDF`. Forms
has no such choice to make: every renderer extends TCPDF unconditionally, FPDF
was never an option for it, and a setting with one possible value is a question
nobody should be asked. Carried across, that condition would have meant an
editor that could not offer the face it draws with, for a reason belonging to
another engine.

The old fallbacks said `helvetica`, which disagreed with what
`PDF_DEFAULT_FONT` actually holds on every real install. They now say
`freeserif`, so the fallback and the value agree.

**Five files stopped requiring PhreeForm at all** — the canvas, the receipt
editor, the datasets page and two ajax handlers. Each had pulled its bootstrap
for the font table or the directory, and neither is there any more. What they
still take from the module is `TABLE_PHREEFORM`, which comes from its
`config.php` by way of `application_top`, not from `defaults.php`.

#### TCPDF's Error() dies with status zero

The check that every offered face is one TCPDF actually has runs in a **child
process**, and finding out why is worth more than the check.

`TCPDF::Error()` prints `Could not include font definition file` and `die()`s.
It does not throw, so there is nothing to catch: asking the question in-process
takes the rest of the tool down with it. That is also the answer to why the list
exists — an unknown face is not a fallback, it is a dead page found by whoever
prints the invoice.

Worse, `die()` exits with **status 0**. A verifier that dies inside TCPDF looks,
to anything checking only the exit status, exactly like a verifier that
finished. The three font checks are therefore placed *before* the first render
in `verify_forms_canvas.php`, where they fail as checks rather than as silence —
setting `FORMS_DEFAULT_FONT` to a face TCPDF lacks killed the run at the first
drawn box when they sat further down. A suite runner must require each tool's
own terminal marker, not its exit status.

Three faults reinstated and caught: a face TCPDF does not have, a default face
absent from the list, and a face whose label constant was deleted.

#### The reader

`get_report_details()` is PhreeForm's, and reading a PhreeForm definition does
**not** stop being necessary when PhreeForm goes. Decision L keeps the converter
as an **Import** feature — for the definition that turns up on a USB stick two
years from now — and `.pre-forms` originals stay on disk as the record of what a
migrated document used to be. So the reader has to outlive the engine.

`FreeBooks\Forms\Legacy\LegacyReader` is that reader. It was also overdue for a
second reason: **the same twelve lines had been written four times** — in
`get_report_details()`, in `DefinitionLoader`, in `legacyOriginal()` and in
`verify_forms_serial.php`. Four copies of a normalisation is four chances for a
converter to be handed a shape it does not expect, and the failure is not an
exception but a form that renders with one table instead of two.

**`xml_to_object()` is wrapped, not replaced.** It lives in
`includes/common_functions.php` — core, not PhreeForm — so it survives step 6
and there is nothing to rescue. Copying ninety lines of hand-rolled parser into
Forms to own it would add a second parser that has to agree with the first about
CDATA, self-closing tags and attributes, and would be tested by nothing. What
Forms needed to own is the *shape*.

There is no separate test for a native file, and there does not need to be one:
`xml_to_object()` hands JSON straight back as a **string** rather than failing,
so `is_object()` catches it. That is worth knowing rather than guarding against,
because it is also the trap — a caller that skips the test gets a string where
an object is expected and dies on "must be of type object, string given", from a
file that is perfectly valid and simply not this engine's. The `?object` return
type makes passing one on impossible.

#### Two more tools had gone blind, and nobody had noticed

§13.32 recorded that migrating a company silently emptied parts of the suite.
Repointing the readers found two the migration commit had missed, both reporting
success while checking nothing:

- `verify_forms_dataset_map` resolves a PhreeForm table list to a dataset. A
  migrated definition has no table list — it names its dataset outright — so
  `get_report_details()` refused all 57 and the tool printed
  **"0 proven mapping(s) checked, 0 failed"** and `PASS`. Through the kept
  original it now checks **33**, with 14 structural and 10 not yet proven.
- `verify_forms_vendor_sku` identifies its definition by the table it joins, so
  it found nothing and skipped with `[--] no form joins …` — on the one company
  that has that customised form. It now compares **40 records and 797 lines**.

The lesson from §13.32 generalises further than it was written: **a check that
reads the stored file measures nothing once the file is native.** Any tool that
wants the PhreeForm side must go through `legacyOriginal()`.

#### Proving the move changed nothing, while that is still possible

`verify_forms_legacy_reader.php` compares `LegacyReader::parse()` with
`get_report_details()` over every definition both can read — 57 on the demo
company, agreeing exactly.

That comparison has a **short window**: only while PhreeForm is installed is
there a second implementation to compare against. After step 6 the only evidence
the reader is right would be that documents still print, which is evidence found
by a customer. So the tool is in two halves with different lifetimes — the
comparison dies with `get_report_details()` and should be deleted rather than
left to pass vacuously, and the shape checks, built from constructed XML with no
company and no database, stay.

On a migrated company the comparison would be 0 of 0, so the fixture is built:
the report directory is copied aside, kept originals are put back as `pf_<id>`
so both readers can see them, and it is restored from a shutdown function so
that happens on a fatal as well as on success — the same machinery, for the same
reason, as `verify_forms_migration.php`.

Three faults reinstated and caught: the list normalisation removed (31 of 57
definitions then differ, and a one-entry list comes back as an object), a native
file handed back as a string, and `original()` no longer looking beside the
stored file.

#### `.pre-forms` did not always hold a PhreeForm original

Found while deciding what `LegacyReader::original()` should do when the kept
file is not XML: on one company **fourteen `.pre-forms` files contain native
JSON**. `forms_design_keep_original()` and its two siblings copied the stored
file on first save *whatever format it was in*, so saving an already-native
definition wrote a copy of it under a name that says PhreeForm XML.

Nothing was reading them — `LegacyReader::original()` answers null for one, and
`verify_forms_native`'s "every PhreeForm original sniffs as legacy" only looks
at definitions it converted, which on a company seeded native is none. But a
file whose name lies about its contents is a trap laid for the next tool, and
the next tool is Import.

All three functions now decline when the stored file is already native, and the
reader is held to refusing a native kept file. The fourteen existing ones are
left alone: they are a pre-edit snapshot of somebody's document, and deleting a
company's files to tidy a name is not a verifier's business.

A pleasant consequence: after step 6 every save is on a native definition, so
with the guard `.pre-forms` is never written again. The file becomes purely
historical, which is what §13.3 condition 2 always meant it to be.

It also decided a predicate. `verify_forms_legacy_reader` skips its comparison
on a company that holds no PhreeForm original — a fact about the company, not a
fault — and it has to ask that of the CONTENTS. Asking it of the filename
counted those fourteen and turned "nothing to compare here" into a failure no
change to the reader could ever have fixed.

#### The verifier that made three companies read-only

Found by a browser pass, not by the suite, and reported as:

> Report not saved: The definition 'pf_91' could not be written.

`verify_forms_legacy_reader` copies the report directory aside and puts it back,
so that a destructive test cannot lose anything. It runs under `docker exec`,
which is **root** — so removing and recopying the directory recreated every
definition, and the directory itself, owned by `root:root`. Apache runs as
`www-data` and could no longer write. Three companies at once.

The tool passed. From where it stood nothing was wrong: it could read
everything, the bytes matched, and it printed *"the report directory was
restored exactly as it was found"*. A verifier proving the definitions were
untouched, by making them unwritable — and `verify_forms_migration.php` had
carried the identical flaw since it was written.

**Bytes are not the whole of a file.** A restore that compares content and
ignores mode and owner has not restored anything; it has replaced a company's
definitions with copies that happen to read the same.

Two fixes, and the second is the one that matters:

- `fb_snapshot_tree()` / `fb_restore_tree()` record mode and owner per path
  going in and reapply them coming out, and write loudly to stderr if the chown
  fails — rather than leaving a company that looks fine and cannot be edited.
- `fb_bootstrap()` registers `fb_guard_file_ownership()` for **every** tool. At
  shutdown it walks `my_files/<company>/` and puts back any ownership that
  drifted, modelled on the company directory itself — which no tool creates, so
  it still carries what the installation intends. Root only, and only where the
  owner actually differs.

The second exists because this was never one tool's mistake. Fixing the two
verifiers by hand would have left `migrate_forms_definitions.php --write`,
`verify_forms_designer.php`, `adopt_forms_layouts.php`,
`make_forms_proving_templates.php` and every tool not yet written free to lay
the same trap. Anything run as root that writes under `my_files/` does this;
the guard is placed where all of them already pass.

Proved non-vacuous by a probe that writes a definition as root: with the guard
the file is `www-data` after the process exits, without it root.

#### The locale table, and two formats that did nothing at all

`forms_formatter_lookups()` read `$GLOBALS['arrLocales']`, which
`phreeform/config_phreeform.php` sets — a file whose **entire body is commented
out**, and has been throughout this project. So `cvt_iso2` and `cvt_cntry` were
offered by the format list, labelled in the editor, and written up in the
manual, and both tables were empty. Every code fell through `?? $key` and
printed itself. Choosing "Country name, from the code" on a country field did
nothing whatever, silently.

It now reads `gen_pull_countries()` — core, phreedom's `locales.xml`, 239
countries, about a millisecond. `GBR` becomes `GB` and `United Kingdom`; a code
the table does not carry still prints as itself, because a report showing a raw
country code is recoverable and one showing an empty cell is not.

**Two implementations that both do nothing agree perfectly.**
`verify_forms_formatter` compares each hint against PhreeForm's `ProcessData()`,
and it reported `cvt_iso2` as matching for as long as it has existed —
because PhreeForm's hook, `pf_process_phreeform()`, is in the same commented-out
file. Both returned the input unchanged. The comparison was real, the agreement
was real, and it proved nothing. A comparison is only worth making against
something that works, so those two hints moved out of that section and are now
held to `locales.xml` itself.

**The check that was missing.** §13.31 built a chain of *performed → offered →
labelled → documented*, and this defect sat one link below it: a `LOOKUP_HINT`
is a name **plus a table**, and only the name was ever tested. `format()` ends
its lookup in `?? $key`, so an empty table is indistinguishable from a code that
happens not to be listed. Every lookup hint must now have a non-empty table, and
the two country ones must agree with the file they come from. Reinstating the
old empty global fails five checks.

**And the label was wrong**, which nobody could have noticed while the format
was inert: `cvt_iso2` was described as taking a country's *name*. It takes the
three-letter code — that is what the dropdowns store, keyed by `iso3`. The
editor label and the manual row both now say so.

Worth recording for step 6: **PhreeForm's own `cvt_iso2` and `cvt_cntry` are
broken too**, for the same reason and for whoever commented that file out. They
are not being fixed; the module is being deleted, and Forms no longer depends on
anything in that file.

### 13.34 Step 6, part one: Forms stops borrowing

The design doc's step 6 was three bullets. Counted properly — by
`verify_forms_standalone`, §13.33 — it was **eighteen names**, and this pass
takes it to **none**. Forms names nothing PhreeForm declares, and all ten
`gen_pull_language` and `require` lines are gone.

#### Three settings that would have changed meaning quietly

The first draft of this stopped at fifteen and left the three renderer defaults
— `PF_DEFAULT_COLUMN_WIDTH`, `PF_DEFAULT_ROWSPACE`, `PF_DEFAULT_PAPERSIZE` —
read behind `defined()`, on the reasoning that they are `pb_configuration` rows
and an installation that tuned one should keep its layout.

That reasoning holds for an **existing** company and fails for a **fresh
install**, which is the case nobody was looking at. `PF_DEFAULT_PAPERSIZE` is
PhreeForm's from the original 3.7.1 import, but issue #24 built the installer's
paper question on top of it: the form writes that key, and `admin_add_reports()`
stamps it onto every seeded definition. Three of those four links are
PhreeForm's. After step 6 the installer would be writing a key for a module that
no longer exists, `defined()` would be false, and a new company choosing A4
would get its documents seeded on Letter — the exact defect #24 fixed, restored
silently.

So the three are Forms' own configuration keys now, seeded by
`forms/classes/install.php`, and each **inherits** the PhreeForm value where the
installation has one. An admin who answered this question once should not be
asked again by the module taking the question over, and certainly should not
have the answer replaced by a shipped default without being told. The installer
and the Company Manager write `FORMS_DEFAULT_PAPER` alongside the old key.

Paper is stored as the **name**. PhreeForm keeps `"A4:210:297"` because its
designer carried the dimensions along; a native definition stores `A4` and TCPDF
looks the dimensions up itself. `forms_seed_native()` takes either spelling.

A settings key needs a value in two places — the row `install.php` seeds, and a
`defined()` fallback for the company whose row is missing. Two places holding
one number is a smell, and the alternative is a page that dies exactly when a
default is wanted. They are held equal by a check instead, read out of both
files.

**One thing `verify_forms_standalone` cannot see**, recorded so nobody assumes
otherwise: `install.php` holds the `PF_*` names as **strings**, to inherit
through `defined()`/`constant()`. A name in a string is not a dependency on the
constant existing — that code runs correctly either way — but it is also where a
real dependency could hide.

#### The cheque that would have printed a number

`n2wrd` — the amount in words on a cheque — was
`function_exists('value_to_words_en_us') ? value_to_words_en_us($raw) : $raw`.
That reads like caution and is the opposite. After step 6 the function is gone,
the guard is satisfied by the else branch, and every cheque prints the **digits**
where the words should be. Nobody would see a failure; they would see a cheque.

And the check that would have caught it dies in the same commit, because it
compared the hint against `value_to_words_en_us()` — the hint *called* that
function, so it was the same implementation on both sides of the equals sign.

So the algorithm is reproduced in the Formatter exactly: same recursion, same
spacing, the hyphen that appears only when a tens word precedes it, and the same
`TEXT_HUNDERD` misspelling, which is a language key and not a word. Then the two
are compared over 32 amounts chosen for the joins — the teens, the hyphen, a
zero hundreds column inside a larger number, each thousand boundary. They agree
exactly. **That comparison is only available while both exist**, which is the
whole argument for doing it now rather than after.

#### An accident nobody had written down

Dropping the requires broke running a report, and the cause is worth keeping.
`forms_formatter_lookups()` requires `phreebooks/defaults.php`, which builds
`$coa_types_list` out of `COA_*_DESC` — constants in that module's **admin**
language file. Requiring it without pulling that first is a fatal.

It had never had to ask. `phreeform/defaults.php` calls
`gen_pull_language($mod, 'admin')` for every module with a `config_phreeform.php`,
and phreebooks has one; every Forms page required phreeform's bootstrap, so the
language was always already there. Removing the require took the accident with
it, and the first thing to run a report died inside that function.

`verify_forms_standalone` could not have seen this: it is a transitive
*language pull*, not a named symbol. `verify_forms_pages` caught it, because it
boots without that bootstrap and runs the page body — which is exactly the
property it was built for (§13.22).

#### Smaller moves, same shape

- **`TextReplace()`** read `global $report` for `%reportname%`, so it was correct
  only where that global happened to be set and silently wrong elsewhere.
  `forms_fill_tokens()` takes the title as a parameter. Same three tokens, same
  spelling — they are in the stored definitions of every company that has set an
  email message.
- **Paper sizes** are Forms' own, keyed by the NAME a definition stores.
  PhreeForm keys `"A4:210:297"` because its designer carried the dimensions
  along, and the report editor split them off again on every load — a conversion
  happening in a page. No `PDF_APP` branch either, for the same reason as the
  fonts.
- **`TABLE_PHREEFORM`** is now defined by `forms/config.php` as well, guarded.
  The table keeps its name: renaming it is a migration every backup, restore and
  installed extension would have to be taught, in exchange for a word. That
  meant teaching the standalone checker to read a bare `'phreeform'` in context
  — `DB_PREFIX . 'phreeform'` is a table, `gen_pull_language('phreeform')` is a
  dependency.

Faults reinstated and caught: the hyphen dropped from the tens (9 of 32 amounts
differ), the teens built as tens-plus-units (7 differ), and a page pulling
PhreeForm's language again.

### 13.35 Step 6, part two: capturing the oracle

Deleting PhreeForm does not merely remove a module. **It removes the standard
everything has been measured against.**

Forms was built by comparison. `verify_forms_formatter` holds every value format
against `ProcessData()`, every separator against `AddSep()`, every amount in
words against `value_to_words_en_us()` — a couple of hundred assertions whose
entire content is *"the same as the engine we are replacing"*. After step 6
those checks cannot run at all, and the only remaining evidence that a format is
right would be that nobody has complained — evidence found by a customer, on a
printed invoice, months later.

Nothing in the design doc's step 6 said so. It is the prerequisite.

#### Written down before it is gone

`capture_forms_baseline.php` runs the case matrix through PhreeForm and writes
`docs/tools/baselines/formatter.json`, which ships with the repository.
`verify_forms_formatter` is then held to the **file**: *"Forms agrees with
PhreeForm"* becomes *"Forms produces what PhreeForm produced"* — the same
guarantee with the dependency removed. 202 answers, and the tool passes with
PhreeForm's bootstrap switched off, which is the state that matters.

**What stops a golden file rotting.** A baseline is a hostage to whoever
regenerates it: run the capture after breaking something and the bug becomes the
standard. Two guards. While the engine is installed, the check compares the FILE
against the live engine as well as Forms against the file — three-way, so a
stale or doctored baseline is caught by the thing that produced it. And the
capture refuses to overwrite without `--force`, because recapturing is not
casual and after step 6 it is not possible at all.

**One matrix**, in `formatter_cases.php`, shared by capture and check. Two lists
that drift apart give a baseline with entries nothing asks for and questions
nothing has an answer to, and both failures are quiet. The check is driven by
the matrix rather than by the file's keys, so a question that silently drops out
of the matrix is reported rather than simply not asked.

#### Three things the capture had to learn

- **`euro` emits a byte that is not UTF-8.** `chr(128)` is the euro sign in the
  single-byte encoding the old FPDF fonts used — PhreeForm's own comment says
  so. `json_encode()` refuses the whole document for it and returns `false`,
  which is how the first capture failed: no exception, no message, just a file
  that did not appear. Values that are not valid UTF-8 are stored base64 behind
  a marker; everything else stays legible, which is most of what a golden file
  is for.
- **`ProcessData()` is not total.** `neg` does `-$strData` on a string and
  `null-dlr` feeds one to `number_format()`, so a name or a date is a PHP 8
  TypeError rather than a value. Those cases are recorded as `null` — "the old
  one died here" is worth keeping after the old one is gone, and what the check
  then asserts is that Forms does *not* die.
- **Some answers belong to the company.** `dlr` is the same everywhere;
  `def_cur` is not, and neither is anything that reads the loaded language. The
  file records the environment it was taken in and the bound half is compared
  only where that matches, so a difference of company never reads as a
  difference of behaviour.

Faults reinstated and caught: Forms changing what `dlr` prints (4 differences), a
doctored baseline (caught by the live engine, not by Forms), and no baseline at
all.

#### Still holding a knife: the serial renderer

`verify_forms_serial` compares `SerialRenderer` against `BuildSeq()` **byte for
byte, for a real POS sale on a real company**. It is the only check that means
anything about the receipt — the one document Forms renders as text — and it
dies with PhreeForm too.

The formatter's answer does not transfer. That baseline is a table of pure
functions over invented inputs, so it ships in the repository. A receipt
baseline is a rendered sale: customer, SKUs, prices. **It cannot go in the
repository** — everything here is public — and it would not be portable between
companies anyway.

Two options, and this is the open decision:

1. **Snapshot per company, beside the definitions.** Capture what each receipt
   prints on the day PhreeForm is removed, into `my_files/<company>/`, and the
   check becomes "still prints what it printed then". A real regression guard,
   no repo pollution, no private names committed — but it is per installation,
   so a fresh install has no baseline and the check is silent there.
2. **Synthesise a sale.** Forms already has `SampleData` for the canvas
   preview. A shipped receipt definition plus invented lines is portable and
   committable — but `BuildSeq()` reaches into the database for its data, so
   there is no way to make the OLD engine render an invented sale, and a
   baseline the old engine never produced is not an oracle at all.

Option 1 is the only one that captures what the old engine actually did, and it
is what `capture_forms_receipts.php` does — into
`my_files/<company>/forms-baselines/receipts.json`, which is outside version
control. `verify_forms_serial` then reads it: three-way while the engine is
here, two-way after.

Two things are recorded besides the bytes. The **record ids**, because the check
must keep asking about the same sales rather than whichever five are newest — on
a live company that set changes daily, and a recorded answer for a sale nobody
asks about is no baseline. And a **fingerprint of each receipt's layout**,
because a receipt that has since been edited *should* print differently, and
that has to be tellable apart from a renderer that has broken.

Only the demo company has a baseline: neither of the others has a
point-of-sale entry to print, which the tool says rather than inventing one.

#### The dress rehearsal, and what it found

The way to know the suite survives the deletion is to try it. Hiding
`modules/phreeform` in the container and running the checks found two things
that the code reading could not:

- **`bootstrap_freebooks.php` required PhreeForm unconditionally.** Every tool in
  `docs/tools/` boots through it, so the deletion would have been a bootstrap
  fatal for all of them at once — including the ones written to keep working
  without it. Now guarded by `is_file()`.
- **The engine is spread over two files, and half of it loaded is worse than
  none.** `function_exists('BuildForm')` was true while `AddSep()` was not, so
  the check took the live-engine path and died inside PhreeForm. The test is now
  for both.

Faults reinstated and caught: a doctored receipt baseline (caught by the live
engine), and a broken `SerialRenderer` with PhreeForm hidden — which is the
proof that the captured bytes are a real oracle and not a decoration.

A latent one fixed on the way past: neither the capture nor the check cleared
`setprintedfield`, so a company whose receipt carried it would have had its
printed counter incremented by a tool that only meant to look. No shipped
receipt carries it; that is not a reason to leave the door open.

### 13.36 Step 6, part three: the receipt that would not have shipped

Stage C began with the other modules and immediately found something else: a
**fresh install with PhreeForm deleted would have had no till receipt.**

Two of the 61 shipped definitions had no native `.json` beside their `.xml` —
`phreepos`'s `ReceiptSlip`, in both locales. Not an oversight: both the
generator and `verify_forms_shipping_set` enforced it, with this reason —

> A serial form is character-cell text for a receipt printer… Forms has no
> renderer for that kind (§13.16), so a native copy would leave a new install
> unable to print a receipt with EITHER engine.

That was true when it was written. §13.28 built `SerialRenderer` and
`verify_forms_serial` holds it to `BuildSeq()` byte for byte. **The reason died
and the rule outlived it**, which is the dangerous shape: a guard whose
justification has quietly become false still passes its own check, every time,
and reads as deliberate to whoever comes next.

Left in place, the failure would have arrived as a till printing nothing on a
customer's first day.

The exclusion is gone from both, and the check now says the opposite: every
serial form ships a native copy, **and** that copy carries its serial layout —
because a receipt also converts to a page template with every element at 0,0,
which is dead weight and would be what got printed if the serial half were ever
lost. Converting the shipped file produces 19 serial fields and no warnings,
which is the same shape the demo company's own receipt has had since migration.

#### And a deployment gap the check caught

The generated files were written inside the container and not copied back, so
the repository shipped nothing. `verify_forms_shipping_set` says exactly that —
*"no master at all — copy it back out of the container into the repository"* —
which is the case it was written for. The fix was to the deploy script, which
had been syncing a hand-listed set of module directories; it syncs the whole
tree now. Piecemeal deployment is how a generated file gets forgotten.

### 13.37 Step 6, part four: the other modules let go

Stage C is everything outside Forms that still called PhreeForm. Counting it
first mattered as much as here as it did in §13.33, so
`verify_forms_standalone` was widened: it scans a **list of trees**, each with
its own allowlist, and fails in both directions for each. What was one question
about Forms is now a target per module, and "still to do" is a list rather than
a memory.

`modules/payment` and `install` are clear. `modules/phreepos` went from four
bootstraps to one class. What is left everywhere is PhreeForm's own machinery,
which is deleted with the engine rather than rewritten.

#### Fallbacks that could only fire when they could not work

Three call sites rendered a document "through Forms, falling back to
PhreeForm". Every one of those fallbacks was unreachable-or-broken:

- The POS receipt (`save_main`, `print_previous`) fell back when
  `forms_render_to_string()` returned null — which it has not done for a receipt
  since §13.28 gave Forms a serial renderer. And on a migrated company the
  fallback could not have worked anyway: `get_report_details()` refuses a native
  definition, which is what that form becomes the moment the receipt editor
  saves it.
- The PayPal invoice PDF looked its form up **by group on every call**, so which
  format it was in could change under it at any time, and the fallback was
  reached exactly in the case it could not handle.

A fallback that fires only when it cannot work is worse than none: it converts a
clear failure into a silent one.

#### The stale sentence, in its third and last place

The §13.16 note — *"Forms has no renderer for character-cell text"* — was still
in `forms_render_to_string()`'s docblock, describing behaviour the function had
not had for weeks, having already been found guarding the shipping set (§13.36).
One dead sentence, copied to three places, each of which read as a decision.

#### The settings that would have had nowhere to live

`gen_print_form_url()` chose between the two engines on
`MODULE_FORMS_PRINT_BUTTONS` and on whether the clicker held
`SECURITY_ID_FORMS_RUN`. Both were transition scaffolding and both are gone; a
user without the key now reaches the Forms print page and is refused there,
which is the honest answer rather than a silent redirect to an engine nobody can
see.

That emptied the Forms settings tab, which had contained nothing else — and made
visible a second problem: **the three renderer defaults Forms took over in
§13.34 were set on PhreeForm's admin tab**, which goes with the module. A
setting with nowhere to set it is not a setting. Paper size, row spacing and
default column width are on the Forms tab now, and the page is held to the
installer's key list rather than to a list written in the test, so a key in one
and not the other is caught either way round.

The installer and the Company Manager offer `forms_paper_sizes()` — keyed by the
name a definition stores, not PhreeForm's `"A4:210:297"`.

#### admin_add_reports(), and Import arriving early

Two things there. Its **signature** defaulted to `PF_DIR_MY_REPORTS`, so a core
function in phreedom could not be evaluated without PhreeForm loaded. And it
fell back to `ImportReport()` for a module shipping only xml.

`forms_seed_legacy()` replaces that fallback, and it is decision L arriving
early: the xml is **converted on the way in** and lands as a native definition,
rather than stored verbatim for every later load to convert afresh. The company
receiving it needs no legacy reader at all.

#### Left for the deletion, and one of them is a decision

Three phreedom dashboards — Company Links, Company Notes, Company To-Do — use
`SECURITY_ID_PHREEFORM` as their own security key. They have nothing to do with
reports; they borrowed a constant. It cannot simply be swapped: changing a
dashboard's security id changes **who can see it**, and pointing it at a new key
means nobody holds it and all three vanish. That needs a role migration and a
decision, not a rename.

Also deferred, because each is PhreeForm's own machinery and dies with it: the
five `config_phreeform.php` hook registrations, `pos_builder.php`,
`build_dir_html()`/`build_dir_tree()` in core (one caller: PhreeForm's report
list) and the `PF_DEFAULT_TRIM_LENGTH` they read, the `menu_bs.php` special case
for PhreeForm menu entries, and `PBtoR30.php`, which is a 2.x upgrade script and
history rather than live code.
