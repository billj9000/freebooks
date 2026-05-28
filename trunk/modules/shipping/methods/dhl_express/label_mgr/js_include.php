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
//  Path: /modules/shipping/methods/dhl_express/label_mgr/js_include.php
//
?>
<script type="text/javascript">
<!--
var formName          = "<?php echo $_GET['form']; ?>";
var image_delete_text = '<?php echo TEXT_DELETE; ?>';
var image_delete_msg  = '<?php echo SHIPPING_DELETE_CONFIRM; ?>';
var originCountry     = '<?php echo defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB'; ?>';
<?php echo js_calendar_init($cal_ship); ?>

function init() {
  <?php
    if (!$error && ($_REQUEST['action'] == 'label' || $_REQUEST['action'] == 'delete')) {
      echo "  window.opener.location.reload();\n";
      echo "  self.close();\n";
    }
    echo "  document.getElementById('wt_1') && document.getElementById('wt_1').focus();\n";
  ?>
  toggleCustoms();
  updateWeight();
}

function toggleCustoms() {
  var sel = document.getElementById('ship_country_code');
  var destCountry = sel ? sel.value : '';
  var isDomestic = (destCountry === originCountry);
  var display = isDomestic ? 'none' : '';
  var sec = document.getElementById('customs_section');
  if (sec) sec.style.display = display;
  var li = document.getElementById('line_items_section');
  if (li) li.style.display = display;
}

function check_form() {
  var error = 0;
  var error_message = "<?php echo JS_ERROR; ?>";
  if (!document.getElementById('total_weight').value) {
    error_message += '<?php echo SHIPPING_ERROR_WEIGHT_ZERO; ?>' + '\n';
    error = 1;
  }
  if (error == 1) { alert(error_message); return false; }
  return true;
}

function addRow() {
  var newRow = document.getElementById("item_table").insertRow(-1);
  var rowCnt = newRow.rowIndex;
  var cell = [
    '<td align="center">' + buildIcon(icon_path+'16x16/emblems/emblem-unreadable.png', image_delete_text, 'style="cursor:pointer" onclick="if (confirm(\''+image_delete_msg+'\')) removeRow('+rowCnt+');"') + '</td>',
    '<td align="center"><input type="text" name="qty_'+rowCnt+'" id="qty_'+rowCnt+'" value="1" size="5" maxlength="4" style="text-align:right"></td>',
    '<td align="center"><input type="text" name="wt_'+rowCnt+'"  id="wt_'+rowCnt+'"  value="" size="6" maxlength="6" style="text-align:right" onchange="updateWeight('+rowCnt+')"></td>',
    '<td align="center"><input type="text" name="len_'+rowCnt+'" id="len_'+rowCnt+'" value="" size="5" maxlength="4" style="text-align:right"></td>',
    '<td align="center"><input type="text" name="wid_'+rowCnt+'" id="wid_'+rowCnt+'" value="" size="5" maxlength="4" style="text-align:right"></td>',
    '<td align="center"><input type="text" name="hgt_'+rowCnt+'" id="hgt_'+rowCnt+'" value="" size="5" maxlength="4" style="text-align:right"></td>',
    '<td align="center"><input type="text" name="ins_'+rowCnt+'" id="ins_'+rowCnt+'" value="0.00" size="8" maxlength="7" style="text-align:right"></td>',
  ];
  for (var i = 0; i < cell.length; i++) {
    var c = newRow.insertCell(-1);
    c.innerHTML = cell[i];
  }
  return rowCnt;
}

function removeRow(delRowCnt) {
  var tbl = document.getElementById('item_table');
  for (var i = delRowCnt; i < tbl.rows.length - 1; i++) {
    document.getElementById('qty_'+i).value = document.getElementById('qty_'+(i+1)).value;
    document.getElementById('wt_'+i).value  = document.getElementById('wt_'+(i+1)).value;
    document.getElementById('len_'+i).value = document.getElementById('len_'+(i+1)).value;
    document.getElementById('wid_'+i).value = document.getElementById('wid_'+(i+1)).value;
    document.getElementById('hgt_'+i).value = document.getElementById('hgt_'+(i+1)).value;
    document.getElementById('ins_'+i).value = document.getElementById('ins_'+(i+1)).value;
  }
  tbl.deleteRow(-1);
  updateWeight();
}

function updateWeight() {
  // Total weight from package rows
  var weightTotal = 0;
  var tbl = document.getElementById('item_table');
  for (var i = 1; i < tbl.rows.length + 1; i++) {
    var wEl = document.getElementById('wt_'+i);
    var qEl = document.getElementById('qty_'+i);
    if (!wEl) break;
    var w = parseFloat(cleanCurrency(wEl.value));
    var q = parseFloat(cleanCurrency(qEl.value));
    if (!isNaN(w) && !isNaN(q)) weightTotal += q * w;
  }
  // Fall back to line-item weights when no package weights have been entered
  if (weightTotal === 0) {
    for (var j = 1; ; j++) {
      var wliEl = document.getElementById('li_wt_' + j);
      var qliEl = document.getElementById('li_qty_' + j);
      if (!wliEl) break;
      var wli = parseFloat(cleanCurrency(wliEl.value));
      var qli = qliEl ? parseFloat(qliEl.value) : 1;
      if (!isNaN(wli) && !isNaN(qli)) weightTotal += wli * qli;
    }
  }
  document.getElementById('total_weight').value = weightTotal;
  // Total value from line items (unit value * qty per item)
  var valueTotal = 0, hasItems = false;
  for (var j = 1; ; j++) {
    var vEl = document.getElementById('li_val_' + j);
    var qEl2 = document.getElementById('li_qty_' + j);
    if (!vEl) break;
    hasItems = true;
    var v = parseFloat(cleanCurrency(vEl.value));
    var q2 = qEl2 ? parseFloat(qEl2.value) : 1;
    if (!isNaN(v) && !isNaN(q2)) valueTotal += v * q2;
  }
  if (hasItems) {
    document.getElementById('total_value').value = formatCurrency(String(valueTotal));
  }
}
// -->
</script>
