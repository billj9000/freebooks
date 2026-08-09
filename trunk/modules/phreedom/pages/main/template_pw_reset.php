<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or  |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /modules/phreedom/pages/main/template_pw_reset.php
//
?>
<div class="d-flex justify-content-center mt-5">
<div class="card fb-card" style="max-width:420px;width:100%">
  <div class="card-header py-2 fb-widget-header text-end">
    <img src="modules/phreedom/images/freebooks_logo.png" alt="FreeBooks ERP" height="40" style="width:auto">
  </div>
  <div class="card-body py-3">
    <h5 class="mb-3"><?php echo TEXT_SET_NEW_PASSWORD; ?></h5>
<?php if (!empty($pw_reset_valid)) { ?>
    <form method="post" action="<?php echo html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=main&amp;action=pw_reset_save', 'SSL'); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($pw_reset_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="company" value="<?php echo htmlspecialchars($pw_reset_company ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <div style="display:grid; grid-template-columns:max-content 1fr; align-items:center; gap:0.4rem 0.75rem;">
        <label><?php echo TEXT_NEW_PASSWORD; ?></label>
        <input type="password" name="password_new" autocomplete="new-password" class="form-control form-control-sm">
        <label><?php echo TEXT_CONFIRM_PASSWORD; ?></label>
        <input type="password" name="password_conf" autocomplete="new-password" class="form-control form-control-sm">
      </div>
      <div class="text-end mt-3">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo TEXT_SET_NEW_PASSWORD; ?></button>
      </div>
    </form>
<?php } else { ?>
    <p class="mb-3"><?php echo ERROR_PW_RESET_INVALID; ?></p>
    <div class="text-end">
      <a class="btn btn-primary btn-sm" href="<?php echo html_href_link(FILENAME_DEFAULT, '', 'SSL'); ?>"><?php echo TEXT_LOGIN_BUTTON; ?></a>
    </div>
<?php } ?>
  </div>
</div>
</div>
