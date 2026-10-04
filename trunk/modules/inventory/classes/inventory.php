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
//  Path: /modules/inventory/classes/inventory.php
//

#[\AllowDynamicProperties]
class inventory {
	public $inventory_type			= '';
	public $help_path   			= '07.04.01.02';
	public $title       			= '';
	public $auto_field    			= '';
	public $tab_list    			= array(); 
	public $account_sales_income	= INV_STOCK_DEFAULT_SALES;
	public $account_inventory_wage	= INV_STOCK_DEFAULT_INVENTORY;
	public $account_cost_of_sales	= INV_STOCK_DEFAULT_COS;
	public $item_taxable			= INVENTORY_DEFAULT_TAX;
	public $purch_taxable			= INVENTORY_DEFAULT_PURCH_TAX;
	public $store_stock 			= array();
	public $qty_table				= false;
	public $posible_transactions	= array('sell','purchase');
	public $purchase_array			= array();
	public $history 				= array();
	public $qty_per_store			= array();
	public $posible_cost_methodes   = array('f','l','a');
	public $not_used_fields			= array();
	public $attachments				= array();
	public $assy_cost				= 0;
	public $remove_image			= false;
	public $purchases_history		= array();
	public $sales_history			= array();
	// What a posted form may never set. The constructor copies every posted
	// field onto the object, so without this a forged post chose a column name
	// that goes into SQL (auto_field), a file to delete (image_with_path), or
	// HTML and state the class builds for itself.
	const NOT_FROM_FORMS = array('security_token', 'auto_field', 'help_path', 'title', 'tab_list', 'inventory_type',
		'posible_transactions', 'posible_cost_methodes', 'not_used_fields', 'attachments', 'store_stock', 'qty_table',
		'qty_per_store', 'purchase_array', 'backup_purchase_array', 'history', 'purchases_history', 'sales_history',
		'min_vendor_id', 'old_id', 'old_sku', 'new_sku', 'image_with_path', 'assy_cost', 'creation_date', 'last_update',
		'edit_ms_list', 'allow_edit_bom', 'bom', 'child_array', 'attr_array0', 'attr_array1', 'master', 'orderHistory');

	/**
	 *
	 * this is the class construct
	 */
	public function __construct(){
		global $db;
		foreach ($_POST as $key => $value) {
			if (in_array($key, self::NOT_FROM_FORMS, true)) continue;
			$this->$key = $value;
		}
		// It goes into SQL unquoted and into file names: a whole number, or
		// empty for an item not saved yet.
		$this->id = ctype_digit((string)($this->id ?? '')) ? (string)$this->id : '';
		$this->creation_date = date('Y-m-d H:i:s');
	  	$this->last_update   = date('Y-m-d H:i:s');
		$this->tab_list['general']  = array('file'=>'template_tab_gen',      'tag'=>'general',  'order'=>10, 'text'=>TEXT_SYSTEM);
		$this->tab_list['shipping'] = array('file'=>'template_tab_shipping', 'tag'=>'shipping', 'order'=>15, 'text'=>INV_TAB_SHIPPING);
		$this->tab_list['history']  = array('file'=>'template_tab_hist',     'tag'=>'history',  'order'=>20, 'text'=>TEXT_HISTORY);
		if($this->auto_field){
			$result = $db->Execute("select ".$this->auto_field." from ".TABLE_CURRENT_STATUS);
        	$this->new_sku = $result->fields[$this->auto_field];
		}
	}
	
	/**
	 * this function gets inventory details from the database by id
	 * @param integer $id
	 */
	function get_item_by_id($id) {
		global $db;
		$this->purchases_history = null;
		$this->sales_history	 = null;
		$this->purchase_array	 = null;
		$id = (int)$id;
		$this->id = $id;
		$result = $db->Execute("SELECT * FROM ".TABLE_INVENTORY." WHERE id = $id");
		if ($result->RecordCount() != 0) foreach ($result->fields as $key => $value) {
			if (is_null($value)) $this->$key = '';
			else $this->$key = $value;
		}
		$this->attachments = $result->fields['attachments'] ? unserialize($result->fields['attachments'], ['allowed_classes' => false]) : array();
		$this->remove_unwanted_keys();
		$this->get_qty();
		$this->assy_cost = $this->item_cost;
		$this->create_purchase_array();
		$this->gather_history();
	}
	
	/** 
	 * this function gets inventory details from the database by sku
	 * @param char $sku
	 */
	
	function get_item_by_sku($sku){
		global $db;
		$this->purchases_history = null;
		$this->sales_history	 = null;
		$this->purchase_array	 = null;
		$this->sku = $sku;
		$result = $db->Execute("select * from " . TABLE_INVENTORY . " where sku = '" . db_input($sku)  . "'");
		if($result->RecordCount()!=0) foreach ($result->fields as $key => $value) {
			if(is_null($value)) $this->$key = '';
			else $this->$key = $value;
		}
		// expand attachments
		$this->attachments = $result->fields['attachments'] ? unserialize($result->fields['attachments'], ['allowed_classes' => false]) : array();
		$this->remove_unwanted_keys();
		$this->get_qty();
		$this->assy_cost = $this->item_cost;	
		$this->create_purchase_array();
		$this->gather_history();
	}
	
	/** 
	 * this function removes keys from this inventory type that we do not need.
	 */
	
	function remove_unwanted_keys(){
		global $fields;
		$this->not_used_fields = $fields->unwanted_fields($this->inventory_type);
		foreach ($this->not_used_fields as $key => $value) {
			if(isset($this->$value)) unset($this->$value);
		}
	}
	
	function get_qty(){
		global $db;
		if(in_array('quantity_on_hand', $this->not_used_fields)) return;
		$sql = " select id, short_name, primary_name from " . TABLE_CONTACTS . " c join " . TABLE_ADDRESS_BOOK . " a on c.id = a.ref_id where c.type = 'b' order by short_name ";
	  	$result = $db->Execute($sql);
	  	$qty = load_store_stock($this->sku, 0);
	  	$this->qty_per_store[0] = $qty;
	  	$this->quantity_on_hand = $qty;
	  	if(ENABLE_MULTI_BRANCH){
	  		$this->qty_table ='<table class="table table-borderless table-hover table-sm" style="width:100%">'. chr(10);
			$this->qty_table .='  <thead class="table-dark">'. chr(10);
		  	$this->qty_table .='	  <tr>';
		    $this->qty_table .='		<th>'. GEN_STORE_ID.'</th>';
		    $this->qty_table .='		<th>'. INV_HEADING_QTY_IN_STOCK .'</th>';
		  	$this->qty_table .='    </tr>'. chr(10);
		 	$this->qty_table .='  </thead>'. chr(10);
		 	$this->qty_table .='  <tbody>'. chr(10);
		  	$this->qty_table .='    <tr>';
			$this->qty_table .='      <td>' . COMPANY_ID . '</td>';
			$this->qty_table .='      <td align="center">' . $qty . '</td>';
		    $this->qty_table .='    </tr>' . chr(10);
		    while (!$result->EOF) {
		    	$qty = load_store_stock($this->sku, $result->fields['id']);
		  		$this->qty_per_store[$result->fields['id']] = $qty;
		  		$this->quantity_on_hand += $qty;
		  		$this->qty_table .= '<tr>';
			  	$this->qty_table .= '  <td>' .$result->fields['primary_name'] . '</td>';
			  	$this->qty_table .= '  <td align="center">' . $qty. '</td>';
		      	$this->qty_table .= '</tr>' . chr(10);
		      	$result->MoveNext();
			}
	     	$this->qty_table .='  </tbody>'. chr(10);
	    	$this->qty_table .='</table>'. chr(10);
	  	}
	  	
	}
	
	function set_ajax_qty($branch_id){
		if(!isset($this->quantity_on_hand)) $this->quantity_on_hand = "NA";
		if(isset($this->qty_per_store[$branch_id])){
			$this->branch_qty_in_stock = $this->qty_per_store[$branch_id];
		}else{
			$this->branch_qty_in_stock = "NA";
		}
	}
	
	//this is to check if you are allowed to create a new product
	function check_create_new() {
		global $messageStack;
		if (!$this->sku) $this->sku = $this->next_sku;
		if (!$this->sku) {
		  	$messageStack->add(INV_ERROR_SKU_BLANK, 'error');
		  	return false;
		}
		if (gen_validate_sku($this->sku)) {
		  	$messageStack->add(INV_ERROR_DUPLICATE_SKU, 'error');
			return false;
		}
		return $this->create_new();
	}
	
	//this is the general create new inventory item
	function create_new() {
		$sql_data_array = array(
	  		'sku'						=> $this->sku,
	  		'inventory_type'			=> $this->inventory_type,
	  		'cost_method'				=> $this->cost_method,
	  		'creation_date'				=> $this->creation_date,
	  		'last_update'				=> $this->last_update,
	  		'item_taxable'				=> $this->item_taxable,
	  		'purch_taxable'				=> $this->purch_taxable,
			'account_sales_income'   	=> $this->account_sales_income,
		    'account_inventory_wage'	=> $this->account_inventory_wage,
			'account_cost_of_sales'  	=> $this->account_cost_of_sales,
			// serialize enum('0','1') NOT NULL - never set on this class by any form field,
			// so it must be defaulted here rather than passed through blank.
			'serialize'					=> $this->serialize ?: '0',
			'creation_date'				=> date('Y-m-d H:i:s'),
			'last_update'				=> date('Y-m-d H:i:s'),
			);
		db_perform(TABLE_INVENTORY, $sql_data_array, 'insert');
		$this->get_item_by_id(db_insert_id());
		$sql_data_array = array (
				'sku'					=> $this->sku,
				'description_purchase'	=> '',
				'purch_package_quantity'=> 1,
				'price_sheet_v'			=> '',
		);
		db_perform(TABLE_INVENTORY_PURCHASE, $sql_data_array, 'insert');
		gen_add_audit_log(INV_LOG_INVENTORY . TEXT_ADD, TEXT_TYPE . ': ' . $this->inventory_type . ' - ' . $this->sku );
		return true;
	}
	
	//this is to copy a product
	function copy($id, $newSku) {
		global $db, $messageStack;
		if (!$newSku) $newSku = $this->next_sku;
		if (!$newSku) {
		  	$messageStack->add(INV_ERROR_SKU_BLANK, 'error');
		  	return false;
		}
		if (gen_validate_sku($newSku)) {
		  	$messageStack->add(INV_ERROR_DUPLICATE_SKU, 'error');
			return false;
		}
		if(isset($id))$this->get_item_by_id($id);
		else return false;
		$this->old_id					= $this->id;
		$this->old_sku					= $this->sku;
		$result = $db->Execute("select * from " . TABLE_INVENTORY . " where sku = '" . db_input($this->old_sku) . "'");
		//if ($result->RecordCount() == 0) return false;
		$sql_data_array = array();
		$not_usable_keys = array('id','sku','last_journal_date','upc_code','image_with_path','quantity_on_hand','quantity_on_order','quantity_on_sales_order','quantity_on_allocation','creation_date','last_update');
		foreach ($result->fields as $key => $value) {
			if(!in_array($key, $not_usable_keys)) $sql_data_array[$key] = $value;
		}
		$this->sku 							= $newSku;
		$sql_data_array['sku']				= $newSku ;
		$sql_data_array['creation_date'] 	= date('Y-m-d H:i:s');
		$sql_data_array['last_update'] 		= date('Y-m-d H:i:s');
		db_perform(TABLE_INVENTORY, $sql_data_array, 'insert');
		$this->id							= db_insert_id();
		$this->store_stock 					= array();
		$this->purchase_array				= array();
		$this->history 						= array();
		$this->qty_per_store				= array();
		$this->attachments					= array();
		$result = $db->Execute("select price_sheet_id, price_levels from " . TABLE_INVENTORY_SPECIAL_PRICES . " where inventory_id = " . (int)$id);
		while(!$result->EOF) {
	  		$output_array = array(
				'inventory_id'   => $this->id,
				'price_sheet_id' => $result->fields['price_sheet_id'],
				'price_levels'   => $result->fields['price_levels'],
	  		);
	  		db_perform(TABLE_INVENTORY_SPECIAL_PRICES, $output_array, 'insert');
	  		$result->MoveNext();
		}
		$result = $db->Execute("select * from " . TABLE_INVENTORY_PURCHASE . " where sku = '" . db_input($this->old_sku) . "'");
		while(!$result->EOF) {
			$sql_data_array = array (
				'sku'						=> $this->sku,
				'vendor_id' 				=> $result->fields['vendor_id'],
				'description_purchase'		=> $result->fields['description_purchase'],
				'item_cost'	 				=> $result->fields['item_cost'],
				'purch_package_quantity'	=> $result->fields['purch_package_quantity'],
				'purch_taxable'	 			=> $result->fields['purch_taxable'],
				'price_sheet_v'				=> $result->fields['price_sheet_v'],
			);
			db_perform(TABLE_INVENTORY_PURCHASE, $sql_data_array, 'insert');
	  		$result->MoveNext();
		}
		gen_add_audit_log(INV_LOG_INVENTORY . TEXT_COPY, $this->old_sku . ' => ' . $this->sku);
		$this->get_item_by_sku($this->sku);
		return true;
	}
	
	/*	
 	* this function is for renaming
 	*/
	
	function rename($id, $newSku){
		global $db, $messageStack;
		if (!$newSku) $newSku = $this->next_sku;
		if (!$newSku) {
		  	$messageStack->add(INV_ERROR_SKU_BLANK, 'error');
		  	return false;
		}
		if (gen_validate_sku($newSku)) {
		  	$messageStack->add(INV_ERROR_DUPLICATE_SKU, 'error');
			return false;
		}
		if(isset($id))$this->get_item_by_id($id); 
		$sku_list = array($this->sku);
		if (isset($this->edit_ms_list) && $this->edit_ms_list == true) { // build list of sku's to rename (without changing contents)
	  		$result = $db->Execute("select sku from " . TABLE_INVENTORY . " where sku like '" . db_input($this->sku) . "-%'");
	  		while(!$result->EOF) {
				$sku_list[] = $result->fields['sku'];
				$result->MoveNext();
	  		}
		}
		// start transaction (needs to all work or reset to avoid unsyncing tables)
		$db->transStart();
		// rename the afffected tables
		for ($i = 0; $i < count($sku_list); $i++) {
	  		$new_sku = str_replace($this->sku, $newSku, $sku_list[$i], $count = 1);
	  		$result = $db->Execute("update " . TABLE_INVENTORY .           " set sku = '" . db_input($new_sku) . "' where sku = '" . db_input($sku_list[$i]) . "'");
	  		$result = $db->Execute("update " . TABLE_INVENTORY_ASSY_LIST . " set sku = '" . db_input($new_sku) . "' where sku = '" . db_input($sku_list[$i]) . "'");
	  		$result = $db->Execute("update " . TABLE_INVENTORY_COGS_OWED . " set sku = '" . db_input($new_sku) . "' where sku = '" . db_input($sku_list[$i]) . "'");
	  		$result = $db->Execute("update " . TABLE_INVENTORY_HISTORY .   " set sku = '" . db_input($new_sku) . "' where sku = '" . db_input($sku_list[$i]) . "'");
	  		$result = $db->Execute("update " . TABLE_INVENTORY_MS_LIST .   " set sku = '" . db_input($new_sku) . "' where sku = '" . db_input($sku_list[$i]) . "'");
	  		$result = $db->Execute("update " . TABLE_JOURNAL_ITEM .        " set sku = '" . db_input($new_sku) . "' where sku = '" . db_input($sku_list[$i]) . "'");
	  		$result = $db->Execute("update " . TABLE_INVENTORY_PURCHASE .  " set sku = '" . db_input($new_sku) . "' where sku = '" . db_input($sku_list[$i]) . "'");
		}
		$db->transCommit();
		return true;
	}
	
	//this is to check if you are allowed to remove
	function check_remove($id) {
		global $messageStack, $db;
		if(isset($id))$this->get_item_by_id($id);
		else return false;
		// check to see if there is inventory history remaining, if so don't allow delete
		$result = $db->Execute("select id from " . TABLE_INVENTORY_HISTORY . " where sku = '" . db_input($this->sku) . "' and remaining > 0");
		if ($result->RecordCount() > 0) {
		 	$messageStack->add(INV_ERROR_DELETE_HISTORY_EXISTS, 'error');
		 	return false;
		}
		// check to see if this item is part of an assembly
		$result = $db->Execute("select id from " . TABLE_INVENTORY_ASSY_LIST . " where sku = '" . db_input($this->sku) . "'");
		if ($result->RecordCount() > 0) {
	  		$messageStack->add(INV_ERROR_DELETE_ASSEMBLY_PART, 'error');
	  		return false;
		}
		$result = $db->Execute( "select id from " . TABLE_JOURNAL_ITEM . " where sku = '" . db_input($this->sku) . "' limit 1");
		if ($result->Recordcount() > 0) {
			$messageStack->add(INV_ERROR_CANNOT_DELETE, 'error');
	  		return false;	
		}
		$this->remove();
	  	return true;
		
	}
	
	/**
	 * The full path of an existing image file under this company's inventory
	 * images directory, or false. The relative path is stored data, so it is only
	 * trusted once it resolves inside that
	 * directory: nothing outside it can be read over or deleted.
	 * @param string $relative
	 */
	function image_file($relative) {
		$relative = (string)$relative;
		if ($relative === '' || strpos($relative, "\0") !== false) return false;
		$base = realpath(DIR_FS_MY_FILES . $_SESSION['company'] . '/inventory/images');
		if ($base === false) return false;
		$full = realpath($base . '/' . $relative);
		if ($full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) return false;
		return $full;
	}

	// this is the general remove function 
	// the function check_remove calls this function. 
	function remove(){
		global $db;
		$db->Execute("delete from " . TABLE_INVENTORY . " where id = " . (int)$this->id);
		if($this->image_with_path != '') {
			$result = $db->Execute("select * from " . TABLE_INVENTORY . " where image_with_path = '" . db_input($this->image_with_path) ."'");
	  		if ( $result->RecordCount() == 0) { // delete image
				$image_file = $this->image_file($this->image_with_path);
				if ($image_file !== false) unlink ($image_file);
	  		}
		}
	  	$db->Execute("delete from " . TABLE_INVENTORY_SPECIAL_PRICES . " where inventory_id = " . (int)$this->id);
	  	$db->Execute("delete from " . TABLE_INVENTORY_PURCHASE . " where sku = '" . db_input($this->sku) . "'");
		gen_add_audit_log(INV_LOG_INVENTORY . TEXT_DELETE, $this->sku);
	}
	
	// this is the general save function.
	function save() {
		global $db, $currencies, $fields, $messageStack;
	    $sql_data_array = $fields->what_to_save();
	    // handle the checkboxes
	    $sql_data_array['inactive'] = isset($_POST['inactive']) ? $_POST['inactive'] : '0'; // else unchecked
	    // All DOUBLE NOT NULL DEFAULT '0' columns that arrive blank until the user (or a
	    // client-side calculation) sets them - reorder/minimum on a new item, the shipping
	    // dimensions, and the price/margin fields a zero-cost item (e.g. an mp/ms master)
	    // leaves untouched. Give each its own default rather than handing db_perform() ''
	    // for a non-string column.
	    foreach (array('reorder_quantity', 'minimum_stock_level', 'item_weight', 'item_length',
	                    'item_width', 'item_height', 'full_price', 'full_price_with_tax',
	                    'product_margin', 'product_markup') as $key) {
	    	if (($sql_data_array[$key] ?? '') === '') $sql_data_array[$key] = 0;
	    }
	    // The page keeps the two prices in step as they are typed, but a save can
	    // still arrive with them disagreeing - the page's script did not run, or
	    // the form carried only one. Store the price with tax that follows.
	    if (isset($sql_data_array['full_price_with_tax'])
	        && inv_price_with_tax_is_stale($sql_data_array['full_price'] ?? 0, $sql_data_array['full_price_with_tax'],
	                                       $sql_data_array['item_taxable'] ?? 0)) {
	    	$sql_data_array['full_price_with_tax'] = inv_full_price_with_tax($sql_data_array['full_price'],
	    	                                                                 $sql_data_array['item_taxable'] ?? 0);
	    }
	    foreach(array('quantity_on_hand', 'quantity_on_order', 'quantity_on_sales_order', 'quantity_on_allocation', 'creation_date', 'last_update', 'last_journal_date' ) as $key){
	    	unset($sql_data_array[$key]);
	    }
		$sql_data_array['last_update'] = date('Y-m-d H-i-s');
		if ($_SESSION['admin_security'][SECURITY_ID_PURCHASE_INVENTORY] > 1){
			$sql_data_array['item_cost'] = $this->store_purchase_array();
			// No purchase row set a vendor (e.g. a brand new item) - vendor_id int NOT NULL DEFAULT '0'.
			$sql_data_array['vendor_id'] = ($this->min_vendor_id ?? '') !== '' ? $this->min_vendor_id : 0;
		} else{
			if (isset($sql_data_array['item_cost'])) unset($sql_data_array['item_cost']);
		}
		// The image is the one this item has on file, never a posted path: only an
		// upload or the remove box below changes it.
		unset($sql_data_array['image_with_path']);
		$this->image_with_path = '';
		if ($this->id != '') {
			$result = $db->Execute("select image_with_path from " . TABLE_INVENTORY . " where id = " . (int)$this->id);
			$this->image_with_path = (string)$result->fields['image_with_path'];
		}
		$file_path = DIR_FS_MY_FILES . $_SESSION['company'] . '/inventory/images';
		if ($this->remove_image == '1') { // update the image with relative path
	  		$old_image = $this->image_file($this->image_with_path);
	  		if ($old_image !== false) unlink ($old_image);
	  		$this->image_with_path = '';
	  		$sql_data_array['image_with_path'] = ''; 
	  		unset($this->remove_image); // this is not a db field, just an action
		}
		if (is_uploaded_file($_FILES['inventory_image']['tmp_name'])) {
	  		$old_image = $this->image_file($this->image_with_path);
	  		if ($old_image !== false) unlink ($old_image);
      		$this->inventory_path = str_replace('\\', '/', (string)$this->inventory_path);
			// strip beginning and trailing slashes if present
			if (substr($this->inventory_path, 0, 1) == '/') $this->inventory_path = substr($this->inventory_path, 1);// remove leading '/' if there
	  		if (substr($this->inventory_path, -1, 1) == '/') $this->inventory_path = substr($this->inventory_path, 0, -1);// remove trailing '/' if there
	  		// A sub-directory of the images directory, typed by the user: plain
	  		// names only, so the upload can never be written anywhere else.
	  		if ($this->inventory_path !== '' && (!preg_match('/^[A-Za-z0-9_\-. \/]+$/', $this->inventory_path)
	  		    || in_array('..', explode('/', $this->inventory_path), true))) {
				$messageStack->add(INV_IMAGE_PATH_ERROR, 'error');
				return false;
	  		}
	  		if ($this->inventory_path) $file_path .= '/' . $this->inventory_path;
	  		$temp_file_name = $_FILES['inventory_image']['tmp_name'];
	  		$file_name = basename(str_replace('\\', '/', (string)$_FILES['inventory_image']['name']));
	  		if ($file_name === '' || $file_name[0] === '.') {
				$messageStack->add(INV_IMAGE_FILE_TYPE_ERROR, 'error');
				return false;
	  		}
	  		if (!validate_path($file_path)) {
				$messageStack->add(INV_IMAGE_PATH_ERROR, 'error');
				return false;
	  		// The extensions my_files/.htaccess will actually serve back - an image saved
	  		// under any other extension would upload and then 403 when the page displayed it.
	  		} elseif (!validate_upload('inventory_image', 'image', array('jpg', 'jpeg', 'png', 'gif'))) {
				return false; // validate_upload() has already said what was wrong
	  		} elseif (@getimagesize($temp_file_name) === false) {
	  			// An allowed extension is not proof of an image; make the content agree with it.
				$messageStack->add(INV_IMAGE_FILE_TYPE_ERROR, 'error');
				return false;
	  		} else { // passed all test, write file
	  			$result = $db->Execute("select * from " . TABLE_INVENTORY . " where image_with_path = '" . db_input(($this->inventory_path ? ($this->inventory_path . '/') : '') . $file_name) ."'");
	  			if ( $result->RecordCount() != 0) {
	  				$messageStack->add(INV_IMAGE_DUPLICATE_NAME, 'error');
	  				return false;
	  			}
	  			if (!copy($temp_file_name, $file_path . '/' . $file_name)) {
		  			$messageStack->add(INV_IMAGE_FILE_WRITE_ERROR, 'error');
		  			return false;
				} else {
		  			$this->image_with_path = ($this->inventory_path ? ($this->inventory_path . '/') : '') . $file_name;
		  			$sql_data_array['image_with_path'] = $this->image_with_path; // update the image with relative path
				}
	  		}
		}
		if ($this->id != ''){
			$result = $db->Execute("select attachments from ".TABLE_INVENTORY." where id = " . (int)$this->id);
			$this->attachments = $result->fields['attachments'] ? unserialize($result->fields['attachments'], ['allowed_classes' => false]) : array();
			$image_id = 0;
	  		while ($image_id < 100) { // up to 100 images
	    		if (isset($_POST['rm_attach_'.$image_id])) {
					@unlink(INVENTORY_DIR_ATTACHMENTS . "inventory_{$this->id}_{$image_id}.zip");
			  		unset($this->attachments[$image_id]);
	    		}
	    		$image_id++;
	  		}
	  		if (is_uploaded_file($_FILES['file_name']['tmp_name'])) { // find an image slot to use
	    		$image_id = 0;
	    		while (true) {
		    		if (!file_exists(INVENTORY_DIR_ATTACHMENTS.'inventory_'.$this->id.'_'.$image_id.'.zip')) break;
		    		$image_id++;
	    		}
	    		saveUploadZip('file_name', INVENTORY_DIR_ATTACHMENTS, 'inventory_'.$this->id.'_'.$image_id.'.zip');
	    		$this->attachments[$image_id] = $_FILES['file_name']['name'];
	  		}
	  		$sql_data_array ['attachments'] = !empty($this->attachments) ? serialize($this->attachments) : '';
		}
		unset($sql_data_array['last_journal_date]']);
		if ($this->id != ''){
			if ($this->price_sheet == ''){
				$db->Execute("DELETE FROM " . TABLE_INVENTORY_SPECIAL_PRICES . " WHERE inventory_id = " . (int)$this->id . " and price_sheet_id IN ( SELECT id FROM " . TABLE_PRICE_SHEETS . " WHERE type = 'c' )");
			}
			unset($sql_data_array['creation_date]']);
			$was = $db->Execute("select sku, item_weight, item_length, item_width, item_height from " . TABLE_INVENTORY . " where id = " . (int)$this->id);
			db_perform(TABLE_INVENTORY, $sql_data_array, 'update', "id = " . (int)$this->id);
			// A part's weight and size travel with every bundle that ships as its parts.
			foreach (array('item_weight', 'item_length', 'item_width', 'item_height') as $key) {
				if (isset($sql_data_array[$key]) && (float)$sql_data_array[$key] != (float)$was->fields[$key]) {
					inv_bundle_pieces_changed(array($was->fields['sku']));
					break;
				}
			}
			gen_add_audit_log(INV_LOG_INVENTORY . TEXT_UPDATE, $this->sku . ' - ' . $sql_data_array['description_short']);
		}else{
			db_perform(TABLE_INVENTORY, $sql_data_array, 'insert');
			$this->id = db_insert_id();
			$result = $db->Execute("select price_sheet_id, price_levels from " . TABLE_INVENTORY_SPECIAL_PRICES . " where inventory_id = " . $this->id);
			while(!$result->EOF) {
	  			$output_array = array(
					'inventory_id'   => $this->id,
					'price_sheet_id' => $result->fields['price_sheet_id'],
					'price_levels'   => $result->fields['price_levels'],
	  			);
	  			db_perform(TABLE_INVENTORY_SPECIAL_PRICES, $output_array, 'insert');
	  			$result->MoveNext();
			}
			gen_add_audit_log(INV_LOG_INVENTORY . TEXT_COPY, " id " . $this->id . ' new sku = ' . $this->sku);
		}
		return $sql_data_array;
	}
	
	function create_purchase_array(){
		global $db;
		if(!in_array('purchase',$this->posible_transactions)) return;
		$result = $db->Execute("select * from " . TABLE_INVENTORY_PURCHASE . " where sku = '" . db_input($this->sku) . "'");
		while(!$result->EOF){
			$this->purchase_array[]= array (
				'id'						=> $result->fields['id'],
				'vendor_id' 				=> $result->fields['vendor_id'],
				'vendor_sku'				=> $result->fields['vendor_sku'],
				'description_purchase'		=> $result->fields['description_purchase'],
				'item_cost'	 				=> $result->fields['item_cost'],
				'currency_code'				=> $result->fields['currency_code'],
				'purch_package_quantity'	=> $result->fields['purch_package_quantity'],
				'purch_taxable'	 			=> $result->fields['purch_taxable'],
				'price_sheet_v'				=> $result->fields['price_sheet_v'],
			);
			$result->MoveNext();
		}
	}

	function store_purchase_array(){
		global $db, $currencies;
		$lowest_cost = isset($this->item_cost) ? $this->item_cost : 99999999999;
		$this->backup_purchase_array = array();
		$result = $db->Execute("SELECT * FROM ".TABLE_INVENTORY_PURCHASE." WHERE sku='" . db_input($this->sku) . "'");
		while(!$result->EOF){
			$this->backup_purchase_array[$result->fields['id']]= array (
				'id'						=> $result->fields['id'],
				'vendor_id' 				=> $result->fields['vendor_id'],
				'vendor_sku'				=> $result->fields['vendor_sku'],
				'description_purchase'		=> $result->fields['description_purchase'],
				'item_cost'	 				=> $result->fields['item_cost'],
				'currency_code'				=> $result->fields['currency_code'],
				'purch_package_quantity'	=> $result->fields['purch_package_quantity'],
				'purch_taxable'	 			=> $result->fields['purch_taxable'],
				'price_sheet_v'				=> $result->fields['price_sheet_v'],
				'action'					=> 'delete',
			); // mark delete by default overwrite later if
			$result->MoveNext();
		}
		$i = 0;
		if($_POST['vendor_id_array']) foreach ($_POST['vendor_id_array'] as $key => $value) {
			$sql_data_array = array ();
			if($_POST['vendor_id_array'][$key] == '' && $_POST['description_purchase_array'][$key] == '' && ($_POST['vendor_sku_array'][$key] ?? '') == '' && $currencies->clean_value($_POST['item_cost_array'][$key]) == 0) break;
			$sql_data_array['sku'] = $this->sku;
			$this->purchase_array[$i]['id']	= isset($_POST['row_id_array'][$key]) ? $_POST['row_id_array'][$key] : '';
			if(isset($_POST['vendor_id_array'][$key])) {
				// vendor_id int NOT NULL DEFAULT '0' - a row can be saved with the
				// vendor left blank (e.g. only a description or cost was entered).
				$vendor_id_value								= ($_POST['vendor_id_array'][$key] !== '') ? $_POST['vendor_id_array'][$key] : 0;
				$sql_data_array['vendor_id'] 					= $vendor_id_value;
				$this->purchase_array[$i]['vendor_id'] 				= $vendor_id_value;
			}
			if(isset($_POST['vendor_sku_array'][$key])) {
				$sql_data_array['vendor_sku'] 					= $_POST['vendor_sku_array'][$key];
				$this->purchase_array[$i]['vendor_sku'] 				= $_POST['vendor_sku_array'][$key];
			}
			if(isset($_POST['description_purchase_array'][$key])){
				$sql_data_array['description_purchase']			= $_POST['description_purchase_array'][$key];
				$this->purchase_array[$i]['description_purchase']	= $_POST['description_purchase_array'][$key];
			} 	
			if(isset($_POST['item_cost_array'][$key])) {
				$sql_data_array['item_cost']	 				= $currencies->clean_value($_POST['item_cost_array'][$key]);
				$this->purchase_array[$i]['item_cost']	 			= $currencies->clean_value($_POST['item_cost_array'][$key]);
			}
			if(isset($_POST['item_cost_currency_array'][$key])) {
				$sql_data_array['currency_code']				= $_POST['item_cost_currency_array'][$key];
				$this->purchase_array[$i]['currency_code']			= $_POST['item_cost_currency_array'][$key];
			}
			if(isset($_POST['purch_package_quantity_array'][$key])){
				$sql_data_array['purch_package_quantity']		= $_POST['purch_package_quantity_array'][$key];
				$this->purchase_array[$i]['purch_package_quantity']	= $_POST['purch_package_quantity_array'][$key];
			}	
			if(isset($_POST['purch_taxable_array'][$key]))	{
				$sql_data_array['purch_taxable']	 			= $_POST['purch_taxable_array'][$key];
				$this->purchase_array[$i]['purch_taxable']	 		= $_POST['purch_taxable_array'][$key];		
			}	
			if(isset($_POST['price_sheet_v_array'][$key])){
				$sql_data_array['price_sheet_v']				= $_POST['price_sheet_v_array'][$key];
				$this->purchase_array[$i]['price_sheet_v']			= $_POST['price_sheet_v_array'][$key];
			}
			if(!empty($sql_data_array)){
				if(isset($_POST['row_id_array'][$key]) && $_POST['row_id_array'][$key] != ''){//update
					$this->backup_purchase_array[$_POST['row_id_array'][$key]]['action'] = 'update';
					db_perform(TABLE_INVENTORY_PURCHASE, $sql_data_array, 'update', "id = " . (int)$_POST['row_id_array'][$key]);
					if($this->backup_purchase_array[$_POST['row_id_array'][$key]]['price_sheet_v'] != "" && $_POST['price_sheet_v_array'][$key] == ""){
						$db->Execute("DELETE FROM " . TABLE_INVENTORY_SPECIAL_PRICES . " WHERE inventory_id = " . (int)$this->id . " and price_sheet_id IN ( SELECT id FROM " . TABLE_PRICE_SHEETS . " WHERE type = 'v' and sheet_name = '".db_input($this->backup_purchase_array[$_POST['row_id_array'][$key]]['price_sheet_v'])."')");
					}
				}else{//insert
					db_perform(TABLE_INVENTORY_PURCHASE, $sql_data_array, 'insert');
					$this->backup_purchase_array[db_insert_id()]= array (
						'id'						=> db_insert_id(),
						'vendor_id' 				=> $_POST['vendor_id_array'][$key],
						'vendor_sku'				=> $_POST['vendor_sku_array'][$key] ?? '',
						'description_purchase'		=> $_POST['description_purchase_array'][$key],
						'item_cost'	 				=> $_POST['item_cost_array'][$key],
						'currency_code'				=> $_POST['item_cost_currency_array'][$key] ?? DEFAULT_CURRENCY,
						'purch_package_quantity'	=> $_POST['purch_package_quantity_array'][$key],
						'purch_taxable'	 			=> $_POST['purch_taxable_array'][$key],
						'price_sheet_v'				=> $_POST['price_sheet_v_array'][$key],
						'action'					=> 'insert',
					);
				}
				$lowest_cost = min($lowest_cost, $sql_data_array['item_cost']);
				if ($lowest_cost == $sql_data_array['item_cost']) $this->min_vendor_id = $sql_data_array['vendor_id'];
			}
			$i++;
		}
		foreach($this->backup_purchase_array as $key => $value){
			if($value['action'] == 'delete'){
				$db->Execute("DELETE FROM " . TABLE_INVENTORY_PURCHASE . " WHERE id = " . (int)$value['id']);
				$db->Execute("DELETE FROM " . TABLE_INVENTORY_SPECIAL_PRICES . " WHERE inventory_id = " . (int)$this->id . " and price_sheet_id IN ( SELECT id FROM " . TABLE_PRICE_SHEETS . " WHERE type = 'v' and sheet_name = '" . db_input($value['price_sheet_v']) . "')");
			}
		}
		return $lowest_cost == 99999999999 ? 0 : $lowest_cost; //added in case no purchase data entered when creating new product
	}
	
	function gather_history() {
    	global $db;
		$dates = gen_get_dates();
		$cur_month = $dates['ThisYear'] . '-' . substr('0' . $dates['ThisMonth'], -2) . '-01';
		$temp_year = $dates['ThisYear'];
		$temp_month = $dates['ThisMonth'];
		for($i = 0; $i < 13; $i++) {
	  		$index = substr($cur_month, 0, 7);
	  		$this->purchases_history[$index] = array(
	  			'post_date'		=> $cur_month,
	  			'MonthName'		=> $dates['MonthName'],
	  			'ThisYear'		=> $dates['ThisYear'],
	  			'qty'			=> 0,
	  			'total_amount'	=> 0,
	  		);
	  		$this->sales_history[$index] = array(
	  			'post_date'		=> $cur_month,
	  			'MonthName'		=> $dates['MonthName'],
	  			'ThisYear'		=> $dates['ThisYear'],
	  			'qty'			=> 0,
	  			'usage'			=> 0,
	  			'total_amount'	=> 0,
	  		);
	  		$cur_month = gen_specific_date($cur_month, 0, -1, 0);
	  		$dates = gen_get_dates($cur_month);
		}
		$last_year = ($temp_year - 1) . '-' . substr('0' . $temp_month, -2) . '-01';

		// load the SO's and PO's and get order, expected del date
		$sql = "select m.id, m.journal_id, m.store_id, m.purchase_invoice_id, i.qty, i.post_date, i.date_1,	i.id as item_id 
	  	  from " . TABLE_JOURNAL_MAIN . " m inner join " . TABLE_JOURNAL_ITEM . " i on m.id = i.ref_id 
	  	  where m.journal_id in (4, 10) and i.sku = '" . db_input($this->sku) . "' and m.closed = '0' 
	  	  order by i.date_1";
		$result = $db->Execute($sql);
		while(!$result->EOF) {
	  		switch ($result->fields['journal_id']) {
	    		case  4:
		  			$gl_type   = 'por';
		  			$hist_type = 'open_po';
		  			break;
	    		case 10:
		  			$gl_type   = 'sos';
		  			$hist_type = 'open_so';
		  		break;
	  		}
	  		$sql = "select sum(qty) as qty from " . TABLE_JOURNAL_ITEM . " 
			  where gl_type = '" . $gl_type . "' and so_po_item_ref_id = " . $result->fields['item_id'];
	  		$adj = $db->Execute($sql); // this looks for partial received to make sure this item is still on order
	  		if ($result->fields['qty'] > $adj->fields['qty']) {
				$this->history[$hist_type][] = array(
		  			'id'                  => $result->fields['id'],
		  			'store_id'            => $result->fields['store_id'],
		  			'purchase_invoice_id' => $result->fields['purchase_invoice_id'],
		  			'post_date'           => $result->fields['post_date'],
		  			'qty'                 => $result->fields['qty'],
		  			'date_1'              => $result->fields['date_1'],
				);
	  		}
	  		$result->MoveNext();
		}

		// load the units received and sold, assembled and adjusted
		$sql = "select m.journal_id, m.post_date, i.qty, i.gl_type, i.credit_amount, i.debit_amount 
		  from " . TABLE_JOURNAL_MAIN . " m inner join " . TABLE_JOURNAL_ITEM . " i on m.id = i.ref_id 
		  where m.journal_id in (6, 12, 14, 16, 19, 21) and i.sku = '" . db_input($this->sku) . "' and m.post_date >= '" . $last_year . "' 
		  order by m.post_date DESC";
		$result = $db->Execute($sql);
		while(!$result->EOF) {
			$month = substr($result->fields['post_date'], 0, 7);
	  		switch ($result->fields['journal_id']) {
	    		case  6:
	    		case 21:
	      			$this->purchases_history[$month]['qty']          += $result->fields['qty'];
	      			$this->purchases_history[$month]['total_amount'] += $result->fields['debit_amount'];
		  			break;
	    		case 12:
	    		case 19:
	      			$this->sales_history[$month]['qty']              += $result->fields['qty'];
	      			$this->sales_history[$month]['usage']            += $result->fields['qty'];
	      			$this->sales_history[$month]['total_amount']     += $result->fields['credit_amount'];
		  			break;
	    		case 14:
		  			if ($result->fields['gl_type'] == 'asi') { // only if part of an assembly
	        			$this->sales_history[$month]['usage'] -= $result->fields['qty']; // need to negate quantity since assy.
		  			}
		  			break;
	    		case 16:
	      			$this->sales_history[$month]['usage'] += $result->fields['qty'];
		  			break;
	  		}
	  		$result->MoveNext();
		}

		// calculate average usage 
		$cnt = 0;
		foreach ($this->sales_history as $key => $value) {
	  		if ($cnt == 0) { 
	    		$cnt++;
				continue; // skip current month since we probably don't have the full months worth
	  		}
	  		$this->history['averages']['12month'] += $this->sales_history[$key]['usage'];
	  		if ($cnt < 7) $this->history['averages']['6month'] += $this->sales_history[$key]['usage'];
	  		if ($cnt < 4) $this->history['averages']['3month'] += $this->sales_history[$key]['usage'];
	  		if ($cnt < 2) $this->history['averages']['1month'] += $this->sales_history[$key]['usage'];
	  		$cnt++;
		}
		$this->history['averages']['12month'] = round($this->history['averages']['12month'] / 12, 2);
		$this->history['averages']['6month']  = round($this->history['averages']['6month']  /  6, 2);
		$this->history['averages']['3month']  = round($this->history['averages']['3month']  /  3, 2);
	}

/*******************************************************************************************************************/
// START Journal Functions
/*******************************************************************************************************************/	
	
	
	function inventory_auto_add($sku, $desc, $item_cost, $full_price, $vendor_id){
		$sql_data_array = array(
	  		'sku'						=> $sku,
	  		'inventory_type'			=> $this->inventory_type,
			'description_short'      	=> $desc, 
		  	'description_purchase'   	=> $desc, 
		  	'description_sales'      	=> $desc, 
			'vendor_id' 				=> $vendor_id,
	  		'cost_method'				=> $this->cost_method,
	  		'creation_date'				=> $this->creation_date,
	  		'last_update'				=> $this->last_update,
	  		'item_taxable'				=> $this->item_taxable,
	  		'purch_taxable'				=> $this->purch_taxable,
			'account_sales_income'   	=> $this->account_sales_income,
		    'account_inventory_wage'	=> $this->account_inventory_wage,
			'account_cost_of_sales'  	=> $this->account_cost_of_sales,
			'serialize'					=> $this->serialize ?: '0',
			'item_cost'              	=> $item_cost,
			'full_price'             	=> $full_price,
			'creation_date'				=> date('Y-m-d H:i:s'),
			'last_update'				=> date('Y-m-d H:i:s'),
		);
		db_perform(TABLE_INVENTORY, $sql_data_array, 'insert');
		$this->get_item_by_id(db_insert_id());
		$sql_data_array = array (
			'sku'						=> $sku,
			'vendor_id' 				=> $vendor_id,
			'description_purchase'		=> $desc,
			'item_cost'	 				=> $item_cost,
			'purch_package_quantity'	=> 1,
			'purch_taxable'	 			=> $this->purch_taxable,
		); 
		db_perform(TABLE_INVENTORY_PURCHASE, $sql_data_array, 'insert');
		return true;
	}
	
	function update_inventory_status($sku, $field, $adjustment, $item_cost, $vendor_id, $desc){
		global $db;
		// The column is named in the SQL: only the inventory quantity columns.
		if (!in_array($field, array('quantity_on_hand', 'quantity_on_order', 'quantity_on_sales_order', 'quantity_on_allocation'), true)) return false;
		$sql = "update " . TABLE_INVENTORY_PURCHASE . " set item_cost = " . (float)$item_cost;
	  	$sql .= " where sku = '" . db_input($sku) . "' and vendor_id = " . (int)$vendor_id;
	  	$result = $db->Execute($sql);
		if($result->RecordCount() == 0) {
			$sql_data_array = array (
			  'sku'						=> $sku,
			  'vendor_id' 				=> $vendor_id,
			  'description_purchase'	=> $desc,
			  'item_cost'	 			=> $item_cost,
			  'purch_package_quantity'	=> 1,
			  'purch_taxable'	 		=> $this->purch_taxable,
			); 
			db_perform(TABLE_INVENTORY_PURCHASE, $sql_data_array, 'insert');
		}
	  	$sql = "update " . TABLE_INVENTORY . " set $field = $field + " . (float)$adjustment . ", ";
	  	$sql .= "last_journal_date = now() where sku = '" . db_input($sku) . "'";
	  	$result = $db->Execute($sql);
		return true;
	}
	
	function __destruct(){
//		if(DEBUG) print_r($this);
	}
}