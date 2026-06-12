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
//  Path: /modules/inventory/pages/transfer/js_include.php
//
?>
<script type="text/javascript">
var securityLevel     = <?php echo $security_level; ?>;
var text_search       = '<?php echo TEXT_SEARCH; ?>';
var serial_num_prompt = '<?php echo ORD_JS_SERIAL_NUM_PROMPT; ?>';
var SalesOrderForItem = '<?php echo THERE_IS_SALES_ORDER_FOR_THIS_ITEM; ?>';
var ItemIsInactive    = '<?php echo ITEM_IS_INACTIVE; ?>';
var ItemOutOfStock    = '<?php echo ITEM_IS_OUT_OF_STOCK; ?>';

<?php echo js_calendar_init($cal_xfr); ?>

function init() {
<?php if ($_REQUEST['action'] == 'edit') echo '  EditTransfer(' . $oID . ')'; ?>
}

function check_form() {
  var numRows = document.getElementById('item_table').rows.length;
  for (var i=1; i<numRows; i++) {
    qty = parseFloat(document.getElementById('qty_'+i).value);
    if (qty <= 0) {
      alert('All quantities for a transfer entry must be greater than zero!');
      return false;
    }
  }
  return true;
}

function clearForm() {
  document.getElementById('id').value                  = 0;
  document.getElementById('ref_id').value              = 0;
  document.getElementById('source_store_id').value     = <?php echo $_SESSION['admin_prefs']['def_store_id'] ? $_SESSION['admin_prefs']['def_store_id'] : 0; ?>;
  document.getElementById('dest_store_id').value       = <?php echo $_SESSION['admin_prefs']['def_store_id'] ? $_SESSION['admin_prefs']['def_store_id'] : 0; ?>;
  document.getElementById('purchase_invoice_id').value = '';
  document.getElementById('post_date').value           = '<?php echo date('Y-m-d'); ?>';
  document.getElementById('adj_reason').value          = '';
  while (document.getElementById('item_table').rows.length > 0) removeInvRow(1);
  addInvRow();
}

function InventoryList(rowCnt) {
  var bID = document.getElementById('source_store_id').value;
  var sku = document.getElementById('sku_'+rowCnt).value;
  window.open("index.php?module=inventory&page=popup_inv&rowID="+rowCnt+"&type=v&storeID="+bID+"&f1=cog&search_text="+sku,"inventory","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

function serialList(id) {
  var rowID = id.replace("serial_", "");
  var qty   = document.getElementById("qty_"+rowID).value;
  if (qty == 1) {
    var newChoice = prompt(serial_num_prompt, document.getElementById(id).value);
    document.getElementById(id).value = newChoice;
  } else if (qty == -1) {
    var curDef  = document.getElementById(id).value;
    var sku     = document.getElementById("sku_"+rowID).value;
    var storeID = document.getElementById("source_store_id").value || '0';
    window.open("index.php?module=inventory&page=popup_serial&def="+curDef+"&sku="+sku+"&rowID="+rowID+"&storeID="+storeID,"serialize","width=700px,height=550px,resizable=1,scrollbars=1,top=150,left=200");
  } else {
    alert('<?php echo "Quantity must be 1 or -1 for serialized items!"; ?>');
  }
}

function loadSkuDetails(iID, rowCnt) {
  var bID = document.getElementById('source_store_id').value;
  var sku = iID==0 ? document.getElementById('sku_'+rowCnt).value : '';
  fetchXml('index.php?module=inventory&page=ajax&op=inv_details&iID='+iID+'&sku='+sku+'&bID='+bID+'&rID='+rowCnt, processSkuStock);
}

function processSkuStock(xml) {
  var stock;
  xml = parseXml(xml);
  if (!xml) return;
  var rCnt = xmlText(xml, "rID");
  if (!rCnt) return;
  var rQty = parseFloat(document.getElementById('qty_'+rCnt).value);
  if (isNaN(rQty)) { rQty = 1; document.getElementById('qty_'+rCnt).value = rQty; }
  var isEdit = document.getElementById('id').value ? true : false;
  var bqis = parseFloat(xmlText(xml, "branch_qty_in_stock"));
  stock = isEdit ? bqis + rQty : bqis;
  document.getElementById('sku_'+rCnt).value       = xmlText(xml, "sku");
  document.getElementById('sku_'+rCnt).style.color = '';
  document.getElementById('stock_'+rCnt).value     = stock;
  document.getElementById('serial_'+rCnt).value    = '';
  document.getElementById('desc_'+rCnt).value      = xmlText(xml, "description_short");
  var invType = xmlText(xml, "inventory_type");
  document.getElementById('imgSerial_'+rCnt).style.display = (invType == 'sr' || invType == 'sa') ? '' : 'none';
  updateBalance();
  rowCnt = document.getElementById('item_table').rows.length;
  var qty = document.getElementById('qty_'+rowCnt).value;
  var sku = document.getElementById('sku_'+rowCnt).value;
  if (qty != '' && sku != '' && sku != text_search) rowCnt = addInvRow();
}

function updateBalance() {
  for (var i=1; i<document.getElementById('item_table').rows.length+1; i++) {
    var stock = parseFloat(document.getElementById('stock_'+i).value);
    if (isNaN(stock)) stock = 0;
    var adj = parseFloat(document.getElementById('qty_'+i).value);
    if (isNaN(adj)) adj = 0;
    document.getElementById('bal_'+i).value = stock - adj;
  }
}

function addInvRow() {
  var wrap   = new Array();
  var cell   = new Array();
  var newRow = document.getElementById("item_table").insertRow(-1);
  var rowCnt = newRow.rowIndex;
  cell[0]  = buildIcon(icon_path+'16x16/emblems/emblem-unreadable.png', '<?php echo TEXT_DELETE; ?>', 'style="cursor:pointer" onclick="if (confirm(\'<?php echo TEXT_ROW_DELETE_ALERT; ?>\')) removeInvRow('+rowCnt+');"');
  cell[1]  = '    <input type="text" name="qty_'+rowCnt+'" id="qty_'+rowCnt+'" style="text-align:right" size="6" maxlength="5" onchange="updateBalance('+rowCnt+')">';
  cell[2]  = '    <input type="text" name="stock_'+rowCnt+'" id="stock_'+rowCnt+'" readonly="readonly" style="text-align:right" size="6" maxlength="5">';
  cell[3]  = '    <input type="text" name="bal_'+rowCnt+'" id="bal_'+rowCnt+'" readonly="readonly" style="text-align:right" size="6" maxlength="5">';
  cell[4]  = '    <input type="text" name="sku_'+rowCnt+'" id="sku_'+rowCnt+'" value="'+text_search+'" size="<?php echo (MAX_INVENTORY_SKU_LENGTH + 1); ?>" maxlength="<?php echo MAX_INVENTORY_SKU_LENGTH; ?>" onfocus="clearField(\'sku_'+rowCnt+'\', \''+text_search+'\')" onblur="setField(\'sku_'+rowCnt+'\', \''+text_search+'\'); loadSkuDetails(0, '+rowCnt+')">';
  cell[4] += buildIcon(icon_path+'16x16/actions/system-search.png', '<?php echo TEXT_SEARCH; ?>', 'style="cursor:pointer" onclick="InventoryList('+rowCnt+')"');
  cell[4] += buildIcon(icon_path+'16x16/actions/tab-new.png', '<?php echo TEXT_SERIAL_NUMBER; ?>', 'id="imgSerial_'+rowCnt+'" style="cursor:pointer; display:none;" onclick="serialList(\'serial_'+rowCnt+'\')"');
  cell[4] += '<input type="hidden" name="serial_'+rowCnt+'" id="serial_'+rowCnt+'" value="" />';
  cell[5]  = '    <input type="text" name="desc_'+rowCnt+'" id="desc_'+rowCnt+'" size="64" maxlength="63">';
  wrap[4]  = 'nowrap';
  for (var i=0; i<cell.length; i++) {
    newCell = newRow.insertCell(-1);
    newCell.innerHTML = cell[i];
    if (wrap[i]) newCell.nowrap = wrap[i];
  }
  setField('sku_'+rowCnt, text_search);
  return rowCnt;
}

function removeInvRow(delRowCnt) {
  var glIndex = delRowCnt;
  for (var i=delRowCnt; i<document.getElementById("item_table").rows.length; i++) {
    document.getElementById('qty_'+i).value    = document.getElementById('qty_'+(i+1)).value;
    document.getElementById('stock_'+i).value  = document.getElementById('stock_'+(i+1)).value;
    document.getElementById('bal_'+i).value    = document.getElementById('bal_'+(i+1)).value;
    document.getElementById('sku_'+i).value    = document.getElementById('sku_'+(i+1)).value;
    document.getElementById('desc_'+i).value   = document.getElementById('desc_'+(i+1)).value;
    document.getElementById('imgSerial_'+i).style.display = document.getElementById('imgSerial_'+(i+1)).style.display === 'none' ? 'none' : '';
    document.getElementById('serial_'+i).value = document.getElementById('serial_'+(i+1)).value;
    document.getElementById('acct_'+i).value   = document.getElementById('acct_'+(i+1)).value;
    document.getElementById('sku_'+i).style.color = (document.getElementById('sku_'+i).value == text_search) ? inactive_text_color : document.getElementById('sku_'+(i+1)).style.color;
    glIndex++;
  }
  document.getElementById("item_table").deleteRow(-1);
  updateBalance();
}

function OpenXfrList() {
  window.open("index.php?module=inventory&page=popup_adj&list=1&adj_type=xfr","inv_adj_open","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

function EditAdjustment(rID) {
  fetchXml('index.php?module=phreebooks&page=ajax&op=load_record&rID='+rID, processEditTransfer);
}

function processEditTransfer(xml) {
  var sku, qty;
  xml = parseXml(xml);
  if (!xml) return;
  var id = xmlText(xml, "id");
  document.getElementById('id').value                  = id;
  document.getElementById('ref_id').value              = xmlText(xml, "so_po_ref_id");
  document.getElementById('source_store_id').value     = xmlText(xml, "store_id");
  document.getElementById('dest_store_id').value       = xmlText(xml, "bill_acct_id");
  document.getElementById('purchase_invoice_id').value = xmlText(xml, "purchase_invoice_id");
  document.getElementById('post_date').value           = xmlText(xml, "post_date");
  if (id && securityLevel < 3) removeElement('tb_main_0', 'tb_icon_save');
  var rowCnt = 1;
  Array.from(xml.getElementsByTagName("items")).forEach(function(item) {
    var type = xmlText(item, "gl_type");
    switch (type) {
      case 'ttl':
        document.getElementById('adj_reason').value = xmlText(item, "description");
        document.getElementById('gl_acct').value    = xmlText(item, "gl_account");
        break;
      case 'adj':
        sku = xmlText(item, "sku");
        qty = xmlText(item, "qty");
        document.getElementById('sku_'+rowCnt).value       = sku;
        document.getElementById('sku_'+rowCnt).style.color = (sku == text_search) ? inactive_text_color : '';
        document.getElementById('serial_'+rowCnt).value    = xmlText(item, "serialize_number");
        document.getElementById('qty_'+rowCnt).value       = -qty;
        document.getElementById('desc_'+rowCnt).value      = xmlText(item, "description");
        loadSkuDetails(0, rowCnt);
        rowCnt = addInvRow();
      default:
    }
  });
}
</script>
