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
//  Path: /modules/phreepos/pages/main/js_include.php
//
?>
<script type="text/javascript">
<!--
// pass any php variables generated during pre-process that are used in the javascript functions.
// Include translations here as well.
var setId                = 1; // flag used for AJAX loading of sku for bar code reading of line item
var skuLength            = <?php echo ORD_BAR_CODE_LENGTH; ?>;
var resClockID           = 0;
var default_disc_acct    = <?Php echo AR_DISCOUNT_SALES_ACCOUNT;?>;
var max_sku_len          = <?php echo MAX_INVENTORY_SKU_LENGTH; ?>;
var auto_load_sku        = <?php echo INVENTORY_AUTO_FILL; ?>;
var image_ser_num        = '<?php echo TEXT_SERIAL_NUMBER; ?>';
var add_array            = new Array("<?php echo implode('", "', $js_arrays['fields']); ?>");
var default_array        = new Array("<?php echo implode('", "', $js_arrays['text']); ?>");
var bill_add             = new Array();
var journalID            = '<?php echo JOURNAL_ID; ?>';
var securityLevel        = <?php echo $security_level; ?>;
var account_type         = '<?php echo $account_type; ?>';
var text_search          = '<?php echo TEXT_SEARCH;?>';
var text_enter_new       = '<?php echo TEXT_ENTER_NEW; ?>';
var text_properties      = '<?php echo TEXT_PROPERTIES; ?>';
var post_error           = <?php echo $error ? "true" : "false"; ?>;
var default_sales_tax    = -1;
var contact_sales_tax    = -1;
var image_delete_text    = '<?php echo TEXT_DELETE; ?>';
var image_delete_msg     = '<?php echo TEXT_DELETE_ENTRY; ?>';
var store_country_code   = '<?php echo COMPANY_COUNTRY; ?>';
var delete_icon_HTML     = '<?php echo substr(html_icon("emblems/emblem-unreadable.png", TEXT_DELETE, "small", "onclick=\"if (confirm(\'" . TEXT_DELETE_ENTRY . "\')) removeInvRow("), 0, -2); ?>';
var delete_icon_HTML_PMT = '<?php echo substr(html_icon("emblems/emblem-unreadable.png", TEXT_DELETE, "small", "onclick=\"if (confirm(\'" . TEXT_DELETE_ENTRY . "\')) removePmtRow("), 0, -2); ?>';
var serial_num_prompt    = '<?php echo ORD_JS_SERIAL_NUM_PROMPT; ?>';
var show_status          = '<?php echo ($account_type == "v") ? AP_SHOW_CONTACT_STATUS : AR_SHOW_CONTACT_STATUS; ?>';
var warn_form_modified   = '<?php echo ORD_WARN_FORM_MODIFIED; ?>';
var default_inv_acct     = '<?php echo DEF_INV_GL_ACCT; ?>';
var defaultCurrency      = '<?php echo DEFAULT_CURRENCY; ?>';
var tax_before_discount  = '<?php echo ($account_type == "c") ? AR_TAX_BEFORE_DISCOUNT : AP_TAX_BEFORE_DISCOUNT; ?>';
var save_allowed		 = true;
var display_with_tax     = <?php echo PHREEPOS_DISPLAY_WITH_TAX; ?>;
var discount_from_total  = <?php echo PHREEPOS_DISCOUNT_OF; ?>;
var rounding_of          = <?php echo PHREEPOS_ROUNDING; ?>;
var newdecimal_places    = '';
var newdecimal_precise   = '';
var newdecimal_point     = '';
var newthousands_point   = '';
// List the currency codes and exchange rates
<?php if (ENABLE_MULTI_CURRENCY) echo $currencies->build_js_currency_arrays(); ?>
// List the tax rates
<?php echo $js_tax_rates; ?>
<?php echo $js_ot_tax_rates ?>
<?php echo $js_pmt_types; ?>
<?php echo $js_currency; ?>
<?php echo $tills->javascript_array(); ?>
<?php echo $trans->javascript_array(); ?>

function init() {
  document.getElementById('disc_gl_acct_id').value    = default_disc_acct;
  // change color of the bill address fields if they are the default values
  default_sales_tax    = -1;
  contact_sales_tax    = -1;
  clearAddress('bill');
  setImage('');
  refreshOrderClock();
  changeOfTill();
  disablePopup();
  document.getElementById('backgroundPopup').addEventListener('click', function() { disablePopup(); });
  document.getElementById('disc_percent').addEventListener('keydown', function() { document.getElementById('discount').value = ''; });
  document.getElementById('discount').addEventListener('keydown', function() { document.getElementById('disc_percent').value = ''; });
  document.getElementById('amount').addEventListener('keydown', function(event) { if (event.keyCode == 13) SavePayment('save'); });
  document.getElementById('open_other_options').addEventListener('click', function() { open_other_options(); });
  document.getElementById('sku').focus();
}

function check_form() {
  var error = 0;
  var i, stock, qty, inactive, message;
  var error_message = "<?php echo JS_ERROR; ?>";
  var todo    = document.getElementById('action').value;
  if (error == 1) {
    alert(error_message);
    return false;
  }
  return true;
}

// Insert other page specific functions here.
function refreshOrderClock() {
  if (resClockID) {
    clearTimeout(resClockID);
    resClockID = 0;
  }
  if (setId) { // call the ajax to load the inventory info
    var upc = document.getElementById('sku').value;
    if (upc != text_search && upc.length == skuLength) {
      var acct = document.getElementById('bill_acct_id').value;
	  var numRows = document.getElementById('item_table_body').rows.length;
	  var qty = 1;
	  var rowCnt = 0;
	  for (var i=1; i<=numRows; i++) {
		if (document.getElementById('sku_' +i).value == sku && document.getElementById('fixed_price_' +i).value > formatted_zero){
		  qty = document.getElementById('pstd_' +i).value;
		  qty++;
		  rowCnt = i;
		}
	  }
	  fetchXml('index.php?module=inventory&page=ajax&op=inv_details&fID=skuDetails&cID='+acct+'&qty='+qty+'&upc='+upc+'&rID='+rowCnt+'&jID='+journalID, fillInventory);
	  document.getElementById('sku').value = '';
	}
  }
  resClockID = setTimeout("refreshOrderClock()", 250);
}

function salesTaxes(id, text, rate) {
  this.id   = id;
  this.text = text;
  this.rate = rate;
}

function purTaxes(id, text, rate) {
	  this.id   = id;
	  this.text = text;
	  this.rate = rate;
}

function currencyType(id, text, value, decimal_point, thousands_point, decimal_places, decimal_precise) {
	  this.id  			   = id;
	  this.text			   = text;
	  this.value 	       = value;
	  this.decimal_point   = decimal_point;
	  this.thousands_point = thousands_point;
	  this.decimal_places  = decimal_places;
	  this.decimal_precise = decimal_precise;
}

function till (id, restrictCurrency, currenciesCode, printer, startingLine, closingLine, openDrawer, defaultTax, storeID) {
	  this.id   		    = id;
	  this.restrictCurrency = restrictCurrency;
	  this.currenciesCode 	= currenciesCode;
	  this.printer			= printer;
	  this.startingLine		= startingLine;
	  this.closingLine		= closingLine;	
	  this.openDrawer		= openDrawer;
	  this.defaultTax		= defaultTax;
	  this.storeID			= storeID;
}

function ot_option (till_id, id, type, use_tax, taxable, description) {
	this.id   			= id;
	this.till_id		= till_id;
	this.type   		= type;
	this.use_tax   		= use_tax;
	this.taxable   		= taxable;
	this.description	= description;
}

function ClearForm() {	
}

function stripslashes (str) {
	return (str + '').replace(/\\(.?)/g, function (s, n1) {
		switch (n1) {
	    	case '\\':
	      		return '\\';
	    	case '0':
	      		return '\u0000';
	    	case '':
	      		return '';
	    	default:
	      		return n1;
	    	}
	});
}


function CloseTill(){
	OpenDrawer();
	var tillId = document.getElementById('till_id').value;
	location.href = 'index.php?module=phreepos&page=closing&till_id='+tillId;
}

function resetForm() {
	clearAddress('bill');
    document.getElementById('purchase_invoice_id').value= '';
	document.getElementById('id').value                 = '';
    document.getElementById('printed').value            = '0';
	document.getElementById('disc_percent').value       = formatted_zero;
	document.getElementById('discount').value           = formatted_zero;
	setImage('');
	// handle checkboxes
	document.getElementById('bill_add_update').checked  = false;
	changeOfTill();
	// remove all item rows and add a new blank one
	while (document.getElementById('item_table_body').rows.length >= 1) document.getElementById('item_table_body').deleteRow(-1);
	while (document.getElementById('payment_table_body').rows.length >= 1) document.getElementById('payment_table_body').deleteRow(-1);
	updateTotalPrices();
	document.getElementById('sku').focus();
}

function clearAddress(type) {
	document.getElementById(type+'_acct_id').value              = '';
	document.getElementById(type+'_address_id').value           = '';
	document.getElementById('search').value   		        	= '';
	document.getElementById('copy_search').value   		        = '';
	document.getElementById(type+'_country_code').value         = store_country_code;
	document.getElementById(type+'_to_select').style.visibility = 'hidden';
  	if (document.getElementById(type+'_to_select')) {
      	while (document.getElementById(type+'_to_select').options.length) {
	    	document.getElementById(type+'_to_select').remove(0);
      	}
  	}
    document.getElementById('copy_bill_primary_name').value       = default_array[0];
  	document.getElementById('copy_bill_primary_name').style.color = inactive_text_color;
  	for (var i=0; i<add_array.length; i++) {
		var add_id = add_array[i];
		if (add_id != 'country_code') document.getElementById(type+'_'+add_id).style.color = inactive_text_color;
		document.getElementById(type+'_'+add_id).value = default_array[i];	
  	}
}

function ajaxOrderData(cID, oID, jID, open_order, ship_only) {
  if (cID) {
    fetchXml('index.php?module=phreebooks&page=ajax&op=load_order&cID='+cID+'&oID='+oID+'&jID='+jID+'&so_po=0&ship_only=0', fillOrderData);
  } else if (oID) {
    fetchXml('index.php?module=phreepos&page=ajax&op=print_previous&oID='+oID, PrintPreviousReceipt);
  }
}

function fillOrderData(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  if (xml.getElementsByTagName("OrderData").length) {
    orderFillAddress(xml, 'bill', false);
    fillOrder(xml);
  } else if (xml.getElementsByTagName("BillContact").length) {
    orderFillAddress(xml, 'bill', true);
  }
}

function orderFillAddress(xml, type, fill_address) {
  var newOpt, mainType;
  while (document.getElementById(type+'_to_select').options.length) document.getElementById(type+'_to_select').remove(0);
  Array.from(xml.getElementsByTagName('BillContact')).forEach(function(contact) {
    var id = xmlText(contact, "id");
    if (!id) return;
    mainType = xmlText(contact, "type") + 'm';
    switch (type) {
      default:
      case 'bill':
        bill_add          = contact;
        default_sales_tax = xmlText(contact, "tax_id");
        contact_sales_tax = xmlText(contact, "tax_id");
        default_inv_acct  = xmlText(contact, "gl_type_account") || '';
        insertValue('bill_acct_id', id);
        insertValue('search',       xmlText(contact, "short_name"));
        insertValue('copy_search',  xmlText(contact, "short_name"));
        insertValue('acct_1',       default_inv_acct);
        var rowCnt = 1;
        while (true) {
          if (!document.getElementById('tax_'+rowCnt)) break;
          document.getElementById('tax_'+rowCnt).value = xmlText(contact, "tax_id");
          rowCnt++;
        }
        if (show_status == 1) {
          window.open("index.php?module=phreebooks&page=popup_status&id="+id,"contact_status","width=500,height=300,resizable=0,scrollbars=1,top=150,left=200");
        }
        break;
    }
    var iIndex = 0;
    Array.from(contact.getElementsByTagName("Address")).forEach(function(addr) {
      newOpt = document.createElement("option");
      newOpt.text = xmlText(addr, "primary_name") + ', ' + xmlText(addr, "city_town") + ', ' + xmlText(addr, "postal_code");
      document.getElementById(type+'_to_select').options.add(newOpt);
      document.getElementById(type+'_to_select').options[iIndex].value = xmlText(addr, "address_id");
      if (fill_address && xmlText(addr, "type") == mainType) {
        insertValue(type+'_address_id', xmlText(addr, "address_id"));
        Array.from(addr.children).forEach(function(child) {
          var tagName = child.tagName;
          if (document.getElementById(type+'_'+tagName)) {
            document.getElementById(type+'_'+tagName).value = child.textContent;
            document.getElementById(type+'_'+tagName).style.color = '';
          }
          if (document.getElementById('copy_'+type+'_'+tagName)) {
            document.getElementById('copy_'+type+'_'+tagName).value = child.textContent;
            document.getElementById('copy_'+type+'_'+tagName).style.color = '';
          }
        });
      }
      iIndex++;
    });
    newOpt = document.createElement("option");
    newOpt.text = text_enter_new;
    document.getElementById(type+'_to_select').options.add(newOpt);
    document.getElementById(type+'_to_select').options[iIndex].value = '0';
    document.getElementById(type+'_to_select').style.visibility      = 'visible';
    document.getElementById(type+'_to_select').disabled              = false;
  });
  var numRows = document.getElementById('item_table_body').rows.length;
  for (var rowCnt = 1; rowCnt <= numRows; rowCnt++) {
    if (document.getElementById('sku_'+rowCnt).value != '') {
      document.getElementById('tax_'+rowCnt).value = (default_sales_tax == -1)
        ? document.getElementById('product_tax_'+rowCnt).value : default_sales_tax;
      updateRowTotal(rowCnt, true);
    }
  }
  document.getElementById('sku').focus();
}

function fillOrder(xml) {
  Array.from(xml.getElementsByTagName("OrderData")).forEach(function(od) {
    Array.from(od.children).forEach(function(child) {
      var tagName = child.tagName;
      if (document.getElementById(tagName)) {
        document.getElementById(tagName).value = child.textContent;
        document.getElementById(tagName).style.color = '';
      }
    });
    document.getElementById('display_currency').value = xmlText(od, "currencies_code");
    if (xmlText(od, "id") && journalID != 6 && journalID != 7 && journalID != 21) {
      document.getElementById('purchase_invoice_id').readOnly = true;
    }
    addInvRow();
    var order_discount = formatted_zero;
    var jIndex = 1;
    Array.from(od.getElementsByTagName("Item")).forEach(function(item) {
      var gl_type = xmlText(item, "gl_type");
      switch (gl_type) {
        case 'ttl':
        case 'tax':
          break;
        case 'dsc':
          order_discount = xmlText(item, "total");
          if (xmlText(item, "gl_account")) insertValue('disc_gl_acct_id', xmlText(item, "gl_account"));
          break;
        case 'soo':
        case 'sos':
        case 'poo':
        case 'por':
          insertValue('id_'       + jIndex, xmlText(item, "id"));
          insertValue('pstd_'     + jIndex, xmlText(item, "pstd"));
          insertValue('sku_'      + jIndex, xmlText(item, "sku"));
          insertValue('desc_'     + jIndex, xmlText(item, "description"));
          insertValue('acct_'     + jIndex, xmlText(item, "gl_account"));
          insertValue('tax_'      + jIndex, xmlText(item, "taxable"));
          insertValue('full_'     + jIndex, xmlText(item, "full_price"));
          insertValue('serial_'   + jIndex, xmlText(item, "serialize"));
          insertValue('inactive_' + jIndex, xmlText(item, "inactive"));
          insertValue('price_'    + jIndex, xmlText(item, "unit_price"));
          insertValue('total_'    + jIndex, xmlText(item, "total"));
          updateRowTotal(jIndex, false);
          jIndex++;
        default:
      }
    });
    insertValue('discount', order_discount);
    calculateDiscountPercent();
  });
}

function accountGuess(force) {
  if (!force) {
	AccountList();
	return;
  } 
  var warn = true;
  var firstguess  = document.getElementById('copy_search').value; 
  var guess = document.getElementById('search').value;
  if( firstguess != guess && firstguess != text_search && firstguess != ''){
	  guess = firstguess;
  }
  // test for data already in the form
  if (guess == '') return alert('Please enter a guess to search for the contact!');
  if (guess != text_search && guess != '') {
    if (document.getElementById('bill_acct_id').value ||
        document.getElementById('bill_primary_name').value != default_array[0]) {
          warn = confirm(warn_form_modified);
	}
	if (warn) {
      fetchXml('index.php?module=phreebooks&page=ajax&op=load_searches&jID='+journalID+'&type=c&guess='+guess, processAccountGuess);
    }
  }
}

function processAccountGuess(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  if (xmlText(xml, "result") == 'success') {
    fillOrderData(xml);
  } else {
    AccountList();
  }
}

function AccountList(currObj) {
	var firstguess  = document.getElementById('copy_search').value; 
	var secondguess = document.getElementById('search').value;
	if ((firstguess == text_search || firstguess == '') && (secondguess == text_search || secondguess == '') ) return;
	var guess = secondguess;
	if( firstguess != secondguess && firstguess != text_search && firstguess != ''){
		  guess = firstguess;
	}
	window.open("index.php?module=contacts&page=popup_accts&type=c&fill=bill&jID=19&search_text="+guess,"accounts","width=850,height=550,resizable=1,scrollbars=1,top=150,left=100");
}

function InventoryList(rowCnt) {
  var url = "index.php?module=inventory&page=popup_inv&type="+account_type+"&rowID="+rowCnt;
  var cid = document.getElementById('bill_acct_id').value;
  var sku = document.getElementById('sku').value;
  var sid = document.getElementById('store_id').value;
  if (cid) url += "&cID="+cid;
  if (sku) url += "&search_text="+sku;
  if (sid) url += "&storeID="+sid;
  window.open(url, "inventory", "width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

function serialList(rowID) {
   var choice    = document.getElementById(rowID).value;
   var newChoice = prompt(serial_num_prompt, choice);
   if (newChoice) document.getElementById(rowID).value = newChoice;
}

function fillAddress(type) {
  var index = document.getElementById(type+'_to_select').value;
  var address;
  if (type == "bill") address = bill_add;
  if (type == "ship") return;
  if (index == '0') { // set to defaults
    document.getElementById(type+'_acct_id').value    = 0;
    document.getElementById(type+'_address_id').value = 0;
    for (var i=0; i<add_array.length; i++) {
	  add_id = add_array[i];
	  if (add_id != 'country_code') document.getElementById(type+'_'+add_id).style.color = inactive_text_color;
	  document.getElementById(type+'_'+add_id).value = default_array[i];
    }
    return;
  }
  Array.from(address.getElementsByTagName("Address")).forEach(function(addr) {
    if (xmlText(addr, "address_id") == index) {
      document.getElementById(type+'_acct_id').value    = xmlText(addr, "ref_id");
      document.getElementById(type+'_address_id').value = (index == 'new') ? '0' : xmlText(addr, "address_id");
      var add_id;
      for (var i = 0; i < add_array.length; i++) {
        add_id = add_array[i];
        var val = xmlText(addr, add_id);
        if (index != '0' && val) {
          document.getElementById(type+'_'+add_id).style.color = '';
          document.getElementById(type+'_'+add_id).value = val;
        } else {
          if (add_id != 'country_code') document.getElementById(type+'_'+add_id).style.color = inactive_text_color;
          document.getElementById(type+'_'+add_id).value = default_array[i];
        }
      }
    }
  });
}

function addInvRow() {
  var newCell;
  var cell;
  var newRow = document.getElementById('item_table_body').insertRow(-1);
  var rowCnt = newRow.rowIndex;
  // NOTE: any change here also need to be made to template form for reload if action fails
  cell  = '<td align="center">';
  cell += buildIcon(icon_path+'16x16/emblems/emblem-unreadable.png', image_delete_text, '  id="delete_'+rowCnt+'" onclick="if (confirm(\''+image_delete_msg+'\')) removeInvRow('+rowCnt+');"') + '</td>';
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  cell  = '<td align="left"><input type="text" name="pstd_'+rowCnt+'" id="pstd_'+rowCnt+'" size="5" maxlength="6" onchange="updateRowTotal('+rowCnt+', true)" style="text-align:right" />';
  cell += '&nbsp;' + buildIcon(icon_path+'16x16/actions/tab-new.png', image_ser_num, 'onclick="serialList(\'serial_'+rowCnt+'\')" id="serial_'+rowCnt+'"');
  cell += '</td>';
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  cell  = '<td align="left"><input type="text" name="sku_'+rowCnt+'" id="sku_'+rowCnt+'" readonly="readonly" size="'+(max_sku_len+1)+'" maxlength="'+max_sku_len+'"  />&nbsp;';
  cell += buildIcon(icon_path+'16x16/actions/document-properties.png', text_properties, 'id="sku_prop_'+rowCnt+'" align="top" style="cursor:pointer" onclick="InventoryProp('+rowCnt+')"');
  cell += '</td>';
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  cell = '<td><input name="desc_'+rowCnt+'" id="desc_'+rowCnt+'" readonly="readonly" size="40" maxlength="255" style="text-overflow:ellipsis;"/></td>';
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  if (display_with_tax) { 
    cell  = '<td align="center"><input type="text" name="wtprice_'+rowCnt+'" id="wtprice_'+rowCnt+'" <?php if($security_level < 3) echo 'readonly="readonly"'; ?> size="10" maxlength="15" style="text-align:right" onchange="rowWithTax('+rowCnt+')" value="'+formatted_zero+'"/></td>';
  }else{
  	cell  = '<td align="center"><input type="text" name="price_'+rowCnt+'"   id="price_'+rowCnt+'"   <?php if($security_level < 3) echo 'readonly="readonly"'; ?> size="10" maxlength="15" style="text-align:right" onchange="updateRowTotal('+rowCnt+',false)" value="'+formatted_zero+'"/></td>';
  }
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  document.getElementById('serial_'+rowCnt).style.display = 'none';
  cell  = '<td align="center">';
// Hidden fields
  cell += '<input type="hidden" name="id_'+rowCnt+'" id="id_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="stock_'+rowCnt+'" id="stock_'+rowCnt+'" value="NA" />';
  cell += '<input type="hidden" name="inactive_'+rowCnt+'" id="inactive_'+rowCnt+'" value="0" />';
  cell += '<input type="hidden" name="serial_'+rowCnt+'" id="serial_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="full_'+rowCnt+'" id="full_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="fixed_price_'+rowCnt+'" id="fixed_price_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="disc_'+rowCnt+'" id="disc_'+rowCnt+'" value="0" />';
  cell += '<input type="hidden" name="acct_'+rowCnt+'" id="acct_'+rowCnt+'" value="'+default_inv_acct+'" />';
  cell += '<input type="hidden" name="tax_'+rowCnt+'" id="tax_'+rowCnt+'" value="0" />';
  cell += '<input type="hidden" name="product_tax_'+rowCnt+'" id="product_tax_'+rowCnt+'" value="0" />';
  if (display_with_tax) { 
	cell += '<input type="hidden" name="price_'+rowCnt+'" id="price_'+rowCnt+'" value="'+formatted_zero+'" />';
    cell += '<input type="hidden" name="total_'+rowCnt+'" id="total_'+rowCnt+'" value="'+formatted_zero+'" />';
    cell += '<input type="text" name="wttotal_'+rowCnt+'" id="wttotal_'+rowCnt+'" value="'+formatted_zero+'" readonly="readonly" size="10" maxlength="20" style="text-align:right" /></td>';
  }else{
	cell += '<input type="hidden" name="wtprice_'+rowCnt+'" id="wtprice_'+rowCnt+'" value="'+formatted_zero+'" />';
    cell += '<input type="hidden" name="wttotal_'+rowCnt+'" id="wttotal_'+rowCnt+'" value="'+formatted_zero+'" />';
    cell += '<input type="text" name="total_'+rowCnt+'" id="total_'+rowCnt+'"   value="'+formatted_zero+'" readonly="readonly" size="10" maxlength="20" style="text-align:right" /></td>';
  }
// End hidden fields
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  return rowCnt;
}

function removeInvRow(index) {
  	var i, acctIndex, offset, newOffset;
  	var numRows = document.getElementById('item_table_body').rows.length;
  	// remove row from display by reindexing and then deleting last row
  	for (i=index; i<numRows; i++) {
		document.getElementById('pstd_'+i).value     	= document.getElementById('pstd_'+(i+1)).value;
		document.getElementById('sku_'+i).value      	= document.getElementById('sku_'+(i+1)).value;
		document.getElementById('desc_'+i).value     	= document.getElementById('desc_'+(i+1)).value;
		document.getElementById('price_'+i).value    	= document.getElementById('price_'+(i+1)).value;
		document.getElementById('acct_'+i).value     	= document.getElementById('acct_'+(i+1)).value;
		document.getElementById('tax_'+i).value      	= document.getElementById('tax_'+(i+1)).value;
		document.getElementById('product_tax_'+i).value = document.getElementById('product_tax_'+(i+1)).value;
		// Hidden fields
		document.getElementById('id_'+i).value       	= document.getElementById('id_'+(i+1)).value;
		document.getElementById('stock_'+i).value    	= document.getElementById('stock_'+(i+1)).value;
		document.getElementById('inactive_'+i).value 	= document.getElementById('inactive_'+(i+1)).value;
		document.getElementById('serial_'+i).value   	= document.getElementById('serial_'+(i+1)).value;
		document.getElementById('full_'+i).value     	= document.getElementById('full_'+(i+1)).value;
		document.getElementById('fixed_price_'+i).value	= document.getElementById('fixed_price_'+(i+1)).value;
		document.getElementById('disc_'+i).value     	= document.getElementById('disc_'+(i+1)).value;
		// End hidden fields
		document.getElementById('total_'+i).value    	= document.getElementById('total_'+(i+1)).value;
		document.getElementById('wttotal_'+i).value  	= document.getElementById('wttotal_'+(i+1)).value;
		document.getElementById('wtprice_'+i).value  	= document.getElementById('wtprice_'+(i+1)).value;
		if (document.getElementById('sku_'+i).value != '') {
			document.getElementById('pstd_'+i).disabled = false;
			document.getElementById('sku_prop_'+i).style.display = '';
			document.getElementById('sku_'+i).style.display = '';
		}
  	}
  	document.getElementById('item_table_body').deleteRow(-1);
  	updateTotalPrices();
} 

function addPmtRow() {
  var newCell;
  var cell;
  var newRow = document.getElementById('payment_table_body').insertRow(-1);
  var rowCnt = newRow.rowIndex;
  // NOTE: any change here also need to be made to template form for reload if action fails
  cell  = '<td align="left">';
  cell += buildIcon(icon_path+'16x16/emblems/emblem-unreadable.png', image_delete_text, 'onclick="if (confirm(\''+image_delete_msg+'\')) removePmtRow('+rowCnt+');"') + '</td>';
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  cell = '<td class="main"><input name="pdes_'+rowCnt+'" id="pdes_'+rowCnt+'" readonly="readonly" size="20" maxlength="25" /></td>';
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  cell  = '<td class="main" align="center">';
// Hidden fields
  cell += '<input type="hidden" name="meth_'+rowCnt+'" id="meth_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="f0_'+rowCnt+'" id="f0_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="f1_'+rowCnt+'" id="f1_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="f2_'+rowCnt+'" id="f2_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="f3_'+rowCnt+'" id="f3_'+rowCnt+'" value="" />';
  cell += '<input type="hidden" name="f4_'+rowCnt+'" id="f4_'+rowCnt+'" value="" />';
// End hidden fields
  cell += '<input type="text" name="pmt_'+rowCnt+'" id="pmt_'+rowCnt+'" value="'+formatted_zero+'" readonly="readonly" size="11" maxlength="20" style="text-align:right" /></td>';
  newCell = newRow.insertCell(-1);
  newCell.innerHTML = cell;
  return rowCnt;
}

function removePmtRow(index) {
  var i, acctIndex, offset, newOffset;
  var numRows = document.getElementById('payment_table_body').rows.length;
  // remove row from display by reindexing and then deleting last row
  for (i=index; i<numRows; i++) {
	// move the delete icon from the previous row
	offset    = i+1;
	newOffset = i;
	document.getElementById('payment_table_body').rows[newOffset].cells[0].innerHTML = delete_icon_HTML_PMT + i + ');">';
	document.getElementById('pdes_'+i).value = document.getElementById('pdes_'+(i+1)).value;
// Hidden fields
	document.getElementById('meth_'+i).value = document.getElementById('meth_'+(i+1)).value;
	document.getElementById('f0_'+i).value   = document.getElementById('f0_'+(i+1)).value;
	document.getElementById('f1_'+i).value   = document.getElementById('f1_'+(i+1)).value;
	document.getElementById('f2_'+i).value   = document.getElementById('f2_'+(i+1)).value;
	document.getElementById('f3_'+i).value   = document.getElementById('f3_'+(i+1)).value;
	document.getElementById('f4_'+i).value   = document.getElementById('f4_'+(i+1)).value;
// End hidden fields
	document.getElementById('pmt_'+i).value  = document.getElementById('pmt_'+(i+1)).value;
  }
  document.getElementById('payment_table_body').deleteRow(-1);
  updateTotalPrices();
} 

function rowWithTax(rowCnt){
	var tax_index = document.getElementById('tax_'+rowCnt).value;
	var price = document.getElementById('wtprice_'+rowCnt).value;
	document.getElementById('wtprice_'+rowCnt).value = formatCurrency(cleanCurrency(price));
	var text = formatCurrency(cleanCurrency(price )/ (1+(tax_rates[tax_index].rate / 100)));
	document.getElementById('price_'+rowCnt).value = text;
	updateRowTotal(rowCnt, false);
}

function updateRowTotal(rowCnt, useAjax) {
	var unit_price   = cleanCurrency(document.getElementById('price_'+rowCnt).value);
	var full_price   = cleanCurrency(document.getElementById('full_' +rowCnt).value);
	var tax_index    = document.getElementById('tax_'+rowCnt).value;
	if (tax_index == -1 || tax_index == '') tax_index = 0;
	var wtunit_price = unit_price * (1 +(tax_rates[tax_index].rate / 100));
	var qty          = parseFloat(document.getElementById('pstd_'+rowCnt).value);
	if (isNaN(qty)) qty = 1; // if blank or a non-numeric value is in the pstd field, assume one
	var total_line   = qty * unit_price;
	var total_l      = new String(total_line);
	var wttotal_line = qty * wtunit_price;
	var wttotal_l    = new String(wttotal_line);
	document.getElementById('price_'   +rowCnt).value    = formatPrecise(unit_price);
	document.getElementById('total_'   +rowCnt).value    = formatCurrency(total_l);
	document.getElementById('wttotal_' +rowCnt).value    = formatCurrency(wttotal_l);
	document.getElementById('wtprice_' +rowCnt).value	 = formatCurrency(wtunit_price);
	// calculate discount
	if (full_price > 0) {
	  	var discount = (full_price - unit_price) / full_price;
	  	document.getElementById('disc_'+rowCnt).value = new String(Math.round(1000*discount)/10);
	}
	updateTotalPrices();
	// call the ajax price sheet update based on customer
	if (useAjax && qty != 0 && sku != '' && sku != text_search) {
	  var sku = document.getElementById('sku_'+rowCnt).value;
	  var cID = document.getElementById('bill_acct_id').value;
	  if (auto_load_sku) {
	    fetchXml('index.php?module=inventory&page=ajax&op=inv_details&fID=skuPrice&cID='+cID+'&sku='+sku+'&qty='+qty+'&rID='+rowCnt+'&strict=1', processSkuPrice);
	  }
	}
}

function processSkuPrice(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var exchange_rate = document.getElementById('currencies_value').value;
  var rowCnt = xmlText(xml, "rID");
  var sales_price = xmlText(xml, "sales_price");
  if (formatPrecise(sales_price) != formatted_zero) {
    document.getElementById('fixed_price_'+rowCnt).value = formatPrecise(sales_price * exchange_rate);
    document.getElementById('price_'+rowCnt).value       = formatPrecise(sales_price * exchange_rate);
    document.getElementById('full_'+rowCnt).value        = formatCurrency(xmlText(xml, "full_price") * exchange_rate);
    updateRowTotal(rowCnt, false);
  }
}

function updateUnitPrice(rowCnt) {
  var total_line = cleanCurrency(document.getElementById('total_'+rowCnt).value);
  document.getElementById('total_'+rowCnt).value = formatCurrency(total_line);
  var qty = parseFloat(document.getElementById('pstd_'+rowCnt).value);
  if (isNaN(qty)) {
	qty = 1;
	document.getElementById('pstd_'+rowCnt).value = qty;
  }
  var unit_price = total_line / qty;
  var unit_p = new String(unit_price);
  document.getElementById('price_'+rowCnt).value = formatPrecise(unit_p);
  updateTotalPrices();
}

function calculateRoundingOf(new_total){
  switch (rounding_of){
	case 0: // no rounding of.
		return new_total;
		break;
	case 1:// rounds of to the nearest Integer. in favor of the customer
		var result = Math.floor (new_total);
		var differance = result - (Math.round(new_total * 100) / 100);
		document.getElementById('rounded_of').value = formatCurrency(differance);
		return result;
		break;
		
	case 2:// rounds of to the nearest 10cents. in favor of the customer
		var result = Math.floor (new_total * 10)/10;
		var differance = result - (Math.round(new_total * 100) / 100);
		document.getElementById('rounded_of').value = formatCurrency(differance);
		return result;
		break;
	case 3:// rounds of to the nearest 0, 5 or 10 cents (1,2,6,7 go down 3,4,8,9 go up)
		var result = Math.round (new_total * 20)/20;
		var differance = result - (Math.round(new_total * 100) / 100);
		document.getElementById('rounded_of').value = formatCurrency(differance);
		return result;
		break;
		
	}
}

function updateTotalPrices() {
  var discount = parseFloat(cleanCurrency(document.getElementById('discount').value));
  if (isNaN(discount)) discount = 0;
  var discountPercent = parseFloat(cleanCurrency(document.getElementById('disc_percent').value));
  if (isNaN(discountPercent)) discountPercent = 0;
  var subtotal         = 0;
  var taxable_subtotal = 0;
  var lineTotal        = '';
  var numRows          = document.getElementById('item_table_body').rows.length;
  for (var i=1; i<=numRows; i++) {
	var tax_index    = document.getElementById('tax_'+i).value;
    lineTotal  = parseFloat(cleanCurrency(document.getElementById('total_'+i).value));
  	if (tax_index != 0) {
	  if (tax_index == -1 || tax_index == '') { // if the rate array index is not defined
		tax_index = 0;
		document.getElementById('tax_'+i).value = tax_index;
	  }
	  if (tax_before_discount == '0') { // tax after discount
        taxable_subtotal += lineTotal * (1-(discountPercent/100)) * (tax_rates[tax_index].rate / 100);
	  } else { 
        taxable_subtotal += lineTotal * (tax_rates[tax_index].rate / 100);
	  }
	}
	subtotal += lineTotal;
  }
  // recalculate discount
  if (discount_from_total){
	discount = (discountPercent/100) * (subtotal + taxable_subtotal) ;
  } else  {
    discount = subtotal * (discountPercent/100);
  }
  var strDiscount = new String(discount);
  document.getElementById('discount').value = formatCurrency(strDiscount);
  var nst         = new String(taxable_subtotal);
  document.getElementById('sales_tax').value = formatCurrency(nst);
  var st          = new String(subtotal);
  document.getElementById('subtotal').value = formatCurrency(st);
  var new_total   = calculateRoundingOf(subtotal - discount + taxable_subtotal);
  var tot         = new String(new_total);
  document.getElementById('total').value = formatCurrency(tot);
  var numRows     = document.getElementById('payment_table_body').rows.length;
  var pmtTotal    = 0;
  for (var i=1; i<=numRows; i++) {
    pmtTotal += parseFloat(cleanCurrency(document.getElementById('pmt_'+i).value));
  }
  document.getElementById('pmt_recvd').value = formatCurrency(pmtTotal);
  var balDue = tot - pmtTotal;
  document.getElementById('bal_due').value = formatCurrency(balDue);
  if(popupStatus==1) document.getElementById('amount').value = formatCurrency(balDue);
}

function calculateDiscountPercent() {
  document.getElementById('discount').value = formatted_zero ;
  var percent  = parseFloat(cleanCurrency(document.getElementById('disc_percent').value));
  if (discount_from_total){
    var Total = parseFloat(cleanCurrency(document.getElementById('total').value));
    var discount = new String((percent / 100) * Total);
  }else{
  	var subTotal = parseFloat(cleanCurrency(document.getElementById('subtotal').value));
  	var discount = new String((percent / 100) * subTotal);
  }
  document.getElementById('discount').value = formatCurrency(discount);
  updateTotalPrices();
}

function calculateDiscount() {
  // determine the discount percent
  document.getElementById('disc_percent').value = formatted_zero ;
  var discount = parseFloat(cleanCurrency(document.getElementById('discount').value));
  document.getElementById('discount').value = formatted_zero ;
  updateTotalPrices();
  if (isNaN(discount)) discount = formatted_zero ;
  if (discount_from_total){
    var StartValue = parseFloat(cleanCurrency(document.getElementById('total').value));
  }else{
  	var StartValue = parseFloat(cleanCurrency(document.getElementById('subtotal').value));
  }
  if (StartValue != 0) {
    var percent = 100000 * (1 - ((StartValue - discount) / StartValue));
    document.getElementById('disc_percent').value = formatCurrency(Math.round(percent) / 1000);
    document.getElementById('discount').value = formatCurrency(discount);
  } 
  updateTotalPrices();
}

function recalculateCurrencies() {
  var workingTotal, workingUnitValue, itemTotal, newTotal, newFull, newFixedPrice;
  var currentCurrency = document.getElementById('currencies_code').value;
  var currentValue = parseFloat(document.getElementById('currencies_value').value);
  var desiredCurrency = document.getElementById('display_currency').value;
  var newValue = currency[desiredCurrency].value;
  newdecimal_places  = currency[desiredCurrency].decimal_places;
  newdecimal_precise = currency[desiredCurrency].decimal_precise;
  newdecimal_point   = currency[desiredCurrency].decimal_point;
  newthousands_point = currency[desiredCurrency].thousands_point;
  // update the line item table
  var numRows = document.getElementById('item_table_body').rows.length;
  for (var i=1; i<=numRows; i++) {
	var tax_index    = document.getElementById('tax_'+numRows).value;
	itemTotal = parseFloat(cleanCurrency(document.getElementById('total_'+i).value, currentCurrency));
	var tax_index  = document.getElementById('tax_'+i).value;
	if (isNaN(itemTotal)) continue;
	newTotal = itemTotal / currentValue * newValue;
	workingUnitValue = newTotal / document.getElementById('pstd_'+i).value;
	if (isNaN(workingUnitValue)) continue;
	newFull 		= parseFloat(cleanCurrency(document.getElementById('full_'   		+i).value)) / currentValue * newValue;
	newFixedPrice 	= parseFloat(cleanCurrency(document.getElementById('fixed_price_'   		+i).value)) / currentValue * newValue;
	document.getElementById('full_'   		+i).value    = newformatCurrency(new String(newFull), desiredCurrency);
	document.getElementById('fixed_price_' 	+i).value    = newformatCurrency(new String(newFixedPrice), desiredCurrency);
	document.getElementById('total_'   		+i).value    = newformatCurrency(new String(newTotal), desiredCurrency);
	document.getElementById('price_'   		+i).value    = newformatPrecise(new String(workingUnitValue), desiredCurrency);
	document.getElementById('wttotal_' 		+i).value    = newformatCurrency(newTotal * (1 +(tax_rates[tax_index].rate / 100)), desiredCurrency);
	document.getElementById('wtprice_' 		+i).value	 = newformatCurrency(workingUnitValue * (1 +(tax_rates[tax_index].rate / 100)), desiredCurrency);
  }
  var payNumRows     = document.getElementById('payment_table_body').rows.length;
  for (var i=1; i<=payNumRows; i++) {
    document.getElementById('pmt_'+i).value     = newformatCurrency(document.getElementById('pmt_'+i).value, desiredCurrency);
  }
  document.getElementById('disc_percent').value     = newformatPrecise(document.getElementById('disc_percent').value, desiredCurrency);
  formatted_zero  = newformatPrecise(formatted_zero, desiredCurrency);
  decimal_places  = currency[desiredCurrency].decimal_places;
  decimal_precise = currency[desiredCurrency].decimal_precise;
  decimal_point   = currency[desiredCurrency].decimal_point;
  thousands_point = currency[desiredCurrency].thousands_point;
  for (var i=1; i<=numRows; i++) {
	  updateRowTotal(i, false);
  }
  updateTotalPrices();
  // prepare the page settings for post
  document.getElementById('currencies_code').value  = desiredCurrency;
  document.getElementById('currencies_value').value = new String(newValue);
  document.getElementById('ot_currencies_code').value  = desiredCurrency;
  document.getElementById('ot_currencies_value').value = new String(newValue);
  
}

function newformatCurrency(amount) { // convert to expected currency format
  // amount needs to be a string type with thousands separator ',' and decimal point dot '.' 
  var factor  = Math.pow(10, newdecimal_places);
  var adj     = Math.pow(10, (newdecimal_places+2)); // to fix rounding (i.e. .1499999999 rounding to 0.14 s/b 0.15)
  var numExpr = parseFloat(amount);
  if (isNaN(numExpr)) return amount;
  numExpr     = Math.round((numExpr * factor) + (1/adj));
  var minus   = (numExpr < 0) ? '-' : ''; 
  numExpr     = Math.abs(numExpr);
  var decimal = (numExpr % factor).toString();
  while (decimal.length < newdecimal_places) decimal = '0' + decimal;
  var whole   = Math.floor(numExpr / factor).toString();
  for (var i = 0; i < Math.floor((whole.length-(1+i))/3); i++)
    whole = whole.substring(0,whole.length-(4*i+3)) + newthousands_point + whole.substring(whole.length-(4*i+3));
  if (newdecimal_places > 0) {
    return minus + whole + newdecimal_point + decimal;
  } else {
	return minus + whole;
  }
}

function newformatPrecise(amount) { // convert to expected currency format with the additional precision
  // amount needs to be a string type with thousands separator ',' and decimal point dot '.' 
  var factor = Math.pow(10, newdecimal_precise);
  var numExpr = parseFloat(amount);
  if (isNaN(numExpr)) return amount;
  numExpr = Math.round(numExpr * factor);
  var minus = (numExpr < 0) ? '-' : ''; 
  numExpr = Math.abs(numExpr);
  var decimal = (numExpr % factor).toString();
  while (decimal.length < newdecimal_precise) decimal = '0' + decimal;
  var whole = Math.floor(numExpr / factor).toString();
  for (var i = 0; i < Math.floor((whole.length-(1+i))/3); i++)
    whole = whole.substring(0,whole.length-(4*i+3)) + newthousands_point + whole.substring(whole.length-(4*i+3));
  if (newdecimal_precise > 0) {
    return minus + whole + newdecimal_point + decimal;
  } else {
	return minus + whole;
  }
}

// AJAX auto load SKU pair
function loadSkuDetails(iID, rowCnt) {
  	var qty, sku;
	// check to see if there is a sku present
  	if (!iID) sku = document.getElementById('sku').value; // read the search field as the real value
  	if (!iID && (sku == '' || sku === text_search)) return;
	// search if item is aready present then increment it by one
  	var numRows = document.getElementById('item_table_body').rows.length;
  	var qty = 1;
  	var rowCnt = 0;
  	for (var i=1; i<=numRows; i++) {
		if (document.getElementById('sku_' +i).value == sku && document.getElementById('fixed_price_' +i).value > formatted_zero){
	  		qty = document.getElementById('pstd_' +i).value;
	  		qty++;
	  		rowCnt = i;
		}
  	}
  	var cID = document.getElementById('bill_acct_id').value;
  	var bID = document.getElementById('store_id').value;
	fetchXml('index.php?module=inventory&page=ajax&op=inv_details&fID=skuDetails&bID='+bID+'&cID='+cID+'&qty='+qty+'&iID='+iID+'&strict=1&sku='+sku+'&rID='+rowCnt+'&jID='+journalID, fillInventory);
}

function fillInventory(xml) {
  var image = '';
  var exchange_rate = document.getElementById('currencies_value').value;
  xml = parseXml(xml);
  if (!xml) return;
  var sku     = xmlText(xml, "sku");
  var invType = xmlText(xml, "inventory_type");
  if (!sku || invType == 'ms' || invType == 'mb') { InventoryList(0); return; }
  var qty    = parseFloat(xmlText(xml, "qty"));
  var negate = <?php echo $_REQUEST['action']=='pos_return' ? 'true' : 'false'; ?>;
  if (negate) qty = -qty;
  var rowCnt = xmlText(xml, "rID");
  if (!rowCnt) {
    addInvRow();
    rowCnt = document.getElementById('item_table_body').rows.length;
  }
  var taxable      = xmlText(xml, "item_taxable");
  var sales_price  = xmlText(xml, "sales_price");
  document.getElementById('sku_'     +rowCnt).value = sku;
  document.getElementById('sku_'     +rowCnt).style.color = '';
  document.getElementById('full_'    +rowCnt).value = formatCurrency(xmlText(xml, "full_price") * exchange_rate);
  document.getElementById('inactive_'+rowCnt).value = xmlText(xml, "inactive");
  document.getElementById('pstd_'    +rowCnt).value = qty;
  document.getElementById('acct_'    +rowCnt).value = xmlText(xml, "account_sales_income");
  document.getElementById('price_'   +rowCnt).value = formatPrecise(sales_price * exchange_rate);
  document.getElementById('fixed_price_'+rowCnt).value = formatPrecise(sales_price * exchange_rate);
  document.getElementById('wtprice_' +rowCnt).value = formatCurrency((sales_price * exchange_rate) * (1 + (tax_rates[taxable].rate / 100)));
  if (invType == 'sr' || invType == 'sa') {
    document.getElementById('serial_'+rowCnt).style.display = '';
  }
  document.getElementById('product_tax_'+rowCnt).value = taxable;
  document.getElementById('tax_'+rowCnt).value = (default_sales_tax == -1) ? taxable : default_sales_tax;
  var desc = xmlText(xml, "description_sales") || xmlText(xml, "description_short");
  document.getElementById('desc_'+rowCnt).value = desc;
  updateRowTotal(rowCnt, false);
  document.getElementById('sku').focus();
  document.getElementById('sku').value = '';
  var imgPath = xmlText(xml, 'image_with_path');
  if (imgPath) image = "<?php echo DIR_WS_MY_FILES . $_SESSION['company'] . '/inventory/images/' ?>" + imgPath;
  setImage(image);
}

function changeOfTill(){
	var tillId = document.getElementById('till_id').value;
	document.getElementById('display_currency').disabled = (tills[tillId].restrictCurrency == 1);
	<?php if (ENABLE_MULTI_CURRENCY) {
	echo "document.getElementById('display_currency').value = tills[tillId].currenciesCode;";
	echo "recalculateCurrencies();";
	} ?>
	var drawerIcon = document.getElementById('tb_icon_open_drawer');
	if (drawerIcon) drawerIcon.style.display = tills[tillId].openDrawer == '' ? 'none' : '';
	var old_tax = default_sales_tax;
	default_sales_tax = tills[tillId].defaultTax;
	if(contact_sales_tax == -1){
		var rowCnt = 1;
		while(true) {
	  		if (!document.getElementById('tax_'+rowCnt)) break;
	  		if(old_tax != -1 && tills[tillId].defaultTax == -1){
	  			document.getElementById('tax_'+rowCnt).value = document.getElementById('product_tax_'+rowCnt).value;
	  		}else{
	  			document.getElementById('tax_'+rowCnt).value = tills[tillId].defaultTax;
	  		}
	  		updateRowTotal(rowCnt);
	  		rowCnt++;
		}
	}
	set_ot_options();
	document.getElementById('ot_till_id').value = tillId ;
	document.getElementById('store_id').value = tills[tillId].storeID;
}


function InventoryProp(elementID) {
  var sku = document.getElementById('sku_'+elementID).value;
  if (sku != text_search && sku != '') {
	  window.open("index.php?module=inventory&page=main&action=properties&sku="+sku+'&rowID='+elementID,"inventory","width=800,height=600,resizable=1,scrollbars=1,top=50,left=50");
  }
}

// this part is for the payment div and the ajax saving.
// pass any php variables generated during pre-process that are used in the javascript functions.
// Include translations here as well.
var resClockID  = 0;
var cardLength  = 30; // guess size of card to auto convert card information
var skuLength   = <?php echo ORD_BAR_CODE_LENGTH; ?>;
var pay_methods = <?php echo count($payment_modules) ? 'true' : 'false'; ?>;


function activateFields() {
  if (!pay_methods) return;
  var index = document.getElementById('payment_method').selectedIndex;
  for (var i=0; i<document.getElementById('payment_method').options.length; i++) {
   document.getElementById('pm_'+i).style.visibility = 'hidden';
  }
  document.getElementById('pm_'+index).style.visibility = '';
}

function readCard(override) {
  var index    = document.getElementById('payment_method').selectedIndex;
  var method   = document.getElementById('payment_method').options[index].value;
  if (!document.getElementById(method+'_field_0')) return false;
  var entry    = document.getElementById(method+'_field_0').value;
  if (entry.length < cardLength) return false; // not enough characters return
  var eof      = entry.search(/\?/);
  if (!eof) return false;// the end of line character has not been read, return and wait for rest of input.
  clearTimeout(resClockID);
  resClockID   = 0;
  resClockID   = setTimeout("parseCard()", 1000); // wait for the rest of the card to be input
  return true;
}

function parseCard() {
  clearTimeout(resClockID);
  var index    = document.getElementById('payment_method').selectedIndex;
  var method   = document.getElementById('payment_method').options[index].value;
  var entry    = document.getElementById(method+'_field_0').value;
  entry = entry.trim();
  // now trim the start if characters were present when scanned
  var bof      = entry.search(/\%B/);
  entry        = entry.substr(bof);
  var caret    = entry.search(/\^/);
  var cardNum  = entry.substr(2, caret-2);
  entry        = entry.substr(caret+1);
  caret        = entry.search(/\^/);
  var cardName = entry.substr(0, caret);
  entry        = entry.substr(caret+1);
  var cardYear = entry.substr(0,2);
  var cardMon  = entry.substr(2,2);
  document.getElementById(method+'_field_0').value = cardName.trim();
  document.getElementById(method+'_field_1').value = cardNum;
  document.getElementById(method+'_field_2').value = cardMon;
  document.getElementById(method+'_field_3').value = cardYear;
  if (document.getElementById(method+'_field_3')) {
    document.getElementById(method+'_field_4').focus();
  } else {
    document.getElementById('btn_save').focus();
  }
}

function SavePayment(PrintOrSave) { // request function
  var error_message = '';
  var amount = cleanCurrency(document.getElementById('amount').value);
  var index  = document.getElementById('payment_method').selectedIndex;
  var payment_method = document.getElementById('payment_method').options[index].value;
  var f0 = document.getElementById(payment_method+'_field_0') ? document.getElementById(payment_method+'_field_0').value : '';
  var f1 = document.getElementById(payment_method+'_field_1') ? document.getElementById(payment_method+'_field_1').value : '';
  var f2 = document.getElementById(payment_method+'_field_2') ? document.getElementById(payment_method+'_field_2').value : '';
  var f3 = document.getElementById(payment_method+'_field_3') ? document.getElementById(payment_method+'_field_3').value : '';
  var f4 = document.getElementById(payment_method+'_field_4') ? document.getElementById(payment_method+'_field_4').value : '';
<?php
  foreach ($payment_modules as $pmt_class) { // fetch the javascript validation of payments module
	$value = $pmt_class['id'];
	echo $$value->javascript_validation();
  }
?>
  if ( error_message != ''){
	  alert(error_message);
	  return false;
  }
  addPmtRow();
  var numRows = document.getElementById('payment_table_body').rows.length;
  document.getElementById('pdes_'+numRows).value = pmt_types[payment_method];
  document.getElementById('meth_'+numRows).value = payment_method;
  document.getElementById('pmt_'+numRows).value  = formatCurrency(amount);
  document.getElementById('f0_'+numRows).value   = f0;
  document.getElementById('f1_'+numRows).value   = f1;
  document.getElementById('f2_'+numRows).value   = f2;
  document.getElementById('f3_'+numRows).value   = f3;
  document.getElementById('f4_'+numRows).value   = f4;
  updateTotalPrices();
  disablePopup();
  if(document.getElementById('bal_due').value == formatCurrency(0)){
	ajaxSave(PrintOrSave);
  }
}

function ajaxSave(PrintOrSave) {
  refreshOrderClock();
  if (!save_allowed) return;
  save_allowed = false;
  showLoading();
  fetch('index.php?module=phreepos&page=ajax&op=save_main&action='+PrintOrSave, {
    method: 'POST',
    body: new FormData(document.querySelector('form'))
  })
  .then(function(r) { if (!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
  .then(function(t) { ajaxPrintAndClean(new DOMParser().parseFromString(t, 'text/xml')); })
  .catch(function(e) { alert('Ajax Error: '+e.message); save_allowed = true; });
  hideLoading();
}

function ajaxPrintAndClean(xml) {
  save_allowed = true;
  xml = parseXml(xml);
  if (!xml) return;
  var massage = xmlText(xml, "massage");
  if (massage) alert(massage);
  resetForm();
  var action = xmlText(xml, "action");
  var print  = action.substring(0, 5) == 'print';
  var tillId = document.getElementById('till_id').value;
  if (print && typeof qz !== 'undefined' && tills[tillId].printer != '') {
    var lines = [];
    for (var i in tills[tillId].startingLine) lines.push({type:'raw', format:'plain', data:tills[tillId].startingLine[i]});
    Array.from(xml.getElementsByTagName("receipt_data")).forEach(function(rd) {
      lines.push({type:'raw', format:'plain', data:stripslashes(xmlText(rd, "line")) + "\n"});
    });
    if (xmlText(xml, "open_cash_drawer") == 1) {
      for (var i in tills[tillId].openDrawer) lines.push({type:'raw', format:'plain', data:tills[tillId].openDrawer[i]});
    }
    for (var i in tills[tillId].closingLine) lines.push({type:'raw', format:'plain', data:tills[tillId].closingLine[i]});
    lines.push({type:'raw', format:'plain', data:"\n"});
    qz.security.setCertificatePromise(function(resolve) { resolve(''); });
    qz.security.setSignatureAlgorithm('SHA512');
    qz.security.setSignaturePromise(function(toSign) { return function(resolve) { resolve(''); }; });
    qz.websocket.connect().then(function() {
      return qz.printers.find(tills[tillId].printer);
    }).then(function(printer) {
      return qz.print(qz.configs.create(printer), lines);
    }).catch(function(e) { alert('Print error: ' + e); });
  } else if (xmlText(xml, "open_cash_drawer") == 1) {
    OpenDrawer();
  } else if (print) {
    var order_id = xmlText(xml, "order_id");
    var printWin = window.open("<?php echo gen_print_form_url(POPUP_FORM_TYPE, 'journal_main.id', 'EQUAL'); ?>" + order_id, "popup_gen", "width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
    printWin.focus();
  }
}


/*
 *printing previous reciept by this admin user 
 *
 */
function GetPrintPreviousReceipt() {
  fetchXml('index.php?module=phreepos&page=ajax&op=print_previous', PrintPreviousReceipt);
}

function PrintPreviousReceipt(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var massage = xmlText(xml, "massage");
  if (massage) alert(massage);
  var tillId = document.getElementById('till_id').value;
  if (typeof qz !== 'undefined' && tills[tillId].printer != '') {
    var lines = [];
    for (var i in tills[tillId].startingLine) lines.push({type:'raw', format:'plain', data:tills[tillId].startingLine[i]});
    Array.from(xml.getElementsByTagName("receipt_data")).forEach(function(rd) {
      lines.push({type:'raw', format:'plain', data:stripslashes(xmlText(rd, "line")) + "\n"});
    });
    for (var i in tills[tillId].closingLine) lines.push({type:'raw', format:'plain', data:tills[tillId].closingLine[i]});
    lines.push({type:'raw', format:'plain', data:"\n"});
    qz.security.setCertificatePromise(function(resolve) { resolve(''); });
    qz.security.setSignatureAlgorithm('SHA512');
    qz.security.setSignaturePromise(function(toSign) { return function(resolve) { resolve(''); }; });
    qz.websocket.connect().then(function() {
      return qz.printers.find(tills[tillId].printer);
    }).then(function(printer) {
      return qz.print(qz.configs.create(printer), lines);
    }).catch(function(e) { alert('Print error: ' + e); });
  } else {
    var order_id = xmlText(xml, "order_id");
    var printWin = window.open("<?php echo gen_print_form_url(POPUP_FORM_TYPE, 'journal_main.id', 'EQUAL'); ?>" + order_id, "reportFilter", "width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
    printWin.focus();
  }
  document.getElementById('sku').focus();
}

function OpenDrawer(){
  var tillId = document.getElementById('till_id').value;
  if (typeof qz !== 'undefined' && tills[tillId].printer != '') {
    var lines = [];
    for (var i in tills[tillId].openDrawer) lines.push({type:'raw', format:'plain', data:tills[tillId].openDrawer[i] + "\n"});
    lines.push({type:'raw', format:'plain', data:"\n"});
    qz.security.setCertificatePromise(function(resolve) { resolve(''); });
    qz.security.setSignatureAlgorithm('SHA512');
    qz.security.setSignaturePromise(function(toSign) { return function(resolve) { resolve(''); }; });
    qz.websocket.connect().then(function() {
      return qz.printers.find(tills[tillId].printer);
    }).then(function(printer) {
      return qz.print(qz.configs.create(printer), lines);
    }).catch(function(e) { alert('Print error: ' + e); });
  }
  document.getElementById('sku').focus();
}

function OpenOrdrList(currObj) {
	  window.open("index.php?module=phreebooks&page=popup_orders&jID="+journalID,"search_po","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

// start other transactions

function changeOfType(){
	if(document.getElementById('Other_trans_type').options.length == 0) return;
	var elt = document.getElementById('Other_trans_type');
	for (i = 0; i < ot_options.length; i++) {
		if(elt.options[elt.selectedIndex].value == ot_options[i].id){
			var showHide = function(sel, show) {
				document.querySelectorAll(sel).forEach(function(el) { el.style.display = show ? '' : 'none'; });
			};
			if (ot_options[i].type == 'expenses') {
				showHide('.ot_desc', true);
				showHide('.ot_amount', true);
				if (ot_options[i].use_tax == 1) {
					showHide('.ot_rate', true);
					showHide('.ot_tax', true);
					document.getElementById('ot_rate').value = ot_options[i].taxable;
				} else {
					showHide('.ot_rate', false);
					showHide('.ot_tax', false);
				}
			} else {
				showHide('.ot_desc', false);
				showHide('.ot_amount', true);
				showHide('.ot_rate', false);
				showHide('.ot_tax', false);
			}
		}
	}
	document.getElementById('sku').focus();
}

function set_ot_options() {
	document.getElementById('Other_trans_type').options.length = 0;
	var tillId = document.getElementById('till_id').value;
	for (i = 0; i < ot_options.length; i++) {
		if(ot_options[i].till_id == tillId){
			newOpt = document.createElement("option");
			newOpt.text = ot_options[i].description;
			newOpt.value = ot_options[i].id;
			document.getElementById('Other_trans_type').options.add(newOpt);
		}
	}
	document.getElementById('other_trans').style.display = document.getElementById('Other_trans_type').options.length == 0 ? 'none' : '';
	changeOfType();
}

function updateOt(){
	var amount		= cleanCurrency(document.getElementById('ot_amount').value);
	var tax_index   = document.getElementById('ot_rate').value;
	var tax_amount  = amount - ( amount / (1 +(ot_tax_rates[tax_index].rate / 100)));
	document.getElementById('ot_tax').value       = formatPrecise(tax_amount);
}

function SaveOt() {
  fetch('index.php?module=phreepos&page=ajax&op=other_transactions&action=save', {
    method: 'POST',
    body: new FormData(document.querySelector('form'))
  })
  .then(function(r) { if (!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
  .then(function() { cleanOt(); })
  .catch(function(e) { alert('Ajax Error: '+e.message); });
}

function cleanOt(){
	document.getElementById('ot_desc').value 	= '';
	document.getElementById('ot_amount').value 	= formatted_zero;
	document.getElementById('ot_tax').value 	= formatted_zero;
	disablePopup();
}

// end ohter transactions
//<!-- javascript for ajax popup

var popupStatus = 0;  //0 means disabled; 1 means enabled; 
var optionsStatus = 0;//0 means disabled; 1 means enabled;

function _centerPopup(id) {
  var el = document.getElementById(id);
  var ww = document.documentElement.clientWidth, wh = document.documentElement.clientHeight;
  el.style.position = 'absolute';
  el.style.top  = (wh / 2 - el.offsetHeight / 2) + 'px';
  el.style.left = (ww / 2 - el.offsetWidth  / 2) + 'px';
  var bg = document.getElementById('backgroundPopup');
  Object.assign(bg.style, { position:'absolute', opacity:'0.7', background:'#000000', top:'0px', left:'0px', height:wh+'px', width:ww+'px' });
}

function popupContact() {
  if (document.getElementById('bill_acct_id').value == '') { accountGuess(false); return; }
  if (popupStatus == 0) {
    document.getElementById('backgroundPopup').style.display = 'block';
    document.getElementById('customer_div').style.display    = 'block';
    popupStatus = 1;
  }
  _centerPopup('customer_div');
}

function popupPayment() {
  if (popupStatus == 0) {
    document.getElementById('backgroundPopup').style.display = 'block';
    document.getElementById('popupPayment').style.display    = 'block';
    popupStatus = 1;
    document.getElementById('amount').value = document.getElementById('bal_due').value;
    activateFields();
    document.getElementById('amount').select();
  }
  _centerPopup('popupPayment');
}

function open_other_options() {
  if (optionsStatus == 0) {
    document.getElementById('other_options').style.display = 'block';
    optionsStatus = 1;
  } else {
    document.getElementById('other_options').style.display = 'none';
    optionsStatus = 0;
    document.getElementById('sku').focus();
  }
}

function ShowOtherTrans() {
  document.getElementById('other_options').style.display   = 'none';
  document.getElementById('backgroundPopup').style.display = 'block';
  document.getElementById('popupOtherTrans').style.display = 'block';
  popupStatus = 1;
  _centerPopup('popupOtherTrans');
}

function disablePopup() {
  if (popupStatus == 1) {
    document.getElementById('popupOtherTrans').style.display = 'none';
    document.getElementById('backgroundPopup').style.display = 'none';
    document.getElementById('popupPayment').style.display    = 'none';
    document.getElementById('customer_div').style.display    = 'none';
    popupStatus = 0;
    document.getElementById('sku').focus();
  }
}

function setImage(src) {
  var img = document.getElementById('curr_image');
  if (src == '') {
    img.style.display = 'none';
    img.src = '';
  } else {
    img.style.display = '';
    img.src = src;
  }
}

document.addEventListener('keydown', function(event) {
  if (event.altKey && event.keyCode == 82) {
    event.preventDefault();
    window.location.assign(location.search === '?module=phreepos&page=main' ? '?module=phreepos&page=main&action=pos_return' : '?module=phreepos&page=main');
  }
  if (event.keyCode == 27) {
    event.preventDefault();
    if (optionsStatus == 1) open_other_options();
    if (popupStatus == 1) { disablePopup(); } else { resetForm(); }
  }
  if (event.keyCode == 38 && popupStatus == 1) { // arrow up
    event.preventDefault();
    var sel = document.getElementById('payment_method');
    sel.selectedIndex = (sel.selectedIndex === 0) ? sel.options.length - 1 : sel.selectedIndex - 1;
    activateFields();
  }
  if (event.keyCode == 40 && popupStatus == 1) { // arrow down
    event.preventDefault();
    var sel = document.getElementById('payment_method');
    sel.selectedIndex = (sel.selectedIndex === sel.options.length - 1) ? 0 : sel.selectedIndex + 1;
    activateFields();
  }
  if (event.keyCode == 118 && popupStatus == 0) { event.preventDefault(); InventoryList(0); }  // F7
  if (event.keyCode == 119 && popupStatus == 0) { event.preventDefault(); popupContact(); }    // F8
  if (event.keyCode == 120 && popupStatus == 0) { event.preventDefault(); popupPayment(); }    // F9
  if (event.keyCode == 122) { event.preventDefault(); if (popupStatus == 1) { SavePayment('save');  } else { popupPayment(); } }  // F11
  if (event.keyCode == 123) { event.preventDefault(); if (popupStatus == 1) { SavePayment('print'); } else { popupPayment(); } }  // F12
});


</script>
<link rel="stylesheet" type="text/css" href="modules/phreepos/style_sheet/main.css" />

