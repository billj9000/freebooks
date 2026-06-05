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
//  Path: /modules/phreebooks/pages/bulk_bills/js_include.php
//
?>
<script type="text/javascript">
var journalID = '<?php echo JOURNAL_ID; ?>';
<?php echo js_calendar_init($cal_bills0); ?>
<?php echo js_calendar_init($cal_bills1); ?>
<?php echo js_calendar_init($cal_bills2); ?>

function init() {
  checkShipAll();

<?php if ($post_success && $_REQUEST['action'] == 'print') { ?>
  var printWin = window.open("index.php?module=phreeform&page=popup_gen&gID=<?php echo POPUP_FORM_TYPE; ?>&date=a&xfld=journal_main.purchase_invoice_id&xcr=RANGE&xmin=<?php echo $print_crit['min']; ?>&xmax=<?php echo $print_crit['max']; ?>","reportFilter","width=700px,height=550px,resizable=1,scrollbars=1,top=150px,left=200px");
  printWin.focus();
<?php } ?>
}

function check_form() {
  return true;
}

function checkShipAll() {
  for (var i = 1; i < document.getElementById('item_table').rows.length + 1; i++) {
    var amt_due = parseFloat(document.getElementById('amt_' + i).value);
    if (document.getElementById('pay_' + i).disabled == false && amt_due > 0) {
      document.getElementById('pay_' + i).checked = true;
      bbUpdatePayValues(i);
    }
  }
}

function updateDiscTotal(rowCnt) {
  var discount_amount = cleanCurrency(document.getElementById('dscnt_' + rowCnt).value);
  document.getElementById('dscnt_' + rowCnt).value = formatCurrency(discount_amount);
  var pay_total = parseFloat(document.getElementById('amt_' + rowCnt).value) - discount_amount;
  document.getElementById('total_' + rowCnt).value  = formatCurrency(String(pay_total));
  document.getElementById('pay_'   + rowCnt).checked = true;
  updateTotalPrices();
}

function updateLineTotal(rowCnt) {
  var total_line = cleanCurrency(document.getElementById('total_' + rowCnt).value);
  document.getElementById('total_' + rowCnt).value  = formatCurrency(total_line);
  document.getElementById('dscnt_' + rowCnt).value  = formatCurrency('0');
  document.getElementById('pay_'   + rowCnt).checked = true;
  updateTotalPrices();
}

function bbUpdatePayValues(rowCnt) {
  var postDate = cleanDate(document.getElementById('post_date').value);
  var discDate = document.getElementById('discdate_' + rowCnt).value;
  if (document.getElementById('pay_' + rowCnt).checked) {
    var amount   = parseFloat(document.getElementById('amt_'      + rowCnt).value);
    var discount = parseFloat(document.getElementById('origdisc_' + rowCnt).value);
    if (postDate > discDate) {
      discount = 0;
      document.getElementById('dscnt_' + rowCnt).value = formatCurrency('0');
    } else {
      document.getElementById('dscnt_' + rowCnt).value = formatCurrency(String(discount));
    }
    document.getElementById('total_' + rowCnt).value = formatCurrency(String(amount - discount));
  } else {
    document.getElementById('dscnt_' + rowCnt).value = (postDate > discDate) ? formatCurrency('0') : document.getElementById('origdisc_' + rowCnt).value;
    document.getElementById('total_' + rowCnt).value = '';
  }
  updateTotalPrices();
}
</script>
<script type="text/javascript" src="modules/phreebooks/javascript/banking.js"></script>
