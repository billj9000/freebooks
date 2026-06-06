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
//  Path: /modules/phreedom/pages/admin/template_tab_email.php
//
?>
<style>
.email-section th    { text-align:left; padding:5px 10px; font-size:1em; letter-spacing:0.03em; }
.email-row           { border-bottom:1px solid #ddd; }
.email-row.shaded    { background-color:#f5f7fa; }
.email-label         { width:40%; padding:8px 12px; vertical-align:top; }
.email-label strong  { display:block; margin-bottom:3px; font-size:1em; }
.email-label .hint   { font-size:0.88em; color:#555; line-height:1.4em; }
.email-field         { width:60%; padding:8px 12px; vertical-align:middle; }
</style>

<div class="tab-pane fade<?php echo ($active_tab === 'email' ? ' show active' : ''); ?>" id="tab_email" role="tabpanel">
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header d-flex justify-content-between align-items-center">
    <span><?php echo MENU_HEADING_EMAIL; ?></span>
    <a href="javascript:void(0)" style="font-size:0.85em"
       onclick="window.open('index.php?module=phreehelp&amp;page=main&amp;idx=04.06','help','width=800,height=600,resizable=1,scrollbars=1,top=100,left=100')">&#9432; Email help</a>
  </div>
  <div class="card-body p-0">
    <table class="email-section" style="border-style:none;width:100%">
      <tbody>

        <tr class="table-secondary"><th colspan="2">General</th></tr>

        <tr class="email-row">
          <td class="email-label">
            <strong>Mail transport method</strong>
            <span class="hint"><?php echo CD_12_01_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_pull_down_menu('email_transport', $sel_transport, $_POST['email_transport'] ?? EMAIL_TRANSPORT, 'id="email_transport" onchange="updateEmailTransport()"'); ?>
          </td>
        </tr>

        <tr class="email-row">
          <td class="email-label">
            <strong>Send email as HTML</strong>
            <span class="hint"><?php echo CD_12_04_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_pull_down_menu('email_use_html', $sel_yes_no, $_POST['email_use_html'] ?: EMAIL_USE_HTML, ''); ?>
          </td>
        </tr>

        <tr class="email-row shaded">
          <td class="email-label">
            <strong>Store owner email address</strong>
            <span class="hint"><?php echo CD_12_10_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('store_owner_email_address', $_POST['store_owner_email_address'] ?: STORE_OWNER_EMAIL_ADDRESS, ''); ?>
          </td>
        </tr>

        <tr class="email-row">
          <td class="email-label">
            <strong>Default from address</strong>
            <span class="hint"><?php echo CD_12_11_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_from', $_POST['email_from'] ?: EMAIL_FROM, ''); ?>
          </td>
        </tr>

        <tr class="email-row shaded">
          <td class="email-label">
            <strong>Admin email format</strong>
            <span class="hint"><?php echo CD_12_15_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_pull_down_menu('admin_extra_email_format', $sel_format, $_POST['admin_extra_email_format'] ?: ADMIN_EXTRA_EMAIL_FORMAT, ''); ?>
          </td>
        </tr>

        <tr class="table-secondary" id="smtp_header"><th colspan="2">SMTP Server</th></tr>

        <tr class="email-row" id="smtp_row_mailbox">
          <td class="email-label">
            <strong>SMTP username</strong>
            <span class="hint"><?php echo CD_12_70_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_smtpauth_mailbox', $_POST['email_smtpauth_mailbox'] ?: EMAIL_SMTPAUTH_MAILBOX, ''); ?>
          </td>
        </tr>

        <tr class="email-row shaded" id="smtp_row_password">
          <td class="email-label">
            <strong>SMTP password</strong>
            <span class="hint"><?php echo CD_12_71_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_smtpauth_password', $_POST['email_smtpauth_password'] ?: EMAIL_SMTPAUTH_PASSWORD, '', false, 'password'); ?>
          </td>
        </tr>

        <tr class="email-row" id="smtp_row_server">
          <td class="email-label">
            <strong>SMTP server</strong>
            <span class="hint"><?php echo CD_12_72_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_smtpauth_mail_server', $_POST['email_smtpauth_mail_server'] ?: EMAIL_SMTPAUTH_MAIL_SERVER, ''); ?>
          </td>
        </tr>

        <tr class="email-row shaded" id="smtp_row_port">
          <td class="email-label">
            <strong>SMTP port</strong>
            <span class="hint"><?php echo CD_12_73_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_smtpauth_mail_server_port', $_POST['email_smtpauth_mail_server_port'] ?: EMAIL_SMTPAUTH_MAIL_SERVER_PORT, 'size="6"'); ?>
          </td>
        </tr>

        <tr class="email-row" id="smtp_row_encryption">
          <td class="email-label">
            <strong>Encryption</strong>
            <span class="hint"><?php echo CD_12_75_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_pull_down_menu('email_smtpauth_tls', $sel_smtp_encryption, $_POST['email_smtpauth_tls'] ?: EMAIL_SMTPAUTH_TLS, ''); ?>
          </td>
        </tr>

        <tr class="email-row shaded" id="smtp_row_self_signed">
          <td class="email-label">
            <strong>Allow self-signed certificates</strong>
            <span class="hint"><?php echo CD_12_76_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_pull_down_menu('email_smtp_allow_self_signed', $sel_yes_no, $_POST['email_smtp_allow_self_signed'] ?: (defined('EMAIL_SMTP_ALLOW_SELF_SIGNED') ? EMAIL_SMTP_ALLOW_SELF_SIGNED : '0'), ''); ?>
          </td>
        </tr>

        <tr class="email-row" id="smtp_row_test">
          <td class="email-label">
            <strong>Test connection</strong>
            <span class="hint">Verify the SMTP settings above without saving. Uses the values currently entered in the form.</span>
          </td>
          <td class="email-field">
            <button type="button" id="smtp_test_btn" onclick="testSmtpConnection()">Test SMTP Connection</button>
            <div id="smtp_test_result" style="margin-top:8px;display:none;padding:8px 10px;border-radius:3px;font-size:0.9em;line-height:1.5em;"></div>
          </td>
        </tr>

        <tr class="table-secondary" id="oauth2_header"><th colspan="2">OAuth2</th></tr>

        <tr class="email-row" id="oauth2_row_provider">
          <td class="email-label">
            <strong>Provider</strong>
            <span class="hint"><?php echo CD_OAUTH2_PROVIDER_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_pull_down_menu('email_oauth2_provider', $sel_oauth2_provider, $_POST['email_oauth2_provider'] ?? (defined('EMAIL_OAUTH2_PROVIDER') ? EMAIL_OAUTH2_PROVIDER : 'google'), 'id="email_oauth2_provider"'); ?>
          </td>
        </tr>

        <tr class="email-row shaded" id="oauth2_row_client_id">
          <td class="email-label">
            <strong>Client ID</strong>
            <span class="hint"><?php echo CD_OAUTH2_CLIENT_ID_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_oauth2_client_id', $_POST['email_oauth2_client_id'] ?? (defined('EMAIL_OAUTH2_CLIENT_ID') ? EMAIL_OAUTH2_CLIENT_ID : ''), ''); ?>
          </td>
        </tr>

        <tr class="email-row" id="oauth2_row_client_secret">
          <td class="email-label">
            <strong>Client secret</strong>
            <span class="hint"><?php echo CD_OAUTH2_CLIENT_SECRET_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_oauth2_client_secret', $_POST['email_oauth2_client_secret'] ?? (defined('EMAIL_OAUTH2_CLIENT_SECRET') ? EMAIL_OAUTH2_CLIENT_SECRET : ''), '', false, 'password'); ?>
          </td>
        </tr>

        <tr class="email-row shaded" id="oauth2_row_tenant">
          <td class="email-label">
            <strong>Tenant (Microsoft only)</strong>
            <span class="hint"><?php echo CD_OAUTH2_TENANT_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_oauth2_tenant', $_POST['email_oauth2_tenant'] ?? (defined('EMAIL_OAUTH2_TENANT') ? EMAIL_OAUTH2_TENANT : 'common'), ''); ?>
          </td>
        </tr>

        <tr class="email-row" id="oauth2_row_email">
          <td class="email-label">
            <strong>Authorised email address</strong>
            <span class="hint"><?php echo CD_OAUTH2_EMAIL_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('email_oauth2_email', $_POST['email_oauth2_email'] ?? (defined('EMAIL_OAUTH2_EMAIL') ? EMAIL_OAUTH2_EMAIL : ''), ''); ?>
          </td>
        </tr>

        <tr class="email-row shaded" id="oauth2_row_refresh_token">
          <td class="email-label">
            <strong>Refresh token</strong>
            <span class="hint"><?php echo CD_OAUTH2_REFRESH_TOKEN_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php
              $has_token = defined('EMAIL_OAUTH2_REFRESH_TOKEN') && gen_not_null(EMAIL_OAUTH2_REFRESH_TOKEN);
              if ($has_token) {
                echo '<span style="color:#3c763d">' . CD_OAUTH2_AUTHORIZED . '</span> &nbsp;';
              } else {
                echo '<span style="color:#a94442">' . CD_OAUTH2_NOT_AUTHORIZED . '</span> &nbsp;';
              }
            ?>
            <input type="hidden" name="email_oauth2_refresh_token" id="email_oauth2_refresh_token"
              value="<?php echo htmlspecialchars(defined('EMAIL_OAUTH2_REFRESH_TOKEN') ? EMAIL_OAUTH2_REFRESH_TOKEN : ''); ?>" />
          </td>
        </tr>

        <tr class="email-row" id="oauth2_row_authorize">
          <td class="email-label">
            <strong><?php echo CD_OAUTH2_AUTHORIZE; ?></strong>
            <span class="hint"><?php echo CD_OAUTH2_AUTHORIZE_HINT; ?>
              &nbsp;<a href="javascript:void(0)" onclick="window.open('index.php?module=phreehelp&amp;page=main&amp;idx=04.06.01','help','width=800,height=600,resizable=1,scrollbars=1,top=100,left=100')">Setup guide</a>
            </span>
          </td>
          <td class="email-field">
            <button type="button" id="oauth2_auth_btn" onclick="startOAuth2()">Authorise with Provider</button>
            <div id="oauth2_auth_result" style="margin-top:8px;display:none;padding:8px 10px;border-radius:3px;font-size:0.9em"></div>
          </td>
        </tr>

        <tr class="table-secondary"><th colspan="2">Other</th></tr>

        <tr class="email-row">
          <td class="email-label">
            <strong>Currency translations</strong>
            <span class="hint"><?php echo CD_12_74_DESC; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('currencies_translations', $_POST['currencies_translations'] ?: CURRENCIES_TRANSLATIONS, ''); ?>
          </td>
        </tr>

        <tr class="email-row shaded">
          <td class="email-label">
            <strong>Server address (HELO)</strong>
            <span class="hint"><?php echo SETUP_SERVER_ADDRESS; ?></span>
          </td>
          <td class="email-field">
            <?php echo html_input_field('server_address', $_POST['server_address'] ?: SERVER_ADDRESS, ''); ?>
          </td>
        </tr>

      </tbody>
    </table>
  </div>
</div>
</div>
