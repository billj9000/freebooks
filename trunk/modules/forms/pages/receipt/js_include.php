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
//  Path: /modules/forms/pages/receipt/js_include.php
//
/**
 * Row handling for the receipt editor.
 *
 * Plain DOM, no jQuery, and the same rule the report editor works to: every
 * mutation renumbers the WHOLE table rather than patching the row it touched,
 * because PHP turns `field[3][width]` into $_POST['field'][3]['width'] and two
 * rows sharing an index silently merge into one.
 *
 * Order is not cosmetic here. A receipt's layout IS the order of its fields, so
 * moving a row up moves the value up the printed receipt; the submitted order
 * is what SerialForm::apply() stores.
 */
?>
<script type="text/javascript">
function formsReceiptRenumber() {
  var tbody = document.getElementById('forms_receipt_fields');
  Array.from(tbody.rows).forEach(function (row, index) {
    row.className = (index % 2) ? 'odd' : 'even';
    row.querySelectorAll('[name]').forEach(function (field) {
      field.name = field.name.replace(/^field\[\d+\]/, 'field[' + index + ']');
    });
  });
  formsReceiptRenumberLines();
  formsReceiptChanged();
}

/**
 * Tell the preview something moved.
 *
 * Moving, deleting and adding a row all happen in the DOM without any field's
 * value changing, so none of them fires `change` and htmx's own triggers see
 * nothing — yet each one alters what would print, because a receipt's layout IS
 * the order of its fields. Every mutation goes through renumbering, so this is
 * announced from there and nowhere else.
 */
function formsReceiptChanged() {
  document.body.dispatchEvent(new CustomEvent('forms-receipt-changed'));
}

/**
 * Renumber the line column.
 *
 * The number belongs to the FIRST cell of each line, and a line ends at the
 * cell whose row-break box is ticked — the same rule SerialTemplate::lines()
 * applies on the server. Recomputed after every move so the editor never shows
 * a grouping the server would not agree with.
 */
function formsReceiptRenumberLines() {
  var tbody = document.getElementById('forms_receipt_fields');
  var line  = 1;
  var first = true;
  Array.from(tbody.rows).forEach(function (row) {
    var cell = row.cells[0];
    cell.textContent = first ? String(line) : '';
    first = false;
    var brk = row.querySelector('input[type=checkbox][name$="[break]"]');
    var hidden = row.querySelector('input[type=hidden][name$="[break]"]');
    if ((brk && brk.checked) || hidden) { line++; first = true; }
  });
}

function formsReceiptMove(button, by) {
  var row   = button.closest('tr');
  var tbody = row.parentNode;
  var rows  = Array.from(tbody.rows);
  var index = rows.indexOf(row);
  var to    = index + by;
  if (to < 0 || to >= rows.length) return;
  // insertBefore with a null reference appends, which is what moving the last
  // row down would otherwise do to the first one.
  if (by < 0) tbody.insertBefore(row, rows[to]);
  else        tbody.insertBefore(rows[to], row);
  formsReceiptRenumber();
}

function formsReceiptRemove(button) {
  var row = button.closest('tr');
  row.parentNode.removeChild(row);
  formsReceiptRenumber();
}

function formsReceiptAddText() {
  var tbody = document.getElementById('forms_receipt_fields');
  var index = tbody.rows.length;
  var row   = document.createElement('tr');
  // No index value: SerialForm::apply() reads an empty one as a NEW field, and
  // will only create the one kind this page can honestly complete — literal
  // text. A bound value needs a binding chosen, which is the canvas's machinery.
  row.innerHTML =
      '<td class="text-muted small"></td>'
    + '<td><input type="hidden" name="field[' + index + '][index]" value="">'
    +     '<input type="text" name="field[' + index + '][text]" class="form-control form-control-sm"'
    +     ' value="' + <?php echo json_encode(FORMS_RECEIPT_NEW_TEXT); ?> + '"></td>'
    + '<td><input type="number" name="field[' + index + '][width]" min="1" max="200"'
    +     ' class="form-control form-control-sm" value="<?php echo (int)($forms_receipt_definition?->serialTemplate?->width ?? 40); ?>"></td>'
    + '<td><select name="field[' + index + '][align]" class="form-select form-select-sm">'
    +     '<option value="L"><?php echo FORMS_RECEIPT_ALIGN_L; ?></option>'
    +     '<option value="C"><?php echo FORMS_RECEIPT_ALIGN_C; ?></option>'
    +     '<option value="R"><?php echo FORMS_RECEIPT_ALIGN_R; ?></option>'
    +     '</select></td>'
    + '<td class="text-center"><input type="checkbox" class="form-check-input" value="1"'
    +     ' name="field[' + index + '][break]" checked></td>'
    + '<td class="text-nowrap">'
    +     '<button type="button" class="btn btn-primary btn-sm" onclick="formsReceiptMove(this,-1)">&uarr;</button> '
    +     '<button type="button" class="btn btn-primary btn-sm" onclick="formsReceiptMove(this,1)">&darr;</button> '
    +     '<button type="button" class="btn btn-primary btn-sm" onclick="formsReceiptRemove(this)">&times;</button>'
    +     '</td>';
  tbody.appendChild(row);
  formsReceiptRenumber();
}

document.addEventListener('DOMContentLoaded', function () {
  var tbody = document.getElementById('forms_receipt_fields');
  if (!tbody) return;
  // The line numbers follow the row-break boxes, so ticking one has to move
  // them — otherwise the editor shows a grouping that stopped being true the
  // moment it was changed.
  tbody.addEventListener('change', function (event) {
    if (event.target.matches('input[type=checkbox][name$="[break]"]')) formsReceiptRenumberLines();
  });
});
</script>
