<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
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
//  Path: /modules/inventory/classes/type/pb.php
//
require_once(DIR_FS_MODULES . 'inventory/classes/type/ma.php');
class pb extends ma {
	public $inventory_type = 'pb';
	public $title          = INV_TYPES_PB;

	/**
	 * A bundle's parts list is ALWAYS editable: there is no assembly step to lock it,
	 * and no stock of its own to contradict. Overriding the rule itself rather than the
	 * flag is what makes that true everywhere - loading an item AND saving one. Setting
	 * the flag after load, as this class used to, left save() free to re-derive it from
	 * last_journal_date, so any bundle that had ever been sold silently discarded every
	 * BOM edit while reporting that it had saved.
	 */
	function bom_editable($last_journal_date, $quantity_on_hand) { return true; }

	function get_item_by_id($id) {
		$this->bom = null;
		parent::get_item_by_id($id);
		$this->quantity_on_hand = $this->get_virtual_stock();
	}

	function get_item_by_sku($sku) {
		$this->bom = null;
		parent::get_item_by_sku($sku);
		$this->quantity_on_hand = $this->get_virtual_stock();
	}

	function get_bom_list() {
		if (!$this->id) return;
		parent::get_bom_list();
	}

	function get_virtual_stock() {
		global $db;
		if (!$this->id) return 0;
		$result = $db->Execute(
			"SELECT MIN(FLOOR(i.quantity_on_hand / a.qty)) AS virtual_stock
			 FROM "   . TABLE_INVENTORY_ASSY_LIST . " a
			 INNER JOIN " . TABLE_INVENTORY . " i ON a.sku = i.sku
			 WHERE a.ref_id = " . (int)$this->id . " AND a.qty > 0"
		);
		if ($result->EOF || $result->fields['virtual_stock'] === null) return 0;
		return max(0, (int)$result->fields['virtual_stock']);
	}
}
