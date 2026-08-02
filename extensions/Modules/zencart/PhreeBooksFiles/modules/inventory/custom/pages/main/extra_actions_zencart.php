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
//  Path: /modules/inventory/custom/pages/main/extra_actions.php
//

// This file contains the extra actions added to the maintain inventory module, it is executed
// before the standard switch statement

switch ($_REQUEST['action']) {
// Begin - Upload operation added by PhreeSoft to upload products to ZenCart
  case 'upload_zc':
	if (!defined('ZENCART_ENABLED') || ZENCART_ENABLED !== '1') {
	  $messageStack->add(defined('ZENCART_DISABLED_NOTICE') ? ZENCART_DISABLED_NOTICE : 'The ZenCart integration is disabled.', 'caution');
	  $_REQUEST['action'] = '';
	  break;
	}
	$id = (int)db_prepare_input($_POST['rowSeq']);
	require_once(DIR_FS_MODULES . 'zencart/functions/zencart.php');
	require_once(DIR_FS_MODULES . 'zencart/classes/zencart.php');
	$upXML = new zencart();
	if ($upXML->uploadProduct($id)) gen_add_audit_log(ZENCART_UPLOAD_PRODUCT, $upXML->sku);
	$_REQUEST['action'] = '';
	break;
// End - Upload operation added by PhreeSoft

// Begin - Save & Upload operation for the inventory item detail page toolbar button.
// $cInfo is not instantiated by the core switch below for unrecognised action names,
// so this mirrors the same instantiation the 'save' case relies on, then re-uses the
// exact same $cInfo->save() call before uploading, and hands off to the core 'edit'
// case (by rewriting $_REQUEST['action']) to redisplay the item afterward.
  case 'save_upload_zc':
	validate_security($security_level, 2); // security check - same level required as a normal save
	if (file_exists(DIR_FS_WORKING . 'custom/classes/type/'.$type.'.php')) {
		require_once(DIR_FS_WORKING . 'custom/classes/type/'.$type.'.php');
	} elseif (file_exists(DIR_FS_WORKING . 'classes/type/'.$type.'.php')) {
		require_once(DIR_FS_WORKING . 'classes/type/'.$type.'.php');
	} else {
		die('Bad inventory type');
	}
	$cInfo = new $type();
	if ($cInfo->save() !== false) {
		if (defined('ZENCART_ENABLED') && ZENCART_ENABLED === '1') {
			require_once(DIR_FS_MODULES . 'zencart/functions/zencart.php');
			require_once(DIR_FS_MODULES . 'zencart/classes/zencart.php');
			$upXML = new zencart();
			if ($upXML->uploadProduct($cInfo->id, true)) {
				gen_add_audit_log(ZENCART_UPLOAD_PRODUCT, $upXML->sku);
				$messageStack->add(ZENCART_SAVE_UPLOAD_SUCCESS, 'success');
			}
		} else {
			$messageStack->add(defined('ZENCART_DISABLED_NOTICE') ? ZENCART_DISABLED_NOTICE : 'The ZenCart integration is disabled.', 'caution');
		}
	}
	$id = $cInfo->id;
	$_REQUEST['action'] = 'edit';
	break;
// End - Save & Upload operation

  default:
}
?>