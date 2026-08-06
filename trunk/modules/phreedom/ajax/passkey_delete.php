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
//  Path: /modules/phreedom/ajax/passkey_delete.php
//
// Deletes a passkey. Used two ways:
//  - self-service, from the profile page: a user deleting their own passkey.
//  - admin revoke, from the user editor: an admin deleting another user's
//    passkey (registration itself can only ever happen on the owner's own
//    device, so there is no admin-side "add").
// Either way the response body is unused - the button that calls this uses
// hx-swap="delete", which removes the target row regardless of what comes
// back, so this only needs to succeed or fail server-side.
$id = (int)($_POST['id'] ?? 0);

if (isset($_POST['target_admin_id']) && (int)$_POST['target_admin_id'] !== (int)$_SESSION['admin_id']) {
  // admin revoking another user's passkey
  $security_level = validate_ajax_user(SECURITY_ID_USERS);
  if ($security_level > 2) {
    require_once(DIR_FS_MODULES . 'phreedom/functions/webauthn.php');
    wa_delete_credential($id, (int)$_POST['target_admin_id']);
  }
} else {
  // self-service: delete one of my own passkeys
  validate_ajax_user(SECURITY_ID_MY_PROFILE);
  require_once(DIR_FS_MODULES . 'phreedom/functions/webauthn.php');
  wa_delete_credential($id, (int)$_SESSION['admin_id']);
}

die;
