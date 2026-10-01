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
//  Path: /modules/contacts/ajax/contacts.php
//
/**************   Check user security   *****************************/
$security_level = validate_ajax_user();
/**************  include page specific files    *********************/
require_once(DIR_FS_MODULES . 'contacts/defaults.php');
require_once(DIR_FS_MODULES . 'contacts/classes/contacts.php');
/**************   page specific initialization  *************************/
$xml     = NULL;
$message = array();

// Whether the user holds $level on the contact's own type, as the contact
// pages require: delete rights (4) to remove an address, stored card or CRM
// note, edit rights (3) to read a stored card back. A CRM contact filed under
// a customer, vendor etc. also accepts the parent's.
function contacts_ajax_may_delete($contact_id, $level = 4) {
	global $db;
	$tokens = array(
		'b' => SECURITY_ID_MAINTAIN_BRANCH,
		'c' => SECURITY_ID_MAINTAIN_CUSTOMERS,
		'e' => SECURITY_ID_MAINTAIN_EMPLOYEES,
		'i' => SECURITY_ID_PHREECRM,
		'j' => SECURITY_ID_MAINTAIN_PROJECTS,
		'v' => SECURITY_ID_MAINTAIN_VENDORS,
	);
	$result = $db->Execute("select type, dept_rep_id from ".TABLE_CONTACTS." where id = ".(int)$contact_id);
	if ($result->RecordCount() < 1) return false;
	$types = array($result->fields['type']);
	if ($result->fields['type'] == 'i' && (int)$result->fields['dept_rep_id']) {
		$parent = $db->Execute("select type from ".TABLE_CONTACTS." where id = ".(int)$result->fields['dept_rep_id']);
		if ($parent->RecordCount() > 0) $types[] = $parent->fields['type'];
	}
	foreach ($types as $t) {
		if (isset($tokens[$t]) && (int)($_SESSION['admin_security'][$tokens[$t]] ?? 0) >= $level) return true;
	}
	return false;
}

function contacts_ajax_refuse() {
	echo createXmlHeader() . xmlEntry('error', ERROR_NO_PERMISSION) . createXmlFooter();
	die;
}

// The deletes come as POST, which index.php has checked for its csrf_token; a
// GET could be a link someone was sent.
if (in_array($_REQUEST['action'] ?? '', array('rm_address', 'rm_payment', 'rm_crm'), true)
    && $_SERVER['REQUEST_METHOD'] !== 'POST') contacts_ajax_refuse();

switch ($_REQUEST['action']) {
	case 'get_address':
		$id   = (int)($_GET['aID'] ?? 0);
		$type = $_GET['type'];
		$xml .= xmlEntry('type', $type);
		$result = $db->Execute("select * from ".TABLE_ADDRESS_BOOK." where address_id = $id");
		if ($result->RecordCount() < 1) {
			$message[] = sprintf('The record could not be found! Looking for id = %s', $id);
		} else {
			$xml .= '<Address>'.chr(10);
			foreach ($result->fields as $key => $value) $xml .= xmlEntry($key, $value);
			$xml .= '</Address>'.chr(10);
		}
		// if it's a CRM entry, we need some primary information
		$result = $db->Execute("select id, short_name, contact_first, contact_middle, contact_last, account_number, gov_id_number 
			from ".TABLE_CONTACTS." where id = ".$result->fields['ref_id']." limit 1");
		$xml .= xmlEntry('contact_id',    $result->fields['id']);
		$xml .= xmlEntry('short_name',    $result->fields['short_name']);
		$xml .= xmlEntry('contact_middle',$result->fields['contact_middle']);
		$xml .= xmlEntry('contact_first', $result->fields['contact_first']);
		$xml .= xmlEntry('contact_last',  $result->fields['contact_last']);
		$xml .= xmlEntry('account_number',$result->fields['account_number']);
		$xml .= xmlEntry('gov_id_number', $result->fields['gov_id_number']);
		break;

	case 'rm_address':
		$id     = (int)($_POST['aID'] ?? 0);
		$result = $db->Execute("select ref_id, type from ".TABLE_ADDRESS_BOOK." where address_id = $id");
		if (!contacts_ajax_may_delete($result->fields['ref_id'] ?? 0)) contacts_ajax_refuse();
		if ($result->fields['type'] == 'im') { // a CRM contact's main address: delete the contact with it
			$short_name = gen_get_contact_name($result->fields['ref_id']);
			$contact = new contacts();
			// Strictly true: delete() answers true, or the reason it refused.
			if ($contact->delete($result->fields['ref_id']) === true) {
	  			gen_add_audit_log(TEXT_CONTACTS . '-' . TEXT_DELETE . '-' . ACT_I_TYPE_NAME, $short_name);
				$message[] = 'The record was successfully deleted!';
			} else {
				$message[] = ACT_ERROR_CANNOT_DELETE;
			}
		} else { // just delete the address
			$db->Execute('delete from '.TABLE_ADDRESS_BOOK." where address_id = $id");
		}
		$message[] = 'The record was successfully deleted!';
		$xml .= xmlEntry('address_id', $id);
		break;

	case 'get_payment':
		$id = (int)($_GET['pID'] ?? 0);
		// A stored card, decrypted: only for someone who may edit that contact.
		$owner = $db->Execute("select ref_1 from ".TABLE_DATA_SECURITY." where id = $id and module = 'contacts'");
		if (!contacts_ajax_may_delete($owner->fields['ref_1'] ?? 0, 3)) contacts_ajax_refuse();
		$result = $db->Execute("select id, hint, enc_value from ".TABLE_DATA_SECURITY." where id = $id limit 1");
		if ($result->RecordCount() < 1) {
			$message[] = sprintf('The record could not be found! Looking for id = %s', $id);
		} else {
			$enc_data = new encryption();
			$data = $enc_data->decrypt($_SESSION['admin_encrypt'], $result->fields['enc_value']);
			$fields = explode(':', $data);
			if (strlen($fields[3]) == 2) $fields[3] = '20'.$fields[3]; // make sure year is 4 digits
			$xml .= "<PaymentMethod>\n";
			$xml .= xmlEntry("payment_id",   $result->fields['id']);
			$xml .= xmlEntry("payment_hint", $result->fields['hint']);
			for ($i = 0; $i < count(is_array($fields) ? $fields : []); $i++) $xml .= xmlEntry("field_" . $i, $fields[$i]);
			$xml .= "</PaymentMethod>\n";
		}
		break;

	case 'rm_payment':
		$id = (int)($_POST['pID'] ?? 0);
		$owner = $db->Execute("select ref_1 from ".TABLE_DATA_SECURITY." where id = $id and module = 'contacts'");
		if (!contacts_ajax_may_delete($owner->fields['ref_1'] ?? 0)) contacts_ajax_refuse();
		$db->Execute("delete from ".TABLE_DATA_SECURITY." where id = $id");
		$xml .= xmlEntry('payment_id', $id);
		$message[] = 'The record was successfully deleted!';
		break;

	case 'rm_crm':
		$id = (int)($_POST['nID'] ?? 0);
		$owner = $db->Execute("select contact_id from ".TABLE_CONTACTS_LOG." where log_id = $id");
		if (!contacts_ajax_may_delete($owner->fields['contact_id'] ?? 0)) contacts_ajax_refuse();
		$db->Execute("delete from ".TABLE_CONTACTS_LOG." where log_id = $id");
		$xml .= xmlEntry('crm_id', $id);
		$message[] = 'The record was successfully deleted!';
		break;

	default: die;
}

if (!empty($message)) $xml .= xmlEntry('message', implode("\n",$message));
echo createXmlHeader() . $xml . createXmlFooter();
die;
?>