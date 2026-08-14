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
//  Path: /modules/forms/pages/datasets/js_include.php
//
if (empty($forms_ds_editing)) return;

// The schema is embedded rather than fetched per change. Choosing a join is
// three dependent selects, and a round trip on each would make the form feel
// broken; this is an admin page reached rarely, so a few tens of kilobytes once
// is the better trade. It is data, not markup — nothing here is an AJAX call.
//
// It carries only what the author may pick: SchemaInspector has already removed
// the sensitive tables, so they cannot appear in a list even client-side.
$forms_ds_schema = array();
foreach ($schema->tables() as $table) {
  $forms_ds_schema[$table] = $schema->columns($table);
}
?>
<script>
const FORMS_SCHEMA     = <?php echo json_encode($forms_ds_schema); ?>;
const FORMS_JOIN_TYPES = <?php echo json_encode($forms_ds_join_types); ?>;
const FORMS_VALUE_TYPES= <?php echo json_encode($forms_ds_value_types); ?>;
const FORMS_TEXT = <?php echo json_encode(array(
  'joinType'  => FORMS_DS_JOIN_TYPE,
  'joinTable' => FORMS_DS_JOIN_TABLE,
  'joinMatch' => FORMS_DS_JOIN_MATCH,
  'remove'    => FORMS_DS_REMOVE,
  'fieldKey'  => FORMS_DS_FIELD_KEY,
  'fieldLabel'=> FORMS_DS_FIELD_LABEL,
  'fieldFrom' => FORMS_DS_FIELD_SOURCE,
  'fieldType' => FORMS_DS_FIELD_TYPE,
)); ?>;

let formsJoinIndex  = 0;
let formsFieldIndex = 0;

function formsOption(value, text, selected) {
  const option = document.createElement('option');
  option.value = value;
  option.textContent = text;
  if (value === selected) option.selected = true;
  return option;
}

function formsSelect(name, options, selected, onChange) {
  const select = document.createElement('select');
  select.name = name;
  select.className = 'form-select form-select-sm';
  options.forEach(o => select.appendChild(formsOption(o.id, o.text, selected)));
  if (onChange) select.addEventListener('change', onChange);
  return select;
}

/** Tables already in the dataset: the base table plus every join above this row. */
function formsTablesInScope(uptoRow) {
  const tables = [document.getElementById('baseTable').value];
  document.querySelectorAll('#ds_joins .ds-join').forEach(row => {
    if (uptoRow !== undefined && Number(row.dataset.index) >= uptoRow) return;
    const table = row.querySelector('.ds-join-table');
    if (table && table.value) tables.push(table.value);
  });
  return tables.filter((t, i) => t && tables.indexOf(t) === i);
}

function formsColumnOptions(table) {
  return (FORMS_SCHEMA[table] || []).map(c => ({id: c, text: c}));
}

function formsTableOptions(tables) {
  return tables.map(t => ({id: t, text: t}));
}

function formsAddJoin(value) {
  value = value || {};
  const index = formsJoinIndex++;
  const wrap  = document.createElement('div');
  wrap.className = 'ds-join border rounded p-2 mb-2';
  wrap.dataset.index = index;

  const row = document.createElement('div');
  row.className = 'row g-2 align-items-end';
  wrap.appendChild(row);

  const tableCol = document.createElement('div');
  tableCol.className = 'col-md-3';
  tableCol.innerHTML = '<label class="form-label mb-1">' + FORMS_TEXT.joinTable + '</label>';
  const tableSelect = formsSelect('join[' + index + '][table]',
    formsTableOptions(Object.keys(FORMS_SCHEMA)), value.table, () => formsRefreshJoin(index));
  tableSelect.classList.add('ds-join-table');
  tableCol.appendChild(tableSelect);
  row.appendChild(tableCol);

  const typeCol = document.createElement('div');
  typeCol.className = 'col-md-3';
  typeCol.innerHTML = '<label class="form-label mb-1">' + FORMS_TEXT.joinType + '</label>';
  typeCol.appendChild(formsSelect('join[' + index + '][type]', FORMS_JOIN_TYPES, value.type));
  row.appendChild(typeCol);

  const matchCol = document.createElement('div');
  matchCol.className = 'col-md-5 ds-join-conditions';
  matchCol.innerHTML = '<label class="form-label mb-1">' + FORMS_TEXT.joinMatch + '</label>';
  row.appendChild(matchCol);

  const removeCol = document.createElement('div');
  removeCol.className = 'col-md-1 text-end';
  const remove = document.createElement('button');
  remove.type = 'button';
  remove.className = 'btn btn-primary btn-sm';
  remove.textContent = FORMS_TEXT.remove;
  remove.addEventListener('click', () => { wrap.remove(); formsRefreshFields(); });
  removeCol.appendChild(remove);
  row.appendChild(removeCol);

  document.getElementById('ds_joins').appendChild(wrap);
  (value.on && value.on.length ? value.on : [{}]).forEach(c => formsAddCondition(index, c));
  formsRefreshFields();
}

function formsAddCondition(joinIndex, value) {
  value = value || {};
  const wrap = document.querySelector('.ds-join[data-index="' + joinIndex + '"] .ds-join-conditions');
  const join = document.querySelector('.ds-join[data-index="' + joinIndex + '"] .ds-join-table');
  const n    = wrap.querySelectorAll('.ds-condition').length;

  const line = document.createElement('div');
  line.className = 'ds-condition d-flex gap-1 align-items-center mb-1';

  // The left side may only be a table already in the dataset. The server checks
  // this again; offering only those keeps the form from producing a join it
  // will refuse.
  const scope = formsTablesInScope(joinIndex);
  const left  = formsSelect('join[' + joinIndex + '][on][' + n + '][leftTable]',
    formsTableOptions(scope), value.leftTable, function () {
      const columns = formsColumnOptions(this.value);
      const target  = line.querySelector('.ds-left-column');
      target.innerHTML = '';
      columns.forEach(o => target.appendChild(formsOption(o.id, o.text)));
    });
  const leftColumn = formsSelect('join[' + joinIndex + '][on][' + n + '][leftColumn]',
    formsColumnOptions(value.leftTable || scope[0]), value.leftColumn);
  leftColumn.classList.add('ds-left-column');

  const equals = document.createElement('span');
  equals.textContent = '=';

  const rightColumn = formsSelect('join[' + joinIndex + '][on][' + n + '][rightColumn]',
    formsColumnOptions(join ? join.value : ''), value.rightColumn);
  rightColumn.classList.add('ds-right-column');

  line.appendChild(left);
  line.appendChild(leftColumn);
  line.appendChild(equals);
  line.appendChild(rightColumn);
  wrap.appendChild(line);
}

/** The joined table changed, so its own column choices have to follow. */
function formsRefreshJoin(index) {
  const row   = document.querySelector('.ds-join[data-index="' + index + '"]');
  const table = row.querySelector('.ds-join-table').value;
  row.querySelectorAll('.ds-right-column').forEach(select => {
    const chosen = select.value;
    select.innerHTML = '';
    formsColumnOptions(table).forEach(o => select.appendChild(formsOption(o.id, o.text, chosen)));
  });
  formsRefreshFields();
}

function formsAddField(value) {
  value = value || {};
  const index = formsFieldIndex++;
  const wrap  = document.createElement('div');
  wrap.className = 'ds-field row g-2 align-items-end mb-2';
  wrap.dataset.index = index;

  const source = document.createElement('div');
  source.className = 'col-md-4';
  source.innerHTML = '<label class="form-label mb-1">' + FORMS_TEXT.fieldFrom + '</label>';
  const inner = document.createElement('div');
  inner.className = 'd-flex gap-1';
  const tableSelect = formsSelect('field[' + index + '][table]',
    formsTableOptions(formsTablesInScope()), value.table, function () {
      const target = wrap.querySelector('.ds-field-column');
      const chosen = target.value;
      target.innerHTML = '';
      formsColumnOptions(this.value).forEach(o => target.appendChild(formsOption(o.id, o.text, chosen)));
    });
  tableSelect.classList.add('ds-field-table');
  const columnSelect = formsSelect('field[' + index + '][column]',
    formsColumnOptions(value.table || formsTablesInScope()[0]), value.column);
  columnSelect.classList.add('ds-field-column');
  inner.appendChild(tableSelect);
  inner.appendChild(columnSelect);
  source.appendChild(inner);
  wrap.appendChild(source);

  ['key', 'label'].forEach((name, i) => {
    const col = document.createElement('div');
    col.className = 'col-md-3';
    col.innerHTML = '<label class="form-label mb-1">'
      + (i === 0 ? FORMS_TEXT.fieldKey : FORMS_TEXT.fieldLabel) + '</label>';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm';
    input.name = 'field[' + index + '][' + name + ']';
    input.value = value[name] || '';
    col.appendChild(input);
    wrap.appendChild(col);
  });

  const typeCol = document.createElement('div');
  typeCol.className = 'col-md-1';
  typeCol.innerHTML = '<label class="form-label mb-1">' + FORMS_TEXT.fieldType + '</label>';
  typeCol.appendChild(formsSelect('field[' + index + '][type]', FORMS_VALUE_TYPES, value.type));
  wrap.appendChild(typeCol);

  const removeCol = document.createElement('div');
  removeCol.className = 'col-md-1 text-end';
  const remove = document.createElement('button');
  remove.type = 'button';
  remove.className = 'btn btn-primary btn-sm';
  remove.textContent = FORMS_TEXT.remove;
  remove.addEventListener('click', () => wrap.remove());
  removeCol.appendChild(remove);
  wrap.appendChild(removeCol);

  document.getElementById('ds_fields').appendChild(wrap);
}

/** A table was added or removed, so every field's table choices change with it. */
function formsRefreshFields() {
  const scope = formsTablesInScope();
  document.querySelectorAll('.ds-field-table').forEach(select => {
    const chosen = select.value;
    select.innerHTML = '';
    scope.forEach(t => select.appendChild(formsOption(t, t, chosen)));
  });
}

document.addEventListener('DOMContentLoaded', function () {
  document.getElementById('baseTable').addEventListener('change', function () {
    document.querySelectorAll('.ds-join .ds-condition').forEach(line => {
      const select = line.querySelector('select');
      const chosen = select.value;
      select.innerHTML = '';
      formsTablesInScope().forEach(t => select.appendChild(formsOption(t, t, chosen)));
    });
    formsRefreshFields();
  });

  <?php
    // Existing rows are drawn by the same functions that draw new ones, so the
    // edit form and the new form cannot drift apart.
    echo 'const existingJoins  = ' . json_encode(array_values((array)$forms_ds_record['joins'])) . ";\n";
    echo '  const existingFields = ' . json_encode(array_values((array)$forms_ds_record['fields'])) . ";\n";
  ?>
  existingJoins.forEach(j => formsAddJoin(j));
  if (existingFields.length) existingFields.forEach(f => formsAddField(f));
  else formsAddField();
});
</script>
