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
//  Path: /modules/phreeform/pages/admin/template_tab_tools.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'tools' ? ' show active' : ''); ?>" id="tab_tools" role="tabpanel">
  <div class="fb-fieldset mb-3">
    <div class="fb-fieldset-legend"><?php echo PHREEFORM_TOOLS_REBUILD_TITLE; ?></div>
    <p><?php echo PHREEFORM_TOOLS_REBUILD_DESC; ?></p>
    <p class="text-center"><?php echo PHREEFORM_TOOLS_REBUILD_SUBMIT; ?> <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('fix')"><?php echo TEXT_SUBMIT; ?></button></p>
  </div>

  <div class="fb-fieldset mb-3">
    <div class="fb-fieldset-legend"><?php echo PHREEFORM_TOOLS_RESTAMP_TITLE; ?></div>
    <p><?php echo sprintf(PHREEFORM_TOOLS_RESTAMP_DESC, PF_DEFAULT_PAPERSIZE); ?></p>
    <p class="text-center"><?php echo PHREEFORM_TOOLS_RESTAMP_SUBMIT; ?> <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo PHREEFORM_TOOLS_RESTAMP_CONFIRM; ?>')) submitToDo('restamp')"><?php echo TEXT_SUBMIT; ?></button></p>
  </div>
</div>
