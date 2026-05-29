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
<link rel="stylesheet" href="ext/bootstrap5/bootstrap.min.css">
<link rel="stylesheet" href="ext/bootstrap5/freebooks.css">
<script src="ext/bootstrap5/bootstrap.bundle.min.js" defer></script>
<script>
var formName          = 'label_mgr';
var image_delete_msg  = '<?php echo addslashes(SHIPPING_DELETE_CONFIRM); ?>';
var _joditMsgBody     = null;

function init() {
  <?php
    if (!$error && ($_REQUEST['action'] == 'label' || $_REQUEST['action'] == 'delete')) {
      echo "  if (window.opener) window.opener.location.reload();\n";
      echo "  self.close();\n";
    }
  ?>
  toggleCustoms();
  toggleEmailEditor();
  updateWeight();
  var w1 = document.getElementById('wt_1');
  if (w1) w1.focus();
}

function toggleEmailEditor() {
  var sel  = document.querySelector('input[name="delivery_method"]:checked');
  var show = (sel && sel.value === 'S');
  document.getElementById('email_editor').style.display = show ? 'block' : 'none';
  if (show && !_joditMsgBody) {
    setTimeout(function() {
      _joditMsgBody = Jodit.make(document.getElementById('message_body'));
    }, 0);
  }
}

function toggleCustoms() {
  var sc = document.getElementById('sender_country_code');
  var rc = document.getElementById('ship_country_code');
  var fromCC = sc ? sc.value : '';
  var toCC   = rc ? rc.value : '';
  var isDomestic = (fromCC !== '' && toCC !== '' && fromCC === toCC);
  var display = isDomestic ? 'none' : '';
  var sec = document.getElementById('customs_section');
  if (sec) sec.style.display = display;
  var li = document.getElementById('line_items_section');
  if (li) li.style.display = display;
}

function swapShipAddresses() {
  var textFields = ['primary_name', 'contact', 'address1', 'address2',
                    'city_town', 'state_province', 'postal_code', 'telephone1', 'email'];
  textFields.forEach(function(f) {
    var a = document.getElementById('sender_' + f);
    var b = document.getElementById('ship_' + f);
    if (!a || !b) return;
    var t = a.value; a.value = b.value; b.value = t;
  });
  var sc = document.getElementById('sender_country_code');
  var rc = document.getElementById('ship_country_code');
  if (sc && rc) { var t = sc.value; sc.value = rc.value; rc.value = t; }
  toggleCustoms();
}

function check_form() {
  var w = document.getElementById('total_weight');
  if (!w || !w.value || parseFloat(w.value) === 0) {
    alert('<?php echo addslashes(JS_ERROR); ?>' + '\n' + '<?php echo addslashes(SHIPPING_ERROR_WEIGHT_ZERO); ?>');
    return false;
  }
  return true;
}

function addRow() {
  var tbl = document.getElementById('item_table');
  var rowCnt = tbl.rows.length + 1;
  var newRow = tbl.insertRow(-1);
  var cells = [
    '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 lh-1"' +
      ' onclick="if(confirm(\'' + image_delete_msg.replace(/'/g, "\\'") + '\')) removeRow(' + rowCnt + ')">&times;</button>',
    '<input type="text" class="form-control form-control-sm text-end" style="width:4.5em"' +
      ' name="qty_' + rowCnt + '" id="qty_' + rowCnt + '" value="1">',
    '<input type="text" class="form-control form-control-sm text-end" style="width:6em"' +
      ' name="wt_' + rowCnt + '" id="wt_' + rowCnt + '" value=""' +
      ' onchange="updateWeight(' + rowCnt + ')">',
    '<input type="text" class="form-control form-control-sm text-end" style="width:5em"' +
      ' name="len_' + rowCnt + '" id="len_' + rowCnt + '" value="">',
    '<input type="text" class="form-control form-control-sm text-end" style="width:5em"' +
      ' name="wid_' + rowCnt + '" id="wid_' + rowCnt + '" value="">',
    '<input type="text" class="form-control form-control-sm text-end" style="width:5em"' +
      ' name="hgt_' + rowCnt + '" id="hgt_' + rowCnt + '" value="">',
    '<input type="text" class="form-control form-control-sm text-end" style="width:7em"' +
      ' name="ins_' + rowCnt + '" id="ins_' + rowCnt + '" value="0.00">',
  ];
  cells.forEach(function(html) {
    var td = newRow.insertCell(-1);
    td.innerHTML = html;
    if (cells.indexOf(html) === 0) td.className = 'text-center';
  });
}

function removeRow(delRowCnt) {
  var tbl = document.getElementById('item_table');
  for (var i = delRowCnt; i < tbl.rows.length; i++) {
    document.getElementById('qty_' + i).value = document.getElementById('qty_' + (i + 1)).value;
    document.getElementById('wt_' + i).value  = document.getElementById('wt_'  + (i + 1)).value;
    document.getElementById('len_' + i).value = document.getElementById('len_' + (i + 1)).value;
    document.getElementById('wid_' + i).value = document.getElementById('wid_' + (i + 1)).value;
    document.getElementById('hgt_' + i).value = document.getElementById('hgt_' + (i + 1)).value;
    document.getElementById('ins_' + i).value = document.getElementById('ins_' + (i + 1)).value;
  }
  tbl.deleteRow(-1);
  updateWeight();
}

function updateWeight() {
  var weightTotal = 0;
  var tbl = document.getElementById('item_table');
  for (var i = 1; i <= tbl.rows.length; i++) {
    var wEl = document.getElementById('wt_' + i);
    var qEl = document.getElementById('qty_' + i);
    if (!wEl) break;
    var w = parseFloat(cleanCurrency(wEl.value));
    var q = parseFloat(cleanCurrency(qEl ? qEl.value : '1'));
    if (!isNaN(w) && !isNaN(q)) weightTotal += q * w;
  }
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
  var twEl = document.getElementById('total_weight');
  if (twEl) twEl.value = weightTotal;
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
    var tvEl = document.getElementById('total_value');
    if (tvEl) tvEl.value = formatCurrency(String(valueTotal));
  }
}
</script>
