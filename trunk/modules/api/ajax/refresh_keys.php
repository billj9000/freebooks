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
//  Path: /modules/api/ajax/refresh_keys.php
//
// Returns the <tbody> rows for the API key table (after key generation).
//
$security_level = validate_ajax_user(SECURITY_ID_CONFIGURATION);
if ($security_level < 1) { die; }

gen_pull_language('api');
require_once(DIR_FS_MODULES . 'api/defaults.php');

$company = db_input($_SESSION['company']);
$rows    = [];

$result = $db->Execute(
  "SELECT id, key_prefix, label, scopes, enabled, created, last_used
   FROM " . TABLE_API_KEYS . " WHERE company = '$company' ORDER BY created DESC"
);
while (!$result->EOF) {
  $rows[] = $result->fields;
  $result->MoveNext();
}

foreach ($rows as $k):
$kid = (int)$k['id'];
?>
<tr id="api-key-row-<?php echo $kid; ?>">
  <td><code><?php echo htmlspecialchars($k['key_prefix']); ?>…</code></td>
  <td><?php echo htmlspecialchars($k['label']); ?></td>
  <td><small><?php echo htmlspecialchars($k['scopes']); ?></small></td>
  <td>
    <button type="button"
      class="btn btn-sm <?php echo $k['enabled'] === '1' ? 'btn-success' : 'btn-secondary'; ?>"
      hx-post="<?php echo html_href_link(FILENAME_DEFAULT, 'module=api&amp;page=ajax&amp;op=toggle_key', 'SSL'); ?>"
      hx-vals='{"key_id": "<?php echo $kid; ?>"}'
      hx-target="#api-key-row-<?php echo $kid; ?>"
      hx-swap="outerHTML">
      <?php echo $k['enabled'] === '1' ? API_BTN_REVOKE : API_BTN_ENABLE; ?>
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
<?php endforeach; ?>
