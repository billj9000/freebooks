<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2013 PhreeSoft                       |
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
//  Path: /modules/phreedom/pages/admin/template_tab_php_info.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'php_info' ? ' show active' : ''); ?>" id="tab_php_info" role="tabpanel">
  <div class="fb-fieldset mt-2 mb-2">
    <div class="fb-fieldset-legend">PHP Info</div>
    <iframe src="<?php echo html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=main&amp;action=php_info', 'SSL'); ?>" style="border-style:none;width:100%;height:400px;"></iframe>
  </div>
</div>
