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
//  Path: /modules/inventory/classes/inventory_fields.php
//
require_once(DIR_FS_MODULES . 'phreedom/classes/fields.php');

class inventory_fields extends fields{
	public  $help_path   = '07.04.05';
	public  $title       = TEXT_EXTRA_FIELDS;
	public  $module      = 'inventory';
	public  $db_table    = TABLE_INVENTORY;
	public  $type_params = 'inventory_type';
	public  $extra_buttons = '';
  
  public function __construct($sync = true){
  	gen_pull_language('inventory');
  	require(DIR_FS_MODULES . 'inventory/defaults.php');
  	foreach ($inventory_types_plus as $key => $value) $this->type_array[] = array('id'=>$key, 'text'=>$value);
    $this->type_desc    = INV_ENTRY_INVENTORY_TYPE;
    parent::__construct($sync);
  }

  function btn_save($id = '') {
  	if(parent::btn_save($id = '')){
  		$sql_data_array['use_in_inventory_filter'] = db_prepare_input($_POST['use_in_inventory_filter']);
  		db_perform(TABLE_EXTRA_FIELDS, $sql_data_array, 'update', "id = $this->id");
  		return true;
  	}
  	return false;
  }
  public function build_form_html($action, $id = '') {
  	$output  = parent::build_form_html($action, $id = '');
  	$output .= '<div class="fb-fieldset mb-2">' . "\n";
  	$output .= '  <div class="fb-fieldset-legend">' . TEXT_OPTIONS . '</div>' . "\n";
  	$output .= '  ' . html_checkbox_field('use_in_inventory_filter', true, $this->use_in_inventory_filter, '') . '&nbsp;' . TEXT_USE_IN_FILTER . "<br />\n";
  	$output .= '</div>' . "\n";
  	return $output;
  }
}

?>