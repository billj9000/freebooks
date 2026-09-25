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
//  Path: /modules/sku_pricer/classes/sku_pricer.php
//
class sku_pricer {
	public $records = array();

  	function __construct() {
  		$this->records = array();
  	}

  	/**
  	 * this function will update the sku's that are in the csv files.
  	 * @param string $lines_array
  	 * @return void|boolean
  	 */
  	function processCSV($filename) { //Master
  		global $db, $messageStack;
  		$rows = $this->csv_to_array($_FILES[$filename]['tmp_name'], $delimiter=',');
  		$messageStack->debug("\nfinished parsing, extracted number of rows = ".count(is_array($rows) ? $rows : []));
		$valid_fields = array(
			'description_short'		=> 'description_short',
			'description_sales'		=> 'description_sales',
			'description_purchase'	=> 'description_purchase',
			'account_sales_income'	=> 'account_sales_income',
			'account_inventory_wage'=> 'account_inventory_wage',
			'account_cost_of_sales'	=> 'account_cost_of_sales',
			'item_taxable'			=> 'item_taxable',
			'price_sheet'			=> 'price_sheet',
			'full_price'			=> 'full_price',
			'full_price_with_tax'	=> 'full_price_with_tax',
			'item_weight'			=> 'item_weight',
			'minimum_stock_level'	=> 'minimum_stock_level',
			'reorder_quantity'		=> 'reorder_quantity',
			'lead_time'				=> 'lead_time',
			'upc_code'				=> 'upc_code',
			'item_cost'				=> 'item_cost',
			'price_sheet_v'			=> 'price_sheet_v',
			'purch_taxable'			=> 'purch_taxable',
			'vendor_id'				=> 'vendor_id',
		);
		// vendor_sku belongs to the purchase record only; there is no such
		// column on the inventory table.
		$purch_fields = array(
			'description_purchase'	=> 'description_purchase',
			'price_sheet_v'			=> 'price_sheet_v',
			'purch_taxable'			=> 'purch_taxable',
			'item_cost'				=> 'item_cost',
			'vendor_sku'			=> 'vendor_sku',
			'vendor_id'				=> 'vendor_id',
		);
  		$count = 0;
  		foreach ($rows as $row) {
			$where = false;
			if (isset($row['sku']) && strlen($row['sku']) > 0) {
				$where = "sku='".db_input($row['sku'])."'";
			} elseif (isset($row['upc_code']) && strlen($row['upc_code']) > 0) {
				$where = "upc_code='".db_input($row['upc_code'])."'";
			}
			if (!$where) {
				$messageStack->add("No search field was found. Either the SKU or UPC Code field must be included in the csv file.", 'error');
				break; // no valid search fields
			}
			// Resolve the vendor before anything is written. inventory.vendor_id is
			// an integer contact id, so a name that matches no vendor is refused
			// outright by a strict server and silently stored as 0 by a lax one,
			// which detaches the item from its vendor. Neither is worth doing
			// halfway, so the whole row is left alone and said out loud.
			if (isset($row['vendor_id']) && trim((string)$row['vendor_id']) !== '') {
				$vendor_id = $this->resolve_vendor($row['vendor_id']);
				if ($vendor_id === false) {
					$messageStack->add(sprintf(SKU_PRICER_ERROR_VENDOR,
						htmlspecialchars($row['vendor_id']),
						htmlspecialchars(isset($row['sku']) && $row['sku'] !== '' ? $row['sku'] : ($row['upc_code'] ?? ''))), 'error');
					continue;
				}
				$row['vendor_id'] = $vendor_id;
			}
			$this->keep_prices_in_step($row, $where);
			$query = "";
			foreach ($valid_fields as $key => $value) if (isset($row[$key])) $query .= " $value='".db_input($row[$key])."',";
			$query .= "last_update='".date('Y-m-d')."'";
			$sql = "UPDATE ".TABLE_INVENTORY." SET $query WHERE $where";
			$messageStack->debug("\nExecuting sql = $sql");
			$result = $db->Execute($sql);
			if ($result->AffectedRows() > 0) $count++;
			// now update the purchase table (need the sku, upc_code will not work)
			if (!isset($row['sku'])) {
				$result = $db->Execute("SELECT sku from ".TABLE_INVENTORY." WHERE upc_code='".db_input($row['upc_code'])."'");
				$row['sku'] = $result->fields['sku'];
				$where = "sku='".db_input($row['sku'])."'";
			}
			if (isset($row['vendor_id']) && $row['vendor_id']) $where .= " AND vendor_id = '".db_input($row['vendor_id'])."'";
			$query = "";
			foreach ($purch_fields as $key => $value) if (isset($row[$key])) $query .= " $value='".db_input($row[$key])."',";
			$query = substr($query, 0, strlen($query)-1); // remove last comma
			if ($query) {
				$sql = "UPDATE ".TABLE_INVENTORY_PURCHASE." SET $query WHERE $where";
				$messageStack->debug("\nExecuting sql = $sql");
				$result = $db->Execute($sql);
			}
		}
		$messageStack->add("Total lines processed: ".count(is_array($rows) ? $rows : []).". Total affected rows = $count.", "success");
  	}

  	/**
  	 * The vendor as a person writing a file has it, as the id the database keeps.
  	 *
  	 * Nothing in the application shows a contact's numeric id: the vendor list
  	 * displays short names, and opening a vendor posts the id in a hidden field
  	 * rather than putting it in the address bar. So a file written by hand names
  	 * the vendor, and a number is accepted only for whoever already has one.
  	 *
  	 * Answers the id, 0 for an empty value, or false when nothing matches.
  	 */
  	function resolve_vendor($vendor) {
  		global $db;
  		static $seen = array();
  		$vendor = trim((string)$vendor);
  		if ($vendor === '')        return 0;
  		if (ctype_digit($vendor))  return (int)$vendor;
  		if (isset($seen[$vendor])) return $seen[$vendor];
  		$result = $db->Execute("SELECT id FROM ".TABLE_CONTACTS
  		    ." WHERE type='v' AND short_name='".db_input($vendor)."' LIMIT 1");
  		return $seen[$vendor] = $result->EOF ? false : (int)$result->fields['id'];
  	}

  	/**
  	 * Set full_price and full_price_with_tax together, however the file gave them.
  	 *
  	 * They are one price stored twice, and the item keeps them in step; a file
  	 * that changes one without the other leaves them disagreeing. So whenever a
  	 * row touches either price or the tax rate, both are written:
  	 *
  	 *   full_price given       - it leads, and the price with tax follows from it
  	 *   only the price with tax - full price is worked back from it, as the
  	 *                             inventory page does with a tax-inclusive price
  	 *   only item_taxable       - the price with tax follows the new rate
  	 *
  	 * Whatever the row does not give is read from the item.
  	 */
  	function keep_prices_in_step(&$row, $where) {
  		global $db;
  		$has = function ($k) use ($row) { return isset($row[$k]) && trim((string)$row[$k]) !== ''; };
  		if (!$has('full_price') && !$has('full_price_with_tax') && !$has('item_taxable')) return;
  		if (!function_exists('inv_full_price_with_tax')) require_once(DIR_FS_MODULES . 'inventory/functions/inventory.php');
  		$stored = $db->Execute("SELECT full_price, item_taxable FROM ".TABLE_INVENTORY." WHERE $where LIMIT 1");
  		if ($stored->EOF) return; // no such item; the update will match nothing either
  		$taxable = $has('item_taxable') ? $row['item_taxable'] : $stored->fields['item_taxable'];
  		if ($has('full_price')) {
  			$row['full_price_with_tax'] = inv_full_price_with_tax($row['full_price'], $taxable);
  		} elseif ($has('full_price_with_tax')) {
  			$row['full_price'] = inv_full_price_from_with_tax($row['full_price_with_tax'], $taxable);
  		} else {
  			$row['full_price_with_tax'] = inv_full_price_with_tax($stored->fields['full_price'], $taxable);
  		}
  	}

  	function csv_to_array($filename='', $delimiter=',') {
  		if(!file_exists($filename) || !is_readable($filename)) return FALSE;
  		$header = NULL;
  		$data = array();
  		if (($handle = fopen($filename, 'r')) !== FALSE) {
  			while (($row = fgetcsv($handle, 1000, $delimiter, escape: "\\")) !== FALSE) {
  				if (!$header) $header = $row;
  				else $data[] = array_combine($header, $row);
  			}
  			fclose($handle);
  		}
  		return $data;
  	}

}

?>