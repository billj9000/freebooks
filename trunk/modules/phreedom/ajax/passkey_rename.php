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
//  Path: /modules/phreedom/ajax/passkey_rename.php
//
// Renames one of the current user's own passkeys. Returns the updated <tr>
// (see modules/phreedom/pages/profile/template_main.php) for direct DOM swap.
$security_level = validate_ajax_user(SECURITY_ID_MY_PROFILE);

gen_pull_language('phreedom', 'admin');
require_once(DIR_FS_MODULES . 'phreedom/functions/webauthn.php');

$admin_id = (int)$_SESSION['admin_id'];
$id       = (int)($_POST['id'] ?? 0);
$label    = trim(db_prepare_input($_POST['label'] ?? ''));

if ($label !== '') wa_rename_credential($id, $admin_id, $label);
$cred = wa_get_credential($id, $admin_id);
if (!$cred) { echo ''; die; }

require(DIR_FS_MODULES . 'phreedom/includes/webauthn/row.php');
die;
