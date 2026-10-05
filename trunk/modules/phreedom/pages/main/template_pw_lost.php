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
//  Path: /modules/phreedom/pages/main/template_pw_lost.php
//
echo html_form('pw_lost', FILENAME_DEFAULT, 'module=phreedom&amp;page=main&amp;action=lost_pw&amp;req=pw_lost_sub') . chr(10);
?>
<div class="d-flex justify-content-center mt-5">
<div class="card fb-card" style="max-width:420px;width:100%">
  <div class="card-header py-2 fb-widget-header text-end">
    <img src="modules/phreedom/images/freebooks_logo.png" alt="FreeBooks ERP" height="40" style="width:auto">
  </div>
  <div class="card-body py-3">
    <h5 class="mb-3"><?php echo TEXT_PASSWORD_FORGOTTEN; ?></h5>
    <div style="display:grid; grid-template-columns:max-content 1fr; align-items:center; gap:0.4rem 0.75rem;">
      <label><?php echo TEXT_ADMIN_EMAIL; ?></label>
      <?php echo html_input_field('admin_email', $_POST['admin_email'] ?? '', 'class="form-control form-control-sm"'); ?>
      <label><?php echo TEXT_LOGIN_COMPANY; ?></label>
      <?php echo html_pull_down_menu('company', load_company_dropdown(), $admin_company ?? '', 'class="form-select form-select-sm"'); ?>
    </div>
    <div class="text-end mt-3">
      <?php echo html_submit_field('submit', TEXT_PASSWORD_FORGOTTEN, 'class="btn btn-primary btn-sm"'); ?>
    </div>
  </div>
</div>
</div>
</form>
