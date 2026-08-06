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
//  Path: /modules/phreedom/includes/webauthn/row.php
//
// Renders one <tr> of the "My Passkeys" table. Expects $cred (a
// TABLE_WEBAUTHN_CREDS row array) in scope. Shared by the initial page
// render (pages/profile/template_main.php) and the rename ajax endpoint
// so the two can't drift out of sync.
$pk_label_js = htmlspecialchars(addslashes($cred['label']), ENT_QUOTES);
$pk_row_class = $pk_row_class ?? '';
?>
<tr id="pk-row-<?php echo (int)$cred['id']; ?>" class="<?php echo htmlspecialchars($pk_row_class); ?>">
  <td><?php echo htmlspecialchars($cred['label']); ?></td>
  <td><?php echo htmlspecialchars($cred['created']); ?></td>
  <td><?php echo $cred['last_used'] ? htmlspecialchars($cred['last_used']) : '<em>' . TEXT_PASSKEY_NEVER_USED . '</em>'; ?></td>
  <td class="text-end">
    <button type="button" class="btn btn-outline-secondary btn-sm"
      onclick="pkRename(<?php echo (int)$cred['id']; ?>, '<?php echo $pk_label_js; ?>')">
      <?php echo TEXT_PASSKEY_RENAME; ?>
    </button>
    <button type="button" class="btn btn-danger btn-sm"
      hx-post="<?php echo html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=ajax&amp;op=passkey_delete', 'SSL'); ?>"
      hx-vals='{"id": "<?php echo (int)$cred['id']; ?>"}'
      hx-target="#pk-row-<?php echo (int)$cred['id']; ?>"
      hx-swap="delete"
      hx-confirm="<?php echo addslashes(TEXT_PASSKEY_CONFIRM_DELETE); ?>">
      <?php echo TEXT_PASSKEY_DELETE; ?>
    </button>
  </td>
</tr>
