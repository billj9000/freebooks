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
//  Path: /modules/phreebooks/pages/admin_tools/template_main.php
//
echo html_form('admin_tools', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo GEN_ADM_TOOLS_TITLE; ?></h1>
<div class="fb-fieldset mb-2">
  <div class="fb-fieldset-legend"><?php echo GL_UTIL_PERIOD_LEGEND; ?></div>
  <div class="d-flex gap-4 align-items-start">
    <div>
      <p class="mb-1"><?php echo GL_CURRENT_PERIOD . $period; ?></p>
      <p class="mb-0"><?php echo GL_UTIL_FISCAL_YEAR_TEXT; ?></p>
    </div>
    <div>
      <div class="mb-1"><?php echo GL_FISCAL_YEAR; ?> <?php echo html_pull_down_menu('fy', get_fiscal_year_pulldown(), $fy, 'onchange="submit()"'); ?></div>
      <table id="item_table" class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_PERIOD; ?></th>
            <th><?php echo TEXT_START_DATE; ?></th>
            <th><?php echo TEXT_END_DATE; ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 0;
          foreach ($fy_array as $key => $value) {
            echo '<tr><td class="text-center">' . $key . html_hidden_field('per_' . $i, $key) . '</td>' . chr(10);
            if ($key > $max_period) {
              echo '<td nowrap="nowrap">' . html_calendar_field($cal_start[$i]) . '</td>' . chr(10);
              echo '<td nowrap="nowrap">' . html_calendar_field($cal_end[$i]) . '</td>' . chr(10);
            } else {
              echo '<td class="text-center" nowrap="nowrap">' . html_input_field('start_' . $i, gen_locale_date($value['start']), 'readonly="readonly"', false, 'text', false) . '</td>' . chr(10);
              echo '<td class="text-center" nowrap="nowrap">' . html_input_field('end_' . $i, gen_locale_date($value['end']), 'readonly="readonly"', false, 'text', false) . '</td>' . chr(10);
            }
            echo '</tr>' . chr(10);
            $i++;
          }
          ?>
        </tbody>
      </table>
    </div>
    <div class="d-flex flex-column gap-2">
      <?php echo html_hidden_field('period', ''); ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="if (fetchPeriod()) submitToDo('change')"><?php echo GL_BTN_CHG_ACCT_PERIOD; ?></button>
      <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('update')"><?php echo GL_BTN_UPDATE_FY; ?></button>
      <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo GL_WARN_ADD_FISCAL_YEAR . ($highest_fy + 1); ?>')) submitToDo('new')"><?php echo GL_BTN_NEW_FY; ?></button>
    </div>
  </div>
</div>
<div class="fb-fieldset mb-2">
  <div class="fb-fieldset-legend"><?php echo GEN_ADM_TOOLS_REPOST_HEADING; ?></div>
  <p class="mb-2"><?php echo GEN_ADM_TOOLS_REPOST_DESC; ?></p>
  <table class="table table-borderless table-sm mb-2">
    <tbody>
      <tr>
        <th colspan="2"><?php echo GEN_ADM_TOOLS_AR; ?></th>
        <th colspan="2"><?php echo GEN_ADM_TOOLS_AP; ?></th>
        <th colspan="2"><?php echo GEN_ADM_TOOLS_BNK_ETC; ?></th>
        <th colspan="2"><?php echo GEN_ADM_TOOLS_DATE_RANGE; ?></th>
      </tr>
      <tr>
        <td><?php echo html_checkbox_field('jID_9', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J09; ?></td>
        <td><?php echo html_checkbox_field('jID_3', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J03; ?></td>
        <td><?php echo html_checkbox_field('jID_2', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J02; ?></td>
        <td colspan="2"><?php echo GEN_ADM_TOOLS_START_DATE; ?></td>
      </tr>
      <tr>
        <td><?php echo html_checkbox_field('jID_10', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J10; ?></td>
        <td><?php echo html_checkbox_field('jID_4', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J04; ?></td>
        <td><?php echo html_checkbox_field('jID_8', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J08; ?></td>
        <td colspan="2"><?php echo html_calendar_field($cal_repost_start); ?></td>
      </tr>
      <tr>
        <td><?php echo html_checkbox_field('jID_12', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J12; ?></td>
        <td><?php echo html_checkbox_field('jID_6', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J06; ?></td>
        <td><?php echo html_checkbox_field('jID_14', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J14; ?></td>
        <td colspan="2"><?php echo GEN_ADM_TOOLS_END_DATE; ?></td>
      </tr>
      <tr>
        <td><?php echo html_checkbox_field('jID_13', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J13; ?></td>
        <td><?php echo html_checkbox_field('jID_7', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J07; ?></td>
        <td><?php echo html_checkbox_field('jID_16', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J16; ?></td>
        <td colspan="2"><?php echo html_calendar_field($cal_repost_end); ?></td>
      </tr>
      <tr>
        <td><?php echo html_checkbox_field('jID_19', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J19; ?></td>
        <td><?php echo html_checkbox_field('jID_21', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J21; ?></td>
        <td><?php echo html_checkbox_field('jID_18', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J18; ?></td>
      </tr>
      <tr>
        <td colspan="2">&nbsp;</td>
        <td colspan="2">&nbsp;</td>
        <td><?php echo html_checkbox_field('jID_20', '1', false); ?></td>
        <td><?php echo GEN_ADM_TOOLS_J20; ?></td>
        <td colspan="2" class="text-end">
          <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo GEN_ADM_TOOLS_REPOST_CONFIRM; ?>')) submitToDo('repost')"><?php echo GEN_ADM_TOOLS_BTN_REPOST; ?></button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
<div class="fb-fieldset mb-2">
  <div class="fb-fieldset-legend"><?php echo GEN_ADM_TOOLS_INVENTORY_OWED; ?></div>
  <p class="mb-2"><?php echo sprintf(GEN_ADM_TOOLS_INVENTORY_DESC, $cogs_owed); ?></p>
  <div class="text-center">
    <div class="fw-bold mb-1"><?php echo GEN_ADM_TOOLS_OWED_FIX; ?></div>
    <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo GEN_ADM_TOOLS_OWED_CONFIRM; ?>')) submitToDo('inv_owed_fix')"><?php echo GEN_ADM_TOOLS_BTN_OWED; ?></button>
  </div>
</div>
<div class="fb-fieldset mb-2">
  <div class="fb-fieldset-legend"><?php echo GEN_ADM_TOOLS_REPAIR_CHART_HISTORY; ?></div>
  <p class="mb-2"><?php echo GEN_ADM_TOOLS_REPAIR_CHART_DESC; ?></p>
  <div class="d-flex gap-4">
    <div>
      <div class="mb-1"><?php echo GEN_ADM_TOOLS_REPAIR_TEST; ?></div>
      <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('coa_hist_test')"><?php echo GEN_ADM_TOOLS_BTN_TEST; ?></button>
    </div>
    <div>
      <div class="mb-1"><?php echo GEN_ADM_TOOLS_REPAIR_FIX; ?></div>
      <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo GEN_ADM_TOOLS_REPAIR_CONFIRM; ?>')) submitToDo('coa_hist_fix')"><?php echo GEN_ADM_TOOLS_BTN_REPAIR; ?></button>
    </div>
  </div>
</div>
<?php if ($security_level == 4) { ?>
<div class="fb-fieldset mb-2">
  <div class="fb-fieldset-legend"><?php echo GL_UTIL_PURGE_ALL; ?></div>
  <p class="mb-2"><?php echo GL_UTIL_PURGE_DB; ?></p>
  <div class="d-flex justify-content-end gap-2 align-items-center">
    <?php echo html_input_field('purge_confirm', '', 'size="10" maxlength="10"'); ?>
    <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo GL_UTIL_PURGE_DB_CONFIRM; ?>')) submitToDo('purge_db')"><?php echo GL_BTN_PURGE_DB; ?></button>
  </div>
</div>
<?php } ?>
