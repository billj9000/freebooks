<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
//  Path: /modules/inventory/pages/main/js_include.php
?>
<script type="text/javascript">
// pass some php variables
var image_delete_text   = '<?php echo TEXT_DELETE; ?>';
var image_delete_msg    = '<?php echo INV_MSG_DELETE_INV_ITEM; ?>';
var text_sku            = '<?php echo TEXT_SKU; ?>';
var text_properties     = '<?php echo TEXT_PROPERTIES;?>';
var default_tax         = '<?php echo isset($cInfo->purch_taxable) ? $cInfo->purch_taxable : ''; ?>';
var delete_icon_HTML    = '<?php echo substr(html_icon("emblems/emblem-unreadable.png", TEXT_DELETE, "small", "onclick=\"if (confirm(\'" . INV_MSG_DELETE_INV_ITEM . "\')) removeBOMRow("), 0, -2); ?>';
var text_no             = '<?php echo TEXT_NO; ?>';
var text_yes            = '<?php echo TEXT_YES; ?>';
var filter_equal_to     = '<?php echo FILTER_EQUAL_TO;?>';
var filter_not_equal_to = '<?php echo FILTER_NOT_EQUAL_TO;?>';
var filter_like         = '<?php echo FILTER_LIKE;?>';
var filter_not_like     = '<?php echo FILTER_NOT_LIKE;?>';
var filter_bigger_than  = '<?php echo FILTER_BIGGER_THAN;?>';
var filter_less_than    = '<?php echo FILTER_LESS_THAN;?>';
var filter_contains     = '<?php echo FILTER_CONTAINS;?>';

<?php if (isset($js_tax_rates))     echo $js_tax_rates; ?>
<?php if (isset($FirstValue))       echo $FirstValue; ?>
<?php if (isset($FirstId))          echo $FirstId; ?>
<?php if (isset($SecondField))      echo $SecondField; ?>
<?php if (isset($SecondFieldValue)) echo $SecondFieldValue; ?>
<?php if (isset($SecondFieldId))    echo $SecondFieldId; ?>
<?php echo $currencies->build_js_currency_arrays(); ?>
var defaultCurrency = '<?php echo DEFAULT_CURRENCY; ?>';

function getCurrencyRate(code) {
  for (var i = 0; i < js_currency_codes.length; i++) {
    if (js_currency_codes[i] == code) return parseFloat(js_currency_values[i]);
  }
  return 1;
}
// Convert a cost in any currency to company currency (rate = foreign per 1 company).
function costToCompany(cost, currCode) {
  var rate = getCurrencyRate(currCode || defaultCurrency);
  return rate == 0 ? cost : cost / rate;
}

function init() {
  // Bootstrap 5 tabs are data-attribute driven — no explicit init needed.
  <?php
  $action_array = array('edit','properties','create');
  if (in_array($_REQUEST['action'] ?? '', $action_array) && empty($cInfo->purchase_array)) {
    echo "addVendorRow();";
  }
  ?>
  var st = document.getElementById('search_text');
  if (st) { st.focus(); st.select(); }
  if (typeof update_full_price_incl_tax === 'function') update_full_price_incl_tax(true, false, false);
}

function check_form() {
  return true;
}

function tax(id, text, rate) {
  this.id   = id;
  this.text = text;
  this.rate = rate;
}

function check_sku() {
  var sku = document.getElementById('sku') ? document.getElementById('sku').value : '';
  if (!sku) {
    alert("<?php echo JS_ERROR; ?>" + "<?php echo JS_SKU_BLANK; ?>");
    return false;
  }
  return true;
}

function setSkuLength() {
  var sku_val = document.getElementById('sku').value;
  if (document.getElementById('inventory_type').value == 'ms') {
    document.getElementById('sku').value     = sku_val.substr(0, <?php echo (MAX_INVENTORY_SKU_LENGTH - 5); ?>);
    document.getElementById('sku').maxLength = <?php echo (MAX_INVENTORY_SKU_LENGTH - 5); ?>;
  } else {
    document.getElementById('sku').maxLength = <?php echo MAX_INVENTORY_SKU_LENGTH; ?>;
  }
}

function deleteItem(id, type) {
  location.href = 'index.php?module=inventory&page=main&action=delete&cID='+id+'&inventory_type='+type;
}

function showImage() {
  new bootstrap.Modal(document.getElementById('inv_image_modal')).show();
}

function copyItem(id, type) {
  var skuID = prompt('<?php echo INV_MSG_COPY_INTRO; ?>', '');
  if (skuID) location.href = 'index.php?module=inventory&page=main&action=copy&cID='+id+'&sku='+skuID+'&inventory_type='+type;
}

function renameItem(id, type) {
  var skuID = prompt('<?php echo INV_MSG_RENAME_INTRO; ?>', '');
  if (skuID) location.href = 'index.php?module=inventory&page=main&action=rename&cID='+id+'&sku='+skuID+'&inventory_type='+type;
}

function product_markup_change() {
  var highest = 0;
  document.getElementsByName("item_cost_array[]").forEach(function(el) {
    var v = cleanCurrency(el.value);
    if (v > highest) highest = v;
  });
  var markup = cleanCurrency(document.getElementById('product_markup').value);
  document.getElementById('full_price_with_tax').value = formatCurrency(highest * markup);
  update_full_price_incl_tax(false, false, true);
  update_full_price_incl_tax(true, false, false);
}

function product_margin_change() {
  var highest = 0;
  document.getElementsByName("item_cost_array[]").forEach(function(el) {
    var v = cleanCurrency(el.value);
    if (v > highest) highest = v;
  });
  var margin = cleanCurrency(document.getElementById('product_margin').value);
  document.getElementById('full_price').value = formatCurrency((highest / (100 - margin)) * 100);
  update_full_price_incl_tax(false, true, false);
  update_full_price_incl_tax(true, false, false);
}

function what_to_update() {
  var highest = 0;
  var costEls = document.getElementsByName("item_cost_array[]");
  var currEls = document.getElementsByName("item_cost_currency_array[]");
  for (var i = 0; i < costEls.length; i++) {
    var curr = currEls[i] ? currEls[i].value : defaultCurrency;
    var v = costToCompany(cleanCurrency(costEls[i].value), curr);
    if (v > highest) highest = v;
  }
  var margin = cleanCurrency(document.getElementById('product_margin').value);
  var markup = cleanCurrency(document.getElementById('product_markup').value);
  if (document.getElementById('full_price_with_tax').value != formatCurrency(highest * margin)) {
    var answer = prompt("<?php echo INV_WHAT_TO_CALCULATE; ?>", '1');
    if (answer == '1')      { update_full_price_incl_tax(true, true, false); }
    else if (answer == '2') { document.getElementById('full_price').value = formatCurrency((highest / (100 - margin)) * 100); update_full_price_incl_tax(false, true, true); update_full_price_incl_tax(true, false, false); }
    else if (answer == '3') { document.getElementById('full_price_with_tax').value = formatCurrency(highest * markup); update_full_price_incl_tax(false, false, true); update_full_price_incl_tax(true, false, false); }
  }
}

function update_full_price_incl_tax(margin, inclTax, fullprice) {
  var highest = 0;
  if (margin) {
    if (!document.getElementById('full_price')) return;
    var costEls = document.getElementsByName("item_cost_array[]");
    var currEls = document.getElementsByName("item_cost_currency_array[]");
    for (var i = 0; i < costEls.length; i++) {
      var curr = currEls[i] ? currEls[i].value : defaultCurrency;
      var v = costToCompany(parseFloat(cleanCurrency(costEls[i].value)), curr);
      if (v > highest) highest = v;
    }
    var sell = cleanCurrency(document.getElementById('full_price').value);
    document.getElementById('product_margin').value = formatCurrency(((sell - highest) / sell) * 100);
    document.getElementById('product_markup').value = formatCurrency(cleanCurrency(document.getElementById('full_price_with_tax').value) / highest);
  }
  if (inclTax) {
    var fp = document.getElementById('full_price'), tax_sel = document.getElementById('item_taxable');
    if (fp && tax_sel && fp.value !== '' && tax_sel.value !== '') {
      document.getElementById('full_price_with_tax').value = formatCurrency(cleanCurrency(fp.value) * (1 + (tax_rates[tax_sel.value].rate / 100)));
    } else if (document.getElementById('full_price_with_tax')) {
      document.getElementById('full_price_with_tax').value = '';
    }
  }
  if (fullprice) {
    var fpt = document.getElementById('full_price_with_tax'), tax_sel = document.getElementById('item_taxable');
    if (fpt && tax_sel && fpt.value !== '' && tax_sel.value !== '') {
      document.getElementById('full_price').value = formatCurrency(cleanCurrency(fpt.value) / (1 + (tax_rates[tax_sel.value].value / 100)));
    } else if (document.getElementById('full_price')) {
      document.getElementById('full_price').value = '';
    }
  }
  if (document.getElementById('full_price_with_tax')) {
    document.getElementById('full_price_with_tax').value = formatCurrency(cleanCurrency(document.getElementById('full_price_with_tax').value));
    var tax_sel = document.getElementById('item_taxable');
    if (tax_sel) {
      var expected = formatCurrency(cleanCurrency(document.getElementById('full_price').value) * (1 + (tax_rates[tax_sel.value].rate / 100)));
      var fpt_el = document.getElementById('full_price_with_tax');
      if (fpt_el.value !== expected) {
        fpt_el.style.background = '#FF3300';
        fpt_el.title = '<?php echo addslashes(INV_CALCULATING_ERROR); ?>' + expected;
      } else {
        fpt_el.style.background = '#FFFFFF';
        fpt_el.removeAttribute('title');
      }
    }
  }
}

function priceMgr(id, cost, price, type, vendor_id) {
  if (!cost)  cost  = document.getElementById('item_cost')  ? cleanCurrency(document.getElementById('item_cost').value)  : 0;
  if (!price) price = document.getElementById('full_price') ? cleanCurrency(document.getElementById('full_price').value) : 0;
  window.open('index.php?module=inventory&page=popup_price_mgr&iID='+id+'&cost='+cost+'&price='+price+'&type='+type+'&vendor_id='+vendor_id,"price_mgr","width=860,height=500,resizable=1,scrollbars=1,top=150,left=200");
}

function InventoryList(rowCnt) {
  if (rowCnt === '') return;
  var url = "index.php?module=inventory&page=popup_inv&rowID="+rowCnt;
  var sku_el = document.getElementById('sku_'+rowCnt);
  if (sku_el && sku_el.value) url += "&search_text=" + sku_el.value;
  window.open(url,"inventory","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

// ── Master Stock functions ──────────────────────────────────────────

function masterStockTitle(id) {
  var el = document.getElementById('attr_name_'+id);
  if (el) document.getElementById('sku_list').rows[1].cells[id+1].textContent = el.value;
}

function masterStockBuildList(action, id) {
  switch (action) {
    case 'add':
      var idEl = document.getElementById('attr_id_'+id), descEl = document.getElementById('attr_desc_'+id);
      if (!idEl.value || !descEl.value) { alert('<?php echo addslashes(JS_MS_INVALID_ENTRY); ?>'); return; }
      if (descEl.value.indexOf(',') !== -1) { alert('<?php echo addslashes(JS_MS_COMMA_NOT_ALLOWED); ?>'); return; }
      if (descEl.value.indexOf(':') !== -1) { alert('<?php echo addslashes(JS_MS_COLON_NOT_ALLOWED); ?>'); return; }
      var newOpt = document.createElement("option");
      newOpt.text = idEl.value + ' : ' + descEl.value;
      newOpt.value = idEl.value + ':' + descEl.value;
      document.getElementById('attr_index_'+id).options.add(newOpt);
      idEl.value = ''; descEl.value = '';
      break;
    case 'delete':
      if (confirm('<?php echo addslashes(INV_MSG_DELETE_INV_ITEM); ?>')) {
        var sel = document.getElementById('attr_index_'+id);
        sel.remove(sel.selectedIndex);
      }
      break;
  }
  masterStockBuildSkus();
}

function masterStockBuildSkus() {
  var ms_attr_0 = '', ms_attr_1 = '', sku = document.getElementById('sku').value;
  var idx0 = document.getElementById('attr_index_0'), idx1 = document.getElementById('attr_index_1');
  var body = document.getElementById('sku_list_body');
  while (body.rows.length > 0) body.deleteRow(0);
  if (idx0.length) {
    for (var i = 0; i < idx0.length; i++) {
      var a0 = idx0.options[i].value.split(':');
      ms_attr_0 += idx0.options[i].value + ',';
      if (idx1.length) {
        for (var j = 0; j < idx1.length; j++) {
          var a1 = idx1.options[j].value.split(':');
          ms_attr_1 += idx1.options[j].value + ',';
          insertTableRow(sku+'-'+a0[0]+a1[0], a0[1], a1[1]);
        }
      } else {
        insertTableRow(sku+'-'+a0[0], a0[1], '');
      }
    }
    for (var j = 0; j < idx1.length; j++) ms_attr_1 += idx1.options[j].value + ',';
  } else {
    insertTableRow('', '', '');
  }
  document.getElementById('ms_attr_0').value = ms_attr_0;
  document.getElementById('ms_attr_1').value = ms_attr_1;
}

function insertTableRow(v0, v1, v2) {
  var body = document.getElementById('sku_list_body');
  for (var r = 0; r < body.rows.length; r++) {
    if (body.rows[r].cells[0].textContent == v0) return;
  }
  var row = body.insertRow(-1);
  [v0, '', v1, v2, 0, 0, 0, 0, 0, 0, 0].forEach(function(val, idx) {
    var cell = row.insertCell(-1);
    cell.textContent = val;
    if (idx > 3) cell.style.textAlign = (idx >= 9) ? 'right' : 'center';
  });
}

// ── BOM functions ────────────────────────────────────────────────────

function addBOMRow() {
  var body = document.getElementById("bom_table_body");
  var newRow = body.insertRow(-1);
  var rowCnt = newRow.rowIndex;
  var cells = [
    '<td class="text-center"><?php echo str_replace("'", "\'", html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\''.TEXT_DELETE_ENTRY.'\')) this.closest(\'tr\').remove();bomTotalValues();"')); ?></td>',
    '<td><input type="hidden" name="id_'+rowCnt+'" id="id_'+rowCnt+'" value="">' +
    '<input type="text" class="form-control form-control-sm d-inline-block" style="width:auto" name="assy_sku[]" id="sku_'+rowCnt+'" value="" size="<?php echo (MAX_INVENTORY_SKU_LENGTH + 1); ?>" onchange="bom_guess('+rowCnt+')" maxlength="<?php echo MAX_INVENTORY_SKU_LENGTH; ?>">&nbsp;' +
    buildIcon(icon_path+"16x16/actions/system-search.png", text_sku, 'align="top" style="cursor:pointer" onclick="InventoryList('+rowCnt+')"') + '&nbsp;</td>',
    '<td><input type="text" class="form-control form-control-sm" name="assy_desc[]" id="desc_'+rowCnt+'" value="" size="40" maxlength="64"></td>',
    '<td><input type="text" class="form-control form-control-sm text-end" name="assy_qty[]" id="qty_'+rowCnt+'" value="0" size="6" maxlength="5"></td>',
    '<td><input type="text" class="form-control form-control-sm text-end" name="assy_item_cost[]" id="item_cost_'+rowCnt+'" value="0" size="6" maxlength="5"></td>',
    '<td><input type="text" class="form-control form-control-sm text-end" name="assy_sales_price[]" id="sales_price_'+rowCnt+'" value="0" size="6" maxlength="5"></td>',
  ];
  newRow.innerHTML = cells.join('');
  return rowCnt;
}

function loadSkuDetails(iID, rID) {
  fetchXml('index.php?module=inventory&page=ajax&op=inv_details&fID=skuDetails&iID='+iID+'&rID='+rID, processSkuDetails);
}

function processSkuDetails(xml) {
  xml = parseXml(xml); if (!xml) return;
  var rowID = xmlText(xml, "rID"), sku = xmlText(xml, "sku");
  var type  = xmlText(xml, "inventory_type");
  if (!sku || type == 'ms' || type == 'mb') { InventoryList(rowID); return; }
  document.getElementById('sku_'+rowID).value          = sku;
  document.getElementById('desc_'+rowID).value         = xmlText(xml, "description_short");
  if (document.getElementById('qty_'+rowID).value == 0) document.getElementById('qty_'+rowID).value = 1;
  document.getElementById('item_cost_'+rowID).value    = formatCurrency(xmlText(xml, "item_cost"));
  document.getElementById('sales_price_'+rowID).value  = formatCurrency(xmlText(xml, "sales_price"));
  bomTotalValues();
}

function InventoryProp(rID) {
  var sku = document.getElementById('sku_'+rID) ? document.getElementById('sku_'+rID).value : '';
  if (sku) window.open("index.php?module=inventory&page=main&action=properties&sku="+sku+'&rowID='+rID,"inventory","width=800,height=600,resizable=1,scrollbars=1,top=50,left=50");
}

function ajaxAssyCost() {
  var id = document.getElementById('rowSeq') ? document.getElementById('rowSeq').value : '';
  if (id) fetchXml('index.php?module=inventory&page=ajax&op=inv_details&fID=bomCost&iID='+id, showBOMCost);
}

function showBOMCost(xml) {
  xml = parseXml(xml); if (!xml) return;
  var cost = xmlText(xml, "assy_cost");
  if (cost) alert('<?php echo addslashes(JS_INV_TEXT_ASSY_COST); ?>'+formatPrecise(cost));
}

function bom_guess(rID) {
  var sku = document.getElementById('sku_'+rID) ? document.getElementById('sku_'+rID).value : '';
  if (sku) fetchXml('index.php?module=inventory&page=ajax&op=inv_details&fID=skuDetails&sku='+sku+'&strict=1&rID='+rID, processSkuDetails);
}

function bomTotalValues() {
  var rows = document.getElementById('bom_table_body').rows, itemCost = 0, salesPrice = 0;
  for (var i = 1; i <= rows.length; i++) {
    var qEl = document.getElementById('qty_'+i), cEl = document.getElementById('item_cost_'+i), sEl = document.getElementById('sales_price_'+i);
    if (!qEl) continue;
    var qty = parseFloat(qEl.value) || 0;
    itemCost   += qty * parseFloat(cleanCurrency(cEl ? cEl.value : '0'));
    salesPrice += qty * parseFloat(cleanCurrency(sEl ? sEl.value : '0'));
  }
  var tc = document.getElementById('total_item_cost'), ts = document.getElementById('total_sales_price');
  if (tc) tc.value = formatCurrency(itemCost);
  if (ts) ts.value = formatCurrency(salesPrice);
}

// ── Where Used ───────────────────────────────────────────────────────

function ajaxWhereUsed() {
  var id = document.getElementById('rowSeq') ? document.getElementById('rowSeq').value : '';
  if (id) fetchXml('index.php?module=inventory&page=ajax&op=inv_details&fID=whereUsed&iID='+id, showWhereUsed);
}

function showWhereUsed(xml) {
  xml = parseXml(xml); if (!xml) return;
  var text = '';
  Array.from(xml.getElementsByTagName("sku_usage")).forEach(function(el) { text += xmlText(el, "text_line") + "\n"; });
  if (text) alert(text);
}

// ── Vendor rows ───────────────────────────────────────────────────────

function fetchVendorCurrency(sel) {
  var cID = sel.value;
  if (!cID) return;
  var row = sel.closest('tr');
  var currSel = row.querySelector('select[name="item_cost_currency_array[]"]');
  if (!currSel) return;
  fetchXml('index.php?module=contacts&page=ajax&op=load_contact&cID=' + encodeURIComponent(cID), function(xml) {
    var code = xmlText(xml, 'currency_code');
    if (code) currSel.value = code;
  });
}

function addVendorRow() {
  var tbody = document.getElementById('vendor_table_tbody');
  var newRow = tbody.insertRow(-1);
  var rowId  = Math.floor(Math.random() * 100000);
  newRow.id  = 'row_id_' + rowId;
  newRow.className = (newRow.rowIndex % 2 == 0) ? 'even' : 'odd';

  var deleteCell = newRow.insertCell(-1);
  deleteCell.innerHTML = buildIcon(icon_path+'16x16/emblems/emblem-unreadable.png', image_delete_text,
    'onclick="if(confirm(\'<?php echo addslashes(INV_MSG_DELETE_VENDOR_ROW); ?>\'))removeVendorRow('+rowId+');"');

  <?php
  $cells = [
    isset($cInfo->vendor_id)           ? str_replace("'", "\'", html_pull_down_menu('vendor_id_array[]', gen_get_contact_array_by_type('v'), $cInfo->vendor_id ?? '', 'class="form-select form-select-sm" onchange="fetchVendorCurrency(this)"')) : '',
    isset($cInfo->vendor_id)           ? str_replace("'", "\'", html_input_field('vendor_sku_array[]', '', 'class="form-control form-control-sm" size="16" maxlength="32"')) : '',
    isset($cInfo->description_purchase) ? str_replace("'", "\'", html_textarea_field('description_purchase_array[]', 40, 2, $cInfo->description_purchase ?? '', '', true)) : '',
    isset($cInfo->item_cost)           ? str_replace("'", "\'", html_pull_down_menu('item_cost_currency_array[]', gen_get_pull_down(TABLE_CURRENCIES, false, false, 'code', 'title'), DEFAULT_CURRENCY, 'class="form-select form-select-sm"')) : '',
    isset($cInfo->item_cost)           ? str_replace("'", "\'", html_input_field('item_cost_array[]', $cInfo->item_cost ?? 0, 'onchange="what_to_update();" size="12" maxlength="20" class="text-end"', false)) : '',
    isset($cInfo->item_cost)           ? str_replace("'", "\'", html_input_field('purch_package_quantity_array[]', 1, 'size="6" maxlength="5" class="text-end"')) : '',
    isset($cInfo->purch_taxable)       ? str_replace("'", "\'", html_pull_down_menu('purch_taxable_array[]', $purch_tax_rates ?? [], INVENTORY_DEFAULT_PURCH_TAX)) : '',
    isset($cInfo->price_sheet_v)       ? str_replace("'", "\'", html_pull_down_menu('price_sheet_v_array[]', get_price_sheet_data('v'), '')) : '',
  ];
  foreach ($cells as $cellHtml) {
    echo "var c = newRow.insertCell(-1); c.innerHTML = '" . $cellHtml . "';\n";
  }
  ?>
}

function removeVendorRow(index) {
  var row = document.getElementById('row_id_'+index);
  if (row) row.parentNode.removeChild(row);
}

var descEl = document.getElementById('description_short');
if (descEl) {
  descEl.addEventListener('change', function() {
    var h = document.getElementById('heading_title');
    if (h) h.textContent = '<?php echo addslashes(MENU_HEADING_INVENTORY . ' - ' . TEXT_SKU . '# ' . (isset($cInfo->sku) ? $cInfo->sku : '')); ?> (' + this.value + ')';
  });
}

// ── Filter functions ─────────────────────────────────────────────────

function updateFilter(rowCnt, start) {
  var text = document.getElementById('filter_field'+rowCnt).value;
  var row  = document.getElementById('filter_table').rows[rowCnt];
  switch (SecondField[text]) {
    case 'multi_check_box':
      row.cells[2].innerHTML = '<input type="text" name="filter_criteria[]" readonly id="filter_criteria'+rowCnt+'" value="'+filter_contains+'" class="form-control form-control-sm"/>';
      break;
    default:
      var vals = [filter_equal_to, filter_not_equal_to, filter_like, filter_not_like, filter_bigger_than, filter_less_than];
      var ids  = ['0','1','2','3','4','5'];
      row.cells[2].innerHTML = '<select name="filter_criteria[]" id="filter_criteria'+rowCnt+'" class="form-select form-select-sm"></select>';
      buildSelect('filter_criteria'+rowCnt, vals, ids);
  }
  switch (SecondField[text]) {
    case 'drop_down': case 'multi_check_box': case 'radio':
      row.cells[3].innerHTML = '<select name="filter_value[]" id="filter_value'+rowCnt+'" class="form-select form-select-sm"></select>';
      buildSelect('filter_value'+rowCnt, SecondFieldId[text], SecondFieldValue[text]);
      break;
    case 'check_box':
      row.cells[3].innerHTML = '<select name="filter_value[]" id="filter_value'+rowCnt+'" class="form-select form-select-sm"></select>';
      buildSelect('filter_value'+rowCnt, [text_no, text_yes], ['0','1']);
      break;
    default:
      if (!start) {
        var cur = document.getElementById('filter_value'+rowCnt);
        var curVal = (cur && cur.tagName === 'INPUT') ? cur.value : '';
        row.cells[3].innerHTML = '<input type="text" name="filter_value[]" id="filter_value'+rowCnt+'" class="form-control form-control-sm" size="32" maxlength="64" value="'+curVal+'"/>';
      } else {
        row.cells[3].innerHTML = '<input type="text" name="filter_value[]" id="filter_value'+rowCnt+'" class="form-control form-control-sm" size="32" maxlength="64"/>';
      }
  }
}

function addFilterRow() {
  var tbody  = document.getElementById('filter_table_body');
  var newRow = tbody.insertRow(-1);
  var rowCnt = newRow.rowIndex;
  newRow.id  = rowCnt;
  newRow.innerHTML =
    '<td class="text-center">' + buildIcon(icon_path+'16x16/emblems/emblem-unreadable.png', image_delete_text, 'onclick="removeFilterRow('+rowCnt+')"') + '</td>' +
    '<td><select name="filter_field[]" id="filter_field'+rowCnt+'" onchange="updateFilter('+rowCnt+',false)" class="form-select form-select-sm"></select></td>' +
    '<td></td><td></td>';
  buildSelect('filter_field'+rowCnt, FirstValue, FirstId);
  updateFilter(rowCnt, true);
}

function buildSelect(selId, values, ids) {
  var sel = document.getElementById(selId);
  for (var i = 0; i < values.length; i++) {
    var opt = document.createElement('option');
    opt.text  = values[i];
    opt.value = ids[i];
    sel.options.add(opt);
  }
}

function removeFilterRow(rowCnt) {
  var row = document.getElementById(rowCnt);
  if (row) row.parentNode.removeChild(row);
}

function TableStartValues(field, criteria, value) {
  addFilterRow();
  var rowCnt = document.getElementById('filter_table_body').rows.length;
  document.getElementById('filter_field'+rowCnt).value    = field;
  updateFilter(rowCnt, true);
  document.getElementById('filter_criteria'+rowCnt).value = criteria;
  document.getElementById('filter_value'+rowCnt).value    = value;
}

<?php if (($include_template ?? '') == 'template_main.php') { ?>
document.addEventListener('keydown', function(e) {
  if (e.keyCode == 13) submitToDo('filter');
});
<?php } ?>
</script>
