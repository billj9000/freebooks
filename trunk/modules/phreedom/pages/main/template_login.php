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
//  Path: /modules/phreedom/pages/main/template_login.php
//
$_login_logo = defined('FOOTER_LOGO') ? FOOTER_LOGO : (DIR_WS_ADMIN . 'modules/phreedom/images/freebooks_logo.png');
?>
<style>
  body { background-color: #e8f4fb; }
  .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
</style>

<div class="login-wrap">
  <div style="width:100%;max-width:360px">

    <!-- Logo -->
    <div class="text-center mb-3">
      <img src="<?php echo $_login_logo; ?>" alt="FreeBooks" style="height:80px;width:auto">
    </div>

    <!-- Card -->
    <div class="card">
      <div class="card-header py-2 fb-widget-header text-center" style="font-size:13px">
        <?php echo TEXT_LOGIN_BUTTON; ?>
      </div>
      <div class="card-body p-3">

        <?php echo html_form('login', FILENAME_DEFAULT, 'module=phreedom&amp;page=main&amp;action=validate', 'post', 'onsubmit="return submit_wait();"'); ?>
        <?php if ($single_company)  echo html_hidden_field('company',  $companies[0]['id']); ?>
        <?php if ($single_language) echo html_hidden_field('language', $languages[0]['id']); ?>

        <?php $msgs = $messageStack->output(); if ($msgs) echo '<div class="mb-2">' . $msgs . '</div>'; ?>

        <div class="mb-2">
          <label class="form-label fw-bold" for="admin_name"><?php echo TEXT_LOGIN_NAME; ?></label>
          <input type="text" class="form-control form-control-sm" id="admin_name" name="admin_name"
                 value="<?php echo htmlspecialchars($_POST['admin_name'] ?? '', ENT_QUOTES); ?>" autocomplete="username webauthn" autofocus>
        </div>

        <div class="mb-2">
          <label class="form-label fw-bold" for="admin_pass"><?php echo TEXT_LOGIN_PASS; ?></label>
          <input type="password" class="form-control form-control-sm" id="admin_pass" name="admin_pass" autocomplete="current-password">
        </div>

        <?php if (!$single_company): ?>
        <div class="mb-2">
          <label class="form-label fw-bold"><?php echo TEXT_LOGIN_COMPANY; ?></label>
          <?php echo html_pull_down_menu('company', $companies, $company_index, 'class="form-select form-select-sm"'); ?>
        </div>
        <?php endif; ?>

        <?php if (!$single_language): ?>
        <div class="mb-2">
          <label class="form-label fw-bold"><?php echo TEXT_LOGIN_LANGUAGE; ?></label>
          <?php echo html_pull_down_menu('language', $languages, $language_index, 'class="form-select form-select-sm"'); ?>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <a href="<?php echo html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=main&amp;req=pw_lost_req', 'SSL'); ?>"
             style="font-size:11px"><?php echo TEXT_PASSWORD_FORGOTTEN; ?></a>
          <button type="submit" class="btn btn-primary btn-sm"><?php echo TEXT_LOGIN_BUTTON; ?></button>
        </div>

        <div id="wait_msg" style="display:none" class="text-center mt-2 text-muted" style="font-size:11px">
          <?php echo TEXT_FORM_PLEASE_WAIT; ?>
        </div>

        </form>

        <?php if (wa_ssl_ok()): ?>
        <div class="d-grid mt-2">
          <button type="button" id="pk-login-btn" class="btn btn-outline-primary btn-sm">
            <?php echo TEXT_PASSKEY_LOGIN_BUTTON; ?>
          </button>
        </div>
        <div id="pk-login-msg" class="text-center mt-2" style="font-size:11px"></div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-3" style="font-size:9px;color:#888">
      <?php echo TEXT_COPYRIGHT; ?> &copy; <?php echo date('Y'); ?> Bill Jones<br>
      Portions &copy; 2008&ndash;2014 PhreeSoft, LLC
    </div>

  </div>
</div>
