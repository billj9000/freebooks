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
//  Path: /modules/phreebooks/pages/reconciliation/js_include.php
//
?>
<script type="text/javascript">
var textShow = '<?php echo TEXT_DETAILS; ?>';
var textHide = '<?php echo TEXT_HIDE; ?>';
var totalCnt = <?php echo count(is_array($bank_list) ? $bank_list : []); ?>;

function init() {
  updateBalance();
}

function check_form() {
  return true;
}

function showDetails(ref) {
  var disp   = document.getElementById('disp_' + ref);
  var detail = document.getElementById('detail_' + ref);
  if (disp.textContent == textHide) {
    detail.style.display   = 'none';
    disp.textContent       = textShow;
  } else {
    detail.style.display   = '';
    disp.textContent       = textHide;
  }
}

function updateSummary(ref) {
  var cnt     = 0;
  var rowRef  = 'disp_' + ref + '_';
  var checked = document.getElementById('sum_' + ref).checked;
  document.getElementById('disp_' + ref).style.backgroundColor = '';
  while (true) {
    if (!document.getElementById(rowRef + cnt)) break;
    document.getElementById('chk_' + ref).checked = checked;
    cnt++;
    ref++;
  }
  updateBalance();
}

function updateDetail(ref) {
  var numDetail  = 0;
  var numChecked = 0;
  var rowRef     = 'disp_' + ref + '_';
  var cnt        = 0;
  var origRef    = ref;
  while (true) {
    if (!document.getElementById(rowRef + cnt)) break;
    if (document.getElementById('chk_' + ref).checked) numChecked++;
    numDetail++;
    cnt++;
    ref++;
  }
  if (numChecked == 0) {
    document.getElementById('disp_' + origRef).style.backgroundColor = '';
    document.getElementById('sum_' + origRef).checked = false;
  } else if (numChecked == numDetail) {
    document.getElementById('disp_' + origRef).style.backgroundColor = '';
    document.getElementById('sum_' + origRef).checked = true;
  } else {
    document.getElementById('disp_' + origRef).style.backgroundColor = 'yellow';
    document.getElementById('sum_' + origRef).checked = true;
  }
  updateBalance();
}

function updateBalance() {
  var start_balance = parseFloat(cleanCurrency(document.getElementById('start_balance').value));
  var open_checks   = 0;
  var open_deposits = 0;
  var gl_balance    = parseFloat(cleanCurrency(document.getElementById('gl_balance').value));
  for (var i = 0; i < totalCnt; i++) {
    if (!document.getElementById('chk_' + i).checked) {
      var value = parseFloat(document.getElementById('pmt_' + i).value);
      if (value < 0) {
        if (!isNaN(value)) open_checks -= value;
      } else {
        if (!isNaN(value)) open_deposits += value;
      }
    }
  }
  document.getElementById('start_balance').value  = formatCurrency(String(start_balance));
  document.getElementById('open_checks').value    = formatCurrency(String(open_checks));
  document.getElementById('open_deposits').value  = formatCurrency(String(open_deposits));
  var balance = start_balance - open_checks + open_deposits - gl_balance;
  document.getElementById('balance').value        = formatCurrency(String(balance));
  var numExpr = Math.round(balance * Math.pow(10, decimal_places));
  document.getElementById('balance').style.color  = (numExpr == 0) ? '' : 'red';
}
</script>
