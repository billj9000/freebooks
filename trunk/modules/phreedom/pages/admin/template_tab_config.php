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
//  Path: /modules/phreedom/pages/admin/template_tab_config.php
//
?>
<style>
#tab_config table td:first-child  { width: 78%; }
#tab_config table td:last-child   { text-align: right; }
#tab_config table input,
#tab_config table select          { width: 100px; }
</style>
<div class="tab-pane fade<?php echo ($active_tab === 'config' ? ' show active' : ''); ?>" id="tab_config" role="tabpanel">

<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header text-center"><?php echo MENU_HEADING_CONFIG; ?></div>
  <div class="card-body px-3 py-0">
    <table class="table table-borderless table-sm mb-0">
      <tbody>
        <tr>
          <td><?php echo CD_00_01_DESC; ?></td>
          <td><?php echo html_input_field('date_format', !empty($_POST['date_format']) ? $_POST['date_format'] : DATE_FORMAT, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_00_02_DESC; ?></td>
          <td><?php echo html_input_field('date_delimiter', !empty($_POST['date_delimiter']) ? $_POST['date_delimiter'] : DATE_DELIMITER, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_00_03_DESC; ?></td>
          <td><?php echo html_input_field('date_time_format', !empty($_POST['date_time_format']) ? $_POST['date_time_format'] : DATE_TIME_FORMAT, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_01_30_DESC; ?></td>
          <td><?php echo html_pull_down_menu('enable_encryption', $sel_yes_no, !empty($_POST['enable_encryption']) ? $_POST['enable_encryption'] : ENABLE_ENCRYPTION, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_07_17_DESC; ?></td>
          <td><?php echo html_input_field('entry_password_min_length', !empty($_POST['entry_password_min_length']) ? $_POST['entry_password_min_length'] : ENTRY_PASSWORD_MIN_LENGTH, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_08_01_DESC; ?></td>
          <td><?php echo html_input_field('max_display_search_results', !empty($_POST['max_display_search_results']) ? $_POST['max_display_search_results'] : MAX_DISPLAY_SEARCH_RESULTS, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_08_10_DESC; ?></td>
          <td><?php echo html_input_field('limit_history_results', !empty($_POST['limit_history_results']) ? $_POST['limit_history_results'] : LIMIT_HISTORY_RESULTS, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_08_05_DESC; ?></td>
          <td><?php echo html_pull_down_menu('hide_success_messages', $sel_yes_no, !empty($_POST['hide_success_messages']) ? $_POST['hide_success_messages'] : HIDE_SUCCESS_MESSAGES, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_15_01_DESC; ?></td>
          <td><?php echo html_input_field('session_timeout_admin', !empty($_POST['session_timeout_admin']) ? $_POST['session_timeout_admin'] : SESSION_TIMEOUT_ADMIN, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_16_01_DESC; ?></td>
          <td><?php echo html_pull_down_menu('webauthn_enabled', $sel_yes_no, !empty($_POST['webauthn_enabled']) ? $_POST['webauthn_enabled'] : WEBAUTHN_ENABLED, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_16_02_DESC; ?></td>
          <td><?php echo html_input_field('webauthn_rp_id', !empty($_POST['webauthn_rp_id']) ? $_POST['webauthn_rp_id'] : WEBAUTHN_RP_ID, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_15_05_DESC; ?></td>
          <td><?php echo html_pull_down_menu('session_auto_refresh', $sel_yes_no, !empty($_POST['session_auto_refresh']) ? $_POST['session_auto_refresh'] : SESSION_AUTO_REFRESH, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_09_01_DESC; ?></td>
          <td><?php echo html_pull_down_menu('ie_rw_export_preference', $sel_ie_method, !empty($_POST['ie_rw_export_preference']) ? $_POST['ie_rw_export_preference'] : IE_RW_EXPORT_PREFERENCE, ''); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header text-center"><?php echo TEXT_OPTIONS; ?></div>
  <div class="card-body px-3 py-0">
    <table class="table table-borderless table-sm mb-0">
      <tbody>
        <tr>
          <td><?php echo CD_01_18_DESC; ?></td>
          <td><?php echo html_pull_down_menu('enable_multi_branch', $sel_yes_no, !empty($_POST['enable_multi_branch']) ? $_POST['enable_multi_branch'] : ENABLE_MULTI_BRANCH, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_01_19_DESC; ?></td>
          <td><?php echo html_pull_down_menu('enable_multi_currency', $sel_yes_no, !empty($_POST['enable_multi_currency']) ? $_POST['enable_multi_currency'] : ENABLE_MULTI_CURRENCY, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_08_07_DESC; ?></td>
          <td><?php echo html_pull_down_menu('auto_update_currency', $sel_yes_no, !empty($_POST['auto_update_currency']) ? $_POST['auto_update_currency'] : AUTO_UPDATE_CURRENCY, ''); ?></td>
        </tr>
        <tr>
          <td><?php echo CD_08_03_DESC; ?></td>
          <td><?php echo html_pull_down_menu('cfg_auto_update_check', $sel_yes_no, !empty($_POST['cfg_auto_update_check']) ? $_POST['cfg_auto_update_check'] : CFG_AUTO_UPDATE_CHECK, ''); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header text-center"><?php echo TEXT_DEBUG; ?></div>
  <div class="card-body px-3 py-0">
    <table class="table table-borderless table-sm mb-0">
      <tbody>
        <tr>
          <td><?php echo CD_20_99_DESC; ?></td>
          <td><?php echo html_pull_down_menu('debug', $sel_yes_no, !empty($_POST['debug']) ? $_POST['debug'] : DEBUG, ''); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

</div>
