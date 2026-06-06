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
//  Path: /modules/phreedom/pages/admin/template_tab_tools.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'tools' ? ' show active' : ''); ?>" id="tab_tools" role="tabpanel">

  <div class="fb-fieldset mb-3">
    <div class="fb-fieldset-legend"><?php echo GEN_ADM_TOOLS_SEQ_HEADING; ?></div>
    <p><?php echo GEN_ADM_TOOLS_SEQ_DESC; ?></p>
    <table class="table table-borderless table-sm mb-2" style="width:auto">
      <tbody>
<?php
  foreach ($status_fields as $field_name) {
    $desc = strtoupper($field_name) . '_DESC';
    echo '        <tr>' . chr(10);
    echo '          <td>' . (defined($desc) ? constant($desc) : $field_name) . '</td>' . chr(10);
    echo '          <td>' . html_input_field($field_name, $status_values->fields[$field_name]) . '</td>' . chr(10);
    echo '        </tr>' . chr(10);
  }
?>
        <tr>
          <td colspan="2" class="text-end">
            <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('ordr_nums')"><?php echo GEN_ADM_TOOLS_BTN_SAVE; ?></button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="fb-fieldset mb-2">
    <div class="fb-fieldset-legend"><?php echo GEN_ADM_TOOLS_SECURITY_HEADING; ?></div>
    <p><?php echo GEN_ADM_TOOLS_SECURITY_DESC; ?></p>
    <div class="text-center">
      <?php echo TEXT_CLEAN_BEFORE . ' ' . html_calendar_field($cal_clean); ?>
      <button type="button" class="btn btn-primary btn-sm ms-2" onclick="submitToDo('clean_security')"><?php echo GEN_ADM_TOOLS_BTN_CLEAN; ?></button>
    </div>
  </div>

</div>
