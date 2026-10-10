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
//  Path: /modules/inventory/pages/main/pre_process.php
//
$security_level = validate_user(SECURITY_ID_MAINTAIN_INVENTORY);
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
require_once(DIR_FS_WORKING . 'functions/inventory.php');
require_once(DIR_FS_WORKING . 'classes/inventory_fields.php');
/**************   page specific initialization  *************************/
inv_ensure_identifier_columns();
if (!isset($_REQUEST['action'])) $_REQUEST['action'] = ''; // a plain page open; the custom hooks and templates read it too
gen_pull_language('inventory','filter');
$error       = false;
$processed   = false;
$criteria    = array();
$fields		 = new inventory_fields(false);
$type        = isset($_REQUEST['inventory_type']) ? $_REQUEST['inventory_type'] : null; // default to stock item
history_filter('inventory');
$first_entry = isset($_GET['add']) ? true : false;
// load the filters
$_SESSION['inv_f0'] = isset($_SESSION['inv_f0']) ? $_SESSION['inv_f0'] : '0'; // show inactive checkbox - default hidden
if ($_SERVER['REQUEST_METHOD'] == 'POST') $_SESSION['inv_f0'] = isset($_POST['f0']) ? '1' : '0';
$f0 = $_SESSION['inv_f0'];
$f1 = $_GET['f1'] = isset($_POST['f1']) ? $_POST['f1'] : ($_GET['f1'] ?? ''); // inventory_type dropdown
$id = isset($_POST['rowSeq']) ? db_prepare_input($_POST['rowSeq']) : db_prepare_input($_GET['cID'] ?? '');
// It goes into SQL unquoted: a whole number, or empty for none.
$id = ctype_digit((string)$id) ? (string)$id : '';
// getting the right inventory type.
if (!isset($_REQUEST['inventory_type'])){
	$result = null;
	if(isset($_REQUEST['cID'])) $result = $db->Execute("SELECT inventory_type FROM ".TABLE_INVENTORY." WHERE id=".(int)$_REQUEST['cID']);
	else if (isset($_REQUEST['rowSeq'])) $result = $db->Execute("SELECT inventory_type FROM ".TABLE_INVENTORY." WHERE id=".(int)$_REQUEST['rowSeq']);
	else if (isset($_REQUEST['sku']))	 $result = $db->Execute("SELECT inventory_type FROM ".TABLE_INVENTORY." WHERE sku='".db_input($_REQUEST['sku'])."'");
	if ($result && $result->RecordCount()>0) $type = $result->fields['inventory_type'];
} 
if ($type == 'as') $type = 'ma'; 
// It names a class file and a class, and can come from the request: only a
// two-letter inventory type, so it can never reach outside classes/type/.
if (!preg_match('/^[a-z]{2}$/', (string)$type)) $type = null;
if ( in_array($_REQUEST['action'], array('create', 'save', 'delete', 'copy', 'edit', 'properties', 'rename', 'saveAndColse', 'post_next'))) {
	if (file_exists(DIR_FS_WORKING . 'custom/classes/type/'.$type.'.php')) { 
		require_once(DIR_FS_WORKING . 'custom/classes/type/'.$type.'.php'); 
	} elseif (file_exists(DIR_FS_WORKING . 'classes/type/'.$type.'.php')) {
		require_once(DIR_FS_WORKING . 'classes/type/'.$type.'.php'); // is needed here for the defining of the class and retriving the security_token
	} else die('Bad inventory type');
	$cInfo = new $type();
}
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/main/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'create':
	validate_security($security_level, 2); // security check
	$_REQUEST['action'] = ($cInfo->check_create_new()) ? 'edit' : 'new'; 
	break;

  case 'post_next':
  case 'save':
	validate_security($security_level, 2); // security check
	if (!$error) $error = $cInfo->save() == false;
	if ($error) $_REQUEST['action'] = 'edit';
	if ($_REQUEST['action'] == 'post_next'){
		$result = $db->Execute("SELECT MAX(sku + 0) AS 'new' FROM " . TABLE_INVENTORY );
		$sku = $result->fields['new'] + 1 ;
		if($cInfo->copy($cInfo->id, $sku)) $_REQUEST['action'] = 'edit';
	}
	break;

  case 'delete':
	validate_security($security_level, 4); // security check
	// Delete, copy and rename only as a POST, which index.php has checked for
	// its csrf_token: a GET could be a link someone was sent.
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') break;
	$id = (int)$_POST['cID'];
	$cInfo->check_remove($id);
	break;

  case 'copy': 	// Pictures are not copied over...
	validate_security($security_level, 2); // security check
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') break;
	$id  = (int)$_POST['cID'];
	$sku = db_prepare_input($_POST['sku']);
	if($cInfo->copy($id, $sku)) $_REQUEST['action'] = 'edit';
	break;
	
  case 'edit':
  case 'properties':
	if(!$error && $id != ''){
		$cInfo->get_item_by_id($id);
	}else if(!$error && isset($_REQUEST['sku'])){
		$cInfo->get_item_by_sku($_REQUEST['sku']);
	}
	// Show the price with tax that follows from full price when the stored one
	// does not - zero, or left behind when full price was changed elsewhere. A
	// price typed tax-inclusive is within rounding of that and is left as typed.
	if (isset($cInfo->full_price_with_tax)
	    && inv_price_with_tax_is_stale($cInfo->full_price ?? 0, $cInfo->full_price_with_tax, $cInfo->item_taxable ?? 0)) {
		$cInfo->full_price_with_tax = inv_full_price_with_tax($cInfo->full_price, $cInfo->item_taxable ?? 0);
	}
	break;

  case 'rename':
	validate_security($security_level, 4); // security check
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') break;
	$id  = (int)$_POST['cID'];
	$sku = db_prepare_input($_POST['sku']);
	$cInfo->rename($id, $sku);
	break;
  case 'download':
   	    // Both name the file to send: whole numbers only.
   	    $cID   = (int)$_POST['id'];
  	    $imgID = (int)$_POST['rowSeq'];
	    $filename = 'inventory_'.$cID.'_'.$imgID.'.zip';
	    if (file_exists(INVENTORY_DIR_ATTACHMENTS . $filename)) {
	       require_once(DIR_FS_MODULES . 'phreedom/classes/backup.php');
	       $backup = new backup();
	       $backup->download(INVENTORY_DIR_ATTACHMENTS, $filename, true);
	    }
        die;
  case 'dn_attach': // download from list, assume the first document only
        $cID   = (int)$_POST['rowSeq'];
  	    $result = $db->Execute("select attachments from ".TABLE_INVENTORY." where id = $cID");
  	    $attachments = unserialize((string)$result->fields['attachments'], ['allowed_classes' => false]) ?: array();
  	    foreach ($attachments as $key => $value) {
		   $filename = 'inventory_'.$cID.'_'.$key.'.zip';
		   if (file_exists(INVENTORY_DIR_ATTACHMENTS . $filename)) {
		      require_once(DIR_FS_MODULES . 'phreedom/classes/backup.php');
		      $backup = new backup();
		      $backup->download(INVENTORY_DIR_ATTACHMENTS, $filename, true);
		      die;
		   }
  	    }
  case 'reset':
  		$_SESSION['filter_field']	 = null; 
  		$_REQUEST['filter_field']	 = null;
  		$_SESSION['filter_criteria'] = null; 
  		$_REQUEST['filter_criteria'] = null;
  		$_SESSION['filter_value'] 	 = null; 
  		$_REQUEST['filter_value'] 	 = null;
		break;
  case 'go_first':    $_REQUEST['list'] = 1;       break;
  case 'go_previous': $_REQUEST['list'] = max($_REQUEST['list']-1, 1); break;
  case 'go_next':     $_REQUEST['list']++;         break;
  case 'go_last':     $_REQUEST['list'] = 99999;   break;
  case 'search':
  case 'search_reset':
  case 'go_page':
  case 'new':
  default:
}

/*****************   prepare to display templates  *************************/
// build the type filter list
$type_select_list = array( // add some extra options
  array('id' => '0',   'text' => TEXT_ALL),
  array('id' => 'cog', 'text' => TEXT_INV_MANAGED),
);
if ( in_array($_REQUEST['action'], array('create', 'edit','properties'))) {
	foreach ($inventory_types_plus as $key => $value) $type_select_list[] = array('id' => $key,  'text' => $value);
	// generate the vendors and fill js arrays for dynamic pull downs
	$vendors = gen_get_contact_array_by_type('v');
	$js_vendor_array = 'var js_vendor_array = new Array();' . chr(10);
	for ($i = 0; $i < count($vendors); $i++) {
	  $js_vendor_array .= 'js_vendor_array[' . $i . '] = new dropDownData("' . $vendors[$i]['id'] . '", "' . $vendors[$i]['text'] . '");' . chr(10);
	}
	// generate the pricesheets and fill js arrays for dynamic pull downs
	$pur_pricesheets = get_price_sheet_data('v');
	$js_pricesheet_array = 'var js_pricesheet_array = new Array();' . chr(10);
	for ($i = 0; $i < count($pur_pricesheets); $i++) {
	  $js_pricesheet_array .= 'js_pricesheet_array[' . $i . '] = new dropDownData("' . $pur_pricesheets[$i]['id'] . '", "' . $pur_pricesheets[$i]['text'] . '");' . chr(10);
	}
	
	// load the tax rates
	$tax_rates        = ord_calculate_tax_drop_down('c');
	$purch_tax_rates  = inv_calculate_tax_drop_down('v',false);
	// The rates for javascript, keyed by tax_rate_id: the page looks a rate up by
	// the drop-down's value, which is that id, not by its position in the list.
	// tax_rate_id runs through customer and vendor rates alike, so the two only
	// line up by chance.
	$js_tax_rates = 'var tax_rates = {};' . chr(10);
	foreach ($tax_rates as $rate) {
	  $js_tax_rates .= 'tax_rates[' . (int)$rate['id'] . '] = new tax(' . json_encode((string)$rate['id']) . ', '
	                 . json_encode((string)$rate['text']) . ', ' . json_encode((string)$rate['rate']) . ');' . chr(10);
	}
	
	// load gl accounts
	$gl_array_list    = gen_coa_pull_down();
}
$include_header    = true;
$include_footer    = true;

switch ($_REQUEST['action']) {
  case 'new':
    define('PAGE_TITLE', BOX_INV_NEW);
    $sku = isset($_REQUEST['sku']) ? $_REQUEST['sku'] : '';
    $include_template = 'template_id.php';
	break;
  case 'create':
  case 'edit':
    define('PAGE_TITLE', BOX_INV_MAINTAIN);
    $include_template = 'template_detail.php';
    break;
  case 'properties':
    define('PAGE_TITLE', BOX_INV_MAINTAIN);
	$include_header   = false;
	$include_footer   = false;
    $include_template = 'template_detail.php';
    break;
  default:
  	//building filter criteria
  	$_SESSION['filter_field'] 	 = isset( $_REQUEST['filter_field']) 	?  $_REQUEST['filter_field'] : ($_SESSION['filter_field'] ?? '');
  	$_SESSION['filter_criteria'] = isset( $_REQUEST['filter_criteria']) ?  $_REQUEST['filter_criteria'] : ($_SESSION['filter_criteria'] ?? '');
  	$_SESSION['filter_value'] 	 = isset( $_REQUEST['filter_value']) 	?  $_REQUEST['filter_value'] : ($_SESSION['filter_value'] ?? '');
  	$filter_criteria = Array(" = "," != "," LIKE "," NOT LIKE "," > "," < ");
	// The field is a column name in the SQL: only one this page offers in its
	// filter list (built the same way below). The criterion is a position in
	// $filter_criteria or the 'contains' text, and the value is escaped. The
	// session is checked too, as it may hold a filter saved by an earlier release.
	$allowed_filter_fields = array();
	$result = $db->Execute("SELECT field_name FROM " . TABLE_EXTRA_FIELDS ." WHERE module_id = 'inventory' AND use_in_inventory_filter = '1'");
	while (!$result->EOF) {
		$append = in_array($result->fields['field_name'], array('vendor_id','description_purchase','item_cost','purch_package_quantity','purch_taxable','price_sheet_v')) ? 'p.' : 'a.';
		$allowed_filter_fields[] = $append . $result->fields['field_name'];
		$result->MoveNext();
	}
	$clean_field = $clean_criteria = $clean_value = array();
	if (is_array($_SESSION['filter_field'])) foreach (array_values($_SESSION['filter_field']) as $x => $field) {
		$crit  = is_array($_SESSION['filter_criteria']) ? (array_values($_SESSION['filter_criteria'])[$x] ?? '') : '';
		$value = is_array($_SESSION['filter_value'])    ? (array_values($_SESSION['filter_value'])[$x]    ?? '') : '';
		if (!is_string($field) || !in_array($field, $allowed_filter_fields, true) || is_array($value)) continue;
		if ($crit !== FILTER_CONTAINS) {
			if (!ctype_digit((string)$crit) || !isset($filter_criteria[(int)$crit])) continue;
			$crit = (string)(int)$crit;
		}
		$clean_field[] = $field;
		$clean_criteria[] = $crit;
		$clean_value[] = (string)$value;
	}
	$_SESSION['filter_field']    = $clean_field    ?: null;
	$_SESSION['filter_criteria'] = $clean_criteria ?: null;
	$_SESSION['filter_value']    = $clean_value    ?: null;
	$x = 0;//@toto add date formater for date fields.
	while (isset($_SESSION['filter_field'][$x])) {
		$f_field = $_SESSION['filter_field'][$x];
		$f_value = db_input($_SESSION['filter_value'][$x]);
		$f_crit  = $_SESSION['filter_criteria'][$x] === FILTER_CONTAINS ? FILTER_CONTAINS : $filter_criteria[(int)$_SESSION['filter_criteria'][$x]];
		if(      $f_crit == " LIKE " || $f_crit == FILTER_CONTAINS){
			if ( $f_value <> '' ) $criteria[] = $f_field . ' Like "%'    . $f_value . '%" ';
			
		}elseif( $f_crit == " NOT LIKE "){
			if ( $f_value <> '' ) $criteria[] = $f_field . ' Not Like "%' . $f_value . '%" ';
			
		}elseif( $f_crit == " = "  && $f_value == ''){
			if ( $f_field == 'a.sku' && $f_value == '' ) { $x++; continue; }
			$criteria[] = '(' . $f_field . $f_crit . ' "' . $f_value . '" or ' . $f_field . ' IS NULL ) ';
			
		}elseif( $f_crit == " != " && $f_value == ''){
			$criteria[] = '(' . $f_field . $f_crit . ' "' . $f_value . '" or ' . $f_field . ' IS NOT NULL ) ';
			
		}else{	
			$criteria[] = $f_field . $f_crit . ' "' . $f_value . '" ';
		}		
		$x++;
	}	
  	
    // build the list header
	$heading_array = array(
	  'a.sku'                     => TEXT_SKU,
	  'a.inactive'                => TEXT_INACTIVE,
	  'a.description_short'       => TEXT_DESCRIPTION,
	  'a.quantity_on_hand'        => INV_HEADING_QTY_ON_HAND,
	  'a.quantity_on_sales_order' => INV_HEADING_QTY_ON_SO,
	  'a.quantity_on_allocation'  => INV_HEADING_QTY_ON_ALLOC,
	  'a.quantity_on_order'       => INV_HEADING_QTY_ON_ORDER,
	);
	$result      = html_heading_bar($heading_array);
	$list_header = $result['html_code'];
	$disp_order  = $result['disp_order'];
//	if ($disp_order == 'a.sku ASC') $disp_order ='LPAD(a.sku,'.MAX_INVENTORY_SKU_LENGTH.',0) ASC';
//	if ($disp_order == 'a.sku DESC')$disp_order ='LPAD(a.sku,'.MAX_INVENTORY_SKU_LENGTH.',0) DESC';
	// build the list for the page selected
    if (isset($_REQUEST['search_text']) && $_REQUEST['search_text'] <> '') {
      $search_fields = array('a.sku', 'a.description_short', 'a.description_sales', 'p.description_purchase', 'p.vendor_sku');
	  // hook for inserting new search fields to the query criteria.
	  if (isset($extra_search_fields) && is_array($extra_search_fields)) $search_fields = array_merge($search_fields, $extra_search_fields);
  	  $criteria[] = db_search_criteria($search_fields, $_REQUEST['search_text']);
	}
	if (!$f0) $criteria[] = "(a.inactive = '0' or a.inactive = '')"; // hide inactive unless requested
	// build search filter string
	$search = (!empty($criteria)) ? (' where ' . implode(' and ', $criteria)) : '';
	$field_list = array('a.id as id', 'a.sku as sku', 'inactive', 'inventory_type', 'description_short', 'full_price', 
			'quantity_on_hand', 'quantity_on_order', 'quantity_on_sales_order', 'quantity_on_allocation', 'last_journal_date');
	// hook to add new fields to the query return results
	if (isset($extra_query_list_fields) && is_array($extra_query_list_fields)) $field_list = array_merge($field_list, $extra_query_list_fields);
    $query_raw    = "SELECT DISTINCT " . implode(', ', $field_list)  . " from " . TABLE_INVENTORY ." a LEFT JOIN " . TABLE_INVENTORY_PURCHASE . " p on a.sku = p.sku ". $search . " order by $disp_order ";
    $query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
    $query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
if ($query_split->current_page_number <> $_REQUEST['list']) { // if here, go last was selected, now we know # pages, requery to get results
    	$_REQUEST['list'] = $query_split->current_page_number;
    	$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
    	$query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
    }
	history_save('inventory');
    //building array's for filter dropdown selection
	$i=0;
	$result = $db->Execute("SELECT * FROM " . TABLE_EXTRA_FIELDS ." WHERE module_id = 'inventory' AND use_in_inventory_filter = '1' ORDER BY description ASC");
	$FirstValue 		= 'var FirstValue = new Array();' 		. chr(10);
	$FirstId 			= 'var FirstId = new Array();' 			. chr(10);
	$SecondField 		= 'var SecondField = new Array();' 		. chr(10);
	$SecondFieldValue	= 'var SecondFieldValue = new Array();'	. chr(10);
	$SecondFieldId		= 'var SecondFieldId = new Array();' 	. chr(10);
	while (!$result->EOF) {
		if(in_array($result->fields['field_name'], array('vendor_id','description_purchase','item_cost','purch_package_quantity','purch_taxable','price_sheet_v')) ){
			$append 	= 'p.';
		}else{
			$append 	= 'a.';
		}
		$FirstValue .= 'FirstValue[' . $i . '] = "' . $result->fields['description'] . '";' . chr(10);
		$FirstId 	.= 'FirstId[' 	 . $i . '] = "' . $append . $result->fields['field_name'] 	. '";' . chr(10);
		Switch($result->fields['field_name']){
			case 'vendor_id':
				$contacts = gen_get_contact_array_by_type('v');
				$tempValue  ='Array("'  ;
				$tempId 	='Array("' ;
				while ($contact = array_shift($contacts)) {
						$tempValue .= $contact['id'].'","';
						$tempId    .= str_replace( array("/","'",chr(34),) , ' ', $contact['text']).'","';
				}
				$tempValue  .='")' ;
				$tempId 	.='")' ;
				$SecondField		.= 'SecondField["'		. $append . $result->fields['field_name'] 	. '"] = "drop_down";' . chr(10);
				$SecondFieldValue	.= 'SecondFieldValue["'	. $append . $result->fields['field_name'] 	. '"] = '. $tempValue . ';' . chr(10);
				$SecondFieldId		.= 'SecondFieldId["'  	. $append . $result->fields['field_name']	. '"] = '. $tempId . ';' . chr(10);
				break;
			case'inventory_type':

				$tempValue 	='Array("'  ;
				$tempId 	='Array("' ;
				foreach ($inventory_types_plus as $key => $value){
					$tempValue .= $key.'","';
					$tempId    .= $value.'","';
				}
				$tempValue 	.='")' ;
				$tempId 	.='")' ;
				$SecondField		.= 'SecondField["' 		. $append . $result->fields['field_name'] . '"] = "drop_down";' . chr(10);
				$SecondFieldValue	.= 'SecondFieldValue["'	. $append . $result->fields['field_name'] . '"] = '. $tempValue . ';' . chr(10);
				$SecondFieldId		.= 'SecondFieldId["' 	. $append . $result->fields['field_name'] . '"] = '. $tempId . ';' . chr(10);
				break;
			case'cost_method':
				$tempValue 	='Array("'  ;
				$tempId 	='Array("' ;
				foreach ($cost_methods as $key => $value){
					$tempValue .= $key.'","';
					$tempId    .= $value.'","';
				}
				$tempValue .='")' ;
				$tempId .='")' ;
				$SecondField		.= 'SecondField["' 		. $append . $result->fields['field_name'] . '"] = "drop_down";' . chr(10);
				$SecondFieldValue	.= 'SecondFieldValue["'	. $append . $result->fields['field_name'] . '"] = '. $tempValue . ';' . chr(10);
				$SecondFieldId		.= 'SecondFieldId["' 	. $append . $result->fields['field_name'] . '"] = '. $tempId . ';' . chr(10);
				break;	
			default:
				$SecondField.= 'SecondField["' . $append . $result->fields['field_name'] . '"] ="'. $result->fields['entry_type'] . '";' . chr(10);
				if(in_array($result->fields['entry_type'], array('drop_down','radio','multi_check_box','data_list'))){
					$tempValue 	='Array("';
					$tempId 	='Array("' ;
					//explode params and splits value form id
					$params  = unserialize($result->fields['params'], ['allowed_classes' => false]);
					$choices = explode(',',$params['default']);
					while ($choice = array_shift($choices)) {
							$values 	 = explode(':',$choice);
							$tempValue	.= $values[0].'","';
							$tempId		.= $values[1].'","';
					}
					$tempValue 	.='")' ;
					$tempId 	.='")' ;
					$SecondFieldValue	.= 'SecondFieldValue["' . $append . $result->fields['field_name'] . '"] ='. $tempValue . ';' . chr(10);
					$SecondFieldId		.= 'SecondFieldId["' 	. $append . $result->fields['field_name'] . '"] ='. $tempId . ';' . chr(10);
				}
		}	
		$i++;
	   	$result->MoveNext();
	}
    //end building array's for filter dropdown selection
	define('PAGE_TITLE', BOX_INV_MAINTAIN);
    $include_template = 'template_main.php';
	break;
}
?>