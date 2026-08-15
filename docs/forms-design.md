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
with no invoices must still be able to lay out an invoice.

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

- **`ImportReport()` is where the paper size is stamped** (§11 paper-size work, commit `5d0b56d`).
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
2. **Report editor.** Columns, group, sort, filter, page setup, live preview through the existing
   `DocumentRunner` → `TablePdfRenderer`. The smaller half, and it exercises load and save end to
   end before any canvas exists.
3. **Form canvas, read-only.** pdf.js underlay with element outlines drawn on top from the
   `Template`. No dragging. This proves the mm-to-pixel alignment, page size, margins and
   orientation — the part most likely to be subtly wrong, and the cheapest point at which to
   abandon approach C if it is.
4. **Form editing.** Drag, resize, snap, add and delete, z-order, and an element inspector for
   font, size, alignment, colour, border and binding.
5. **Native shipping set.** Generated, committed, seeded by the installer, with paper-size stamping
   and a re-stamp that understands the native format.
6. **Migration, and delete PhreeForm** — including `MODULE_FORMS_PRINT_BUTTONS`, the helper's
   PhreeForm branch, and the settings row.

During steps 2–5 the loader tries native then legacy, so development and testing work against a
mixed tree. Step 6 deletes that branch; it is a few lines, not an architecture.

### 13.8 Open risks

Not yet validated.

1. **mm-to-pixel alignment between pdf.js and `LayoutPdfRenderer`.** The underlay is only worth
   having if a box drawn at 140 mm sits over the text rendered at 140 mm, across page sizes,
   orientations and zoom levels. Step 3 exists to find out early.
2. **The three long-tail element types.** `TDup`, `ImgLink` and `BarCode` appear once each in the
   shipped set. `TDup` was *eliminated* rather than mapped when the vocabulary was collapsed to
   nine (§4.2), so its single instance is the one definition most likely to block the migration
   under condition 1.
3. **Editing a definition served by a provider.** Nine forms are built by a `DocumentProvider`
   rather than a dataset query, and their available fields come from the provider's own contract,
   not from a dataset the palette can enumerate. What the palette offers for those is unresolved.
4. **pdf.js weight.** Roughly 1 MB vendored, on a repository that commits its dependencies by
   design. Acceptable, but it is the largest single front-end asset added so far.

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
