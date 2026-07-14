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
//  Path: /modules/api/ajax/toggle_key.php
//
// Toggles a key's enabled flag.  Returns the updated <tr> so htmx
// can swap it in-place (hx-target="#api-key-row-{id}", hx-swap="outerHTML").
//
$security_level = validate_ajax_user(SECURITY_ID_CONFIGURATION);
if ($security_level < 3) {
  echo '<tr><td colspan="7" class="text-danger">' . htmlspecialchars(ERROR_NO_PERMISSION) . '</td></tr>';
  die;
}

gen_pull_language('api');
require_once(DIR_FS_MODULES . 'api/defaults.php');

$kid     = (int)($_POST['key_id'] ?? 0);
$company = db_input($_SESSION['company']);

// Fetch current state (verify ownership)
$result = $db->Execute(
  "SELECT * FROM " . TABLE_API_KEYS . " WHERE id = $kid AND company = '$company'"
);
if ($result->EOF) {
  echo '<tr><td colspan="7"></td></tr>';
  die;
}
$k = $result->fields;

// Flip enabled
$new_enabled = $k['enabled'] === '1' ? '0' : '1';
$db->Execute("UPDATE " . TABLE_API_KEYS . " SET enabled = '$new_enabled' WHERE id = $kid");
$k['enabled'] = $new_enabled;

// Return the updated <tr> so htmx can swap it in place
?>
<tr id="api-key-row-<?php echo $kid; ?>">
  <td><code><?php echo htmlspecialchars($k['key_prefix']); ?>…</code></td>
  <td><?php echo htmlspecialchars($k['label']); ?></td>
  <td><small><?php echo htmlspecialchars($k['scopes']); ?></small></td>
  <td>
    <button type="button"
      class="btn btn-sm <?php echo $new_enabled === '1' ? 'btn-success' : 'btn-secondary'; ?>"
      hx-post="<?php echo html_href_link(FILENAME_DEFAULT, 'module=api&amp;page=ajax&amp;op=toggle_key', 'SSL'); ?>"
      hx-vals='{"key_id": "<?php echo $kid; ?>"}'
      hx-target="#api-key-row-<?php echo $kid; ?>"
      hx-swap="outerHTML">
      <?php echo $new_enabled === '1' ? API_BTN_REVOKE : API_BTN_ENABLE; ?>
    </button>
  </td>
  <td><?php echo htmlspecialchars($k['created']); ?></td>
  <td><?php echo $k['last_used'] ? htmlspecialchars($k['last_used']) : '<em>' . API_LBL_NEVER . '</em>'; ?></td>
  <td>
    <form method="post"
      action="<?php echo html_href_link(FILENAME_DEFAULT, 'module=api&amp;page=admin&amp;action=delete', 'SSL'); ?>"
      onsubmit="return confirm('<?php echo addslashes(API_CONFIRM_DELETE); ?>')"
      class="d-inline">
      <?php echo html_hidden_field('csrf_token', $_SESSION['csrf_token']); ?>
      <?php echo html_hidden_field('key_id', $kid); ?>
      <button type="submit" class="btn btn-danger btn-sm"><?php echo API_BTN_DELETE; ?></button>
    </form>
  </td>
</tr>
