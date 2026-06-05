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
//  Path: /modules/inventory/pages/adjustments/js_include.php
//
?>
<script type="text/javascript">
var securityLevel    = <?php echo $security_level; ?>;
var text_search      = '<?php echo TEXT_SEARCH; ?>';
var ItemIsInactive   = '<?php echo ITEM_IS_INACTIVE; ?>';
var serial_num_prompt= '<?php echo ORD_JS_SERIAL_NUM_PROMPT; ?>';

<?php echo js_calendar_init($cal_adj); ?>

function init() {
<?php if ($_REQUEST['action'] == 'edit') echo '  EditAdjustment(' . $oID . ');'; ?>
}

function check_form() {
  return true;
}

function clearForm() {
  document.getElementById('id').value                  = 0;
  document.getElementById('store_id').value            = <?php echo $_SESSION['admin_prefs']['def_store_id'] ? $_SESSION['admin_prefs']['def_store_id'] : 0; ?>;
  document.getElementById('purchase_invoice_id').value = '';
  document.getElementById('post_date').value           = '<?php echo date(DATE_FORMAT); ?>';
  document.getElementById('adj_reason').value          = '';
  while (document.getElementById('item_table').rows.length > 0) removeInvRow(1);
  addInvRow();
}

function InventoryList(rowCnt) {
  var bID = document.getElementById('store_id').value;
  var sku = document.getElementById('sku_' + rowCnt).value;
  window.open('index.php?module=inventory&page=popup_inv&rowID=' + rowCnt + '&type=v&storeID=' + bID + '&search_text=' + sku, 'inventory', 'width=700,height=550,resizable=1,scrollbars=1,top=150,left=200');
}

function serialList(id) {
  var rowID = id.replace('serial_', '');
  var qty   = $('#qty_' + rowID).val();
  if (qty == 1) {
    var newChoice = prompt(serial_num_prompt, $('#' + id).val());
    $('#' + id).val(newChoice);
  } else if (qty == -1) {
    var sku     = $('#sku_' + rowID).val();
    var storeID = $('#store_id').val() || '0';
    window.open('index.php?module=inventory&page=popup_serial&def=' + $('#' + id).val() + '&sku=' + sku + '&rowID=' + rowID + '&storeID=' + storeID, 'serialize', 'width=700,height=550,resizable=1,scrollbars=1,top=150,left=200');
  } else {
    alert('<?php echo addslashes('Quantity must be 1 or -1 for serialized items!'); ?>');
  }
}

function loadSkuDetails(iID, rowCnt, strict) {
  var bID = document.getElementById('store_id').value;
  var sku = iID == 0 ? document.getElementById('sku_' + rowCnt).value : '';
  var tempRowCnt = document.getElementById('item_table').rows.length;
  if ((iID && rowCnt == tempRowCnt) || (document.getElementById('sku_' + tempRowCnt).value != text_search && document.getElementById('sku_' + tempRowCnt).value != '')) {
    var value = addInvRow();
    document.getElementById('sku_' + value).focus();
  }
  if (sku == text_search) return;
  $.ajax({
    type: 'GET',
    url: 'index.php?module=inventory&page=ajax&op=inv_details&iID=' + iID + '&sku=' + sku + '&bID=' + bID + '&rID=' + rowCnt + '&strict=' + strict,
    dataType: 'xml',
    error: function(xhr, textStatus, errorThrown) {
      alert('Ajax Error: ' + xhr.responseText + '\nTextStatus: ' + textStatus + '\nErrorThrown: ' + errorThrown);
    },
    success: processSkuStock
  });
}

function processSkuStock(sXml) {
  var xml = parseXml(sXml);
  if (!xml) return;
  if (!$(xml).find('rID').text()) return;
  var rCnt = $(xml).find('rID').text();
  var sku  = $(xml).find('sku').first().text();
  if (!sku || $(xml).find('inventory_type').text() == 'ms' || $(xml).find('inventory_type').text() == 'mb') {
    InventoryList(rCnt);
    return;
  }
  var rQty = parseFloat(document.getElementById('qty_' + rCnt).value);
  if (isNaN(rQty)) {
    rQty = 1;
    document.getElementById('qty_' + rCnt).value = rQty;
  }
  var id = document.getElementById('id').value;
  if (!id) {
    document.getElementById('sku_' + rCnt).value = sku;
    document.getElementById('sku_' + rCnt).style.color = '';
    if ($(xml).find('inactive').text() == 1) {
      document.getElementById('sku_' + rCnt).style.backgroundColor = 'pink';
      document.getElementById('sku_' + rCnt).title = ItemIsInactive;
    } else {
      document.getElementById('sku_' + rCnt).style.backgroundColor = '';
      document.getElementById('sku_' + rCnt).removeAttribute('title');
    }
    document.getElementById('stock_' + rCnt).value  = parseFloat($(xml).find('branch_qty_in_stock').text());
    document.getElementById('price_' + rCnt).value  = formatCurrency($(xml).find('item_cost').text());
    document.getElementById('serial_' + rCnt).value = '';
    document.getElementById('desc_' + rCnt).value   = $(xml).find('description_short').text();
  } else {
    var qty = document.getElementById('qty_' + rCnt).value;
    document.getElementById('stock_' + rCnt).value  = parseFloat($(xml).find('branch_qty_in_stock').text()) - qty;
  }
  document.getElementById('acct_' + rCnt).value     = $(xml).find('account_inventory_wage').text();
  document.getElementById('def_cost_' + rCnt).value = formatCurrency($(xml).find('item_cost').text());
  if ($(xml).find('inventory_type').text() == 'sr' || $(xml).find('inventory_type').text() == 'sa') {
    document.getElementById('imgSerial_' + rCnt).style.display = '';
  } else {
    document.getElementById('imgSerial_' + rCnt).style.display = 'none';
  }
  updateBalance();
}

function updateBalance() {
  for (var i = 1; i < document.getElementById('item_table').rows.length + 1; i++) {
    var stock = parseFloat(document.getElementById('stock_' + i).value) || 0;
    var adj   = parseFloat(document.getElementById('qty_' + i).value)   || 0;
    if (adj < 0) {
      document.getElementById('price_' + i).value    = '';
      document.getElementById('price_' + i).readOnly = true;
    } else {
      if (document.getElementById('price_' + i).value == '') document.getElementById('price_' + i).value = document.getElementById('def_cost_' + i).value;
      document.getElementById('price_' + i).readOnly = false;
    }
    document.getElementById('bal_' + i).value = stock + adj;
  }
}

function addInvRow() {
  var newRow = document.getElementById('item_table').insertRow(-1);
  var rowCnt = newRow.rowIndex;
  var skuSize = <?php echo (MAX_INVENTORY_SKU_LENGTH + 1); ?>;
  var skuMax  = <?php echo MAX_INVENTORY_SKU_LENGTH; ?>;
  newRow.className = (rowCnt % 2) ? 'odd' : 'even';

  var cells = [
    buildIcon(icon_path + '16x16/emblems/emblem-unreadable.png', '<?php echo TEXT_DELETE; ?>', 'style="cursor:pointer" onclick="if (confirm(\'<?php echo addslashes(TEXT_ROW_DELETE_ALERT); ?>\')) removeInvRow(' + rowCnt + ');"'),

    '<input type="text" name="sku_' + rowCnt + '" id="sku_' + rowCnt + '" value="' + text_search + '" class="form-control form-control-sm d-inline-block" style="width:auto" size="' + skuSize + '" maxlength="' + skuMax + '" onfocus="clearField(\'sku_' + rowCnt + '\', \'' + text_search + '\')" onblur="setField(\'sku_' + rowCnt + '\', \'' + text_search + '\'); loadSkuDetails(0,' + rowCnt + ',1)">'
    + buildIcon(icon_path + '16x16/actions/system-search.png', '<?php echo TEXT_SEARCH; ?>', 'style="cursor:pointer" onclick="InventoryList(' + rowCnt + ')"')
    + buildIcon(icon_path + '16x16/actions/tab-new.png', '<?php echo TEXT_SERIAL_NUMBER; ?>', 'id="imgSerial_' + rowCnt + '" style="cursor:pointer;display:none;" onclick="serialList(\'serial_' + rowCnt + '\')"')
    + '<input type="hidden" name="serial_' + rowCnt + '" id="serial_' + rowCnt + '" value="">'
    + '<input type="hidden" name="acct_' + rowCnt + '" id="acct_' + rowCnt + '" value="">'
    + '<input type="hidden" name="def_cost_' + rowCnt + '" id="def_cost_' + rowCnt + '" value="">',

    '<input type="text" name="stock_' + rowCnt + '" id="stock_' + rowCnt + '" readonly class="form-control form-control-sm text-end" style="width:70px">',
    '<input type="text" name="qty_' + rowCnt + '" id="qty_' + rowCnt + '" class="form-control form-control-sm text-end" style="width:70px" onchange="updateBalance()">',
    '<input type="text" name="price_' + rowCnt + '" id="price_' + rowCnt + '" class="form-control form-control-sm text-end" style="width:100px">',
    '<input type="text" name="bal_' + rowCnt + '" id="bal_' + rowCnt + '" readonly class="form-control form-control-sm text-end" style="width:70px">',
    '<input type="text" name="desc_' + rowCnt + '" id="desc_' + rowCnt + '" class="form-control form-control-sm">',
  ];

  for (var i = 0; i < cells.length; i++) {
    var cell = newRow.insertCell(-1);
    cell.innerHTML = cells[i];
    if (i === 1) cell.style.whiteSpace = 'nowrap';
  }
  setField('sku_' + rowCnt, text_search);
  return rowCnt;
}

function removeInvRow(delRowCnt) {
  var tableLen = document.getElementById('item_table').rows.length;
  for (var i = delRowCnt; i < tableLen; i++) {
    document.getElementById('sku_' + i).value      = document.getElementById('sku_' + (i + 1)).value;
    document.getElementById('imgSerial_' + i).style.display = document.getElementById('imgSerial_' + (i + 1)).style.display;
    document.getElementById('serial_' + i).value   = document.getElementById('serial_' + (i + 1)).value;
    document.getElementById('acct_' + i).value     = document.getElementById('acct_' + (i + 1)).value;
    document.getElementById('def_cost_' + i).value = document.getElementById('def_cost_' + (i + 1)).value;
    document.getElementById('stock_' + i).value    = document.getElementById('stock_' + (i + 1)).value;
    document.getElementById('qty_' + i).value      = document.getElementById('qty_' + (i + 1)).value;
    document.getElementById('price_' + i).value    = document.getElementById('price_' + (i + 1)).value;
    document.getElementById('bal_' + i).value      = document.getElementById('bal_' + (i + 1)).value;
    document.getElementById('desc_' + i).value     = document.getElementById('desc_' + (i + 1)).value;
    document.getElementById('sku_' + i).style.color = (document.getElementById('sku_' + i).value == text_search) ? inactive_text_color : document.getElementById('sku_' + (i + 1)).style.color;
  }
  document.getElementById('item_table').deleteRow(-1);
  updateBalance();
}

function OpenAdjList() {
  window.open('index.php?module=inventory&page=popup_adj&list=1&form=inv_adj', 'inv_adj_open', 'width=700,height=550,resizable=1,scrollbars=1,top=150,left=200');
}

function EditAdjustment(rID) {
  $.ajax({
    type: 'GET',
    url: 'index.php?module=phreebooks&page=ajax&op=load_record&rID=' + rID,
    dataType: 'xml',
    error: function(xhr, textStatus, errorThrown) {
      alert('Ajax Error: ' + xhr.responseText + '\nTextStatus: ' + textStatus + '\nErrorThrown: ' + errorThrown);
    },
    success: processEditAdjustment
  });
}

function processEditAdjustment(sXml) {
  var xml = parseXml(sXml);
  if (!xml) return;
  var id = $(xml).find('id').first().text();
  document.getElementById('id').value                  = id;
  document.getElementById('store_id').value            = $(xml).find('store_id').text();
  document.getElementById('purchase_invoice_id').value = $(xml).find('purchase_invoice_id').text();
  document.getElementById('post_date').value           = formatDate($(xml).find('post_date').first().text());
  if (id && securityLevel < 3) removeElement('tb_main_0', 'tb_icon_save');
  var rowCnt = 1;
  $(xml).find('items').each(function() {
    var type = $(this).find('gl_type').text();
    switch (type) {
      case 'ttl':
        document.getElementById('adj_reason').value = $(this).find('description').text();
        document.getElementById('gl_acct').value    = $(this).find('gl_account').text();
        break;
      case 'adj':
        var sku = $(this).find('sku').text();
        var qty = $(this).find('qty').text();
        document.getElementById('sku_' + rowCnt).value      = sku;
        document.getElementById('sku_' + rowCnt).style.color= (sku == text_search) ? inactive_text_color : '';
        document.getElementById('serial_' + rowCnt).value   = $(this).find('serialize_number').text();
        document.getElementById('qty_' + rowCnt).value      = qty;
        document.getElementById('price_' + rowCnt).value    = formatCurrency($(this).find('debit_amount').text() / qty);
        document.getElementById('desc_' + rowCnt).value     = $(this).find('description').text();
        loadSkuDetails(0, rowCnt, 1);
        rowCnt = addInvRow() - 1;
    }
  });
}

function checkEnterEvent(event, row) {
  if (event.keyCode == 13) loadSkuDetails(0, row, false);
}
</script>
