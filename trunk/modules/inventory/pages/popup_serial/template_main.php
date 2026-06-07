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
//  Path: /modules/inventory/pages/popup_serial/template_main.php
//
if (!ENABLE_MULTI_CURRENCY) echo html_hidden_field('display_currency', DEFAULT_CURRENCY);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
echo $toolbar->build_toolbar();
?>
<div class="d-inline-flex flex-column gap-2">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo PAGE_TITLE; ?></div>
  <div class="card-body py-2">
    <div class="mb-1"><strong><?php echo TEXT_SKU; ?>:</strong> <?php echo htmlspecialchars($sku); ?></div>
    <div class="mb-2"><?php echo htmlspecialchars($description); ?></div>
    <?php echo html_pull_down_menu('selSerial', $snValues, $snDef, 'size="10" class="form-select" onChange="setReturnValue(' . $rowID . ')"'); ?>
  </div>
</div>
</div>
