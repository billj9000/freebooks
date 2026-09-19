<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/forms/pages/receipt/template_main.php
//
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->icon_list['save']['show']   = $forms_receipt_definition !== null && $security_level > 2;
$toolbar->icon_list['save']['params'] = 'onclick="document.getElementById(\'forms_receipt_form\').submit()"';
// Back to the list a RECEIPT appears on, which is the form list — the same one
// the canvas returns to, because a receipt is listed there beside the page
// forms (§13.28). Reports have their own list and the report editor returns to
// that; forms are deliberately kept off it, so page=main would land an author
// on a list their document is not on.
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \''
    . html_href_link(FILENAME_DEFAULT, 'module=forms&page=canvas', 'SSL') . '\'"';
// 11.10, the receipt editor's own topic. This pointed at 11.06, "Editing a
// Report" - a receipt is not a report, and 11.07 is no better because that
// describes moving boxes on a page, which is exactly what a receipt has none
// of. Neither existing topic could be right; this one was written for it.
$toolbar->add_help('11.10');
echo $toolbar->build_toolbar();

// Reaching here with nothing loaded means the definition named could not be
// read — pre_process has already sent every other case to the editor that
// handles it, and a request naming nothing to the one list of forms. The
// message on the stack says what went wrong; this only has to stop.
if ($forms_receipt_definition === null) { ?>
<h1><?php echo FORMS_RECEIPT_TITLE; ?></h1>
<p><a class="btn btn-primary btn-sm" href="<?php
  echo html_href_link(FILENAME_DEFAULT, 'module=forms&amp;page=canvas', 'SSL'); ?>">
  <?php echo FORMS_RECEIPT_BACK_TO_LIST; ?></a></p>
<?php
  return;
}

$paper = (int)$forms_receipt_definition->serialTemplate->width;
?>
<h1><?php echo htmlspecialchars($forms_receipt_definition->title); ?></h1>

<?php echo html_form('forms_receipt_form', FILENAME_DEFAULT,
    'module=forms&page=receipt&action=save&rID=' . (int)$forms_receipt_id); ?>

<div class="row g-3">
  <div class="col-xl-7">

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_RECEIPT_ABOUT; ?></div>
      <div class="card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-8">
            <label class="form-label mb-0 small"><?php echo FORMS_RECEIPT_NAME; ?></label>
            <input type="text" name="title" class="form-control form-control-sm"
                   value="<?php echo htmlspecialchars($forms_receipt_definition->title); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-0 small"><?php echo FORMS_RECEIPT_PAPER; ?></label>
            <input type="number" name="paper" min="1" max="200"
                   class="form-control form-control-sm"
                   value="<?php echo $paper; ?>">
          </div>
        </div>
        <p class="text-muted small mb-0 mt-2"><?php echo FORMS_RECEIPT_PAPER_HELP; ?></p>
      </div>
    </div>

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_RECEIPT_LINES; ?></div>
      <div class="card-body py-2">
        <p class="text-muted small"><?php echo FORMS_RECEIPT_LINES_HELP; ?></p>

        <table class="table table-sm table-borderless table-hover align-middle">
          <thead class="table-dark">
            <tr>
              <th style="width:2.5rem"><?php echo FORMS_RECEIPT_COL_LINE;  ?></th>
              <th><?php echo FORMS_RECEIPT_COL_PRINTS; ?></th>
              <th style="width:11rem"><?php echo FORMS_RECEIPT_COL_FORMAT; ?></th>
              <th style="width:5rem"><?php echo FORMS_RECEIPT_COL_WIDTH; ?></th>
              <th style="width:7rem"><?php echo FORMS_RECEIPT_COL_ALIGN; ?></th>
              <th style="width:4rem"><?php echo FORMS_RECEIPT_COL_BREAK; ?></th>
              <th style="width:7rem"><?php echo FORMS_RECEIPT_COL_ACTION; ?></th>
            </tr>
          </thead>
          <tbody id="forms_receipt_fields">
<?php
$row = 0;
foreach ($forms_receipt_rows as $line) {
  foreach ($line['cells'] as $position => $cell) {
    $v = $cell['values'];
?>
            <tr class="<?php echo ($row % 2) ? 'odd' : 'even'; ?>">
              <td class="text-muted small">
                <?php echo $position === 0 ? (int)$line['number'] : ''; ?>
              </td>
              <td>
                <input type="hidden" name="field[<?php echo $row; ?>][index]"
                       value="<?php echo htmlspecialchars($v['index']); ?>">
<?php   if ($cell['isText']) { ?>
<?php     // A textarea for the reason the canvas has one: a text input strips
          // line feeds outright. Here nothing has one yet - no shipped receipt
          // carries a multi-line field - but SerialRenderer::overlay() splits
          // its value on "\n" and pads EACH part to the cell width, so the
          // renderer was built expecting one and only the editor refused. A
          // single row, so the grid keeps its shape. ?>
                <textarea name="field[<?php echo $row; ?>][text]" rows="1"
                          class="form-control form-control-sm"><?php
                    echo htmlspecialchars($v['text']); ?></textarea>
<?php   } else { ?>
                <span class="badge text-bg-secondary"><?php echo htmlspecialchars($cell['kind']); ?></span>
                <span class="small text-muted"><?php echo htmlspecialchars($cell['bind']); ?></span>
<?php   } ?>
              </td>
              <td>
<?php   if ($cell['isValue']) {
          // A raw value in a fixed number of character cells is not a cosmetic
          // problem: 12.5 where 12.50 belongs is a column that stops lining up.
          echo forms_format_select('name="field[' . $row . '][format]" class="form-select form-select-sm"',
                                   $forms_receipt_formats, (string)$v['format']);
        } else { ?>
                <span class="text-muted">&mdash;</span>
<?php   } ?>
              </td>
              <td>
                <input type="number" name="field[<?php echo $row; ?>][width]" min="1" max="200"
                       class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($v['width']); ?>">
              </td>
              <td>
                <select name="field[<?php echo $row; ?>][align]" class="form-select form-select-sm">
<?php   foreach (array('L' => FORMS_RECEIPT_ALIGN_L,
                       'C' => FORMS_RECEIPT_ALIGN_C,
                       'R' => FORMS_RECEIPT_ALIGN_R) as $code => $label) { ?>
                  <option value="<?php echo $code; ?>"
                    <?php echo $v['align'] === $code ? 'selected' : ''; ?>><?php echo $label; ?></option>
<?php   } ?>
                </select>
              </td>
              <td class="text-center">
<?php   if ($cell['isTable']) { ?>
                <?php // A table prints whole lines of its own, so it always ends
                      // the line. Shown ticked and disabled, with the value
                      // carried in a hidden field, because a disabled input
                      // submits nothing — and the server enforces this anyway. ?>
                <input type="checkbox" class="form-check-input" checked disabled>
                <input type="hidden" name="field[<?php echo $row; ?>][break]" value="1">
<?php   } else { ?>
                <input type="checkbox" class="form-check-input" value="1"
                       name="field[<?php echo $row; ?>][break]"
                       <?php echo $v['break'] ? 'checked' : ''; ?>>
<?php   } ?>
              </td>
              <td class="text-nowrap">
                <button type="button" class="btn btn-primary btn-sm"
                        onclick="formsReceiptMove(this,-1)">&uarr;</button>
                <button type="button" class="btn btn-primary btn-sm"
                        onclick="formsReceiptMove(this,1)">&darr;</button>
                <button type="button" class="btn btn-primary btn-sm"
                        onclick="formsReceiptRemove(this)">&times;</button>
              </td>
            </tr>
<?php
    $row++;
  }
}
?>
          </tbody>
        </table>

        <button type="button" class="btn btn-primary btn-sm" onclick="formsReceiptAddText()">
          <?php echo FORMS_RECEIPT_ADD_TEXT; ?>
        </button>

<?php if ($forms_receipt_bindings !== array()) {
        // The names this receipt can actually supply, sorted so the list can be
        // read. Not a free-text box: a name that is merely plausible stores
        // perfectly well and then prints nothing, on every receipt, for ever.
        $names = array_keys($forms_receipt_bindings);
        sort($names); ?>
        <span class="d-inline-block ms-2">
          <label class="small text-muted me-1" for="forms_receipt_new_bind">
            <?php echo FORMS_RECEIPT_ADD_VALUE; ?></label>
          <select id="forms_receipt_new_bind" class="form-select form-select-sm d-inline-block"
                  style="width:auto">
<?php   foreach ($names as $name) { ?>
            <option value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>">
              <?php echo htmlspecialchars($name); ?></option>
<?php   } ?>
          </select>
          <?php echo forms_format_select(
              'id="forms_receipt_new_format" class="form-select form-select-sm d-inline-block" style="width:auto"',
              $forms_receipt_formats, ''); ?>
          <button type="button" class="btn btn-primary btn-sm" onclick="formsReceiptAddValue()">
            <?php echo FORMS_RECEIPT_ADD_VALUE_BTN; ?>
          </button>
        </span>
<?php } ?>
        <p class="text-muted small mb-0 mt-1"><?php echo FORMS_RECEIPT_ADD_HELP; ?></p>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_RECEIPT_PREVIEW; ?></div>
      <div class="card-body py-2">
        <?php
        // Refreshed from the FORM, not from a copy of its values: htmx posts the
        // editor's own fields to the same SerialForm the save uses, so what is
        // shown is what would be stored. A receipt's fields overlay one another,
        // so seeing the effect of a width or an alignment is the whole reason
        // this pane exists — and a preview that waited for a save would make the
        // author commit to a change to find out what it was.
        //
        // `changed` on the input trigger keeps arrow-key traversal of a number
        // field from firing a request per keystroke; the delay coalesces a
        // typed number into one.
        ?>
        <div id="forms_receipt_preview"
             hx-post="<?php echo html_href_link(FILENAME_DEFAULT,
                 'module=forms&amp;page=ajax&amp;op=receipt_preview&amp;rID=' . (int)$forms_receipt_id
                 . '&amp;sample=' . (int)$forms_receipt_sample, 'SSL'); ?>"
             hx-include="#forms_receipt_form"
             hx-target="#forms_receipt_preview"
             hx-swap="innerHTML"
             hx-trigger="change from:#forms_receipt_form,
                         keyup changed delay:400ms from:#forms_receipt_form,
                         forms-receipt-changed from:body">
<?php if ($forms_receipt_preview === '') { ?>
          <p class="text-muted small mb-0"><?php echo FORMS_RECEIPT_NO_SAMPLE; ?></p>
<?php } else { ?>
          <p class="text-muted small">
            <?php echo sprintf(FORMS_RECEIPT_PREVIEW_OF, (int)$forms_receipt_sample); ?>
          </p>
          <?php
          // The ruler makes a width mean something: a cell of 15 is only legible
          // against the column it lands in.
          $ruler = '';
          for ($i = 1; $i <= $paper; $i++) {
            $ruler .= ($i % 10 === 0) ? (string)(intdiv($i, 10) % 10) : (($i % 5 === 0) ? '+' : '.');
          }
          ?>
          <div style="overflow-x:auto">
            <pre class="small mb-0" style="line-height:1.25"><code><?php
              echo htmlspecialchars($ruler) . "\n";
              echo htmlspecialchars(rtrim($forms_receipt_preview, "\n"));
            ?></code></pre>
          </div>
<?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>

</form>
