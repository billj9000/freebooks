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
//  Path: /modules/phreebooks/ajax/low_stock.php
//
/**************   Check user security   *****************************/
$xml   = NULL;
$security_level = validate_ajax_user();
/**************  include page specific files    *********************/
/**************   page specific initialization  *************************/
$cID = db_prepare_input($_GET['cID'] ?? ''); //vendor
$sID = db_prepare_input($_GET['sID'] ?? ''); //store
$rID = db_prepare_input($_GET['rID'] ?? ''); //row
// Both ids go straight into the WHERE clause. Without a vendor that read
// " p.vendor_id = " and the statement died of a syntax error, which came back to
// the browser as an ajax reply quoting raw SQL. There is no low stock to report
// for a vendor nobody named.
if (!is_numeric($cID)) {
  echo createXmlHeader() . xmlEntry('error', PB_ERROR_NO_VENDOR_GIVEN) . createXmlFooter();
  die;
}
$where = '';
if (is_numeric($sID) && ENABLE_MULTI_BRANCH) $where = " store_id = " . (int)$sID . " and ";
$where .= " p.vendor_id = " . (int)$cID;	
if (ENABLE_MULTI_BRANCH) {
  $quantity_where = "remaining";
  // Grouped by sku, every other column is bare - which ONLY_FULL_GROUP_BY, the
  // default in MySQL 8, refuses unless the server can prove one value per group.
  // It cannot: inventory.sku carries no unique key and as many as three rows
  // share one, so this query has always been choosing among them arbitrarily.
  //
  // Each bare column is therefore wrapped in min(), which keeps exactly the row
  // count the page has today and makes that choice deterministic instead of
  // whatever the server happened to reach first. The HAVING needs the same
  // treatment, for the same reason. Aliases are unchanged: the template reads
  // these by name. min() rather than any_value() so as not to require MariaDB
  // 10.5 of anyone.
  $sql = "select min(a.id) as id, a.sku, min(full_price) as full_price, min(item_weight) as item_weight,
  	min(lead_time) as lead_time, min(reorder_quantity) as reorder_quantity, sum(remaining) as 'quantity',
  	min(inactive) as inactive, min(account_inventory_wage) as account_inventory_wage,
  	min(p.item_cost) as item_cost, min(p.purch_taxable) as purch_taxable,
  	min(p.description_purchase) as description_purchase, min(p.vendor_sku) as vendor_sku,
  	min(description_short) as description_short,
  	min(minimum_stock_level) as minimum_stock_level, min(quantity_on_sales_order) as quantity_on_sales_order,
  	min(quantity_on_order) as quantity_on_order, min(quantity_on_allocation) as quantity_on_allocation,
  	((min(minimum_stock_level) + min(quantity_on_sales_order) - min(quantity_on_order) - min(quantity_on_allocation) - min(quantity_on_hand)) / min(purch_package_quantity)) as qty_required,
  	min(purch_package_quantity) as purch_package_quantity
	from ".TABLE_INVENTORY. " a join " . TABLE_INVENTORY_HISTORY . "  c join " . TABLE_INVENTORY_PURCHASE . " p ON a.sku = p.sku and a.sku = c.sku
	where minimum_stock_level <> 0 and inactive = '0' and remaining > 0 and ".$where."
	group by a.sku having sum(remaining) < (min(minimum_stock_level) + min(quantity_on_sales_order) - min(quantity_on_order) - min(quantity_on_allocation)) order by a.sku";
} else {
  $quantity_where = "a.quantity_on_hand";
  $sql = "select a.id, a.sku, full_price, item_weight, lead_time, reorder_quantity,  quantity_on_hand as 'quantity' , inactive, account_inventory_wage, p.item_cost, p.purch_taxable, p.description_purchase, p.vendor_sku, description_short,
  	((minimum_stock_level + quantity_on_sales_order - quantity_on_order - quantity_on_allocation - quantity_on_hand )/purch_package_quantity) as qty_required, purch_package_quantity    
	from ".TABLE_INVENTORY."  a join " . TABLE_INVENTORY_PURCHASE . " p on a.sku = p.sku 
	where minimum_stock_level <> 0 and inactive = '0' and quantity_on_hand < (minimum_stock_level + quantity_on_sales_order - quantity_on_order - quantity_on_allocation) and ".$where." 
    order by a.sku";
}

$result = $db->Execute($sql);
while (!$result->EOF) {
	if($result->fields['purch_package_quantity'] == 0 ) $result->fields['purch_package_quantity'] = 1;
	if( $result->fields['reorder_quantity'] >= $result->fields['qty_required']){
		$qty = ceil(($result->fields['reorder_quantity'] / $result->fields['purch_package_quantity']));
	}else{
		$qty = ceil(($result->fields['qty_required'] / $result->fields['purch_package_quantity']));
	}
	if ($qty < 1) $qty = 1; 
	$xml .= "<LowStock>\n";
	$xml .= "\t" . xmlEntry("id",   				  $result->fields['id']);
	$xml .= "\t" . xmlEntry("sku",   				  $result->fields['sku']);
	$xml .= "\t" . xmlEntry("full_price",			  $result->fields['full_price'] * $result->fields['purch_package_quantity']);
	$xml .= "\t" . xmlEntry("item_weight",			  $result->fields['item_weight']);
	$xml .= "\t" . xmlEntry("quantity",				  $result->fields['quantity']);
	$xml .= "\t" . xmlEntry("lead_time",			  $result->fields['lead_time']);
	$xml .= "\t" . xmlEntry("inactive",				  $result->fields['inactive']);
	$xml .= "\t" . xmlEntry("reorder_quantity", 	  $qty);
	$xml .= "\t" . xmlEntry("account_inventory_wage", $result->fields['account_inventory_wage']);
	$xml .= "\t" . xmlEntry("item_cost",			  $result->fields['item_cost'] * $result->fields['purch_package_quantity']);
	$xml .= "\t" . xmlEntry("purch_taxable",		  $result->fields['purch_taxable']);
	$xml .= "\t" . xmlEntry("description_purchase",	  $result->fields['description_purchase']);
	$xml .= "\t" . xmlEntry("vendor_sku",			  $result->fields['vendor_sku']);
	$xml .= "\t" . xmlEntry("description_short",	  $result->fields['description_short']);
	$xml .= "\t" . xmlEntry("purch_package_quantity", $result->fields['purch_package_quantity']);
	$xml .= "\t" . xmlEntry("rID", 					  $rID++);
	$xml .= "</LowStock>\n";
	$result->MoveNext();
}
$xml .= xmlEntry('debug', $debug);
echo createXmlHeader() . $xml . createXmlFooter();
die;
	