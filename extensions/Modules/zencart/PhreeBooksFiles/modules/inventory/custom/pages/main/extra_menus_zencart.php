<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft, LLC (www.PhreeSoft.com)       |
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
//  Path: /modules/inventory/custom/pages/main/extra_menus.php
//
// This file contains the extra defines that can be used for customizing you output and 
// adding functionality to PhreeBooks
// Modified Language defines, used to over-ride the standard language for customization. These
// values are loaded prior to the standard language defines and take priority.

// Additional action bar buttons (DYNAMIC AS IT IS SET BASED ON EVERY LINE!!!)
@include_once(DIR_FS_MODULES . 'zencart/config.php'); // pull the current ZenCart config info, if it is there
@include_once(DIR_FS_MODULES . 'zencart/language/' . $_SESSION['language'] . '/language.php');
function add_extra_action_bar_buttons($query_fields) {
  $output = '';
  $list_icon_enabled = defined('ZENCART_LIST_ICON_ENABLED') && ZENCART_LIST_ICON_ENABLED === '1';
  if (defined('ZENCART_URL') && $list_icon_enabled && $_SESSION['admin_security'][SECURITY_ID_MAINTAIN_INVENTORY] > 1 && $query_fields['catalog'] == '1') {
   $output .= html_icon('../../../../modules/zencart/images/zencart.gif', ZENCART_IVENTORY_UPLOAD, 'small', 'onclick="submitSeq('.$query_fields['id'].', \'upload_zc\')"', '16', '16').chr(10);
  }
  return $output;
}
// Defines used to increase search scope (additional fields) within a module, the constant
// cannot change and the format should be as follows:
//$extra_search_fields = array('field_name');
// defines to use to retrieve more fields from sql for custom processing in list generation operations
$extra_fields = array();
// for the ZenCart upload mod, the catalog field should be in the table
if (defined('ZENCART_URL')) $extra_fields[] = 'catalog';
if (count($extra_fields) > 0) $extra_query_list_fields = $extra_fields;

// Additional toolbar button on the inventory item edit/detail page: saves the item
// and uploads it (with image) to the ZenCart catalog in one click. Only shown when
// the item's ZenCart tab "catalog" checkbox is currently ticked — checked directly
// against the DB (mirrors the row-icon check above) since this button is what
// performs the *next* save, so it must reflect the last saved state, not the
// in-progress form.
$extra_toolbar_buttons_detail = array();
if (defined('ZENCART_URL') && $_SESSION['admin_security'][SECURITY_ID_MAINTAIN_INVENTORY] > 2) {
  $zc_detail_id = (int)($_REQUEST['cID'] ?? $_REQUEST['rowSeq'] ?? 0);
  if ($zc_detail_id > 0) {
    $zc_check = $db->Execute("SELECT catalog FROM " . TABLE_INVENTORY . " WHERE id = " . $zc_detail_id);
    if (!$zc_check->EOF && $zc_check->fields['catalog'] == '1') {
      $extra_toolbar_buttons_detail['save_upload_zc'] = array(
        'show'   => true,
        'icon'   => '../../../../modules/zencart/images/zencart.gif',
        'params' => 'onclick="submitToDo(\'save_upload_zc\')"',
        'text'   => ZENCART_SAVE_UPLOAD,
        'order'  => '6',
      );
    }
  }
}

?>