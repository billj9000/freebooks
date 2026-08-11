# Forms — Design

**Status:** Approved design, not yet implemented. Targets the 1.0.0 release.
**Supersedes:** the `phreeform` module.
**Last updated:** 2026-08-11

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

Use `BuildForm($report, 'S')` — delivery method `S` returns the bytes instead of emitting HTTP
headers and calling `exit()`.

A fifth trap sits outside the harness: **company auto-detection**. A baseline taken from the wrong
database still renders plausible-looking output and exits 0 — an early run silently captured `demo`
instead of `production`. The bootstrap always echoes the company it resolved and warns when it guessed.

The tools, all under `docs/tools/` and all run inside the container with `-d display_errors=1`:

| Tool | Purpose |
|---|---|
| `bootstrap_freebooks.php` | Shared CLI bootstrap (trap 4) + the Formatter's lookup tables |
| `capture_phreeform_baseline.php` | Step 0 — snapshot legacy data + PDF bytes |
| `diff_forms_provider.php` | Semantic-diff a provider against the baseline |
| `verify_forms_seam.php` | Assert the seam holds (no DB needed) |
| `verify_forms_formatter.php` | Assert `Formatter` parity with `ProcessData()`/`AddSep()` |
| `render_forms_document.php` | Render a form through Forms, for diffing against the baseline |
| `make_forms_proving_templates.php` | Create/remove the scratch multi-page + `Ttl` templates (§8.3) |
| `analyse_phreeform_defs.py` | Regenerate the §6 catalog inventory |

### 8.2 Conversion utility

The XML format is fully mechanical (56/56 parsed clean) and carries labels, widths, alignment,
totals, and processing hints that map directly. But conversion is a **mapping** problem, not a
translation problem: `<relationship>` must map to a *dataset*, and `fieldname` to a *catalog key*.

Two facts make this tractable:

1. The definitions are overwhelmingly **product-shipped** (~56 files across 7 modules; the live
   production set is essentially the stock set). The bulk of conversion is a one-time authoring job we
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

Results (2026-08-11, production test data):

| Form | Records | Pages | Text | Pixels |
|---|---|---|---|---|
| `pf_91` Invoice | 349 (EUR, 14), 110 (GBP), 89 (USD), 507, 430 | 1 | identical | 2 per page |
| `pf_9991` paginating | 349 | 6 = 6 | identical | 2 per page |

The residual 2 pixels are two greys one level apart (108/109, 168/169) — antialiasing rounding.

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
5. Build the dataset + field catalog from §6 (D1 split into Transactions and General Ledger) —
   the PO baseline is its first test case, and the first chance to diff a `Ttl` and a paginating
   form against unmodified legacy output. `rep_id`/`branch` (§4.3.1) must land here as
   provider-resolved fields, since `pf_73` needs `rep_id`.
