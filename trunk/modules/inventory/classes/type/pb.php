<?php
require_once(DIR_FS_MODULES . 'inventory/classes/type/ma.php');
class pb extends ma {
	public $inventory_type = 'pb';
	public $title          = INV_TYPES_PB;

	function get_item_by_id($id) {
		$this->bom = null;
		parent::get_item_by_id($id);
		$this->allow_edit_bom   = true; // BOM can always be edited; no assembly step locks it
		$this->quantity_on_hand = $this->get_virtual_stock();
	}

	function get_item_by_sku($sku) {
		$this->bom = null;
		parent::get_item_by_sku($sku);
		$this->allow_edit_bom   = true;
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
