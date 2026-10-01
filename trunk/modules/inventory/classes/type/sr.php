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
//  Path: /modules/inventory/classes/type/sr.php
//
require_once(DIR_FS_MODULES . 'inventory/classes/inventory.php');
class sr extends inventory {//Serialized Item
	public $inventory_type			= 'sr';
	public $title       			= INV_TYPES_SR;
    public $serialize 				= 1;
    public $account_sales_income	= INV_SERIALIZE_DEFAULT_SALES;
	public $account_inventory_wage	= INV_SERIALIZE_DEFAULT_INVENTORY;
	public $account_cost_of_sales	= INV_SERIALIZE_DEFAULT_COS; 
	public $cost_method				= 'f';
	public $posible_cost_methodes   = array('f');

	function get_item_by_id($id){
		parent::get_item_by_id($id);
		$this->get_sr_list();
	}
	
	function get_item_by_sku($sku){
		parent::get_item_by_sku($sku);
		$this->get_sr_list();
	}
	
	function get_sr_list(){
		global $db;
		$branches = gen_get_store_ids();
		$this->quantity_on_hand = 0;
		$result = $db->Execute("select store_id, qty, serialize_number from " . TABLE_INVENTORY_HISTORY . " 
	  		where sku = '" . db_input($this->sku) . "' and remaining > 0 order by store_id");
  		$this->qty_table ='<table class="table table-borderless table-hover table-sm" style="width:100%">'. chr(10);
		$this->qty_table .='  <thead class="table-dark">'. chr(10);
	  	$this->qty_table .='	  <tr>';
	    $this->qty_table .='		<th>'. GEN_STORE_ID.'</th>';
	    $this->qty_table .='		<th>'. TEXT_QUANTITY.'</th>';
	    $this->qty_table .='		<th>'. INV_HEADING_SERIAL_NUMBER .'</th>';
	  	$this->qty_table .='    </tr>'. chr(10);
	 	$this->qty_table .='  </thead>'. chr(10);
	 	$this->qty_table .='  <tbody>'. chr(10);
	    while (!$result->EOF) {
	  		$this->quantity_on_hand += $result->fields['qty'];
	  		$this->qty_table .= '<tr>';
		  	$this->qty_table .= '  <td>' .$branches[$result->fields['store_id']]['text'] . '</td>';
		  	$this->qty_table .= '  <td>' .$result->fields['qty'] . '</td>';
		  	$this->qty_table .= '  <td align="center">' . $result->fields['serialize_number']. '</td>';
	      	$this->qty_table .= '</tr>' . chr(10);
	      	$result->MoveNext();
		}
     	$this->qty_table .='  </tbody>'. chr(10);
    	$this->qty_table .='</table>'. chr(10);

    	$field_list  = array('m.id', 'm.post_date', 'm.purchase_invoice_id', 'm.closed', 'm.bill_primary_name', 'm.total_amount', 'i.serialize_number');
    	$sql   = "SELECT ".implode(', ', $field_list)." FROM ".TABLE_JOURNAL_MAIN." m JOIN ".TABLE_JOURNAL_ITEM." i on m.id=i.ref_id
    	WHERE m.journal_id=12 AND i.sku='" . db_input($this->sku) . "' ORDER BY m.purchase_invoice_id DESC";
    	$this->orderHistory = $db->Execute($sql);
    	$this->tab_list['orderhist'] = array('file'=>'template_tab_hist_sr', 'tag'=>'orderhist', 'order'=>40, 'text'=>'Unit History');
	}
}