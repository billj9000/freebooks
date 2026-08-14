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
//  Path: /modules/phreedom/pages/admin/template_tab_manager.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'manager' ? ' show active' : ''); ?>" id="tab_manager" role="tabpanel">

  <div class="fb-fieldset mb-3">
    <div class="fb-fieldset-legend"><?php echo SETUP_CO_MGR_COPY_CO; ?></div>
    <table class="table table-borderless table-sm mb-2">
      <tbody>
        <tr>
          <td colspan="2" class="text-end">
            <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('copy_co')"><?php echo TEXT_COPY; ?></button>
          </td>
        </tr>
        <tr>
          <td colspan="2"><?php echo SETUP_CO_MGR_COPY_HDR; ?></td>
        </tr>
        <tr>
          <td><?php echo SETUP_CO_MGR_SRVR_NAME; ?></td>
          <td><?php echo html_input_field('db_server', $db_server ? $db_server : 'localhost', 'size="40"', true); ?></td>
        </tr>
        <tr>
          <td><?php echo SETUP_CO_MGR_DB_NAME; ?></td>
          <td><?php echo html_input_field('db_name', $db_name, '', true); ?></td>
        </tr>
        <tr>
          <td><?php echo SETUP_CO_MGR_DB_USER; ?></td>
          <td><?php echo html_input_field('db_user', $db_user, '', true); ?></td>
        </tr>
        <tr>
          <td><?php echo SETUP_CO_MGR_DB_PW; ?></td>
          <td><?php echo html_password_field('db_pw', '', true); ?></td>
        </tr>
        <tr>
          <td><?php echo SETUP_CO_MGR_CO_NAME; ?></td>
          <td><?php echo html_input_field('co_name', $co_name, 'size="50"', true); ?></td>
        </tr>
<?php if (!empty($PaperSizes)) { // phreeform supplies the list; skip the row if it is not installed ?>
        <tr>
          <td><?php echo SETUP_CO_MGR_PAPER_SIZE; ?></td>
          <td><?php echo html_pull_down_menu('papersize', gen_build_pull_down($PaperSizes), $_POST['papersize'] ?? (defined('PF_DEFAULT_PAPERSIZE') ? PF_DEFAULT_PAPERSIZE : 'A4:210:297'), 'class="form-select form-select-sm"'); ?></td>
        </tr>
<?php } ?>
      </tbody>
    </table>
    <p class="mb-1 fw-bold"><?php echo SETUP_CO_MGR_MOD_SELECT; ?></p>
    <table class="table table-borderless table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th style="width:40%"><?php echo TEXT_MODULE;    ?></th>
          <th style="width:20%"><?php echo TEXT_DEF_DATA;  ?></th>
          <th style="width:20%"><?php echo TEXT_DEMO_DATA; ?></th>
          <th style="width:20%"><?php echo TEXT_ALL_DATA;  ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($loaded_modules as $mod) { // load modules and query for copy
          if ($mod == 'phreedom') continue;
        ?>
        <tr>
          <td><?php echo html_checkbox_field($mod, '1', (!in_array($mod, $core_modules) && $error && !isset($_POST[$mod])) ? false : true, '', in_array($mod, $core_modules) ? 'disabled="disabled"' : '') . '&nbsp;' . TEXT_MODULE . ': ' . $mod; ?></td>
          <td class="text-center"><?php echo html_radio_field($mod . '_action', 'core', $_POST[$mod.'_action']=='core' ? true : false, '', $parameters = ''); ?></td>
          <td class="text-center"><?php echo html_radio_field($mod . '_action', 'demo', $_POST[$mod.'_action']=='demo' ? true : false, '', $parameters = ''); ?></td>
          <td class="text-center"><?php echo html_radio_field($mod . '_action', 'data', (!isset($_POST[$mod.'_action'])||$_POST[$mod.'_action']=='data') ? true : false, '', $parameters = ''); ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

  <div class="fb-fieldset mb-2">
    <div class="fb-fieldset-legend"><?php echo SETUP_CO_MGR_DEL_CO; ?></div>
    <table class="table table-borderless table-sm mb-0">
      <tbody>
        <tr>
          <td colspan="3" style="color:red"><?php echo SETUP_CO_MGR_DELETE_CONFIRM; ?></td>
        </tr>
        <tr>
          <td><?php echo SETUP_CO_MGR_SELECT_DELETE . html_pull_down_menu('del_company', load_company_dropdown(true)); ?></td>
          <td class="text-end">
            <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo SETUP_CO_MGR_JS_DELETE_CONFIRM; ?>')) submitToDo('delete_co')"><?php echo TEXT_DELETE; ?></button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

</div>
