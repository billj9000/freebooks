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
//  Path: /modules/inventory/ajax/inv_details.php
//
$security_level = validate_ajax_user();
/**************  include page specific files    *********************/
gen_pull_language('phreebooks');
require(DIR_FS_MODULES . 'inventory/defaults.php');
require(DIR_FS_MODULES . 'inventory/functions/inventory.php');
/**************   page specific initialization  *************************/
$stock_note = array();
// One of the following identifers is required.
$sku    = db_prepare_input($_GET['sku']); // specifies the sku, could be a search field as well
$UPC    = db_prepare_input($_GET['upc']); // specifies the upc code
$iID    = db_prepare_input($_GET['iID']); // specifies the item database id
// optional for more detailed operation
$bID    = db_prepare_input($_GET['bID']); // specifies the branch ID
$cID    = db_prepare_input($_GET['cID']); // specifies the contact ID
$jID    = db_prepare_input($_GET['jID']); // specifies the journal ID
$rID    = db_prepare_input($_GET['rID']); // specifies the row to update
$qty    = db_prepare_input($_GET['qty']); // specifes the quantity (for pricing)
$strict = isset($_GET['strict']) ? $_GET['strict'] : false; // specifes strict match of sku value
// some error checking
if (!$sku && !$UPC && !$iID) {
	echo createXmlHeader() . xmlEntry('error', AJAX_INV_NO_INFO) . createXmlFooter();
	die;
}

if(!$UPC && !$iID && (validate_UPCABarcode($sku) || validate_EAN13Barcode($sku) )){
	$UPC = $sku;	
}
// Escaped copies for SQL string interpolation below. The raw values are kept
// for barcode validation (above) and XML output, so we don't escape in place.
$sku_db = db_input($sku);
$upc_db = db_input($UPC);
$iid_db = db_input($iID);
$cid_db = db_input($cID);
// Load the sku information
if ($iID) {
  $search = " where id = '$iid_db'";
} else if ($UPC) {
  $search = " where upc_code = '$upc_db'";
} else if ($strict){ // exact search for sku match
  $search = " where sku = '$sku_db'";
} else { // broad search for a match
  $search_fields = array('sku', 'upc_code', 'description_short', 'description_sales', 'description_purchase');
  $search = " where " . implode(" like '%$sku_db%' or ", $search_fields) . " like '%$sku_db%'";
}

if (!$bID) $bID = 0; // assume only one branch or main branch if not specified
if ($cID) $xml .= xmlEntry("cID", $cID);
if ($jID) $xml .= xmlEntry("jID", $jID);
if ($rID) $xml .= xmlEntry("rID", $rID);

$vendor = in_array($jID, array(3,4,6,7)) ? true : false;
if ($vendor && !$iID && $strict == false && $UPC == false) { // just search for products from that vendor for purchases
 	$v_search_fields = array('a.sku', 'a.upc_code', 'a.description_short', 'a.description_sales', 'p.description_purchase', 'p.vendor_sku' );
  	$first_search = " where " . implode(" like '%$sku_db%' or ", $v_search_fields) . " like '%$sku_db%' and p.vendor_id = '$cid_db' and a.inactive = '0'";
  	$purchase     = $db->Execute("select DISTINCT a.id as id, p.vendor_id as vendor_id, p.vendor_sku as vendor_sku, p.description_purchase as description_purchase, p.purch_package_quantity as purch_package_quantity,
  	p.purch_taxable as purch_taxable, p.item_cost as item_cost, p.price_sheet_v as price_sheet_v from " . TABLE_INVENTORY . " a LEFT JOIN " . TABLE_INVENTORY_PURCHASE . " p on a.sku = p.sku $first_search GROUP BY a.sku");
  	if($purchase->recordCount() == 1){
  		$search = " where id = '{$purchase->fields['id']}'";
  	}elseif($purchase->recordCount() != 0){
  		$xml .= xmlEntry('result', 'Too many hits!');
		$xml .= xmlEntry("qty", 1);
  		echo createXmlHeader() . $xml . createXmlFooter();  
  		die;
  	}
}
for ($step = 1; $step < 4; $step++){
	switch($step){
		case 1:
			if ($vendor == true  || $iID == true || $strict == true || $UPC == true) break;
			$inventory =  $db->Execute("select * from " . TABLE_INVENTORY .  " where sku = '$sku_db' and inactive = '0'");
			break; 
		case 2:
			$inventory =  $db->Execute("select * from " . TABLE_INVENTORY . $search . " and inactive = '0'");
			break;
		case 3:
			$inventory =  $db->Execute("select * from " . TABLE_INVENTORY . $search);
			break;
		default:
			break 2; 
	}
	if(isset($inventory) && $inventory->RecordCount() == 1) break;
}
if ($inventory->RecordCount() != 1) { // need to return something to avoid error in FireFox
	if($UPC) 	$xml .= xmlEntry('error', ORD_JS_SKU_NOT_UNIQUE); // for UPC codes submitted only, send an error
	else 		$xml .= xmlEntry('result', 'Not enough or too many hits, exiting!');
	$xml .= xmlEntry("qty", 1);
  	echo createXmlHeader() . $xml . createXmlFooter();  
  	die;
}
foreach ($inventory->fields as $key => $value) $inventory_array[$key] = $value;
if($vendor) {
	$purchase  = $db->Execute("select vendor_id, vendor_sku, description_purchase, purch_package_quantity, purch_taxable, item_cost, currency_code, price_sheet_v from " . TABLE_INVENTORY_PURCHASE . " where sku = '" .$inventory_array['sku']."' and vendor_id = '$cID'" );
	if($purchase->RecordCount() == 1 ) foreach ($purchase->fields as $key => $value) $inventory_array[$key] = $value;
	else {
		// no vendor-specific purchase row — fall back to cheapest row for cost/description
		$purchase = $db->Execute("select vendor_id, vendor_sku, description_purchase, purch_package_quantity, purch_taxable, item_cost, currency_code, price_sheet_v from " . TABLE_INVENTORY_PURCHASE . " where sku = '" . $inventory_array['sku'] . "' order by item_cost asc limit 1");
		if($purchase->RecordCount() == 1) foreach ($purchase->fields as $key => $value) $inventory_array[$key] = $value;
		// if vendor is known, override fallback currency with the vendor's stored currency
		if ($cID) {
			$vend = $db->Execute("select currency_code from " . TABLE_CONTACTS . " where id = '$cID'");
			if (!$vend->EOF && $vend->fields['currency_code'] != '') $inventory_array['currency_code'] = $vend->fields['currency_code'];
		}
	}
	$purchase  = $db->Execute("select MIN(item_cost) as cheapest from " . TABLE_INVENTORY_PURCHASE . " where sku = '{$inventory_array['sku']}' and item_cost <> '0'" );
	if( $jID == 4 && $inventory_array['price_sheet_v'] == '' && $inventory_array['item_cost'] >= $purchase->fields['cheapest'] &&
	  abs($inventory_array['item_cost'] - $purchase->fields['cheapest']) > 0.00001 ) $stock_note[] = sprintf(INV_CHEAPER_ELSEWHERE, $inventory_array['sku']);
}else{
	$purchase  = $db->Execute("select vendor_id, vendor_sku, description_purchase, purch_package_quantity, purch_taxable, MAX(item_cost) as item_cost, currency_code, price_sheet_v
		from " . TABLE_INVENTORY_PURCHASE . " where sku = '" .$inventory_array['sku']."' GROUP BY sku" );
	if($purchase->RecordCount() == 1 )foreach ($purchase->fields as $key => $value) $inventory_array[$key] = $value;
}
if($inventory_array['purch_package_quantity'] == 0) $inventory_array['purch_package_quantity'] = 1;
if (!$qty){
	if($jID == 6 && $inventory_array['purch_package_quantity'] != 0) {
		$qty = $inventory_array['purch_package_quantity'];
	}elseif($jID == 4 && $inventory_array['reorder_quantity'] != 0) {
		$qty = ceil($inventory_array['reorder_quantity'] / $inventory_array['purch_package_quantity']); 
	}else{
		$qty = 1; // assume that one is required, will set to 1 on the form
	}
}
$xml .= xmlEntry("qty", $qty); 
$iID = $inventory_array['id']; // set the item id (just in case UPC or sku was the only identifying parameter)
$sku = $inventory_array['sku'];
// fix some values for special cases
$cog_types = explode(',', COG_ITEM_TYPES);
if ($inventory_array['inventory_type'] == 'pb') {
  // Product Bundle: virtual stock = how many bundles the current component stock can satisfy
  $vstock = $db->Execute(
    "SELECT MIN(FLOOR(i.quantity_on_hand / a.qty)) AS v
     FROM " . TABLE_INVENTORY_ASSY_LIST . " a
     INNER JOIN " . TABLE_INVENTORY . " i ON a.sku = i.sku
     WHERE a.ref_id = '$iID' AND a.qty > 0"
  );
  $virtual_stock = (!$vstock->EOF && $vstock->fields['v'] !== null) ? max(0, (int)$vstock->fields['v']) : 0;
  $inventory_array['quantity_on_hand']  = $virtual_stock;
  $inventory_array['branch_qty_in_stock'] = strval($virtual_stock);
} else {
  if (!in_array($inventory_array['inventory_type'], $cog_types)) $inventory_array['quantity_on_hand'] = 'NA';
  // load branch stock ( must be before BOM loading )
  $inventory_array['branch_qty_in_stock'] = (strpos(COG_ITEM_TYPES,$inventory_array['inventory_type']) === false) ? 'NA' : strval(load_store_stock($sku, $bID));
}
//$debug .= 'qty in stock = ' . $inventory_array['quantity_on_hand'] . ' and branch qty = ' . $inventory_array['branch_qty_in_stock'];
// Load the assembly information
$assy_cost = 0;
if ($inventory_array['inventory_type'] == 'ma' || $inventory_array['inventory_type'] == 'sa' || $inventory_array['inventory_type'] == 'pb') {
  $result = $db->Execute("select sku, qty from " . TABLE_INVENTORY_ASSY_LIST . " where ref_id = '$iID'");
  $bom    = array();
  while (!$result->EOF) {
	$sql = "select description_short, inventory_type, item_cost, quantity_on_hand from " . TABLE_INVENTORY . " where sku = '" . $result->fields['sku'] . "'";
	$sku_cost = $db->Execute($sql);
	$assy_cost += $result->fields['qty'] * $sku_cost->fields['item_cost'];
	if (in_array($sku_cost->fields['inventory_type'], $cog_types)) {
	  $qty_in_stock = strval(load_store_stock($result->fields['sku'], $bID));
	} else {
	  $qty_in_stock = 'NA';
	}
	$bom[] = array(
	  'qty'               => $result->fields['qty'],
	  'sku'               => $result->fields['sku'],
	  'description_short' => $sku_cost->fields['description_short'],
	  'item_cost'         => $sku_cost->fields['item_cost'],
	  'quantity_on_hand'  => $qty_in_stock,
	);
	$result->MoveNext();
  }
} else {
  $assy_cost = $inventory_array['item_cost'];
}
// load where used
$sku_usage = array();
$result = $db->Execute("select ref_id, qty from " . TABLE_INVENTORY_ASSY_LIST . " where sku = '$sku'");
if ($result->RecordCount() > 0) {
  while (!$result->EOF) {
    $stock = $db->Execute("select sku, description_short from ".TABLE_INVENTORY." where id='" . $result->fields['ref_id'] . "' and inactive = '0'");
    if ($stock->RecordCount() > 0) {
    	$sku_usage[] =  TEXT_QUANTITY . ' ' . $result->fields['qty'] . ' ' . TEXT_SKU . ': ' . $stock->fields['sku'] . ' - ' . $stock->fields['description_short'];
    }
    $result->MoveNext();
  }
}
if (!empty($sku_usage)) {
	array_unshift($sku_usage, JS_INV_TEXT_USAGE);
} else {
	$sku_usage = array(JS_INV_TEXT_USAGE_NONE);
}
// load prices, tax
$prices = inv_calculate_sales_price(abs($qty), $iID, $cID, $vendor ? 'v' : 'c');
$sales_price = strval($prices['price']);
$inventory_array['item_taxable']  = strval($prices['sales_tax']);
$inventory_array['purch_taxable'] = strval($prices['purch_tax']);
//$debug .= 'sales_tax = ' . $prices['sales_tax'] . ' and purch tax = ' . $prices['purch_tax'] . ' and price = ' . $sales_price . chr(10);
// Load default tax to use
if ($cID) {
	
}
// load sku stock status and open orders
switch($jID) {
  case  '9':
  case '10':
  case '12':
  case '19':
	// check for stock available for SO, Customer Quote and Sales
	if ($qty > $inventory_array['branch_qty_in_stock'] && (strpos(COG_ITEM_TYPES, $inventory_array['inventory_type']) !== false || $inventory_array['inventory_type'] == 'pb')) {
	  $stock_note[] = ORD_INV_STOCK_LOW;
	  $stock_note[] = ORD_INV_STOCK_BAL . $inventory_array['branch_qty_in_stock'];
	  // fetch open orders
	  $sku_history = gather_history($inventory_array['sku']);
	  if (is_array($sku_history['open_po'])) {
	    $stock_note[] = ORD_INV_OPEN_POS;
	    foreach ($sku_history['open_po'] as $value) {
		  $store = $value['store_id'] ? gen_get_contact_name($value['store_id']) : COMPANY_NAME; // get name from id
		  $stock_note[] = sprintf(ORD_INV_STOCK_STATUS, $store, $value['purchase_invoice_id'], $value['qty'], gen_locale_date($value['date_1']));
	    }
	  }
	}
	break;
  default:
}
if ($jID == 4 ) {
	$inventory_array['item_cost'] = $inventory_array['item_cost'] * $inventory_array['purch_package_quantity'];
	// build the sales price
	$xml .= xmlEntry("sales_price", $sales_price * $inventory_array['purch_package_quantity']);
}else{
	// build the sales price
	$xml .= xmlEntry("sales_price", $sales_price);
}

//put it all together
// build the core inventory data
foreach ($inventory_array as $key => $value) $xml .= "\t" . xmlEntry($key, $value);
// An ms master's own attribute definition, so a Master Product Bundle picking this
// master into its BOM can offer to pre-fill its own attribute list from it (prefixed
// ms_* - these are inventory_ms_list columns, not TABLE_INVENTORY fields, so there's
// no risk of colliding with $inventory_array above).
if ($inventory_array['inventory_type'] == 'ms') {
  $ms_row = $db->Execute("select attr_0, attr_name_0, attr_1, attr_name_1 from " . TABLE_INVENTORY_MS_LIST . " where sku = '" . db_input($sku) . "'");
  if ($ms_row->RecordCount() > 0) {
    $xml .= "\t" . xmlEntry('ms_attr_0',      $ms_row->fields['attr_0']);
    $xml .= "\t" . xmlEntry('ms_attr_name_0', $ms_row->fields['attr_name_0']);
    $xml .= "\t" . xmlEntry('ms_attr_1',      $ms_row->fields['attr_1']);
    $xml .= "\t" . xmlEntry('ms_attr_name_1', $ms_row->fields['attr_name_1']);
  }
}
// build the assembly information
//print_r($inventory_array);
if (!empty($bom)) foreach ($bom as $value) {
  $xml .= "<bom>\n";
  $xml .= "\t" . xmlEntry("bom_qty",               $value['qty']);
  $xml .= "\t" . xmlEntry("bom_sku",               $value['sku']);
  $xml .= "\t" . xmlEntry("bom_description_short", $value['description_short']);
  $xml .= "\t" . xmlEntry("bom_item_cost",         $value['item_cost']);
  $xml .= "\t" . xmlEntry("bom_quantity_on_hand",  $value['quantity_on_hand']);
  $xml .= "</bom>\n";
}
$xml .= xmlEntry("assy_cost", $assy_cost);
// build where used
foreach ($sku_usage as $value) {
  $xml .= "<sku_usage>\n";
  $xml .= "\t" . xmlEntry("text_line", $value);
  $xml .= "</sku_usage>\n";
}

// build the stock status
if (!empty($stock_note)) {
  foreach ($stock_note as $value) {
    $xml .= "<stock_note>\n";
    $xml .= "\t" . xmlEntry("text_line", $value);
    $xml .= "</stock_note>\n";
  }
}
if ($debug) $xml .= xmlEntry('debug', $debug);
echo createXmlHeader() . $xml . createXmlFooter();
die;
?>