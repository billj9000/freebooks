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
//  Path: /modules/inventory/pages/admin/template_tab_tools.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'tools' ? ' show active' : ''); ?>" id="tab_tools" role="tabpanel">
  <div class="card fb-card mt-2 mb-2">
    <div class="card-header py-1 fb-widget-header"><?php echo INV_TOOLS_VALIDATE_INVENTORY; ?></div>
    <div class="card-body py-2 px-3" style="font-size:11px">
      <p class="mb-2"><?php echo INV_TOOLS_VALIDATE_INV_DESC; ?></p>
      <div class="d-flex gap-2">
        <div>
          <div class="mb-1 fw-bold"><?php echo INV_TOOLS_REPAIR_TEST; ?></div>
          <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('inv_hist_test')"><?php echo INV_TOOLS_BTN_TEST; ?></button>
        </div>
        <div>
          <div class="mb-1 fw-bold"><?php echo INV_TOOLS_REPAIR_FIX; ?></div>
          <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo INV_TOOLS_REPAIR_CONFIRM; ?>')) submitToDo('inv_hist_fix')"><?php echo INV_TOOLS_BTN_REPAIR; ?></button>
        </div>
      </div>
    </div>
  </div>
  <div class="card fb-card mb-2">
    <div class="card-header py-1 fb-widget-header"><?php echo INV_TOOLS_VALIDATE_SO_PO; ?></div>
    <div class="card-body py-2 px-3" style="font-size:11px">
      <p class="mb-2"><?php echo INV_TOOLS_VALIDATE_SO_PO_DESC; ?></p>
      <div>
        <div class="mb-1 fw-bold"><?php echo INV_TOOLS_REPAIR_SO_PO; ?></div>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('inv_on_order_fix')"><?php echo INV_TOOLS_BTN_SO_PO_FIX; ?></button>
      </div>
    </div>
  </div>
</div>
