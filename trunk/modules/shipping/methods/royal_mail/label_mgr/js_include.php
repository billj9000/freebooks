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
//  Path: /modules/shipping/methods/royal_mail/label_mgr/js_include.php
//
?>
<script>
var formName         = 'label_mgr';
var image_delete_msg = '<?php echo addslashes(SHIPPING_DELETE_CONFIRM); ?>';
var pkgFormats       = <?php echo json_encode(array(
  array('id' => 'letter',       'text' => RM_PKG_LETTER),
  array('id' => 'largeLetter',  'text' => RM_PKG_LARGE_LETTER),
  array('id' => 'smallParcel',  'text' => RM_PKG_SMALL_PARCEL),
  array('id' => 'mediumParcel', 'text' => RM_PKG_MEDIUM_PARCEL),
  array('id' => 'largeParcel',  'text' => RM_PKG_LARGE_PARCEL),
  array('id' => 'parcel',       'text' => RM_PKG_PARCEL),
  array('id' => 'documents',    'text' => RM_PKG_DOCUMENTS),
)); ?>;
// Click & Drop rejects package formats that don't match the service's mail class
// (e.g. a parcel format on a Business Mail letter-post service) — keep in sync with
// royal_mail::FORMATS_BY_SERVICE in royal_mail.php.
var formatsByService = <?php echo json_encode(royal_mail::FORMATS_BY_SERVICE); ?>;
var allFormatIds      = pkgFormats.map(function(f) { return f.id; });

function init() {
  <?php
    if (!$error && ($_REQUEST['action'] == 'label' || $_REQUEST['action'] == 'delete')) {
      echo "  if (window.opener) window.opener.location.reload();\n";
      echo "  self.close();\n";
    }
  ?>
  toggleCustoms();
  for (var n = 1; document.getElementById('li_qty_' + n); n++) lineQtyChanged(n);
  filterFormatOptions();
  updateWeight();
  var w1 = document.getElementById('wt_1');
  if (w1) w1.focus();
}

function allowedFormatIds() {
  var svcEl = document.getElementById('ship_method');
  var svc   = svcEl ? svcEl.value : '';
  return formatsByService[svc] || allFormatIds;
}

function filterFormatOptions() {
  var allowed = allowedFormatIds();
  var tbl = document.getElementById('item_table');
  for (var i = 1; i <= tbl.rows.length; i++) {
    var sel = document.getElementById('fmt_' + i);
    if (!sel) break;
    var current = sel.value;
    sel.innerHTML = '';
    pkgFormats.forEach(function(f) {
      if (allowed.indexOf(f.id) === -1) return;
      var opt = document.createElement('option');
      opt.value = f.id;
      opt.textContent = f.text;
      sel.appendChild(opt);
    });
    sel.value = (allowed.indexOf(current) !== -1) ? current : allowed[0];
  }
}

// Keep in sync with royal_mail::isNorthernIreland() in royal_mail.php.
function isNorthernIreland(fromCC, toCC) {
  var fp = document.getElementById('sender_postal_code');
  var tp = document.getElementById('ship_postal_code');
  var isBT = function(el) { return !!el && /^\s*BT/i.test(el.value); };
  var isGB = function(cc) { return cc === 'GBR' || cc === 'GB'; };
  return isGB(fromCC) && isGB(toCC) && isBT(tp) && !isBT(fp);
}

// The customs cards show for an international shipment and for one to Northern Ireland;
// delivery terms only for international, the business box only for Northern Ireland.
function toggleCustoms() {
  var sc = document.getElementById('sender_country_code');
  var rc = document.getElementById('ship_country_code');
  var fromCC = sc ? sc.value : '';
  var toCC   = rc ? rc.value : '';
  var isDomestic = (fromCC !== '' && toCC !== '' && fromCC === toCC);
  var isNI       = isDomestic && isNorthernIreland(fromCC, toCC);
  var show = function(id, on) { var el = document.getElementById(id); if (el) el.style.display = on ? '' : 'none'; };
  show('customs_section',        !isDomestic || isNI);
  document.querySelectorAll('#line_items_section .li-customs').forEach(function(el) { el.style.display = (!isDomestic || isNI) ? '' : 'none'; });
  show('row_delivery_terms',     !isDomestic);
  show('row_recipient_business', isNI);
  var term = document.getElementById('delivery_term');
  var ddp  = !!term && term.value === 'DDP';
  show('label_duty', ddp);
  show('input_duty', ddp);
}

// A contents line whose quantity is 0 is not in this shipment: it is greyed out and its button
// puts back what remains to send. A line sent short asks whether the rest follows later.
function lineQtyChanged(n) {
  var q = document.getElementById('li_qty_' + n);
  if (!q) return;
  var qty     = parseFloat(q.value);
  var removed = !(qty > 0);
  var row = document.getElementById('li_row_' + n);
  if (row) row.style.opacity = removed ? '0.5' : '';
  var btn = document.getElementById('li_btn_' + n);
  if (btn) btn.textContent = removed ? '<?php echo addslashes(SHIPPING_LINE_RESTORE); ?>' : '<?php echo addslashes(SHIPPING_LINE_REMOVE); ?>';
  var o     = document.getElementById('li_ord_' + n);
  var short = document.getElementById('li_short_wrap_' + n);
  if (short && o) short.style.display = (isNaN(qty) ? 0 : qty) < parseFloat(o.value) ? '' : 'none';
  updateWeight();
}

function lineToggle(n) {
  var q = document.getElementById('li_qty_' + n);
  var o = document.getElementById('li_ord_' + n);
  if (!q || !o) return;
  q.value = parseFloat(q.value) > 0 ? 0 : o.value;
  lineQtyChanged(n);
}

function swapShipAddresses() {
  var textFields = ['primary_name', 'contact', 'address1', 'address2',
                    'city_town', 'postal_code', 'telephone1', 'email',
                    'vat_number', 'eori_number'];
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

function buildFormatSelect(rowCnt, selected) {
  var allowed = allowedFormatIds();
  var html = '<select class="form-select form-select-sm" name="fmt_' + rowCnt + '" id="fmt_' + rowCnt + '" style="min-width:9em">';
  pkgFormats.forEach(function(f) {
    if (allowed.indexOf(f.id) === -1) return;
    html += '<option value="' + f.id + '"' + (f.id === selected ? ' selected' : '') + '>' + f.text + '</option>';
  });
  html += '</select>';
  return html;
}

function addRow() {
  var tbl    = document.getElementById('item_table');
  var rowCnt = tbl.rows.length + 1;
  var newRow = tbl.insertRow(-1);
  var allowed    = allowedFormatIds();
  var defaultFmt = (allowed.indexOf('parcel') !== -1) ? 'parcel' : allowed[0];
  var cells  = [
    '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 lh-1"' +
      ' onclick="if(confirm(\'' + image_delete_msg.replace(/'/g, "\\'") + '\')) removeRow(' + rowCnt + ')">&times;</button>',
    '<input type="text" class="form-control form-control-sm text-end" style="width:4.5em"' +
      ' name="qty_' + rowCnt + '" id="qty_' + rowCnt + '" value="1">',
    '<input type="text" class="form-control form-control-sm text-end" style="width:6em"' +
      ' name="wt_' + rowCnt + '" id="wt_' + rowCnt + '" value=""' +
      ' onchange="updateWeight()">',
    buildFormatSelect(rowCnt, defaultFmt),
  ];
  cells.forEach(function(html, i) {
    var td = newRow.insertCell(-1);
    td.innerHTML = html;
    if (i === 0) td.className = 'text-center';
  });
}

function removeRow(delRowCnt) {
  var tbl = document.getElementById('item_table');
  for (var i = delRowCnt; i < tbl.rows.length; i++) {
    document.getElementById('qty_' + i).value = document.getElementById('qty_' + (i + 1)).value;
    document.getElementById('wt_'  + i).value = document.getElementById('wt_'  + (i + 1)).value;
    // Copy format select value
    var fSrc = document.getElementById('fmt_' + (i + 1));
    var fDst = document.getElementById('fmt_' + i);
    if (fSrc && fDst) fDst.value = fSrc.value;
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
    var vEl  = document.getElementById('li_val_' + j);
    var qEl2 = document.getElementById('li_qty_' + j);
    if (!vEl) break;
    hasItems = true;
    var v  = parseFloat(cleanCurrency(vEl.value));
    var q2 = qEl2 ? parseFloat(qEl2.value) : 1;
    if (!isNaN(v) && !isNaN(q2)) valueTotal += v * q2;
  }
  if (hasItems) {
    var tvEl = document.getElementById('total_value');
    if (tvEl) tvEl.value = formatCurrency(String(valueTotal));
  }
}
</script>
