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
//  Path: /modules/forms/pages/design/js_include.php
//
/**
 * Row handling for the report editor.
 *
 * Plain DOM, no jQuery. The rows are added, removed and reordered in the
 * browser and then submitted as one array, so the only thing this has to get
 * right is the INDEX in each field name — PHP builds `column[3][key]` into
 * $_POST['column'][3]['key'], and a duplicated index silently merges two rows
 * into one. Every mutation therefore renumbers the whole table rather than
 * trying to patch the row it touched.
 *
 * The options come from the page, which got them from the dataset: this file
 * never invents a field name (§13.4).
 */
if ($forms_design_definition === null) return;

$forms_design_options = function ($choices, $selected = '') {
  $html = '';
  foreach ($choices as $choice) {
    $html .= '<option value="' . htmlspecialchars($choice['id'], ENT_QUOTES) . '"'
          . ($choice['id'] === $selected ? ' selected' : '') . '>'
          . htmlspecialchars($choice['text']) . '</option>';
  }
  return $html;
};
?>
<script type="text/javascript">
var formsDesignFields    = '<?php echo $forms_design_options($forms_design_fields); ?>';
var formsDesignAligns    = '<?php echo $forms_design_options($forms_design_aligns); ?>';
var formsDesignOperators = '<?php echo $forms_design_options($forms_design_operators); ?>';

// PHP indexes $_POST by the number in the field name, so two rows sharing one
// index become one row. Renumbering after every change is the only way that
// cannot drift.
function formsDesignRenumber(tbody, prefix) {
  Array.from(tbody.rows).forEach(function (row, index) {
    row.className = (index % 2) ? 'odd' : 'even';
    row.querySelectorAll('[name]').forEach(function (field) {
      field.name = field.name.replace(
        new RegExp('^' + prefix + '\\[\\d+\\]'), prefix + '[' + index + ']');
    });
  });
}

function formsDesignSection(button) {
  var row   = button.closest('tr');
  var tbody = row.parentNode;
  return { row: row, tbody: tbody, prefix: tbody.id === 'forms_design_columns' ? 'column'
                                         : (tbody.id === 'forms_design_sorts' ? 'sort' : 'filter') };
}

function formsDesignRemove(button) {
  var at = formsDesignSection(button);
  at.row.remove();
  formsDesignRenumber(at.tbody, at.prefix);
}

function formsDesignMove(button, by) {
  var at    = formsDesignSection(button);
  var rows  = Array.from(at.tbody.rows);
  var index = rows.indexOf(at.row);
  var to    = index + by;
  if (to < 0 || to >= rows.length) return;
  // insertBefore with a null reference appends, which is what moving the last
  // row down would otherwise do to the first one.
  if (by < 0) at.tbody.insertBefore(at.row, rows[to]);
  else        at.tbody.insertBefore(rows[to], at.row);
  formsDesignRenumber(at.tbody, at.prefix);
}

function formsDesignAppend(tbody, prefix, html) {
  var row = document.createElement('tr');
  row.innerHTML = html.replace(/%i%/g, tbody.rows.length);
  tbody.appendChild(row);
  formsDesignRenumber(tbody, prefix);
}

function formsDesignAddColumn() {
  var tbody = document.getElementById('forms_design_columns');
  formsDesignAppend(tbody, 'column',
      '<td><select name="column[%i%][key]" class="form-select form-select-sm">' + formsDesignFields + '</select></td>'
    + '<td><input type="text" class="form-control form-control-sm" name="column[%i%][heading]"></td>'
    + '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="column[%i%][width]"></td>'
    + '<td><select name="column[%i%][align]" class="form-select form-select-sm">' + formsDesignAligns + '</select></td>'
    + '<td><input type="text" class="form-control form-control-sm" name="column[%i%][format]"></td>'
    + '<td class="text-center"><input type="checkbox" class="form-check-input" name="column[%i%][total]" value="1"></td>'
    // A NEW column defaults to breaking, which is the ordinary side-by-side
    // grid. Defaulting the other way would stack it under its neighbour.
    + '<td class="text-center"><input type="checkbox" class="form-check-input" name="column[%i%][pageBreak]" value="1" checked></td>'
    + '<td class="text-end text-nowrap">'
    + '<button type="button" class="btn btn-primary btn-sm" onclick="formsDesignMove(this,-1)">&uarr;</button> '
    + '<button type="button" class="btn btn-primary btn-sm" onclick="formsDesignMove(this,1)">&darr;</button> '
    + '<button type="button" class="btn btn-primary btn-sm" onclick="formsDesignRemove(this)">&times;</button></td>');
}

function formsDesignAddSort() {
  var tbody = document.getElementById('forms_design_sorts');
  formsDesignAppend(tbody, 'sort',
      '<td style="width:55%"><select name="sort[%i%][field]" class="form-select form-select-sm">' + formsDesignFields + '</select></td>'
    + '<td style="width:30%"><select name="sort[%i%][direction]" class="form-select form-select-sm">'
    + '<option value="ASC"><?php echo addslashes(FORMS_DESIGN_ASCENDING); ?></option>'
    + '<option value="DESC"><?php echo addslashes(FORMS_DESIGN_DESCENDING); ?></option></select></td>'
    + '<td class="text-end">'
    + '<button type="button" class="btn btn-primary btn-sm" onclick="formsDesignMove(this,-1)">&uarr;</button> '
    + '<button type="button" class="btn btn-primary btn-sm" onclick="formsDesignMove(this,1)">&darr;</button> '
    + '<button type="button" class="btn btn-primary btn-sm" onclick="formsDesignRemove(this)">&times;</button></td>');
}

function formsDesignAddFilter() {
  var tbody = document.getElementById('forms_design_filters');
  formsDesignAppend(tbody, 'filter',
      '<td><select name="filter[%i%][field]" class="form-select form-select-sm">' + formsDesignFields + '</select></td>'
    + '<td><select name="filter[%i%][operator]" class="form-select form-select-sm">' + formsDesignOperators + '</select></td>'
    + '<td><input type="text" class="form-control form-control-sm" name="filter[%i%][value1]"></td>'
    + '<td><input type="text" class="form-control form-control-sm" name="filter[%i%][value2]"></td>'
    + '<td class="text-end">'
    + '<button type="button" class="btn btn-primary btn-sm" onclick="formsDesignRemove(this)">&times;</button></td>');
}

// The preview starts empty rather than auto-running: a report over a large
// ledger takes seconds, and spending them before the author has looked at the
// page is spending them on nothing.
</script>
