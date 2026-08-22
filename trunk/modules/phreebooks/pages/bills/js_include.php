<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
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
//  Path: /modules/phreebooks/pages/bills/js_include.php
//

?>
<script type="text/javascript">
<!--
// pass any php variables generated during pre-process that are used in the javascript functions.
// Include translations here as well.
var pmt_array          = new Array(); // holds the encrypted payment information
var add_array          = new Array("<?php echo implode('", "', $js_arrays['fields']); ?>");
//var company_array    = new Array("<?php echo implode('", "', $js_arrays['company']); ?>");
var securityLevel      = <?php echo $security_level; ?>;
var default_array      = new Array("<?php echo implode('", "', $js_arrays['text']); ?>");
var defaultPostDate    = '<?php echo date('Y-m-d', time()); ?>';
var defaultGlAcct      = '<?php echo JOURNAL_ID == 18 ? AR_SALES_RECEIPTS_ACCOUNT : AP_PURCHASE_INVOICE_ACCOUNT; ?>';
var defaultDiscAcct    = '<?php echo JOURNAL_ID == 18 ? AR_DISCOUNT_SALES_ACCOUNT : AP_DISCOUNT_PURCHASE_ACCOUNT; ?>';
var journalID          = '<?php echo JOURNAL_ID; ?>';
var text_enter_new     = '<?php echo TEXT_ENTER_NEW; ?>';
var post_error         = <?php echo $error ? "true;" : "false;"; ?>
var account_type       = '<?php echo $account_type; ?>';
var store_country_code = '<?php echo COMPANY_COUNTRY; ?>';
var payments_installed = <?php echo count($payment_modules) ? 'true' : 'false'; ?>;
// printDocument() opens the form popup on the stored record, without posting
// this one. Same URL the status list's print icon uses, less the record id.
var printFormUrlBase   = '<?php echo gen_print_form_url(POPUP_FORM_TYPE, 'journal_main.id', 'EQUAL'); ?>';
var nothing_to_print   = '<?php echo ORD_JS_NOTHING_TO_PRINT; ?>';
<?php echo js_calendar_init($cal_bills); ?>

function init() {
  document.getElementById('bill_to_select').style.visibility = 'hidden';
  if (journalID == '18' || (journalID == '20' && account_type == 'c')) activateFields();
  // change color of the bill and ship address fields if they are the default values
  var add_id;
  for (var i=0; i<add_array.length; i++) {
	add_id = add_array[i];
	if (document.getElementById('bill_'+add_id).value == default_array[i]) {
	  document.getElementById('bill_'+add_id).style.color = inactive_text_color;
	}
  }
  document.getElementById('search').focus();
<?php if ($_REQUEST['action'] == 'edit') { // if paying from sales window automatically check first box
    echo 'ajaxBillData(0, ' . $oID . ', ' . JOURNAL_ID . ');';
  } else if ($_REQUEST['action'] == 'pmt') {
	echo 'loadNewPayment();' . chr(10);
    echo 'updateTotalPrices();' . chr(10);
  } else {
    echo 'updateTotalPrices();' . chr(10);
  }
?>

<?php
  // Print of a receipt that HAD been changed saves it and then comes back here as
  // action=edit&print=1, so the record printed is still the one in front of them.
  // Print of an unchanged receipt never posts at all - printDocument() opens the
  // popup directly and this page is not reloaded.
  if (isset($_GET['print']) && $_REQUEST['action'] == 'edit') { ?>
  var printWin = window.open(printFormUrlBase + <?php echo json_encode((string)$oID); ?>,"reportFilter","width=700px,height=550px,resizable=1,scrollbars=1,top=150px,left=200px");
  if (printWin) printWin.focus();
<?php } ?>
  document.getElementById('search').addEventListener('change', function(){
	if(document.getElementById('search').value != ''){ AccountList(); }
  });
  // Watch for changes, so that Print can leave an unchanged record alone.
  fbDocInit('bills_form');
<?php
  // Where the record arrives over AJAX the form is still empty here, so the
  // snapshot is fillBill()'s and showNewPayment()'s to take once the response has
  // been written in. Taking one now as well would only invite a race: a click
  // landing between the two cancels the second, and it would read as changed.
  // After a failed post, none either: what is on screen is unsaved work, and a
  // snapshot of it would grey out the Save that has to happen next.
  if (!$error && $_REQUEST['action'] <> 'edit') echo '  fbDocBaselineSoon();' . "\n";
?>
}

function check_form() {
  var error = 0;
  var error_message = "<?php echo JS_ERROR; ?>";

  var todo = document.getElementById('action').value;

  if (journalID == '18' && (todo == 'save' || todo == 'print')) { // only check payment if saving
    var index = document.getElementById('shipper_code').selectedIndex;
    var payment_method = document.getElementById('shipper_code').options[index].value;
	<?php
	  foreach ($payment_modules as $pmt_class) { // fetch the javascript validation of payments module
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

// Insert other page specific functions here.
// ******* AJAX payment stored payment values request function pair *********/
function loadNewPayment() { // request funtion
  var contact_id = document.getElementById('bill_acct_id').value;
  if (!contact_id) return;
  fetchXml('index.php?module=phreebooks&page=ajax&op=stored_payments&contact_id='+contact_id, showNewPayment);
}

function showNewPayment(xml) { // call back function
  var show = false;
  xml = parseXml(xml);
  if (!xml) return;
  while (document.getElementById('payment_id').options.length) document.getElementById('payment_id').remove(0);
  // build the dropdown
  newOpt = document.createElement("option");
  newOpt.text = '<?php echo TEXT_ENTER_NEW; ?>';
  document.getElementById('payment_id').options.add(newOpt);
  document.getElementById('payment_id').options[0].value = '';
  pmt_array[0] = new Object();
  pmt_array[0].field_0 = '';
  pmt_array[0].field_1 = '';
  pmt_array[0].field_2 = '';
  pmt_array[0].field_3 = '';
  pmt_array[0].field_4 = '';
  pmt_array[0].field_5 = '';
  pmt_array[0].field_6 = '';
  var jIndex = 1;
  Array.from(xml.getElementsByTagName("Payment")).forEach(function(pmt) {
	show = true;
	newOpt = document.createElement("option");
	newOpt.text = xmlText(pmt, "name");
	if (xmlText(pmt, "hint")) newOpt.text += ', ' + xmlText(pmt, "hint");
	document.getElementById('payment_id').options.add(newOpt);
	document.getElementById('payment_id').options[jIndex].value = xmlText(pmt, "id");
	pmt_array[jIndex] = new Object();
	if (xmlText(pmt, "field_0")) pmt_array[jIndex].field_0 = xmlText(pmt, "field_0");
	if (xmlText(pmt, "field_1")) pmt_array[jIndex].field_1 = xmlText(pmt, "field_1");
	if (xmlText(pmt, "field_2")) pmt_array[jIndex].field_2 = xmlText(pmt, "field_2");
	if (xmlText(pmt, "field_3")) pmt_array[jIndex].field_3 = xmlText(pmt, "field_3");
	if (xmlText(pmt, "field_4")) pmt_array[jIndex].field_4 = xmlText(pmt, "field_4");
	if (xmlText(pmt, "field_5")) pmt_array[jIndex].field_5 = xmlText(pmt, "field_5");
	if (xmlText(pmt, "field_6")) pmt_array[jIndex].field_6 = xmlText(pmt, "field_6");
	jIndex++;
  });
  document.getElementById('payment_id').style.visibility = show ? '' : 'hidden';
  // The last request the page makes when opening a record - fillBillData() calls
  // this after fillBill(). Re-snapshotting here keeps the stored payment list
  // from reading as somebody's edit. A no-op once the person has typed anything.
  fbDocBaselineSoon();
}
// ******* END - AJAX payment stored payment values request function pair *********/

function fillPayment() {
  var index = document.getElementById('shipper_code').selectedIndex;
  var pmtMethod = document.getElementById('shipper_code').options[index].value;
  var pmtIndex = document.getElementById('payment_id').selectedIndex;
  if (document.getElementById(pmtMethod+'_field_0')) 
    document.getElementById(pmtMethod+'_field_0').value = pmt_array[pmtIndex].field_0;
  if (document.getElementById(pmtMethod+'_field_1')) 
    document.getElementById(pmtMethod+'_field_1').value = pmt_array[pmtIndex].field_1;
  if (document.getElementById(pmtMethod+'_field_2')) 
    document.getElementById(pmtMethod+'_field_2').value = pmt_array[pmtIndex].field_2;
  if (document.getElementById(pmtMethod+'_field_3')) 
    document.getElementById(pmtMethod+'_field_3').value = pmt_array[pmtIndex].field_3;
  if (document.getElementById(pmtMethod+'_field_4')) 
	document.getElementById(pmtMethod+'_field_4').value = pmt_array[pmtIndex].field_4;
  if (document.getElementById(pmtMethod+'_field_5')) 
	document.getElementById(pmtMethod+'_field_5').value = pmt_array[pmtIndex].field_5;
  if (document.getElementById(pmtMethod+'_field_6')) 
	document.getElementById(pmtMethod+'_field_6').value = pmt_array[pmtIndex].field_6;
}

// -->
</script>
<script type="text/javascript" src="modules/phreebooks/javascript/banking.js?v=<?php echo filemtime(DIR_FS_MODULES . 'phreebooks/javascript/banking.js'); ?>"></script>
