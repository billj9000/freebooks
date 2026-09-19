---
name: bootstrap-page-conversion
description: Rules for converting an existing jQuery UI page in FreeBooks to the Bootstrap/htmx theme — card headers, table-dark styling, address/contact blocks, fieldset emulation, and tab pages. Use when converting or restyling an existing page.
---

# Bootstrap Page Conversion Rules

When converting an existing jQuery UI page to Bootstrap, follow these rules. Detailed notes are in memory file `reference_phreebooks_tab_conversion.md`.

**Every converted page must:**
- Set `$use_bootstrap_nav = true;` in `pre_process.php`
- Every `fb-card` must have `<div class="card-header py-1 fb-widget-header">Title</div>` — gives the blue gloss-wave header (`#a6c9e2` border, `#fcfdfd` body)
- Use `table-dark` on `<thead>` — freebooks.css overrides this to the gloss-wave gradient
- Use Bootstrap `form-control`/`form-select` on inputs in **form/detail cards** (transaction details, payment fields, etc.)

**Address/contact blocks** (stacked name/address inputs with clearField placeholders):
- Use **plain inputs with `size=` attributes** — no `form-control` class
- Use plain `<select>` with `style="width:100%"` — no `form-select` class
- This matches the orders page and makes placeholder text look like labels rather than form fields

**Fieldset-style sections** (grouped content with a floating title in the border):
- Use `<div class="fb-fieldset mb-2">` + `<div class="fb-fieldset-legend">Title</div>` — do **not** use native `<fieldset>`/`<legend>` elements
- `fb-fieldset` and `fb-fieldset-legend` are defined in `trunk/ext/bootstrap5/freebooks.css`
- This approach is CSS-only so it can be restyled per theme; native `<fieldset>` fights CSS overrides
- Use for standalone utility/tool pages (e.g. admin_tools); use `fb-card`/`fb-widget-header` for dashboard-style panels with a filled header bar

**Tab pages** — see the tab conversion checklist in the memory reference.
