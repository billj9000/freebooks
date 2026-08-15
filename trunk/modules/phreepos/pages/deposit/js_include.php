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
//  Path: /modules/phreepos/pages/deposit/js_include.php
//
?>
<script type="text/javascript">
var pmt_array          = new Array();
var add_array          = new Array("<?php echo implode('", "', $js_arrays['fields']); ?>");
var default_array      = new Array("<?php echo implode('", "', $js_arrays['text']); ?>");
var journalID          = '<?php echo JOURNAL_ID; ?>';
var account_type       = '<?php echo $type; ?>';
var payments_installed = <?php echo count($payment_modules) ? 'true' : 'false'; ?>;
<?php echo js_calendar_init($cal_bills); ?>

<?php echo $js_gl_array; ?>

function init() {
  document.getElementById('bill_to_select').style.visibility = 'hidden';
  activateFields();
  var add_id;
  for (var i = 0; i < add_array.length; i++) {
    add_id = add_array[i];
    if (document.getElementById('bill_' + add_id).value == default_array[i]) {
      document.getElementById('bill_' + add_id).style.color = inactive_text_color;
    }
  }
  document.getElementById('search').focus();
<?php
  if ($_REQUEST['action'] == 'pmt') echo '  loadNewPayment();' . chr(10);
  echo '  updateDepositPrice();' . chr(10);
?>
<?php if ($post_success && $_REQUEST['action'] == 'print'): ?>
  ClearForm();
  var printWin = window.open("<?php echo gen_print_form_url(POPUP_FORM_TYPE, 'journal_main.id', 'EQUAL', $oID); ?>", "popup_gen", "width=700px,height=550px,resizable=1,scrollbars=1,top=150px,left=200px");
  printWin.focus();
<?php endif; ?>
}

function check_form() {
  var error = 0;
  var error_message = "<?php echo JS_ERROR; ?>";
  var todo = document.getElementById('action').value;
  if (journalID == '19' && (todo == 'save' || todo == 'print')) {
    var index = document.getElementById('shipper_code').selectedIndex;
    var payment_method = document.getElementById('shipper_code').options[index].value;
    <?php
      foreach ($payment_modules as $pmt_class) {
        $value = $pmt_class['id'];
        echo $$value->javascript_validation();
      }
    ?>
  }
  if (error == 1) {
    alert(error_message);
    return false;
  }
  return true;
}

function activateFields() {
  if (payments_installed) {
    var index = document.getElementById('shipper_code').selectedIndex;
    for (var i = 0; i < document.getElementById('shipper_code').options.length; i++) {
      document.getElementById('pm_' + i).style.visibility = 'hidden';
    }
    document.getElementById('pm_' + index).style.visibility = '';
  }
}

function ClearForm() {
  var add_id;
  document.getElementById('id').value                     = '';
  document.getElementById('bill_acct_id').value           = '';
  document.getElementById('bill_address_id').value        = '';
  document.getElementById('bill_telephone1').value        = '';
  document.getElementById('search').value                 = text_search;
  document.getElementById('purchase_invoice_id').value    = '<?php echo $next_inv_ref; ?>';
  document.getElementById('post_date').value              = '<?php echo date('Y-m-d'); ?>';
  document.getElementById('purch_order_id').value         = '';
  document.getElementById('gl_acct_id').value             = '<?php echo $gl_acct_id; ?>';
  document.getElementById('gl_disc_acct_id').value        = '';
  document.getElementById('total').value                  = '';
  document.getElementById('shipper_code').value           = '';
  document.getElementById('search').style.color           = inactive_text_color;
  document.getElementById('purchase_invoice_id').readOnly = false;
  document.getElementById('bill_country_code').value      = '<?php echo COMPANY_COUNTRY; ?>';
  for (var i = 0; i < add_array.length; i++) {
    add_id = add_array[i];
    if (add_id != 'country_code') document.getElementById('bill_' + add_id).style.color = inactive_text_color;
    document.getElementById('bill_' + add_id).value = default_array[i];
  }
  while (document.getElementById('bill_to_select').options.length) {
    document.getElementById('bill_to_select').remove(0);
  }
  while (document.getElementById('payment_id').options.length) {
    document.getElementById('payment_id').remove(0);
  }
  while (document.getElementById('item_table').rows.length > 1) {
    document.getElementById('item_table').deleteRow(-1);
  }
  document.getElementById('acct_1').value = '<?php echo DEF_DEP_GL_ACCT; ?>';
  loadNewBalance('<?php echo $gl_acct_id; ?>');
}

function billsAcctList() {
  window.open("index.php?module=contacts&page=popup_accts&type=" + account_type + "&form=bills_deposit&fill=bill&jID=" + journalID + "&search_text=" + document.getElementById('search').value, "accounts", "width=850px,height=550px,resizable=1,scrollbars=1,top=150,left=100");
}

function loadNewBalance() {
  var gl_acct   = document.getElementById('gl_acct_id').value;
  var post_date = document.getElementById('post_date').value;
  fetchXml('index.php?module=phreebooks&page=ajax&op=acct_balance&gl_acct_id=' + gl_acct + '&post_date=' + post_date, showNewBalance);
}

function showNewBalance(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var value = xmlText(xml, "value");
  if (isNaN(value) || !value) value = '0';
  var start_balance = parseFloat(value);
  var total_checks  = cleanCurrency(document.getElementById('total').value);
  balance_remain    = new String(start_balance - total_checks);
  sb = new String(start_balance);
  document.getElementById('acct_balance').value = formatCurrency(sb);
  document.getElementById('end_balance').value  = formatCurrency(balance_remain);
}

function ajaxOrderData(cID, oID, jID, open_order, ship_only) {
  fetchXml('index.php?module=contacts&page=ajax&op=load_contact&cID=' + cID, fillOrderData);
}

function fillOrderData(xml) {
  var newOpt;
  var type = 'bill';
  xml = parseXml(xml);
  if (!xml) return;
  while (document.getElementById(type + '_to_select').options.length) document.getElementById(type + '_to_select').remove(0);
  var id = xmlText(xml, "id");
  if (!id) return;
  var mainType = xmlText(xml, "type") + 'm';
  bill_add = xml;
  insertValue('bill_acct_id', id);
  insertValue('search', xmlText(xml, "short_name"));
  var iIndex = 0;
  Array.from(xml.getElementsByTagName("BillAddress")).forEach(function(addr) {
    newOpt = document.createElement("option");
    newOpt.text = xmlText(addr, "primary_name") + ', ' + xmlText(addr, "city_town") + ', ' + xmlText(addr, "postal_code");
    document.getElementById(type + '_to_select').options.add(newOpt);
    document.getElementById(type + '_to_select').options[iIndex].value = xmlText(addr, "address_id");
    if (xmlText(addr, "type") == mainType) {
      insertValue(type + '_address_id', xmlText(addr, "address_id"));
      Array.from(addr.children).forEach(function(child) {
        var tagName = child.tagName;
        if (document.getElementById(type + '_' + tagName)) {
          document.getElementById(type + '_' + tagName).value = child.textContent;
          document.getElementById(type + '_' + tagName).style.color = '';
        }
      });
    }
    iIndex++;
    newOpt = document.createElement("option");
    newOpt.text = '<?php echo TEXT_ENTER_NEW; ?>';
    document.getElementById(type + '_to_select').options.add(newOpt);
    document.getElementById(type + '_to_select').options[iIndex].value = '0';
    document.getElementById(type + '_to_select').style.visibility = 'visible';
    document.getElementById(type + '_to_select').disabled = false;
  });
}

function loadNewPayment() {
  var contact_id = document.getElementById('bill_acct_id').value;
  if (!contact_id) return;
  fetchXml('index.php?module=phreebooks&page=ajax&op=stored_payments&contact_id=' + contact_id, showNewPayment);
}

function showNewPayment(xml) {
  var show = false;
  xml = parseXml(xml);
  if (!xml) return;
  while (document.getElementById('payment_id').options.length) document.getElementById('payment_id').remove(0);
  if (xml.getElementsByTagName("payments").length) {
    var newOpt = document.createElement("option");
    newOpt.text = '<?php echo TEXT_ENTER_NEW; ?>';
    document.getElementById('payment_id').options.add(newOpt);
    document.getElementById('payment_id').options[0].value = '';
    pmt_array[0] = new Object();
    pmt_array[0].field_0 = '';
    pmt_array[0].field_1 = '';
    pmt_array[0].field_2 = '';
    pmt_array[0].field_3 = '';
    pmt_array[0].field_4 = '';
    var j = 1;
    Array.from(xml.getElementsByTagName("payments")).forEach(function(pmt) {
      show = true;
      newOpt = document.createElement("option");
      newOpt.text = xmlText(pmt, "name");
      if (xmlText(pmt, "hint")) newOpt.text += ', ' + xmlText(pmt, "hint");
      document.getElementById('payment_id').options.add(newOpt);
      document.getElementById('payment_id').options[j].value = xmlText(pmt, "id");
      pmt_array[j] = new Object();
      if (xmlText(pmt, "field_0")) pmt_array[j].field_0 = xmlText(pmt, "field_0");
      if (xmlText(pmt, "field_1")) pmt_array[j].field_1 = xmlText(pmt, "field_1");
      if (xmlText(pmt, "field_2")) pmt_array[j].field_2 = xmlText(pmt, "field_2");
      if (xmlText(pmt, "field_3")) pmt_array[j].field_3 = xmlText(pmt, "field_3");
      if (xmlText(pmt, "field_4")) pmt_array[j].field_4 = xmlText(pmt, "field_4");
      j++;
    });
  }
  document.getElementById('payment_id').style.visibility = show ? '' : 'hidden';
}

function fillPayment() {
  var index = document.getElementById('shipper_code').selectedIndex;
  var pmtMethod = document.getElementById('shipper_code').options[index].value;
  var pmtIndex = document.getElementById('payment_id').selectedIndex;
  if (document.getElementById(pmtMethod + '_field_0')) document.getElementById(pmtMethod + '_field_0').value = pmt_array[pmtIndex].field_0;
  if (document.getElementById(pmtMethod + '_field_1')) document.getElementById(pmtMethod + '_field_1').value = pmt_array[pmtIndex].field_1;
  if (document.getElementById(pmtMethod + '_field_2')) document.getElementById(pmtMethod + '_field_2').value = pmt_array[pmtIndex].field_2;
  if (document.getElementById(pmtMethod + '_field_3')) document.getElementById(pmtMethod + '_field_3').value = pmt_array[pmtIndex].field_3;
  if (document.getElementById(pmtMethod + '_field_4')) document.getElementById(pmtMethod + '_field_4').value = pmt_array[pmtIndex].field_4;
}

function updateDepositPrice() {
  var temp  = cleanCurrency(document.getElementById('total_1').value);
  var total = parseFloat(temp);
  var tot   = new String(total);
  document.getElementById('total').value   = formatCurrency(tot);
  document.getElementById('total_1').value = formatCurrency(tot);
  temp = cleanCurrency(document.getElementById('acct_balance').value);
  var bank_bal = parseFloat(temp);
  tot = new String(bank_bal - total);
  document.getElementById('end_balance').value = formatCurrency(tot);
}

function buildFreightDropdown() {}
</script>
