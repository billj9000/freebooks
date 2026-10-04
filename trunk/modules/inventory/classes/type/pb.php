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
	 * flag is what makes that true everywhere - loading an item AND saving one, where
	 * save() would otherwise re-derive it from last_journal_date and discard the BOM
	 * edit of any bundle that has ever been sold.
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

	/**
	 * A bundle that ships as its separate parts weighs what is in it, so item_weight is
	 * derived rather than typed. Without this it is an ordinary field inherited from ma,
	 * and a blank one becomes 0 on save (inventory::save()) - a 0 kg parcel that Royal
	 * Mail and Parcelforce, which quote on the product's own weight, price silently
	 * wrong, while DHL weighs the packed pieces and quotes correctly.
	 *
	 * Ticking "ships complete" hands the field back: the real parcel weight, packaging
	 * included, is then a fact only the user knows, and it deliberately overrides the
	 * sum of the contents.
	 *
	 * The figure MUST come from $_POST, not from the database. ma::save() writes the
	 * inventory row before it rewrites inventory_assy_list, so reading the BOM back here
	 * would return the PREVIOUS parts list and leave the weight one edit behind.
	 */
	function save() {
		global $messageStack, $currencies;
		$bom_weight = $this->derive_bom_weight();
		if (empty($_POST['ship_complete'])) {
			$_POST['item_weight'] = (string)round($bom_weight, 4);
		} else {
			// A declared parcel weight is the one figure here with nothing to check it
			// against - the contents no longer constrain it, which is the whole point of
			// ticking the box. So say so when it is below the sum of what is in the
			// parcel, because that is not a judgement call about packaging, it is
			// physically impossible: a box cannot weigh less than the things inside it.
			//
			// A caution, never an error. The user may be mid-edit, or may know something
			// the Bill of Materials does not, and refusing the save would be worse than
			// storing a figure they can see is flagged.
			//
			// Skipped for an empty Bill of Materials: nothing to compare against, and 0
			// would flag every such bundle for no reason. The epsilon only keeps rounding
			// noise from tripping it, not a real shortfall.
			$declared = (float)$currencies->clean_value(db_prepare_input($_POST['item_weight'] ?? 0));
			if ($bom_weight > 0 && $declared < ($bom_weight - 0.001)) {
				$messageStack->add(sprintf(INV_SHIP_COMPLETE_WEIGHT_LOW,
					$currencies->precise($declared), $currencies->precise($bom_weight)), 'caution');
			}
		}
		return parent::save();
	}

	/**
	 * Sum of each posted component's weight x quantity. Components whose SKU is unknown
	 * contribute nothing - ma::save() is what reports a bad SKU, and doing it here too
	 * would put the same error on screen twice.
	 */
	function derive_bom_weight() {
		global $db, $currencies;
		$total = 0.0;
		$skus  = $_POST['assy_sku'] ?? array();
		$qtys  = $_POST['assy_qty'] ?? array();
		for ($x = 0; $x < count($skus); $x++) {
			$sku = trim(db_prepare_input($skus[$x]));
			if ($sku == '') continue;
			$qty = (float)$currencies->clean_value(db_prepare_input($qtys[$x] ?? 0));
			if ($qty == 0) continue;
			$result = $db->Execute("select item_weight from " . TABLE_INVENTORY
				. " where sku = '" . db_input($sku) . "' limit 1");
			if ($result->EOF) continue;
			$total += $qty * (float)$result->fields['item_weight'];
		}
		return $total;
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
