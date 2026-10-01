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
//  Path: /modules/inventory/classes/type/mp.php
//
require_once(DIR_FS_MODULES . 'inventory/classes/inventory.php');
class mp extends inventory { //Master Product Bundle (combination of Master Stock Item and a bundle recipe) parent of pc
	public $inventory_type			= 'mp';
	public $title       			= INV_TYPES_MP;
	public $account_sales_income	= INV_ASSY_DEFAULT_SALES;
	public $account_inventory_wage	= INV_ASSY_DEFAULT_INVENTORY;
	public $account_cost_of_sales	= INV_ASSY_DEFAULT_COS;
	public $cost_method				= INV_ASSY_DEFAULT_COSTING;
	public $attr_array0 			= array();
	public $attr_array1 			= array();
	public $ms_attr_0				= '';
	public $ms_attr_1				= '';
	public $attr_name_0				= '';
	public $attr_name_1				= '';
	public $edit_ms_list			= true;
	public $bom		 				= array();
	public $allow_edit_bom			= true;
	public $child_array 			= array();

	function __construct(){
		parent::__construct();
		$this->tab_list['master'] = array('file'=>'template_tab_ms',	'tag'=>'master',    'order'=>30, 'text'=>INV_MS_ATTRIBUTES);
		$this->tab_list['bom'] 	  = array('file'=>'template_tab_bom',	'tag'=>'bom',    	'order'=>40, 'text'=>INV_BOM);
	}

	function get_item_by_id($id){
		$this->child_array  = null;
		$this->bom 			= null;
		parent::get_item_by_id($id);
		$this->get_ms_list();
		$this->get_bom_list();
	}

	function get_item_by_sku($sku){
		$this->child_array  = null;
		$this->bom 			= null;
		parent::get_item_by_sku($sku);
		$this->get_ms_list();
		$this->get_bom_list();
	}

	//this is to copy a product - copies the template BOM and attribute definitions, children are (re)generated on the next Save
	function copy($id, $newSku) {
		global $db;
		if(parent::copy($id, $newSku)){
			$result = $db->Execute("select * from " . TABLE_INVENTORY_ASSY_LIST . " where ref_id = " . (int)$id);
			while(!$result->EOF) {
				$bom_list = array(
				  	'ref_id'      => $this->id,
				  	'sku'         => $result->fields['sku'],
					'description' => $result->fields['description'],
					'qty'         => $result->fields['qty'],
				);
				db_perform(TABLE_INVENTORY_ASSY_LIST, $bom_list, 'insert');
				$result->MoveNext();
			}
			$result = $db->Execute("select * from " . TABLE_INVENTORY_MS_LIST . " where sku = '" . db_input($this->old_sku) . "'");
			$data_array = array(
				'sku'			=> $this->sku,
				'attr_0'		=> $result->fields['attr_0'],
				'attr_name_0'	=> $result->fields['attr_name_0'],
				'attr_1'		=> $result->fields['attr_1'],
				'attr_name_1'	=> $result->fields['attr_name_1']);
			db_perform(TABLE_INVENTORY_MS_LIST, $data_array, 'insert');
			$this->get_ms_list();
			$this->get_bom_list();
			return true;
		}else{
			return false;
		}
	}

	function get_bom_list(){
		global $db;
		$this->assy_cost = 0;
		$result = $db->Execute("select i.id as inventory_id, l.id, l.sku, l.description, l.qty as qty from " . TABLE_INVENTORY_ASSY_LIST . " l join " . TABLE_INVENTORY . " i on l.sku = i.sku where l.ref_id = " . $this->id . " order by l.id");
		$x =0;
		while (!$result->EOF) {
	  		$this->bom[$x] = $result->fields;
	  		$prices = inv_calculate_sales_price(abs($result->fields['qty']), $result->fields['inventory_id'], 0, 'v');
			$this->bom[$x]['item_cost'] = strval($prices['price']);
			$this->assy_cost += $result->fields['qty'] * strval($prices['price']);
	  		$prices = inv_calculate_sales_price(abs($result->fields['qty']), $result->fields['inventory_id'], 0, 'c');
			$this->bom[$x]['full_price'] = strval($prices['price']);
	  		$x++;
	  		$result->MoveNext();
		}
	}

	function get_ms_list(){
		global $db;
		$result = $db->Execute("select * from " . TABLE_INVENTORY_MS_LIST . " where sku = '" . db_input($this->sku) . "'");
	  	$this->ms_attr_0   = ($result->RecordCount() > 0) ? $result->fields['attr_0'] 		: '';
	  	$this->attr_name_0 = ($result->RecordCount() > 0) ? $result->fields['attr_name_0'] 	: '';
	  	$this->ms_attr_1   = ($result->RecordCount() > 0) ? $result->fields['attr_1'] 		: '';
	  	$this->attr_name_1 = ($result->RecordCount() > 0) ? $result->fields['attr_name_1'] 	: '';
		if ($this->ms_attr_0) {
			$temp = explode(',', $this->ms_attr_0);
			foreach ($temp as $key => $value) {
				if($value){
			  		$code = substr($value, 0, strpos($value, ':'));
			  		$desc = substr($value, strpos($value, ':') + 1);
			  		$this->attr_array0[$value] = array('id' => $value, 'text' => $code . ' : ' . $desc);
			  		$temp_ms0[$code] = $desc;
				}
			}
			ksort($this->attr_array0);
		}
		if ($this->ms_attr_1) {
			$temp = explode(',', $this->ms_attr_1);
			foreach ($temp as $key => $value) {
				if($value){
			  		$code = substr($value, 0, strpos($value, ':'));
			  		$desc = substr($value, strpos($value, ':') + 1);
			  		$this->attr_array1[$value] = array('id' => $value, 'text' => $code . ' : ' . $desc);
					$temp_ms1[$code] = $desc;
				}
			}
			ksort($this->attr_array1);
		}
		// a bundle part holds no real stock of its own - its "on hand" is the same virtual
		// MIN(FLOOR(component qty on hand / bom qty)) that pb::get_virtual_stock() uses, so it
		// is computed as a correlated subquery here rather than read off the row directly.
		$result = $db->Execute("select i.*,
			(select MIN(FLOOR(c.quantity_on_hand / a.qty)) from " . TABLE_INVENTORY_ASSY_LIST . " a
			 join " . TABLE_INVENTORY . " c on a.sku = c.sku where a.ref_id = i.id and a.qty > 0) as virtual_stock
			from " . TABLE_INVENTORY . " i where i.sku like '" . db_input($this->sku) . "-%' and i.inventory_type = 'pc' order by i.sku asc");
		$i = 0;
		while(!$result->EOF){
			$temp = explode('-',$result->fields['sku']);
			$this->child_array[$i] = array(	'id'       		=> $result->fields['id'],
											'sku'      		=> $result->fields['sku'],
											'inactive' 		=> $result->fields['inactive'],
											'desc' 			=> $result->fields['description_short'],
											'0'				=> $temp_ms0[substr($temp[1],0,2)],
											'1'				=> (strlen($temp[1])>2)? $temp_ms1[substr($temp[1],2,4)] : '',
											'on_hand'		=> max(0, (int)($result->fields['virtual_stock'] ?? 0)),
											'on_order'		=> $result->fields['quantity_on_order'],
											'on_sales'		=> $result->fields['quantity_on_sales_order'],
											'min_stock'		=> $result->fields['minimum_stock_level'],
											'reorder_qty'	=> $result->fields['reorder_quantity'],
											'tax'			=> $result->fields['item_taxable'],
			);
			$temp =  inv_calculate_sales_price(1, $result->fields['id'], 0, 'v');
			$this->child_array[$i]['cost']	= $temp['price'];
			$temp =  inv_calculate_sales_price(1, $result->fields['id'], 0, 'c');
			$this->child_array[$i]['price']	= $temp['price'];
			$i++;
			$result->MoveNext();
		}
	}

	function check_remove($id){
		global $messageStack, $db;
		if(isset($id))$this->get_item_by_id($id);
		else return false;
		// check to see if there is inventory history remaining, if so don't allow delete
		$result = $db->Execute("select id from " . TABLE_INVENTORY_HISTORY . " where ( sku like '" . db_input($this->sku) . "-%' or sku = '" . db_input($this->sku) . "') and remaining > 0");
		if ($result->RecordCount() > 0) {
		 	$messageStack->add(INV_ERROR_DELETE_HISTORY_EXISTS, 'error');
		 	return false;
		}
		// check to see if this item is part of an assembly
		$result = $db->Execute("select id from " . TABLE_INVENTORY_ASSY_LIST . " where sku like '" . db_input($this->sku) . "-%' or sku = '" . db_input($this->sku) . "'");
		if ($result->RecordCount() > 0) {
	  		$messageStack->add(INV_ERROR_DELETE_ASSEMBLY_PART, 'error');
	  		return false;
		}
		$result = $db->Execute( "select id from " . TABLE_JOURNAL_ITEM . " where sku like '" . db_input($this->sku) . "-%' or sku = '" . db_input($this->sku) . "' limit 1");
		if ($result->Recordcount() > 0) {
			$messageStack->add(INV_ERROR_CANNOT_DELETE, 'error');
	  		return false;
		}
		$this->remove();
	  	return true;
	}

	function remove(){
		global $db;
		$ms_array  = $db->Execute("select * from " . TABLE_INVENTORY . " where sku like '" . db_input($this->sku) . "-%'");
		// captured separately (rather than reusing $ms_array) so the image-cleanup loop
		// below still gets a fresh, unconsumed cursor over the same rows.
		$child_ids = array();
		$child_id_result = $db->Execute("select id from " . TABLE_INVENTORY . " where sku like '" . db_input($this->sku) . "-%'");
		while (!$child_id_result->EOF) {
			$child_ids[] = (int)$child_id_result->fields['id'];
			$child_id_result->MoveNext();
		}
		parent::remove();
		$db->Execute("delete from " . TABLE_INVENTORY_MS_LIST . " where sku = '" . db_input($this->sku) . "'");
		$db->Execute("delete from " . TABLE_INVENTORY . " where sku like '" . db_input($this->sku) . "-%'");
		$db->Execute("delete from " . TABLE_INVENTORY_PURCHASE . " where sku like '" . db_input($this->sku) . "-%'");
		// the 'sku' delete only catches rows where THIS master is used as someone else's
		// component - it never touches the master's own template row or any child's own
		// BOM row, both keyed by ref_id, so those were left orphaned.
		$db->Execute("delete from " . TABLE_INVENTORY_ASSY_LIST . " where sku = '" . db_input($this->sku) . "'");
		$db->Execute("delete from " . TABLE_INVENTORY_ASSY_LIST . " where ref_id = " . (int)$this->id);
		if (!empty($child_ids)) {
			$db->Execute("delete from " . TABLE_INVENTORY_ASSY_LIST . " where ref_id in (" . implode(',', $child_ids) . ")");
		}
		while(!$ms_array->EOF){
			if($ms_array->fields['image_with_path'] != ''){
				$result = $db->Execute("select * from " . TABLE_INVENTORY . " where image_with_path = '" . db_input($ms_array->fields['image_with_path']) ."'");
	  			if ( $result->RecordCount() == 0){ // delete image
					$image_file = $this->image_file($ms_array->fields['image_with_path']);
					if ($image_file !== false) unlink ($image_file);
	  			}
			}
			$ms_array->MoveNext();
		}
	}

	function save(){
		global $db, $messageStack, $security_level, $currencies;
		$bom_list = array();
		for($x=0; $x < count($_POST['assy_sku']); $x++) {
			$bom_list[$x] = array(
			  	'ref_id'      => $this->id,
			  	'sku'         => db_prepare_input($_POST['assy_sku'][$x]),
				'description' => db_prepare_input($_POST['assy_desc'][$x]),
				'qty'         => $currencies->clean_value(db_prepare_input($_POST['assy_qty'][$x])),
			);
		  	$result = $db->Execute("select id from " . TABLE_INVENTORY . " where sku = '". db_input($_POST['assy_sku'][$x])."'" );
		  	if (($result->RecordCount() == 0 || $currencies->clean_value($_POST['assy_qty'][$x]) == 0) && $_POST['assy_sku'][$x] =! '') {
		  		// show error, bad sku, negative quantity. error check sku is valid and qty > 0
				$error = $messageStack->add(INV_ERROR_BAD_SKU . htmlspecialchars(db_prepare_input($_POST['assy_sku'][$x]), ENT_QUOTES, 'UTF-8'), 'error');
		  	}else{
		  		$prices = inv_calculate_sales_price(abs($this->bom[$x]['qty']), $result->fields['id'], 0, 'v');
				$bom_list[$x]['item_cost'] = strval($prices['price']);
		  		$prices = inv_calculate_sales_price(abs($this->bom[$x]['qty']), $result->fields['id'], 0, 'c');
				$bom_list[$x]['full_price'] = strval($prices['price']);
		  	}
		}
		$this->bom = $bom_list;
		$current_situation = $db->Execute("select * from " . TABLE_INVENTORY . " where id = '" . $this->id  . "'");
		$sql_data_array = parent::save();
		if ($sql_data_array == false) return false;
		if($error) return false;
		// a bundle template has no assembly step and holds no stock of its own, so its
		// parts list is never locked - same reasoning as pb::bom_editable().
		$result = $db->Execute("delete from " . TABLE_INVENTORY_ASSY_LIST . " where ref_id = " . $this->id);
		foreach($bom_list as $list_array) {
			unset($list_array['item_cost']);
			unset($list_array['full_price']);
			db_perform(TABLE_INVENTORY_ASSY_LIST, $list_array, 'insert');
		}
		$sql_data_array['inventory_type'] = 'pc';
		// 	split attributes
		$attr0 = array();
		$attr1 = array();
		if($this->ms_attr_0 != '') $attr0 = explode(',', $this->ms_attr_0);
		if($this->ms_attr_1 != '') $attr1 = explode(',', $this->ms_attr_1);
		if (empty($attr0)) {
			$this->get_ms_list();
			return false; // no attributes, nothing to do
		}
		// build skus
		$sku_list = array();
		for ($i = 0; $i < count($attr0); $i++) {
		 	$idx0 = explode(':', $attr0[$i]);
			if (empty($attr1)) {
				if($idx0[0] != ''){
					$sku_list[] = $this->sku . '-' . $idx0[0];
					$variables[$this->sku . '-' . $idx0[0]]['idx0'] = $idx0[1];
					$variables[$this->sku . '-' . $idx0[0]]['code0'] = $idx0[0];
				}
			} else {
				for ($j = 0; $j < count($attr1); $j++) {
					$idx1 = explode(':', $attr1[$j]);
					if($idx0[0] != '' && $idx1[0] != '') {
						$sku_list[] = $this->sku . '-' . $idx0[0] . $idx1[0];
						$variables[$this->sku . '-' . $idx0[0] . $idx1[0]]['idx0'] = $idx0[1];
						$variables[$this->sku . '-' . $idx0[0] . $idx1[0]]['idx1'] = $idx1[1];
					}
				}
			}
		}
		// Phase 2: a BOM line naming an ms-typed master resolves to the mi child matching
		// each variant's own attribute code (e.g. H707 -> H707-RD for the RD variant),
		// instead of every variant sharing the literal master SKU. Single-attribute
		// bundles only - a two-attribute bundle keeps the Phase 1 literal-copy behaviour,
		// since which axis should drive a given linked component is not yet expressible.
		// All-or-nothing: if any variant can't resolve, nothing is generated this save.
		$resolved_bom = array();
		foreach ($sku_list as $sku) {
			$resolved_bom[$sku] = $bom_list;
			if (!empty($attr1)) continue;
			foreach ($resolved_bom[$sku] as $k => $list_array) {
				$linked = $db->Execute("select inventory_type from " . TABLE_INVENTORY . " where sku = '" . db_input($list_array['sku']) . "'");
				if ($linked->fields['inventory_type'] != 'ms') continue;
				$child_sku = $list_array['sku'] . '-' . $variables[$sku]['code0'];
				$child = $db->Execute("select description_short from " . TABLE_INVENTORY . " where sku = '" . db_input($child_sku) . "' and inventory_type = 'mi'");
				if ($child->RecordCount() == 0) {
					$messageStack->add(sprintf(INV_ERROR_MP_LINKED_VARIANT_MISSING, $child_sku, $list_array['sku'], $sku), 'error');
					$this->get_ms_list();
					return false;
				}
				$resolved_bom[$sku][$k]['sku']         = $child_sku;
				$resolved_bom[$sku][$k]['description'] = $child->fields['description_short'];
			}
		}
		// either update, delete or insert sub skus depending on sku list
		$result = $db->Execute("select sku from " . TABLE_INVENTORY . " where inventory_type = 'pc' and sku like '" . db_input($this->sku) . "-%'");
		$existing_sku_list = array();
		while (!$result->EOF) {
			$existing_sku_list[] = $result->fields['sku'];
			$result->MoveNext();
		}
		$delete_list = array_diff($existing_sku_list, $sku_list);
		$update_list = array_intersect($existing_sku_list, $sku_list);
		$insert_list = array_diff($sku_list, $update_list);

		foreach($insert_list as $sku) { // first insert new sku's with all fields
			$sql_data_array['sku'] = $sku;
			$sql_data_array['description_short'] 	= sprintf($this->description_short, 	$variables[$sku]['idx0'], $variables[$sku]['idx1'] );
			$sql_data_array['description_purchase'] = sprintf($this->description_purchase, 	$variables[$sku]['idx0'], $variables[$sku]['idx1'] );
			$sql_data_array['description_sales'] 	= sprintf($this->description_sales, 	$variables[$sku]['idx0'], $variables[$sku]['idx1'] );
			db_perform(TABLE_INVENTORY, $sql_data_array, 'insert');
			$new_id = db_insert_id();
			foreach ($this->purchase_array as $purchase_row){
				$purchase_data_array = array (
					'sku'						=> $sku,
					'vendor_id' 				=> $purchase_row['vendor_id'],
					'vendor_sku'				=> $purchase_row['vendor_sku'],
					'description_purchase'		=> sprintf($purchase_row['description_purchase'], $variables[$sku]['idx0'], $variables[$sku]['idx1'] ),
					'item_cost'	 				=> $purchase_row['item_cost'],
					'purch_package_quantity'	=> $purchase_row['purch_package_quantity'],
					'purch_taxable'	 			=> $purchase_row['purch_taxable'],
					'price_sheet_v'				=> $purchase_row['price_sheet_v'],
				);
				db_perform(TABLE_INVENTORY_PURCHASE, $purchase_data_array, 'insert');
			}
			foreach($resolved_bom[$sku] as $list_array) {
				$list_array['ref_id'] = $new_id;
				unset($list_array['item_cost']);
				unset($list_array['full_price']);
				db_perform(TABLE_INVENTORY_ASSY_LIST, $list_array, 'insert');
			}
		}
		if ($this->id != ''){ //only update fields that are changed otherwise fields in the child could be overwritten
			foreach ($current_situation->fields as $key => $value) { // remove fields where the parent is unchanged because the childeren could have different values in these fields.
				switch($key){
					case 'description_short': 		if($this->description_short == $value) 		unset($sql_data_array[$key]); Break;
					case 'description_purchase': 	if($this->description_purchase == $value) 	unset($sql_data_array[$key]); Break;
					case 'description_sales': 		if($this->description_sales == $value) 		unset($sql_data_array[$key]); Break;
					default:						if($sql_data_array[$key] == $value) 		unset($sql_data_array[$key]);
				}
			}
		}
		foreach($update_list as $sku) { //update with reduced number of fields.
			$sql_data_array['sku'] = $sku;
			if(isset($sql_data_array['description_short'])) 	$sql_data_array['description_short'] 	= sprintf($this->description_short, 	$variables[$sku]['idx0'], $variables[$sku]['idx1'] );
			if(isset($sql_data_array['description_purchase'])) 	$sql_data_array['description_purchase'] = sprintf($this->description_purchase, 	$variables[$sku]['idx0'], $variables[$sku]['idx1'] );
			if(isset($sql_data_array['description_sales'])) 	$sql_data_array['description_sales'] 	= sprintf($this->description_sales, 	$variables[$sku]['idx0'], $variables[$sku]['idx1'] );
			db_perform(TABLE_INVENTORY, $sql_data_array, 'update', "sku = '" . db_input($sku) . "'");
			foreach($this->backup_purchase_array as $backUpKey => $backUpRow) {
				$backUpRow['description_purchase'] = sprintf($backUpRow['description_purchase'], 	$variables[$sku]['idx0'], $variables[$sku]['idx1'] );
				$purchase_data_array = null;
				if($backUpRow['action'] == 'delete'){
					$result = $db->Execute("delete from " . TABLE_INVENTORY_PURCHASE . " where sku = '" . db_input($sku) . "' and vendor_id = " . (int)$backUpRow['vendor_id']);
				} else if($backUpRow['action'] == 'insert'){
					$purchase_data_array = $backUpRow;
					unset($purchase_data_array['id']);
					unset($purchase_data_array['action']);
					$purchase_data_array['sku'] = $sku;
					db_perform(TABLE_INVENTORY_PURCHASE, $purchase_data_array, 'insert');
				}
			}
			// a bundle part holds no real stock of its own - the BOM is always re-synced,
			// unlike ia's real-stock siblings which can lock a child's own recipe.
			$result = $db->Execute("select id from " . TABLE_INVENTORY . " where sku = '" . db_input($sku) . "'");
			$temp = $db->Execute("delete from " . TABLE_INVENTORY_ASSY_LIST . " where ref_id = " . $result->fields['id']);
			foreach($resolved_bom[$sku] as $list_array) {
				$list_array['ref_id'] = $result->fields['id'];
				unset($list_array['item_cost']);
				unset($list_array['full_price']);
				db_perform(TABLE_INVENTORY_ASSY_LIST, $list_array, 'insert');
			}
		}
		if (count($delete_list) && $security_level < 4){
			$messageStack->add(ERROR_NO_PERMISSION,'error');
			$this->get_ms_list();
	  		return false;
		}
		foreach($delete_list as $sku) {
			$temp = $this->pc_check_remove($sku);
			if($temp === true){
				$result = $db->Execute("delete from " . TABLE_INVENTORY . " where sku = '" . db_input($sku) . "'");
				$result = $db->Execute("delete from " . TABLE_INVENTORY_PURCHASE . " where sku = '" . db_input($sku) . "'");
				$result = $db->Execute("select id from " . TABLE_INVENTORY . " where sku = '" . db_input($sku) . "'");
				$temp   = $db->Execute("delete from " . TABLE_INVENTORY_ASSY_LIST . " where ref_id = " . $result->fields['id']);
			}elseif ($temp === false){
				$result = $db->Execute("update " . TABLE_INVENTORY . " set inactive = '1' where sku = '" . db_input($sku) . "'");
			}
		}
		// update/insert into inventory_ms_list table
		$result = $db->Execute("select id from " . TABLE_INVENTORY_MS_LIST . " where sku = '" . db_input($this->sku) . "'");
		$exists = $result->RecordCount();
		$data_array = array(
			'sku'         => $this->sku,
			'attr_0'      => $this->ms_attr_0,
			'attr_name_0' => $this->attr_name_0,
			'attr_1'      => $this->ms_attr_1,
			'attr_name_1' => $this->attr_name_1);
		if ($exists) {
			db_perform(TABLE_INVENTORY_MS_LIST, $data_array, 'update', "id = " . $result->fields['id']);
		} else {
			db_perform(TABLE_INVENTORY_MS_LIST, $data_array, 'insert');
		}
		$this->get_ms_list();
		return true;
	}

	function pc_check_remove($sku) {
		global $messageStack, $db;
		// check to see if there is inventory history remaining, if so don't allow delete
		$result = $db->Execute("select id from " . TABLE_INVENTORY_HISTORY . " where sku = '" . db_input($sku) . "' and remaining > 0");
		if ($result->RecordCount() > 0) {
			$messageStack->add(sprintf(INV_MS_ERROR_DELETE_HISTORY_EXISTS, $sku), 'caution');
		 	return 'remaining';
		}
		// check to see if this item is part of an assembly
		$result = $db->Execute("select id from " . TABLE_INVENTORY_ASSY_LIST . " where sku = '" . db_input($sku) . "'");
		if ($result->RecordCount() > 0) {
			$messageStack->add(sprintf(INV_MS_ERROR_DELETE_ASSEMBLY_PART, $sku), 'caution');
	  		return false;
		}
		$result = $db->Execute( "select id from " . TABLE_JOURNAL_ITEM . " where sku = '" . db_input($sku) . "' limit 1");
		if ($result->Recordcount() > 0) {
			$messageStack->add(sprintf(INV_MS_ERROR_CANNOT_DELETE, $sku), 'caution');
	  		return false;
		}
	  	return true;
	}
}
