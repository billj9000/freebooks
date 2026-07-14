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
//  Path: /modules/api/pages/admin/template_main.php
//
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href=\'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_help('11.API.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo API_PAGE_TITLE; ?></div>
  <div class="card-body p-0">
    <table class="table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo API_LBL_PREFIX; ?></th>
          <th><?php echo API_LBL_LABEL; ?></th>
          <th><?php echo API_LBL_SCOPES; ?></th>
          <th><?php echo API_LBL_ENABLED; ?></th>
          <th><?php echo API_LBL_CREATED; ?></th>
          <th><?php echo API_LBL_LAST_USED; ?></th>
          <th><?php echo API_LBL_ACTIONS; ?></th>
        </tr>
      </thead>
      <tbody id="api-key-table-body">
        <?php if (empty($api_keys)): ?>
        <tr><td colspan="7" class="p-3 text-muted"><?php echo API_NO_KEYS; ?></td></tr>
        <?php else: ?>
        <?php foreach ($api_keys as $k): ?>
        <tr id="api-key-row-<?php echo (int)$k['id']; ?>">
          <td><code><?php echo htmlspecialchars($k['key_prefix']); ?>…</code></td>
          <td><?php echo htmlspecialchars($k['label']); ?></td>
          <td><small><?php echo htmlspecialchars($k['scopes']); ?></small></td>
          <td>
            <button type="button"
              class="btn btn-sm <?php echo $k['enabled'] === '1' ? 'btn-success' : 'btn-secondary'; ?>"
              hx-post="<?php echo html_href_link(FILENAME_DEFAULT, 'module=api&amp;page=ajax&amp;op=toggle_key', 'SSL'); ?>"
              hx-vals='{"key_id": "<?php echo (int)$k['id']; ?>"}'
              hx-target="#api-key-row-<?php echo (int)$k['id']; ?>"
              hx-swap="outerHTML">
              <?php echo $k['enabled'] === '1' ? API_BTN_REVOKE : API_BTN_ENABLE; ?>
            </button>
          </td>
          <td><?php echo htmlspecialchars($k['created']); ?></td>
          <td><?php echo $k['last_used'] ? htmlspecialchars($k['last_used']) : '<em>' . API_LBL_NEVER . '</em>'; ?></td>
          <td>
            <?php if ($security_level >= 3): ?>
            <form method="post"
              action="<?php echo html_href_link(FILENAME_DEFAULT, 'module=api&amp;page=admin&amp;action=delete', 'SSL'); ?>"
              onsubmit="return confirm('<?php echo addslashes(API_CONFIRM_DELETE); ?>')"
              class="d-inline">
              <?php echo html_hidden_field('csrf_token', $_SESSION['csrf_token']); ?>
              <?php echo html_hidden_field('key_id', (int)$k['id']); ?>
              <button type="submit" class="btn btn-danger btn-sm"><?php echo API_BTN_DELETE; ?></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($security_level >= 3): ?>
  <div class="card-footer py-2">
    <button type="button" class="btn btn-primary btn-sm"
      data-bs-toggle="modal" data-bs-target="#generateKeyModal">
      <?php echo API_BTN_GENERATE; ?>
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- Generate Key Modal -->
<div class="modal fade" id="generateKeyModal" tabindex="-1" aria-labelledby="generateKeyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="generateKeyModalLabel"><?php echo API_BTN_GENERATE; ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="generate-key-form">
          <div class="mb-3">
            <label class="form-label"><?php echo API_LBL_GENERATE_HEADING; ?></label>
            <input type="text" id="new-key-label" name="new_key_label" class="form-control form-control-sm"
              placeholder="<?php echo htmlspecialchars(API_KEY_LABEL_PROMPT); ?>">
          </div>
        </div>
        <!-- htmx swaps this div with the generated key display -->
        <div id="generated-key-display"></div>
      </div>
      <div class="modal-footer" id="generate-key-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo API_BTN_CLOSE; ?></button>
        <button type="button" class="btn btn-primary btn-sm"
          hx-post="<?php echo html_href_link(FILENAME_DEFAULT, 'module=api&amp;page=ajax&amp;op=generate_key', 'SSL'); ?>"
          hx-include="#new-key-label"
          hx-target="#generated-key-display"
          hx-swap="innerHTML"
          hx-on::after-request="afterKeyGenerated(event)">
          <?php echo API_BTN_GENERATE; ?>
        </button>
      </div>
    </div>
  </div>
</div>
