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
//  Path: /modules/phreebooks/pages/budget/js_include.php
//
?>
<script type="text/javascript">
var num_periods = <?php echo count(is_array($fy_array) ? $fy_array : []); ?>;

function init() {
  calculateTotal();
}

function check_form() {
  return true;
}

function copyBudget(action) {
  var i;
  switch (action) {
    case 'spread':
      var total = cleanCurrency(document.getElementById('total').value);
      var allocation = formatCurrency(String(parseFloat(total) / num_periods));
      for (i = 0; i < num_periods; i++) {
        document.getElementById('budget_' + i).value = allocation;
      }
      break;
    case 'prior':
      for (i = 0; i < num_periods; i++) {
        document.getElementById('budget_' + i).value = document.getElementById('prior_' + i).value;
      }
      break;
    case 'next':
      for (i = 0; i < num_periods; i++) {
        document.getElementById('budget_' + i).value = document.getElementById('next_' + i).value;
      }
      break;
    case 'clear':
      for (i = 0; i < num_periods; i++) {
        document.getElementById('budget_' + i).value = formatted_zero;
      }
      break;
    default:
  }
  calculateTotal();
}

function calculateTotal() {
  var p_total = 0, b_total = 0, n_total = 0;
  for (var i = 0; i < num_periods; i++) {
    p_total += parseFloat(cleanCurrency(document.getElementById('prior_'  + i).value));
    b_total += parseFloat(cleanCurrency(document.getElementById('budget_' + i).value));
    n_total += parseFloat(cleanCurrency(document.getElementById('next_'   + i).value));
  }
  document.getElementById('prior').value = formatCurrency(String(p_total));
  document.getElementById('total').value = formatCurrency(String(b_total));
  document.getElementById('next').value  = formatCurrency(String(n_total));
}

function fetchAcct() {
  var gl_acct = document.getElementById('gl_acct').value;
  var fy      = document.getElementById('fy').value;
  if (!gl_acct) return;
  copyBudget('clear');
  $.ajax({
    type: 'GET',
    url: 'index.php?module=phreebooks&page=ajax&op=load_gl_data&glAcct=' + gl_acct + '&fy=' + fy,
    dataType: 'xml',
    error: function(xhr, textStatus, errorThrown) {
      alert('Ajax Error: ' + xhr.responseText + '\nTextStatus: ' + textStatus + '\nErrorThrown: ' + errorThrown);
    },
    success: processLoadAccount
  });
}

function processLoadAccount(sXml) {
  var xml = parseXml(sXml);
  if (!xml) return;
  var iIndex = 0;
  $(xml).find('items').each(function() {
    document.getElementById('budget_' + iIndex).value = $(this).find('balance').text();
    iIndex++;
  });
  calculateTotal();
}
</script>
