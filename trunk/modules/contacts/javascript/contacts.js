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
//  Path: /modules/contacts/javascript/contacts.js
//

function contactChart(func, d0) {
	chartProps.modID = "contacts";
	chartProps.divID = "contact_chart";
	chartProps.func  = func;
	chartProps.d0    = d0;
	document.getElementById(chartProps.divID).innerHTML = '&nbsp;';
	phreedomChart();
}

function getAddress(id, type) {
  fetchXml('index.php?module=contacts&page=ajax&op=contacts&action=get_address&type='+type+'&aID='+id, fillAddress);
}

function fillAddress(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var message = xmlText(xml, "message");
  if (message) { alert(message); }
  else {
	var type = xmlText(xml, "type");
	Array.from(xml.getElementsByTagName("Address")).forEach(function(addr) {
	  Array.from(addr.children).forEach(function(child) {
		var tagName = child.tagName;
		if (document.getElementById('address_'+type+'_'+tagName)) insertValue('address_'+type+'_'+tagName, child.textContent || '');
	  });
	});
	if (type == 'im') {
		insertValue('i_id',            xmlText(xml, "contact_id"));
		insertValue('i_short_name',    xmlText(xml, "short_name"));
		insertValue('i_contact_first', xmlText(xml, "contact_first"));
		insertValue('i_contact_middle',xmlText(xml, "contact_middle"));
		insertValue('i_contact_last',  xmlText(xml, "contact_last"));
		insertValue('i_account_number',xmlText(xml, "account_number"));
		insertValue('i_gov_id_number', xmlText(xml, "gov_id_number"));
	}
  }
}

function copyContactAddress(type) {
  insertValue('address_im_primary_name',   document.getElementById('address_'+type+'m_primary_name').value);
  insertValue('address_im_contact',        document.getElementById('address_'+type+'m_contact').value);
  insertValue('address_im_telephone1',     document.getElementById('address_'+type+'m_telephone1').value);
  insertValue('address_im_telephone2',     document.getElementById('address_'+type+'m_telephone2').value);
  insertValue('address_im_telephone3',     document.getElementById('address_'+type+'m_telephone3').value);
  insertValue('address_im_telephone4',     document.getElementById('address_'+type+'m_telephone4').value);
  insertValue('address_im_email',          document.getElementById('address_'+type+'m_email').value);
  insertValue('address_im_website',        document.getElementById('address_'+type+'m_website').value);
  insertValue('address_im_address1',       document.getElementById('address_'+type+'m_address1').value);
  insertValue('address_im_address2',       document.getElementById('address_'+type+'m_address2').value);
  insertValue('address_im_city_town',      document.getElementById('address_'+type+'m_city_town').value);
  insertValue('address_im_state_province', document.getElementById('address_'+type+'m_state_province').value);
  insertValue('address_im_postal_code',    document.getElementById('address_'+type+'m_postal_code').value);
  insertValue('address_im_country_code',   document.getElementById('address_'+type+'m_country_code').value);
}

function clearAddress(type) {
	document.querySelectorAll('#'+type+'_address_form input').forEach(function(el) { el.value = ''; });
	document.getElementById('address_'+type+'_address_id').value   = 0;
	document.getElementById('address_'+type+'_country_code').value = default_country;
	if (type == 'im') {
		if(document.getElementById('i_id'))             document.getElementById('i_id').value             = '';
		if(document.getElementById('i_short_name'))     document.getElementById('i_short_name').value     = '';
		if(document.getElementById('i_contact_middle')) document.getElementById('i_contact_middle').value = '';
		if(document.getElementById('i_contact_first'))  document.getElementById('i_contact_first').value  = '';
		if(document.getElementById('i_contact_last'))   document.getElementById('i_contact_last').value   = '';
		if(document.getElementById('i_account_number')) document.getElementById('i_account_number').value = '';
		if(document.getElementById('i_gov_id_number'))  document.getElementById('i_gov_id_number').value  = '';
	}
}

function deleteAddress(id) {
  fetchXml('index.php?module=contacts&page=ajax&op=contacts&action=rm_address&aID='+id, deleteAddressResp);
}

function deleteAddressResp(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var rowID = xmlText(xml, "address_id");
  if (rowID) { var row = document.getElementById('tr_add_'+rowID); if (row) row.remove(); }
  var message = xmlText(xml, "message");
  if (message) alert(message);
}

function TermsList() {
  var terms = document.getElementById('terms').value;
  window.open("index.php?module=contacts&page=popup_terms&type="+account_type+"&val="+terms,"terms","width=500,height=300,resizable=1,scrollbars=1,top=150,left=200");
}

function getPayment(id) {
  fetchXml('index.php?module=contacts&page=ajax&op=contacts&action=get_payment&pID='+id, fillPayment);
}

function fillPayment(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var message = xmlText(xml, "message");
  if (message) { alert(message); }
  else {
	document.getElementById('payment_id').value        = xmlText(xml, "payment_id");
	document.getElementById('payment_cc_name').value   = xmlText(xml, "field_0");
	document.getElementById('payment_cc_number').value = xmlText(xml, "field_1");
	document.getElementById('payment_exp_month').value = xmlText(xml, "field_2");
	document.getElementById('payment_exp_year').value  = xmlText(xml, "field_3");
	document.getElementById('payment_cc_cvv2').value   = xmlText(xml, "field_4");
  }
}

function clearPayment() {
  document.getElementById('payment_id').value                = 0;
  document.getElementById('payment_cc_name').value           = '';
  document.getElementById('payment_cc_number').value         = '';
  document.getElementById('payment_exp_month').selectedIndex = 0;
  document.getElementById('payment_exp_year').selectedIndex  = 0;
  document.getElementById('payment_cc_cvv2').value           = '';
}

function deletePayment(id) {
  fetchXml('index.php?module=contacts&page=ajax&op=contacts&action=rm_payment&pID='+id, deletePaymentResp);
}

function deletePaymentResp(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var rowID = xmlText(xml, "payment_id");
  if (rowID) { var row = document.getElementById('tr_pmt_'+rowID); if (row) row.remove(); }
  var message = xmlText(xml, "message");
  if (message) alert(message);
}

function deleteCRM(id) {
  fetchXml('index.php?module=contacts&page=ajax&op=contacts&action=rm_crm&nID='+id, deleteCRMResp);
}

function deleteCRMResp(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var rowID = xmlText(xml, "crm_id");
  if (rowID) {
	  var rowA = document.getElementById('tr_crm_a_'+rowID); if (rowA) rowA.remove();
	  var rowB = document.getElementById('tr_crm_b_'+rowID); if (rowB) rowB.remove();
  }
  var message = xmlText(xml, "message");
  if (message) alert(message);
}

function downloadAttachment(filename) {
  var file_name = attachment_path+filename;
  fetchXml('index.php?module=phreedom&page=ajax&op=phreedom&action=download&file='+file_name, downloadResponse);
}

function downloadResponse(xml) {
  xml = parseXml(xml);
  if (!xml) return;
}
