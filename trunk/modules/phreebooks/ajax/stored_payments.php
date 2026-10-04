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
//  Path: /modules/phreebooks/ajax/stored_payments.php
//
/**************   Check user security   *****************************/
$security_level = validate_ajax_user();
/**************  include page specific files    *********************/
/**************   page specific initialization  *************************/
$contact_id = db_prepare_input($_GET['contact_id'] ?? '');
$xml = NULL;
// The id is interpolated into the WHERE clause, so a missing one would be a
// syntax error. A contact nobody named has no stored payments, which is an empty
// answer rather than an error.
if (!is_numeric($contact_id)) {
	echo createXmlHeader() . createXmlFooter();
	die;
}

$enc_data = new encryption();
$enc_key  = $_SESSION['admin_encrypt'] ?? ''; // entered once per session; absent until then
$sql = "select id, hint, enc_value from ".TABLE_DATA_SECURITY." where module='contacts' and ref_1 = " . (int)$contact_id;
$result = $db->Execute($sql);
// Stored details cannot be read without the key, so they are refused before any decryption is tried.
if ($enc_key === '' && $result->RecordCount() > 0) {
  echo createXmlHeader() . xmlEntry('error', BNK_ERROR_NO_ENCRYPT_KEY) . createXmlFooter();
  die;
}
while (!$result->EOF) {
	$data = $enc_data->decrypt($enc_key, $result->fields['enc_value']);
	$fields = explode(':', $data);
	if (strlen($fields[3] ?? '') == 2) $fields[3] = '20'.$fields[3]; // make sure year is 4 digits
	$xml .= "<Payment>\n";
	$xml .= "\t" . xmlEntry("id",   $result->fields['id']);
	$xml .= "\t" . xmlEntry("name", $fields[0]); // will be the name field for credit cards
	$xml .= "\t" . xmlEntry("hint", $result->fields['hint']);
	for ($i = 0; $i < count(is_array($fields) ? $fields : []); $i++) $xml .= "\t" . xmlEntry("field_" . $i, $fields[$i]);
	$xml .= "</Payment>\n";
	$result->MoveNext();
}
echo createXmlHeader() . $xml . createXmlFooter();
die;
?>