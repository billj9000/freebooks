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
//  Path: /modules/inventory/classes/type/pc.php
//
require_once(DIR_FS_MODULES . 'inventory/classes/type/mp.php');
require_once(DIR_FS_MODULES . 'inventory/classes/type/pb.php');
class pc extends pb { //Product Bundle Part. child of mp (master product bundle) - a plain pb with a locked-to-master identity
	public $inventory_type			= 'pc';
	public $title 					= INV_TYPES_PC;
	public $edit_ms_list			= false;
	public $master					= '';
	public $attr_array0 			= array();
	public $attr_array1 			= array();
	public $ms_attr_0				= '';
	public $ms_attr_1				= '';
	public $attr_name_0				= '';
	public $attr_name_1				= '';
	public $child_array 			= array();
	public $posible_transactions	= array('sell');

	function __construct(){
		parent::__construct();
		$this->tab_list['master'] = array('file'=>'template_tab_ms',	'tag'=>'master',    'order'=>30, 'text'=>INV_MS_ATTRIBUTES);
	}

	function get_item_by_id($id){
		parent::get_item_by_id($id);
		$this->get_ms_list();
	}

	function get_item_by_sku($sku){
		parent::get_item_by_sku($sku);
		$this->get_ms_list();
	}

	// a bundle part belongs to its master's attribute family - it cannot be copied or
	// deleted on its own, same restriction ia places on mb's children.
	function copy($id, $newSku) {
		global $messageStack;
		$messageStack->add(INV_ERROR_CANNOT_COPY, 'error');
		return false;
	}

	function check_remove($id){ // this is disabled in the form but just in case, error here as well
		global $messageStack;
		$messageStack->add(INV_PC_ERROR_CANNOT_DELETE_SEPARATE, 'error');
		return false;
	}

	function get_ms_list(){
		global $db;
		$master = explode('-',$this->sku);
		$this->master = $master[0];
		$result = $db->Execute("select * from " . TABLE_INVENTORY_MS_LIST . " where sku = '" . $this->master . "'");
	  	$this->ms_attr_0   = ($result->RecordCount() > 0) ? $result->fields['attr_0'] : '';
	  	$this->attr_name_0 = ($result->RecordCount() > 0) ? $result->fields['attr_name_0'] : '';
	  	$this->ms_attr_1   = ($result->RecordCount() > 0) ? $result->fields['attr_1'] : '';
	  	$this->attr_name_1 = ($result->RecordCount() > 0) ? $result->fields['attr_name_1'] : '';
		if ($this->ms_attr_0) {
			$temp = explode(',', $this->ms_attr_0);
			for ($i = 0; $i < count($temp); $i++) {
			  $code = substr($temp[$i], 0, strpos($temp[$i], ':'));
			  $desc = substr($temp[$i], strpos($temp[$i], ':') + 1);
			  $this->attr_array0[] = array('id' => $code . ':' . $desc, 'text' => $code . ' : ' . $desc);
			  $temp_ms0[$code] = $desc;
			}
		}
		if ($this->ms_attr_1) {
			$temp = explode(',', $this->ms_attr_1);
			for ($i = 0; $i < count($temp); $i++) {
			  $code = substr($temp[$i], 0, strpos($temp[$i], ':'));
			  $desc = substr($temp[$i], strpos($temp[$i], ':') + 1);
			  $this->attr_array1[] = array('id' => $code . ':' . $desc, 'text' => $code . ' : ' . $desc);
			  $temp_ms1[$code] = $desc;
			}
		}
		// siblings hold no real stock of their own - "on hand" is the same virtual
		// MIN(FLOOR(component qty on hand / bom qty)) that pb::get_virtual_stock() uses.
		$result = $db->Execute("select i.*,
			(select MIN(FLOOR(c.quantity_on_hand / a.qty)) from " . TABLE_INVENTORY_ASSY_LIST . " a
			 join " . TABLE_INVENTORY . " c on a.sku = c.sku where a.ref_id = i.id and a.qty > 0) as virtual_stock
			from " . TABLE_INVENTORY . " i where i.sku like '" . $this->master . "-%' and i.inventory_type = 'pc' and i.sku<>'".$this->sku."'");
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
}
