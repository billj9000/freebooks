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
//  Path: /modules/xero_sync/pages/callback/template_main.php
//
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . $settings_url . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->add_help('10.XERO.01');
echo $toolbar->build_toolbar();
echo html_form('xero_tenant', FILENAME_DEFAULT, 'module=xero_sync&amp;page=callback&amp;action=tenant') . chr(10);
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="card fb-card mb-3">
  <div class="card-body">
    <p><?php echo htmlspecialchars(XERO_SYNC_PICK_TENANT); ?></p>
    <?php foreach ($tenants as $i => $tenant): ?>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="tenant_id" id="xs_tenant_<?php echo $i; ?>"
        value="<?php echo htmlspecialchars((string)$tenant['tenantId']); ?>"<?php echo $i ? '' : ' checked'; ?>>
      <label class="form-check-label" for="xs_tenant_<?php echo $i; ?>"><?php echo htmlspecialchars((string)($tenant['tenantName'] ?? $tenant['tenantId'])); ?></label>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary btn-sm mt-2"><?php echo XERO_SYNC_BTN_USE_TENANT; ?></button>
  </div>
</div>
</form>
