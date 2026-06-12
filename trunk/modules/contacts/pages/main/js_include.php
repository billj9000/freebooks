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
//  Path: /modules/contacts/pages/main/js_include.php
//
?>
<script type="text/javascript">
<!--
// pass any php variables generated during pre-process that are used in the javascript functions.
// Include translations here as well.
var attachment_path = '<?php echo urlencode(CONTACTS_DIR_ATTACHMENTS); ?>';
var default_country = '<?php echo COMPANY_COUNTRY; ?>';
var account_type    = '<?php echo $type; ?>';

function init() {
  var firstTabPane = document.querySelector('#detailtabs .tab-pane');
  if (firstTabPane) {
    firstTabPane.classList.add('show', 'active');
  }

<?php if ($include_template == 'template_main.php') {
 	echo '  document.getElementById("search_text").focus();'  . chr(10); 
  	echo '  document.getElementById("search_text").select();' . chr(10); 
  }
?>
  if (window.extra_init) { extra_init() } // hook for additional initializations
}

function check_form() {
  var error = 0;
  var error_message = "<?php echo JS_ERROR; ?>";
  <?php if ($cInfo->auto_type == false && ($_REQUEST['action'] == 'edit' || $_REQUEST['action'] == 'update' || $_REQUEST['action'] == 'new')) { ?> // if showing the edit/update detail form
  var acctId = document.getElementById('short_name').value;
  if (acctId == '') {
      error_message += "<?php echo ACT_JS_SHORT_NAME; ?>";
	  error = 1;
  }
  <?php } ?>
  if (error == 1) {
    alert(error_message);
    return false;
  } else {
    return true;
  }
}

// Insert other page specific functions here.
function fillAddressFromLookup(el, addType) {
  ['primary_name', 'address1', 'address2', 'city_town', 'state_province', 'postal_code'].forEach(function(f) {
    var input = document.querySelector('[name="address[' + addType + '][' + f + ']"]');
    if (input) input.value = el.dataset[f] || '';
  });
  document.getElementById('postcode_results_' + addType).innerHTML = '';
}
function loadContacts() {
  var guess = document.getElementById('dept_rep_id').value;
  if (guess.length < 3) return;
  fetchXml('index.php?module=contacts&page=ajax&op=load_contact_info&guess='+guess, fillContacts);
}

function fillContacts(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  while (document.getElementById('comboseldept_rep_id').options.length) document.getElementById('comboseldept_rep_id').remove(0);
  var iIndex = 0;
  Array.from(xml.getElementsByTagName("guesses")).forEach(function(guess) {
    var text = xmlText(guess, "guess");
    var newOpt = document.createElement("option");
    newOpt.text = text ? text : '<?php echo TEXT_FIND; ?>';
    document.getElementById('comboseldept_rep_id').options.add(newOpt);
    document.getElementById('comboseldept_rep_id').options[iIndex].value = xmlText(guess, "id");
    if (!fActiveMenu) cbMmenuActivate('dept_rep_id', 'combodivdept_rep_id', 'comboseldept_rep_id', 'imgNamedept_rep_id');
    document.getElementById('dept_rep_id').focus();
    iIndex++;
  });
}

// -->
</script>
<script type="text/javascript" src="modules/contacts/javascript/contacts.js"></script>